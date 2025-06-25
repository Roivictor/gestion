<?php
require_once 'config.php'; // Inclut la configuration et les fonctions utilitaires

// Assurez-vous que l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour passer commande.";
    redirect(BASE_URL . 'login.php');
    exit();
}

// Assurez-vous que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Méthode de requête invalide.";
    redirect(BASE_URL . 'checkout.php'); // Ou toute autre page appropriée
    exit();
}

// Vérification du token CSRF
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $_SESSION['error_message'] = "Erreur de sécurité : Token CSRF invalide.";
    error_log("CSRF Checkout Error: Invalid token from IP: " . $_SERVER['REMOTE_ADDR']);
    redirect(BASE_URL . 'checkout.php');
    exit();
}

// Récupérer les informations du panier
$user_id = $_SESSION['user']['id']; // C'est l'ID de l'utilisateur de la table `users`
$cart_items = $_SESSION['cart'];

// Assurez-vous que le panier n'est pas vide (double vérification)
if (empty($cart_items)) {
    $_SESSION['warning_message'] = "Votre panier est vide. Impossible de finaliser la commande.";
    redirect(BASE_URL . 'cart.php');
    exit();
}

// Récupérer la méthode de paiement et le montant total soumis par le formulaire
$payment_method = sanitize($_POST['payment_method'] ?? '');
$submitted_total_amount = filter_var($_POST['total_amount'] ?? 0, FILTER_VALIDATE_FLOAT);

// Vérification de la méthode de paiement (ajoutez d'autres validations si nécessaire)
$allowed_payment_methods = ['card', 'paypal', 'bank_transfer'];
if (!in_array($payment_method, $allowed_payment_methods)) {
    $_SESSION['error_message'] = "Méthode de paiement invalide.";
    redirect(BASE_URL . 'checkout.php');
    exit();
}

// --- DÉBUT DE LA MODIFICATION CRUCIALE ---
// Récupérer le customer_id de la table 'customers' correspondant au user_id
$stmt_customer = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
$stmt_customer->execute([$user_id]);
$customer_data = $stmt_customer->fetch(PDO::FETCH_ASSOC);

if (!$customer_data) {
    // Si aucun customer_id correspondant n'est trouvé, cela signifie que l'utilisateur n'est pas un client enregistré dans la table 'customers'.
    // Ceci peut arriver si un utilisateur s'inscrit mais n'a pas encore de profil client complet.
    $_SESSION['error_message'] = "Votre compte n'est pas configuré comme un compte client valide. Veuillez compléter votre profil.";
    // Redirigez l'utilisateur vers une page où il peut créer/compléter son profil client.
    // Par exemple : redirect(BASE_URL . 'profile_setup.php');
    redirect(BASE_URL . 'checkout.php'); // Pour le moment, on le renvoie au checkout
    exit();
}

$customer_id = $customer_data['id']; // C'est l'ID du client que nous allons utiliser pour la commande
// --- FIN DE LA MODIFICATION CRUCIALE ---


// Démarrer une transaction PDO
// C'est crucial pour garantir l'atomicité : soit toutes les opérations réussissent, soit aucune.
$pdo->beginTransaction();

try {
    $calculated_total_amount = 0;
    $products_to_update_stock = []; // Pour stocker les produits et leurs quantités à déduire du stock

    // 1. Re-vérifier les stocks et calculer le total (IMPORTANT pour éviter la fraude)
    foreach ($cart_items as $product_id => $item) {
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity FROM products WHERE id = ? FOR UPDATE"); // LOCK la ligne pour éviter les problèmes de concurrence
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC); // Assurez-vous d'utiliser FETCH_ASSOC ou FETCH_BOTH

        if (!$product) {
            throw new Exception("Le produit '" . htmlspecialchars($item['name']) . "' n'existe plus.");
        }

        if ($product['stock_quantity'] < $item['quantity']) {
            throw new Exception("Stock insuffisant pour '" . htmlspecialchars($product['name']) . "'. Disponible: " . $product['stock_quantity'] . ".");
        }

        $calculated_total_amount += $item['quantity'] * $product['price'];
        $products_to_update_stock[] = [
            'id' => $product['id'],
            'quantity' => $item['quantity'],
            'unit_price' => $product['price']
        ];
    }

    // Vérifier si le montant soumis correspond au montant calculé (pour éviter la falsification côté client)
    // Permet une petite marge d'erreur due aux arrondis si nécessaire
    if (abs($calculated_total_amount - $submitted_total_amount) > 0.01) {
        throw new Exception("Le montant total calculé ne correspond pas au montant soumis. Tentative de fraude détectée.");
    }

    // 2. Insérer la commande dans la table `orders`
    $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_date, total_amount, status, payment_method, payment_status) VALUES (?, NOW(), ?, ?, ?, ?)");
    $stmt->execute([
        $customer_id, // <<< C'EST ICI QU'ON UTILISE LE customer_id RÉCUPÉRÉ
        $calculated_total_amount,
        'pending', // Statut initial de la commande
        $payment_method,
        'pending'  // Statut initial du paiement
    ]);
    $order_id = $pdo->lastInsertId(); // Récupère l'ID de la commande nouvellement insérée

    // 3. Insérer les détails de la commande dans la table `order_details`
    $stmt_details = $pdo->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    foreach ($products_to_update_stock as $product) {
        $stmt_details->execute([
            $order_id,
            $product['id'],
            $product['quantity'],
            $product['unit_price']
        ]);
    }

    // 4. Mettre à jour les quantités en stock des produits dans la table `products`
    $stmt_update_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    foreach ($products_to_update_stock as $product) {
        $stmt_update_stock->execute([$product['quantity'], $product['id']]);
    }

    // 5. Si toutes les opérations réussissent, valider la transaction
    $pdo->commit();

    // 6. Vider le panier de la session
    unset($_SESSION['cart']);

    // 7. Rediriger avec un message de succès
    $_SESSION['success_message'] = "Votre commande (#" . $order_id . ") a été passée avec succès !";
    redirect(BASE_URL . 'order_confirmation.php?order_id=' . $order_id); // Redirige vers une page de confirmation
    exit();

} catch (Exception $e) {
    // En cas d'erreur, annuler toutes les opérations de la transaction
    $pdo->rollBack();
    error_log("Erreur lors du processus de commande: " . $e->getMessage() . " (User ID: " . $user_id . ")");
    $_SESSION['error_message'] = "Erreur lors du traitement de votre commande : " . $e->getMessage() . ". Veuillez réessayer ou contacter le support.";
    redirect(BASE_URL . 'checkout.php'); // Retourne à la page de paiement avec l'erreur
    exit();
}