<?php
session_start(); // Démarrage de la session
require_once 'config.php'; // Inclut le fichier de configuration

// Assurez-vous que les fonctions utilitaires comme redirect() sont disponibles dans config.php
// et que la connexion PDO est établie via $pdo.

// 1. Initialiser le panier si ce n'est pas déjà fait
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 2. Nettoyer le panier : supprimer les entrées non conformes ou vides
// Cela garantit que chaque article dans le panier est un tableau avec les clés nécessaires.
foreach ($_SESSION['cart'] as $key => $item) {
    if (!is_array($item) || !isset($item['id'], $item['name'], $item['price'], $item['quantity']) || $item['quantity'] <= 0) {
        unset($_SESSION['cart'][$key]); // Supprime l'entrée si elle est corrompue ou quantité <= 0
    }
}

// 3. Gestion des actions sur le panier (Ajout, Mise à jour, Suppression, Vider, Incrémenter, Décrémenter)
// Ce bloc est exécuté lorsque l'utilisateur soumet un formulaire depuis la page du panier.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT); // Utilisation de filter_var pour la sécurité

        // Protection CSRF (Fortement recommandé pour tout formulaire POST)
        // Assurez-vous d'avoir une fonction generateCsrfToken() et verifyCsrfToken() dans config.php
        /*
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error_message'] = "Requête invalide (jeton de sécurité manquant ou incorrect).";
            redirect('cart.php');
            exit();
        }
        */

        // Pour les actions nécessitant un product_id, vérifier sa validité
        if (in_array($action, ['add', 'update', 'remove', 'increment_quantity', 'decrement_quantity'])) {
            if ($product_id === false || $product_id <= 0) {
                $_SESSION['error_message'] = "ID produit invalide pour l'action demandée.";
                redirect('cart.php');
                exit();
            }
        }

        switch ($action) {
            case 'add': // Ajouter un produit (si un bouton "Ajouter au panier" soumet ici)
                $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT);
                if ($quantity === false || $quantity <= 0) {
                    $_SESSION['error_message'] = "Quantité d'ajout invalide.";
                    break;
                }

                $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, image_url FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product) {
                    $current_cart_quantity = $_SESSION['cart'][$product_id]['quantity'] ?? 0;
                    $new_total_quantity = $current_cart_quantity + $quantity;

                    if ($product['stock_quantity'] >= $new_total_quantity) {
                        $_SESSION['cart'][$product_id] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $new_total_quantity,
                            'image' => $product['image_url'] ?? null
                        ];
                        $_SESSION['success_message'] = "Produit ajouté au panier avec succès.";
                    } else {
                        $_SESSION['error_message'] = "Stock insuffisant pour cette quantité. Stock disponible: " . $product['stock_quantity'];
                    }
                } else {
                    $_SESSION['error_message'] = "Produit introuvable.";
                }
                break;

            case 'update': // Mise à jour directe de la quantité via l'input type="number"
                $new_quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
                if ($new_quantity === false) {
                    $_SESSION['error_message'] = "Quantité invalide.";
                    break;
                }

                if (isset($_SESSION['cart'][$product_id])) {
                    if ($new_quantity <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                        $_SESSION['success_message'] = "Produit retiré du panier.";
                    } else {
                        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $stock = $stmt->fetchColumn();

                        if ($stock !== false && $stock >= $new_quantity) {
                            $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                            $_SESSION['success_message'] = "Quantité mise à jour.";
                        } else {
                            $_SESSION['error_message'] = "Stock insuffisant pour cette quantité (disponible: " . ($stock ?: 0) . ").";
                            // Optionnel: remettre la quantité à la quantité maximale disponible si le stock est dépassé
                            // $_SESSION['cart'][$product_id]['quantity'] = $stock; 
                            // $_SESSION['error_message'] .= " La quantité a été ajustée au stock disponible.";
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Produit introuvable dans le panier.";
                }
                break;

            case 'decrement_quantity': // Action du bouton '-'
                if (isset($_SESSION['cart'][$product_id])) {
                    if ($_SESSION['cart'][$product_id]['quantity'] > 1) {
                        $_SESSION['cart'][$product_id]['quantity']--;
                        $_SESSION['success_message'] = "Quantité diminuée.";
                    } else {
                        // Si la quantité est 1 et qu'on décrémente, on supprime l'article
                        unset($_SESSION['cart'][$product_id]);
                        $_SESSION['success_message'] = "Produit retiré du panier.";
                    }
                } else {
                    $_SESSION['error_message'] = "Produit introuvable dans le panier.";
                }
                break;

            case 'increment_quantity': // Action du bouton '+'
                if (isset($_SESSION['cart'][$product_id])) {
                    $current_quantity = $_SESSION['cart'][$product_id]['quantity'];
                    
                    $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $stmt->execute([$product_id]);
                    $stock = $stmt->fetchColumn();

                    if ($stock !== false && $stock > $current_quantity) {
                        $_SESSION['cart'][$product_id]['quantity']++;
                        $_SESSION['success_message'] = "Quantité augmentée.";
                    } else {
                        $_SESSION['error_message'] = "Stock maximum atteint pour ce produit (disponible: " . ($stock ?: 0) . ").";
                    }
                } else {
                    $_SESSION['error_message'] = "Produit introuvable dans le panier.";
                }
                break;

            case 'remove': // Supprimer un produit du panier
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]);
                    $_SESSION['success_message'] = "Produit supprimé du panier.";
                } else {
                    $_SESSION['error_message'] = "Produit introuvable dans le panier.";
                }
                break;

            case 'clear': // Vider tout le panier
                $_SESSION['cart'] = []; // Réinitialise le panier à un tableau vide
                $_SESSION['success_message'] = "Votre panier a été vidé.";
                break;

            default:
                $_SESSION['error_message'] = "Action non reconnue.";
                break;
        }
    } else {
        $_SESSION['error_message'] = "Aucune action spécifiée.";
    }
    // Redirige vers la même page pour éviter les re-soumissions de formulaire (Post/Redirect/Get pattern)
    redirect('cart.php'); 
    exit(); // Termine l'exécution après la redirection
}

