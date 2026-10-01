<?php
// cart_ajax.php

// S'assurer que la session est démarrée.
// config.php devrait déjà le faire, mais c'est une vérification utile.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Les chemins sont corrects étant donné votre structure:
// C:\xampp\htdocs\u\
//   - config.php
//   - cart_ajax.php
//   - includes\functions.php
require_once 'config.php';
require_once 'includes/functions.php'; // Assurez-vous que verifyCsrfToken est ici

header('Content-Type: application/json');

global $pdo; // $pdo doit être initialisé dans config.php et rendu accessible globalement

// Remplacement des appels à translate_message par des chaînes de caractères directes
$response = ['success' => false, 'message' => 'Une erreur est survenue lors du traitement de votre demande.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    $response['message'] = 'Méthode de requête invalide.';
    echo json_encode($response);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Pour le débogage (peut être désactivé en production)
error_log("Cart AJAX Raw Input: " . $input);
error_log("Cart AJAX Decoded Data: " . print_r($data, true));

// Vérifier si json_decode a échoué
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Données JSON invalides : ' . json_last_error_msg();
    error_log("JSON Decode Error: " . json_last_error_msg() . " Raw Input: " . $input);
    echo json_encode($response);
    exit();
}

// Vérification du token CSRF
$csrf_token = $data['_csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token)) {
    $response['message'] = 'Jeton CSRF invalide.';
    error_log("CSRF Token Invalid: Provided '{$csrf_token}' vs Session '" . ($_SESSION['csrf_token'] ?? 'NOT SET') . "'");
    echo json_encode($response);
    exit();
}

$action = $data['action'] ?? '';
$product_id = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
$quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

