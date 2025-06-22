<?php
session_start(); // Démarrage de la session
require_once 'config.php'; // Inclut le fichier de configuration

// 1. Initialiser le panier si ce n'est pas déjà fait
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 2. Nettoyer le panier : supprimer les entrées non conformes
// Cela garantit que chaque article dans le panier est un tableau avec les clés nécessaires.
foreach ($_SESSION['cart'] as $key => $item) {
    if (!is_array($item) || !isset($item['name'], $item['price'], $item['quantity'])) {
        unset($_SESSION['cart'][$key]); // Supprime l'entrée si elle est corrompue
    }
}

// 3. Gestion des actions sur le panier (Ajout, Mise à jour, Suppression, Vider)
// Ce bloc est exécuté lorsque l'utilisateur soumet un formulaire depuis la page du panier.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0; // Convertit en entier pour sécurité

        switch ($action) {
            case 'add': // Ajouter un produit (peut être redondant avec add_to_cart.php si tout passe par AJAX)
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

                // Vérifier si le produit existe et est en stock
                $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, image_url FROM products WHERE id = ?"); // Ajout de image_url
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();

                if ($product && $product['stock_quantity'] >= $quantity) {
                    if (isset($_SESSION['cart'][$product_id]) && is_array($_SESSION['cart'][$product_id])) {
                        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                    } else {
                        // S'assurer que toutes les infos nécessaires sont stockées
                        $_SESSION['cart'][$product_id] = [
                            'id' => $product['id'], // Ajout de l'ID pour consistance
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $quantity,
                            'image' => $product['image_url'] ?? null // Ajout de l'image
                        ];
                    }
                    $_SESSION['success_message'] = "Produit ajouté au panier avec succès.";
                } else {
                    $_SESSION['error_message'] = "Produit indisponible ou stock insuffisant.";
                }
                break;

            case 'update': // Mettre à jour la quantité d'un produit
                $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

                // S'assure que la quantité est positive et que l'article existe dans le panier
                if ($quantity > 0 && isset($_SESSION['cart'][$product_id]) && is_array($_SESSION['cart'][$product_id])) {
                    // Vérifier le stock disponible en base de données pour la nouvelle quantité
                    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $stock = $stmt->fetchColumn(); // Récupère la valeur de la première colonne

                    if ($stock >= $quantity) {
                        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        $_SESSION['success_message'] = "Quantité mise à jour.";
                    } else {
                        $_SESSION['error_message'] = "Stock insuffisant pour cette quantité. Stock disponible: " . $stock;
                    }
                } else {
                     $_SESSION['error_message'] = "Quantité ou produit invalide.";
                }
                break;

            case 'remove': // Supprimer un produit du panier
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]); // Supprime l'entrée du panier
                    $_SESSION['success_message'] = "Produit supprimé du panier.";
                }
                break;

            case 'clear': // Vider tout le panier
                $_SESSION['cart'] = []; // Réinitialise le panier à un tableau vide
                $_SESSION['success_message'] = "Votre panier a été vidé.";
                break;
        }
    }
    redirect('cart.php'); // 4. Redirige vers la même page pour éviter les resoumissions de formulaire (Post/Redirect/Get pattern)
}

// 5. Calculer le total du panier pour l'affichage
$total = 0;
foreach ($_SESSION['cart'] as $item) {
    if (is_array($item) && isset($item['price'], $item['quantity'])) {
        $total += $item['price'] * $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="js/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <h2 class="mb-4">Votre panier</h2>

            <!-- 6. Affichage des messages de succès et d'erreur -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <?php unset($_SESSION['success_message']); // Supprime le message après affichage ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <?php unset($_SESSION['error_message']); // Supprime le message après affichage ?>
                </div>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <!-- 7. Message si le panier est vide -->
                <div class="alert alert-info">
                    Votre panier est vide. <a href="products.php" class="alert-link">Parcourir nos produits</a>
                </div>
            <?php else: ?>
                <!-- 8. Contenu du panier (tableau des articles, récapitulatif) -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Produit</th>
                                                <th>Prix</th>
                                                <th>Quantité</th>
                                                <th>Total</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                                <?php if (is_array($item) && isset($item['name'], $item['price'], $item['quantity'])): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td><?= number_format($item['price'], 2) ?> €</td>
                                                    <td>
                                                        <!-- Formulaire pour mettre à jour la quantité -->
                                                        <form method="POST" class="d-flex">
                                                            <input type="hidden" name="action" value="update">
                                                            <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                                                            <input type="number" name="quantity" value="<?= (int)$item['quantity'] ?>" min="1" class="form-control form-control-sm" style="width: 70px;">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary ms-2">
                                                                <i class="bi bi-arrow-repeat"></i> <!-- Icône de rafraîchissement -->
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td><?= number_format($item['price'] * $item['quantity'], 2) ?> €</td>
                                                    <td>
                                                        <!-- Formulaire pour supprimer un produit -->
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="remove">
                                                            <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i> <!-- Icône de corbeille -->
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <!-- Formulaire pour vider tout le panier -->
                                    <form method="POST">
                                        <input type="hidden" name="action" value="clear">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-cart-x"></i> Vider le panier
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Récapitulatif</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Sous-total</span>
                                    <span><?= number_format($total, 2) ?> €</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Livraison</span>
                                    <span>Gratuite</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Total</span>
                                    <span><?= number_format($total, 2) ?> €</span>
                                </div>

                                <?php if (isLoggedIn()): ?>
                                    <!-- Bouton pour passer à la commande si connecté -->
                                    <a href="checkout.php" class="btn btn-primary w-100 mt-3">
                                        <i class="bi bi-credit-card"></i> Passer la commande
                                    </a>
                                <?php else: ?>
                                    <!-- Message invitant à se connecter/créer un compte si non connecté -->
                                    <div class="alert alert-warning mt-3">
                                        Vous devez <a href="login.php" class="alert-link">vous connecter</a> ou
                                        <a href="register.php" class="alert-link">créer un compte</a> pour passer commande.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>