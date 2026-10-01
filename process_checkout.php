<?php
session_start();
// Pour le débogage: Affiche le contenu de la session
echo '<pre>';
var_dump($_SESSION);
echo '</pre>';
// N'oubliez pas de le supprimer après le débogage !

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'includes/functions.php'; // Assurez-vous que verifyCsrfToken, isLoggedIn, redirect, et sanitize sont dedans

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Vous devez être connecté pour finaliser votre commande.";
    redirect('login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Méthode de requête non autorisée."; // Message plus spécifique
    redirect('checkout.php'); // Rediriger si accès direct ou méthode incorrecte
    exit();
}

$csrf_token_posted = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token_posted)) {
    $_SESSION['error_message'] = 'Jeton CSRF invalide. Veuillez réessayer.';
    redirect('checkout.php');
    exit();
}

// CORRECTION IMPORTANTE ICI : Utiliser $_SESSION['user']['id'] pour l'ID de l'utilisateur authentifié
$user_id = $_SESSION['user']['id'] ?? null; 

// Vérification supplémentaire au cas où l'ID utilisateur ne serait pas défini après isLoggedIn
if ($user_id === null) {
    error_log("User ID (SESSION['user']['id']) not found for logged in user on process_checkout.php.");
    $_SESSION['error_message'] = "Votre session utilisateur est invalide. Veuillez vous reconnecter.";
    redirect('login.php');
    exit();
}

// ANCIENNE LIGNE : $customer_id = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
// SUPPRIMÉ : Le customer_id sera récupéré de manière sécurisée depuis la base de données

$total_amount = filter_var($_POST['total_amount'] ?? null, FILTER_VALIDATE_FLOAT);
$payment_method = sanitize($_POST['payment_method'] ?? '');
$delivery_address = sanitize($_POST['delivery_address'] ?? ''); // Récupérer et nettoyer l'adresse de livraison

// Validation de base des données POST
// $customer_id n'est plus vérifié ici car il sera récupéré de manière sécurisée
if ($total_amount === null || $total_amount <= 0 || empty($payment_method) || empty($delivery_address)) {
    $_SESSION['error_message'] = "Données de commande invalides ou incomplètes. Veuillez vérifier votre panier et les informations de livraison/paiement.";
    redirect('checkout.php');
    exit();
}

// Liste des méthodes de paiement autorisées (à adapter)
$allowed_payment_methods = ['cash_on_delivery', 'card', 'paypal', 'bank_transfer'];
if (!in_array($payment_method, $allowed_payment_methods)) {
    $_SESSION['error_message'] = "Méthode de paiement sélectionnée invalide.";
    redirect('checkout.php');
    exit();
}

