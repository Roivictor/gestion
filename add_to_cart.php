<?php
require_once 'config.php'; // 1. Inclut le fichier de configuration (connexion DB, fonctions utilitaires, PHPMailer)

header('Content-Type: application/json'); // 2. Définit l'en-tête pour indiquer que la réponse sera du JSON

try {
    // 3. Vérification de la méthode HTTP (doit être POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Lance une exception si la méthode n'est pas POST, avec un code d'erreur HTTP 405 (Méthode non autorisée)
        throw new Exception('Méthode non autorisée', 405);
    }

    // 4. Vérification du token CSRF
    // C'est une mesure de sécurité cruciale pour prévenir les attaques Cross-Site Request Forgery.
    // Le token envoyé dans la requête POST est comparé à celui stocké en session.
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        throw new Exception('Token CSRF invalide', 403); // Code d'erreur HTTP 403 (Interdit)
    }

    // 5. Validation et filtrage des entrées (product_id et quantity)
    // 'filter_input()' est une fonction sécurisée pour récupérer et valider des données.
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1] // La quantité doit être au moins 1
    ]);

    // 6. Vérification si les paramètres sont valides
    if (!$product_id || !$quantity) {
        throw new Exception('Paramètres invalides', 400); // Code d'erreur HTTP 400 (Mauvaise requête)
    }

    // 7. Vérification du produit en base de données
    // Récupère l'ID, le nom, le prix et la quantité en stock du produit.
    $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, image_url FROM products WHERE id = ?"); // Ajout de image_url
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    // 8. Vérification de l'existence du produit
    if (!$product) {
        throw new Exception('Produit introuvable', 404); // Code d'erreur HTTP 404 (Non trouvé)
    }

    // 9. Vérification du stock disponible
    // S'assure que la quantité demandée ne dépasse pas le stock actuel.
    if ($product['stock_quantity'] < $quantity) {
        throw new Exception('Stock insuffisant', 400); // Code d'erreur HTTP 400 (Mauvaise requête)
    }

    // 10. Ajout ou mise à jour du produit dans le panier en session
    // Le panier ($_SESSION['cart']) est un tableau associatif où la clé est l'ID du produit.
    if (isset($_SESSION['cart'][$product_id])) {
        // Si le produit est déjà dans le panier, augmente la quantité
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        // Sinon, ajoute le produit avec ses détails
        $_SESSION['cart'][$product_id] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'image' => $product['image_url'] ?? null // Utilise l'image si disponible, sinon null
        ];
    }

    // 11. Calcul du nombre total d'articles et du montant total du panier
    $total = 0;
    $count = 0;
    foreach ($_SESSION['cart'] as $item) {
        // Assurez-vous que $item est un tableau et contient 'price' et 'quantity'
        if (is_array($item) && isset($item['price'], $item['quantity'])) {
            $total += $item['price'] * $item['quantity'];
            $count += $item['quantity'];
        }
    }

    // 12. Réponse JSON en cas de succès
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'cart_count' => $count, // Nombre total d'articles dans le panier
        'cart_total' => $total, // Montant total du panier
        'cart_items' => $_SESSION['cart'] // Le contenu complet du panier (utile pour le débogage)
    ]);

} catch (Exception $e) {
    // 13. Gestion des erreurs : renvoie une réponse JSON avec l'erreur
    // 'http_response_code()' : Définit le code de statut HTTP de la réponse.
    // Utilise le code de l'exception si défini, sinon 500 (Erreur interne du serveur).
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
?>