// --- Gérer variant_id comme null si c'est une chaîne vide ou "null" ---
$variant_id = null; // Initialise à null par défaut
if (isset($data['variant_id'])) {
    if ($data['variant_id'] === null || $data['variant_id'] === '' || (is_string($data['variant_id']) && strtolower($data['variant_id']) === 'null')) {
        $variant_id = null; // Considérez-le comme null (pas de variante sélectionnée)
    } else {
        $variant_id = filter_var($data['variant_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }
}
// --- FIN DE LA GESTION DE variant_id ---

// Pour le débogage (peut être désactivé en production)
error_log("Cart AJAX Action: " . $action);
error_log("Cart AJAX Product ID (Processed): " . var_export($product_id, true));
error_log("Cart AJAX Quantity (Processed): " . var_export($quantity, true));
error_log("Cart AJAX Variant ID (Processed): " . var_export($variant_id, true));

// Validation des données
if ($action !== 'add' || $product_id === null || $product_id <= 0 || $quantity === null || $quantity < 1) {
    $response['message'] = 'Données d\'entrée invalides ou action non supportée.';
    error_log("Invalid input data: Action={$action}, ProductID=" . var_export($product_id, true) . ", Quantity=" . var_export($quantity, true));
    echo json_encode($response);
    exit();
}

// Validation spécifique du variant_id (si un ID a été fourni, il doit être valide et positif)
if ($variant_id !== null && $variant_id <= 0) {
    $response['message'] = 'ID de variante invalide.';
    error_log("Invalid variant ID after processing: " . var_export($variant_id, true));
    echo json_encode($response);
    exit();
}

// Récupérer l'ID de l'utilisateur de la session
// CORRECTION APPLIQUÉE ICI : Utilise $_SESSION['user']['id']
$user_id = $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    $response['message'] = 'Vous devez être connecté pour ajouter des articles au panier.';
    error_log("User not logged in. Session user_id: " . var_export($user_id, true));
    echo json_encode($response);
    exit();
}

try {
    // Vérifier l'existence du produit et son stock, et des variantes si l'ID de variante est fourni
    $stmt = $pdo->prepare("SELECT p.id as product_main_id, p.stock_quantity as product_main_stock, p.price,
                                   v.id as variant_db_id, v.stock_quantity as variant_stock, v.price_adjustment
                           FROM products p
                           LEFT JOIN product_variants v ON p.id = v.product_id AND v.id = :variant_id
                           WHERE p.id = :product_id");

    // Lier :variant_id. Utilisez bindValue pour gérer NULL correctement avec PDO::PARAM_INT.
    $stmt->bindValue(':variant_id', $variant_id, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    error_log("DEBUG SELECT Product/Variant Info - Binding variant_id: " . var_export($variant_id, true) . ", product_id: " . var_export($product_id, true));
    $stmt->execute();
    if ($stmt->errorCode() !== '00000') {
        $errorInfo = $stmt->errorInfo();
        error_log("PDO Statement Error (SELECT Product/Variant Info): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
    }
    $product_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product_info) {
        $response['message'] = 'Produit non trouvé.';
        error_log("Product ID {$product_id} not found in DB.");
        echo json_encode($response);
        exit();
    }

    $available_stock = (int)$product_info['product_main_stock']; // Stock du produit principal par défaut
    $effective_price = (float)$product_info['price']; // Convertir en float pour les calculs

    // Si une variante a été spécifiée ET qu'elle a été trouvée dans la BDD pour ce produit
    if ($variant_id !== null && $product_info['variant_db_id'] !== null) {
        // La variante a été demandée et trouvée. Utiliser ses données.
        $available_stock = (int)$product_info['variant_stock'];
        $effective_price += (float)$product_info['price_adjustment'];
        error_log("Using variant data for Product ID {$product_id}, Variant ID {$variant_id}. Stock: {$available_stock}, Price: {$effective_price}");
    } elseif ($variant_id !== null && $product_info['variant_db_id'] === null) {
        // Le variant_id a été fourni (non-null), mais il n'existe PAS pour ce product_id dans la BDD.
        // Cela signifie qu'il y a un variant_id invalide envoyé par le client.
        $response['message'] = 'La variante sélectionnée n\'existe pas pour ce produit.';
        error_log("Variant ID {$variant_id} was provided but not found for Product ID {$product_id}.");
        echo json_encode($response);
        exit();
    }
    // Si $variant_id est null (cas du produit principal), on utilise les infos par défaut
    // qui sont déjà celles du produit principal ($product_info['product_main_stock'], $product_info['price']).

    if ($available_stock < $quantity) {
        $response['message'] = 'Stock insuffisant pour ajouter plus de cet article. Stock actuel disponible: ' . $available_stock . '.';
        error_log("Insufficient stock for Product ID {$product_id}, Variant ID " . ($variant_id ?? 'null') . ". Requested: {$quantity}, Available: {$available_stock}");
        echo json_encode($response);
        exit();
    }

    // Vérifier si l'article est déjà dans le panier de l'utilisateur
    // L'opérateur de comparaison NULL-safe (<=>) est essentiel ici pour gérer les cas avec ou sans variante.
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE user_id = :user_id AND product_id = :product_id AND variant_id <=> :variant_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindValue(':variant_id', $variant_id, PDO::PARAM_INT); // bindValue pour NULL
    error_log("DEBUG SELECT Existing Item - Binding user_id: " . var_export($user_id, true) . ", product_id: " . var_export($product_id, true) . ", variant_id: " . var_export($variant_id, true));
    $stmt->execute();
    if ($stmt->errorCode() !== '00000') {
        $errorInfo = $stmt->errorInfo();
        error_log("PDO Statement Error (SELECT Existing Item): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
    }
    $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_item) {
        // Mettre à jour la quantité existante
        $new_quantity = $existing_item['quantity'] + $quantity;
        if ($new_quantity > $available_stock) {
            $response['message'] = 'Stock insuffisant pour ajouter plus de cet article. Vous avez déjà ' . $existing_item['quantity'] . ' en panier. Stock total disponible: ' . $available_stock . '.';
            error_log("Cart item update failed due to insufficient stock. Product ID {$product_id}, Variant ID " . ($variant_id ?? 'null') . ". Current cart: {$existing_item['quantity']}, Requested addition: {$quantity}, New total: {$new_quantity}, Available: {$available_stock}");
            echo json_encode($response);
            exit();
        }
        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = :new_quantity, updated_at = NOW() WHERE id = :item_id AND user_id = :user_id_check");
        $stmt->bindParam(':new_quantity', $new_quantity, PDO::PARAM_INT);
        $stmt->bindParam(':item_id', $existing_item['id'], PDO::PARAM_INT);
        $stmt->bindParam(':user_id_check', $user_id, PDO::PARAM_INT); // Ajout d'une vérification supplémentaire
        error_log("DEBUG UPDATE Cart Item - Binding new_quantity: " . var_export($new_quantity, true) . ", item_id: " . var_export($existing_item['id'], true));
        $stmt->execute();
        if ($stmt->errorCode() !== '00000') {
            $errorInfo = $stmt->errorInfo();
            error_log("PDO Statement Error (UPDATE Cart Item): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
        }
        $response['message'] = 'Produit mis à jour dans le panier.';
        error_log("Cart item updated: Product ID {$product_id}, Variant ID " . ($variant_id ?? 'null') . ". New quantity: {$new_quantity}");
    } else {
        // Ajouter un nouvel article
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, variant_id, quantity, price_at_addition) VALUES (:user_id, :product_id, :variant_id, :quantity, :price)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindValue(':variant_id', $variant_id, PDO::PARAM_INT); // Utiliser bindValue pour gérer correctement NULL
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':price', $effective_price, PDO::PARAM_STR); // Utiliser STR pour les décimaux
        error_log("DEBUG INSERT Cart Item - Binding user_id: " . var_export($user_id, true) . ", product_id: " . var_export($product_id, true) . ", variant_id: " . var_export($variant_id, true) . ", quantity: " . var_export($quantity, true) . ", price: " . var_export($effective_price, true));
        $stmt->execute();
        if ($stmt->errorCode() !== '00000') {
            $errorInfo = $stmt->errorInfo();
            error_log("PDO Statement Error (INSERT Cart Item): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
        }
        $response['message'] = 'Produit ajouté au panier !';
        error_log("New cart item added: Product ID {$product_id}, Variant ID " . ($variant_id ?? 'null') . ". Quantity: {$quantity}");
    }

    // Récupérer le nouveau nombre total d'articles dans le panier
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total_items FROM cart_items WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    error_log("DEBUG SELECT Cart Count - Binding user_id: " . var_export($user_id, true));
    $stmt->execute();
    if ($stmt->errorCode() !== '00000') {
        $errorInfo = $stmt->errorInfo();
        error_log("PDO Statement Error (SELECT Cart Count): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
    }
    $cart_count = $stmt->fetchColumn();

    $response['success'] = true;
    $response['cart_count'] = (int)$cart_count;

} catch (PDOException $e) {
    error_log("Database error in cart_ajax.php: " . $e->getMessage());
    $response['message'] = 'Une erreur de base de données est survenue lors de l\'ajout au panier.';
} catch (Exception $e) { // Ajout d'une capture d'exception générique
    error_log("General error in cart_ajax.php: " . $e->getMessage());
    $response['message'] = 'Une erreur inattendue est survenue.';
}

echo json_encode($response);
exit();
?>