try {
    // Vérifier que la connexion PDO est bien établie
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Erreur de configuration : la connexion à la base de données (PDO) n\'est pas disponible.');
    }

    $pdo->beginTransaction();

    // Re-vérifier l'identité du client et son panier une dernière fois pour des raisons de sécurité et d'intégrité
    $stmt_customer = $pdo->prepare("SELECT id FROM customers WHERE user_id = :user_id");
    // CORRECTION : Utiliser $stmt_customer et le bon paramètre :user_id avec $user_id
    $stmt_customer->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_customer->execute();
    $db_customer_data = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    // CORRECTION : Simplification de la vérification, on s'assure juste que les données client existent
    if (!$db_customer_data) {
        $_SESSION['error_message'] = "Erreur de sécurité: Données client introuvables pour votre session.";
        $pdo->rollBack();
        redirect('checkout.php');
        exit();
    }

    // Définir le customer_id à utiliser pour la commande, basé sur la recherche sécurisée
    $customer_id_for_order = (int)$db_customer_data['id'];


    $cart_items_for_order = [];
    $recalculated_total = 0;

    // Récupérer les articles du panier et vérifier le stock une dernière fois AVANT l'insertion de la commande
    // Utiliser `cart_id` ou `user_id` pour joindre, `user_id` est plus direct ici.
    $stmt_cart = $pdo->prepare("
        SELECT
            ci.quantity,
            ci.price_at_addition AS item_price,
            p.id AS product_id,
            p.stock_quantity AS product_stock_quantity,
            pv.id AS variant_id,
            pv.stock_quantity AS variant_stock_quantity,
            p.name AS product_name,
            pv.name AS variant_name
        FROM
            cart_items ci
        INNER JOIN
            products p ON ci.product_id = p.id
        LEFT JOIN
            product_variants pv ON ci.variant_id = pv.id
        WHERE
            ci.user_id = :user_id FOR UPDATE
    "); // FOR UPDATE pour verrouiller les lignes et éviter les problèmes de concurrence
    $stmt_cart->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_cart->execute();
    $cart_items_for_order = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items_for_order)) {
        $_SESSION['error_message'] = "Votre panier est vide. Impossible de passer une commande.";
        $pdo->rollBack();
        redirect('cart.php');
        exit();
    }

    // Vérification finale du stock et calcul du total
    foreach ($cart_items_for_order as $item) {
        $current_stock = ($item['variant_id'] !== null) ? (int)$item['variant_stock_quantity'] : (int)$item['product_stock_quantity'];
        if ((int)$item['quantity'] > $current_stock) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Stock insuffisant pour l'article " . htmlspecialchars($item['product_name']);
            if (!empty($item['variant_name'])) {
                $_SESSION['error_message'] .= " (" . htmlspecialchars($item['variant_name']) . ")";
            }
            $_SESSION['error_message'] .= " (Quantité demandée: " . (int)$item['quantity'] . ", Disponible: " . $current_stock . "). Veuillez ajuster votre panier.";
            redirect('cart.php'); // Rediriger vers le panier si problème de stock
            exit();
        }
        $recalculated_total += (float)$item['item_price'] * (int)$item['quantity'];
    }

    // Vérifier si le total recalculé correspond au total envoyé par le client (pour éviter la manipulation)
    // Une petite tolérance (epsilon) est utilisée pour comparer les floats
    if (abs($recalculated_total - $total_amount) > 0.01) { // 0.01 est une tolérance courante pour les devises
        $_SESSION['error_message'] = "Le total de votre commande a changé en raison d'une mise à jour de prix ou d'une erreur. Veuillez vérifier votre panier.";
        $pdo->rollBack();
        redirect('cart.php');
        exit();
    }

    // Définir l'employee_id (null pour une commande client typique) et le statut de paiement initial
    $employee_id = null;
    $payment_status = 'pending'; // Par défaut, en attente de paiement

    // 1. Créer une nouvelle commande dans la table 'orders'
    $stmt = $pdo->prepare("INSERT INTO orders (customer_id, employee_id, order_date, total_amount, status, payment_method, payment_status, delivery_address) VALUES (:customer_id, :employee_id, NOW(), :total_amount, 'pending', :payment_method, :payment_status, :delivery_address)");
    // CORRECTION : Utiliser le customer_id sécurisé
    $stmt->bindParam(':customer_id', $customer_id_for_order, PDO::PARAM_INT);
    $stmt->bindValue(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':total_amount', $recalculated_total, PDO::PARAM_STR); // Utiliser le total recalculé
    $stmt->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
    $stmt->bindParam(':payment_status', $payment_status, PDO::PARAM_STR);
    $stmt->bindParam(':delivery_address', $delivery_address, PDO::PARAM_STR);
    $stmt->execute();
    $order_id = $pdo->lastInsertId();

    // 2. Ajouter les articles du panier dans les détails de la commande et mettre à jour les stocks
    foreach ($cart_items_for_order as $item) {
        $product_id_order = (int)$item['product_id'];
        $variant_id_order = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
        $quantity_order = (int)$item['quantity'];
        $unit_price_order = (float)$item['item_price'];

        $stmt = $pdo->prepare("INSERT INTO order_details (order_id, product_id, variant_id, quantity, unit_price) VALUES (:order_id, :product_id, :variant_id, :quantity, :unit_price)");
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $product_id_order, PDO::PARAM_INT);
        $stmt->bindValue(':variant_id', $variant_id_order, PDO::PARAM_INT); // Utilisez bindValue pour les valeurs null
        $stmt->bindParam(':quantity', $quantity_order, PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $unit_price_order, PDO::PARAM_STR);
        $stmt->execute();

        // Mettre à jour le stock
        if ($variant_id_order !== null) {
            $stmt = $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity - :quantity WHERE id = :variant_id");
            $stmt->bindParam(':quantity', $quantity_order, PDO::PARAM_INT);
            $stmt->bindParam(':variant_id', $variant_id_order, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id");
            $stmt->bindParam(':quantity', $quantity_order, PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $product_id_order, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    // 3. Vider le panier de l'utilisateur après la commande réussie
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();

    $_SESSION['success_message'] = "Votre commande (#" . $order_id . ") a été passée avec succès !";
    redirect('order_confirmation.php?order_id=' . $order_id);
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur PDO dans process_checkout.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $_SESSION['error_message'] = 'Une erreur de base de données est survenue lors de la finalisation de votre commande. Veuillez réessayer plus tard.';
    redirect('checkout.php');
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur générale dans process_checkout.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $_SESSION['error_message'] = 'Une erreur inattendue est survenue lors de la finalisation de votre commande. Veuillez réessayer plus tard.';
    redirect('checkout.php');
    exit();
}
?>