<?php
session_start();
require_once 'config.php'; // Assurez-vous que ce fichier inclut votre connexion PDO et les fonctions utilitaires
require_once 'includes/functions.php'; // Assurez-vous que verifyCsrfToken et les autres fonctions sont ici

header('Content-Type: application/json'); // Indique que la réponse est du JSON

$response = ['success' => false, 'message' => '', 'cart_count' => 0];

try {
    // DEBUG: Entrée du script (à désactiver en production)
    error_log("DEBUG add_to_cart.php: Entrée - product_id: " . ($_POST['product_id'] ?? 'N/A') . ", variant_id: " . ($_POST['variant_id'] ?? 'N/A') . ", quantity: " . ($_POST['quantity'] ?? 'N/A') . ", CSRF: " . ($_POST['_csrf_token'] ?? 'N/A'));

    // 1. Vérification de la méthode HTTP (doit être POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée.', 405);
    }

    // 2. Vérification du token CSRF
    // Utilisez la clé '_csrf_token' car c'est ce qui est envoyé par la requête AJAX.
    if (!isset($_POST['_csrf_token']) || !verifyCsrfToken($_POST['_csrf_token'])) {
        error_log("DEBUG add_to_cart.php: Erreur CSRF - Token fourni: " . ($_POST['_csrf_token'] ?? 'Non défini') . ", Token session: " . ($_SESSION['csrf_token'] ?? 'Non défini'));
        throw new Exception('Jeton de sécurité invalide. Veuillez rafraîchir la page.', 403);
    }

    // 3. Validation et filtrage des entrées (product_id, variant_id et quantity)
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    // filter_input renvoie null si la clé n'existe pas, ou false si le filtre échoue (non-entier).
    // Nous traitons null/false comme "pas de variante sélectionnée" ou "variante invalide".
    $variant_id = filter_input(INPUT_POST, 'variant_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    // 4. Vérification si les paramètres sont valides (quantité >= 1, ID produit valide et positif)
    if (!$product_id || $product_id <= 0 || !$quantity) { // $quantity sera false si < 1
        error_log("DEBUG add_to_cart.php: Validation échouée - ID produit ({$product_id}) ou quantité ({$quantity}) invalide.");
        throw new Exception('Paramètres de produit ou quantité invalides.', 400);
    }

    // 5. Connexion à la base de données (assurez-vous que $pdo est disponible via config.php)
    if (!isset($pdo) || !$pdo instanceof PDO) {
        error_log("ERREUR add_to_cart.php: La connexion PDO n'est pas disponible dans config.php.");
        throw new Exception('Erreur interne du serveur: connexion à la base de données indisponible.', 500);
    }

    // 6. Récupérer l'information du produit principal (y compris s'il a des variantes et les infos de promotion)
    $stmt_product = $pdo->prepare("SELECT id, name, price, has_variants, stock_quantity, promo_price, promo_start_date, promo_end_date FROM products WHERE id = :product_id");
    $stmt_product->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $product_info = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product_info) {
        error_log("DEBUG add_to_cart.php: Produit ID " . $product_id . " introuvable.");
        throw new Exception('Produit introuvable.', 404);
    }

    $has_variants = (bool)$product_info['has_variants'];
    
    // DEBUG: Affichage des informations de variante pour le produit
    error_log("DEBUG add_to_cart.php: Produit ID " . $product_id . ", has_variants: " . ($has_variants ? 'true' : 'false') . ", variant_id reçu: " . ($variant_id ?? 'NULL') . ", type variant_id: " . gettype($variant_id));


    // 7. Vérifier si une variante est requise mais manquante ou invalide
    // Si le produit *doit* avoir des variantes ET que $variant_id est null (non fourni) ou false (invalide)
    if ($has_variants && ($variant_id === null || $variant_id === false)) {
        error_log("DEBUG add_to_cart.php: Erreur - Produit avec variantes sans variant_id valide.");
        throw new Exception('Veuillez sélectionner une variante pour ce produit.', 400);
    }

    // Initialisation des données de l'article pour le panier
    $item_data = [
        'product_id' => $product_id,
        'product_name' => $product_info['name'],
        'base_price' => (float)$product_info['price'],
        'final_price' => (float)$product_info['price'], // Prix par défaut du produit de base
        'variant_id' => null,
        'variant_name' => null,
        'image_url' => getProductImageUrl($product_id), // Assurez-vous que cette fonction existe et est fiable
        'sku' => null,
        'color_name' => null // Initialisation
    ];

    // Gérer les prix promotionnels du produit principal
    $promo_price = (float)$product_info['promo_price'];
    $promo_start = $product_info['promo_start_date'];
    $promo_end = $product_info['promo_end_date'];
    $current_date = date('Y-m-d H:i:s');

    if ($promo_price > 0 && $promo_start <= $current_date && $promo_end >= $current_date) {
        $item_data['final_price'] = $promo_price;
    }

    $available_stock = (int)$product_info['stock_quantity']; // Stock initial, sera mis à jour si variante

    // 8. Si le produit a des variantes ET qu'un variant_id valide a été fourni, récupérer les détails de la variante
    if ($has_variants && $variant_id !== null) {
        $stmt_variant = $pdo->prepare("
            SELECT pv.id, pv.name AS variant_name, pv.price_adjustment, pv.sku, pv.stock_quantity, pv.image_url, pc.name AS color_name
            FROM product_variants pv
            LEFT JOIN product_colors pc ON pv.color_id = pc.id
            WHERE pv.id = :variant_id AND pv.product_id = :product_id
        ");
        $stmt_variant->bindParam(':variant_id', $variant_id, PDO::PARAM_INT);
        $stmt_variant->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_variant->execute();
        $variant_info = $stmt_variant->fetch(PDO::FETCH_ASSOC);

        if (!$variant_info) {
            error_log("DEBUG add_to_cart.php: Variante ID " . $variant_id . " pour produit " . $product_id . " introuvable.");
            throw new Exception('Variante de produit introuvable.', 404);
        }

        // Mettre à jour le stock disponible et le prix en fonction de la variante
        $available_stock = (int)$variant_info['stock_quantity'];
        $item_data['final_price'] += (float)$variant_info['price_adjustment']; // Appliquer l'ajustement de prix
        $item_data['variant_id'] = $variant_info['id'];
        $item_data['variant_name'] = $variant_info['variant_name'];
        $item_data['sku'] = $variant_info['sku'];
        $item_data['color_name'] = $variant_info['color_name'];
        if (!empty($variant_info['image_url'])) {
            $item_data['image_url'] = $variant_info['image_url']; // L'image de la variante prime
        }
    }
    // Si $has_variants est false ou $variant_id est null, $available_stock reste le stock du produit principal

    // 9. Vérification finale du stock
    // Calcule la nouvelle quantité si l'article est déjà dans le panier
    $cart_item_key_check = $item_data['product_id'] . '_' . ($item_data['variant_id'] ?? 'null');
    $current_cart_quantity = $_SESSION['cart'][$cart_item_key_check]['quantity'] ?? 0;
    $projected_total_quantity = $current_cart_quantity + $quantity;

    if ($available_stock < $projected_total_quantity) {
        error_log("DEBUG add_to_cart.php: Stock insuffisant pour article " . $product_id . " (variante: " . ($variant_id ?? 'null') . "). Demandé: " . $quantity . ", Actuel panier: " . $current_cart_quantity . ", Projeté: " . $projected_total_quantity . ", Disponible: " . $available_stock);
        throw new Exception('Stock insuffisant. Quantité disponible: ' . ($available_stock - $current_cart_quantity > 0 ? $available_stock - $current_cart_quantity : '0') . '. Total en panier après ajout: ' . $current_cart_quantity, 400);
    }
    
    // 10. Initialiser le panier de session si ce n'est pas déjà fait
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // 11. Ajouter ou mettre à jour le produit/variante dans le panier de session
    // La clé unique pour un article de panier est product_id + variant_id
    $cart_item_key = $item_data['product_id'] . '_' . ($item_data['variant_id'] ?? 'null');

    if (isset($_SESSION['cart'][$cart_item_key])) {
        // L'article existe déjà, mettre à jour la quantité
        $_SESSION['cart'][$cart_item_key]['quantity'] += $quantity;
        error_log("DEBUG add_to_cart.php: Quantité mise à jour pour le produit " . $item_data['product_id'] . " (Variante: " . ($item_data['variant_id'] ?? 'null') . "). Nouvelle quantité: " . $_SESSION['cart'][$cart_item_key]['quantity']);
    } else {
        // Nouvel article, l'ajouter
        $_SESSION['cart'][$cart_item_key] = [
            'product_id' => $item_data['product_id'],
            'variant_id' => $item_data['variant_id'],
            'name' => $item_data['product_name'],
            'variant_name' => $item_data['variant_name'],
            'price' => $item_data['final_price'],
            'quantity' => $quantity,
            'image_url' => $item_data['image_url'],
            'sku' => $item_data['sku'],
            'color_name' => $item_data['color_name']
        ];
        error_log("DEBUG add_to_cart.php: Nouvel article ajouté: Produit " . $item_data['product_id'] . " (Variante: " . ($item_data['variant_id'] ?? 'null') . "). Quantité: " . $quantity);
    }

    // 12. Calcul du nombre total d'articles (quantités) dans le panier
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item) && isset($item['quantity'])) {
            $count += $item['quantity'];
        }
    }

    // 13. Préparation de la réponse
    $response['success'] = true;
    $response['message'] = htmlspecialchars($item_data['product_name']) . (empty($item_data['variant_name']) ? '' : ' (' . htmlspecialchars($item_data['variant_name']) . ')') . " ajouté au panier.";
    $response['cart_count'] = $count;
    // $response['cart_items'] = $_SESSION['cart']; // Utile pour le débogage, à commenter en production

} catch (Exception $e) {
    // 14. Gestion des erreurs
    // Journalise l'erreur complète sur le serveur
    error_log("ERREUR add_to_cart.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ") sur la ligne " . $e->getLine() . " dans " . $e->getFile() . "\nTrace: " . $e->getTraceAsString());
    
    // Assure que le message d'erreur est envoyé au frontend dans la réponse JSON
    $response['message'] = $e->getMessage();
    $response['success'] = false;
    http_response_code($e->getCode() ?: 500); // Utilise le code d'erreur si défini, sinon 500

} catch (PDOException $e) {
    // Gérer spécifiquement les erreurs PDO pour des messages plus clairs si besoin
    error_log("ERREUR PDO add_to_cart.php: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\nTrace: " . $e->getTraceAsString());
    $response['message'] = 'Une erreur de base de données est survenue. Veuillez réessayer plus tard.';
    $response['success'] = false;
    http_response_code(500);
}

// 15. Envoi de la réponse JSON
echo json_encode($response);