// 4. Calculer le total du panier pour l'affichage
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

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="alert alert-info">
                    Votre panier est vide. <a href="products.php" class="alert-link">Parcourir nos produits</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="text-center">Produit</th>
                                                <th scope="col">Prix</th>
                                                <th scope="col">Quantité</th>
                                                <th scope="col">Total</th>
                                                <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                                            <?php if (is_array($item) && isset($item['name'], $item['price'], $item['quantity'])): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($item['image'])): ?>
                                                            <img src="<?= htmlspecialchars(BASE_URL . UPLOAD_URL_RELATIVE . $item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                        <?php endif; ?>
                                                        <span><?= htmlspecialchars($item['name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= number_format($item['price'], 2, ',', ' ') ?> €</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <form method="POST" class="d-inline-flex me-1">
                                                            <input type="hidden" name="action" value="decrement_quantity">
                                                            <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" 
                                                                    title="Diminuer la quantité" 
                                                                    <?= ($item['quantity'] <= 1) ? 'disabled' : '' ?>>
                                                                -
                                                            </button>
                                                        </form>

                                                        <input type="number" 
                                                               value="<?= (int)$item['quantity'] ?>" 
                                                               min="1" 
                                                               class="form-control form-control-sm text-center mx-1" 
                                                               style="width: 70px;" 
                                                               readonly> 
                                                        <form method="POST" class="d-inline-flex ms-1">
                                                            <input type="hidden" name="action" value="increment_quantity">
                                                            <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Augmenter la quantité">+</button>
                                                        </form>
                                                    </div>
                                                </td>
                                                <td><?= number_format($item['price'] * $item['quantity'], 2, ',', ' ') ?> €</td>
                                                <td>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer cet article" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article du panier ?');">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="clear">
                                        <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir vider tout le panier ?');">
                                            <i class="bi bi-cart-x"></i> Vider le panier
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Récapitulatif de la commande</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Sous-total</span>
                                    <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Livraison</span>
                                    <span>Gratuite</span> </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Total</span>
                                    <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                                </div>

                                <?php if (function_exists('isLoggedIn') && isLoggedIn()): // Vérifie si la fonction existe avant de l'appeler ?>
                                    <a href="checkout.php" class="btn btn-primary w-100 mt-3">
                                        <i class="bi bi-credit-card"></i> Passer la commande
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning mt-3">
                                        Vous devez <a href="login.php" class="alert-link">vous connecter</a> ou
                                        <a href="register.php" class="alert-link">créer un compte</a> pour passer commande.
                                    </div>
                                <?php endif; ?>
                                <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">
                                    <i class="bi bi-shop"></i> Continuer vos achats
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>