<?php
// cart_ajax.php
session_start();
require_once 'config.php'; // Assurez-vous que config.php est correctement inclus

header('Content-Type: application/json'); // Indique que la réponse est du JSON

$response = ['success' => false, 'message' => ''];

// Vérifiez si la méthode de requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = __('invalid_request_method'); // Utilisation de la fonction de traduction
    echo json_encode($response);
    exit;
}

// Récupérer le corps de la requête JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Vérifier si les données sont valides et si le token CSRF est présent
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['action'], $data['product_id'], $data['quantity'], $data['_csrf_token'])) {
    $response['message'] = __('invalid_input_data'); // Utilisation de la fonction de traduction
    echo json_encode($response);
    exit;
}

$action = $data['action'];
$productId = (int)$data['product_id'];
$quantity = (int)$data['quantity'];
$csrfToken = $data['_csrf_token'];

// Vérifier le token CSRF
if (!verifyCsrfToken($csrfToken)) {
    $response['message'] = __('csrf_token_invalid'); // Utilisation de la fonction de traduction
    echo json_encode($response);
    exit;
}

// Traitement de l'action 'add' (ajouter au panier)
if ($action === 'add') {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    try {
        $stmt = $pdo->prepare("SELECT name, price, stock_quantity, image_url FROM products WHERE id = ?"); // Ajout de image_url
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $response['message'] = __('product_not_found_cart'); // Utilisation de la fonction de traduction
            echo json_encode($response);
            exit;
        }

        $current_cart_quantity = $_SESSION['cart'][$productId]['quantity'] ?? 0;
        $new_total_quantity = $current_cart_quantity + $quantity;

        if ($product['stock_quantity'] < $new_total_quantity) {
            $response['message'] = __('not_enough_stock'); // Utilisation de la fonction de traduction
            echo json_encode($response);
            exit;
        }

        // Si le produit est déjà dans le panier, mettez à jour la quantité
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity'] += $quantity;
        } else {
            // Sinon, ajoutez le nouveau produit
            $_SESSION['cart'][$productId] = [
                'id' => $productId,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image' => $product['image_url'] // Ajout de l'URL de l'image
            ];
        }

        // Calculer le nombre total d'articles dans le panier (pour l'affichage)
        $cart_count = 0;
        foreach ($_SESSION['cart'] as $item_in_cart) {
            $cart_count += $item_in_cart['quantity'];
        }

        $response['success'] = true;
        $response['message'] = htmlspecialchars($product['name']) . ' ' . __('added_to_cart'); // Utilisation de la fonction de traduction
        $response['cart_count'] = $cart_count;

    } catch (PDOException $e) {
        error_log("Erreur PDO dans cart_ajax.php: " . $e->getMessage());
        $response['message'] = __('db_error_cart_add'); // Utilisation de la fonction de traduction
    }

} else {
    $response['message'] = __('unknown_action'); // Utilisation de la fonction de traduction
}

echo json_encode($response);
exit;