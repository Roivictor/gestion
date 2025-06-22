<?php
session_start(); // 1. Démarrage de la session pour accéder au panier

$cart_count = 0; // 2. Initialisation du compteur d'articles
$cart_total = 0; // 3. Initialisation du total du panier

// ATTENTION: Ces prix sont des exemples. Dans une vraie application, vous devriez
// toujours récupérer les prix des produits depuis la base de données
// pour éviter la fraude ou les prix obsolètes.
$prices = [1 => 10.0, 2 => 15.5, 3 => 20.0]; // Exemple de prix (à remplacer !)

if (isset($_SESSION['cart'])) { // 4. Vérifie si le panier existe en session
    // Ce calcul de $cart_count et $cart_total est basé sur une ancienne structure de panier
    // où $_SESSION['cart'] contenait directement les quantités par ID.
    // Votre 'add_to_cart.php' actuel stocke des tableaux associatifs dans $_SESSION['cart'].

    // Si $_SESSION['cart'] est un tableau de [id => quantity], alors array_sum($_SESSION['cart']) fonctionne.
    // Si $_SESSION['cart'] est un tableau de [id => ['name' => ..., 'price' => ..., 'quantity' => ...]],
    // alors vous devez itérer et sommer les quantités.

    // 5. Calcul du nombre d'articles et du total (selon la structure actuelle de votre panier)
    foreach ($_SESSION['cart'] as $product_id => $item) {
        if (is_array($item) && isset($item['quantity'], $item['price'])) {
            $cart_count += $item['quantity'];
            $cart_total += $item['price'] * $item['quantity'];
        }
    }
}

// 6. Renvoie la réponse JSON
echo json_encode([
    'success' => true,
    'count' => $cart_count, // Nombre total d'articles
    'total' => $cart_total  // Montant total du panier
]);
?>