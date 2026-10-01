<?php
session_start();
 // Démarrage de la session

// --- PARAMÈTRES DE DÉBOGAGE (À DÉSACTIVER EN PRODUCTION) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DES PARAMÈTRES DE DÉBOGAGE ---

require_once 'config.php'; // Inclut le fichier de configuration (doit contenir $pdo, redirect, SITE_NAME, UPLOAD_URL)
require_once 'includes/functions.php'; // Inclut les fonctions utilitaires, comme isLoggedIn(), generateCsrfToken(), verifyCsrfToken()

// Vérification que la connexion PDO est bien établie
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Erreur fatale : La connexion à la base de données (\$pdo) n'est pas définie ou n'est pas un objet PDO valide dans config.php.");
}

// Générer le jeton CSRF si ce n'est pas déjà fait pour cette session
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token']; // Récupérer le token pour l'affichage dans les formulaires

// 1. Récupération de l'ID de l'utilisateur connecté
// CORRECTION APPLIQUÉE ICI : Utilise $_SESSION['user']['id'] pour la cohérence avec le login.
$user_id = $_SESSION['user']['id'] ?? null;
$cart_items_db = []; // Initialisation, sera remplie ci-dessous
$total = 0; // Initialisation du total du panier

// --- RÉCUPÉRATION DU PANIER (TOUJOURS À JOUR AVANT LES ACTIONS POST) ---
if (isLoggedIn()) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                ci.id AS cart_item_id,
                ci.quantity,
                ci.price_at_addition AS item_price,
                p.id AS product_id,
                p.name AS product_name,
                p.image_url AS product_image_url,
                p.stock_quantity AS product_stock_quantity,
                pv.id AS variant_id,
                pv.name AS variant_name,
                pv.image_url AS variant_image_url,
                pv.stock_quantity AS variant_stock_quantity
            FROM
                cart_items ci
            INNER JOIN
                products p ON ci.product_id = p.id
            LEFT JOIN
                product_variants pv ON ci.variant_id = pv.id
            WHERE
                ci.user_id = :user_id
            ORDER BY
                ci.id ASC
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $cart_items_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0; // Réinitialiser le total avant le calcul
        foreach ($cart_items_db as $key => $item) {
            if (isset($item['item_price'], $item['quantity']) && is_numeric($item['item_price']) && is_numeric($item['quantity'])) {
                $total += (float)$item['item_price'] * (int)$item['quantity'];

                if ($item['variant_id'] !== null) {
                    $cart_items_db[$key]['current_available_stock'] = (int)$item['variant_stock_quantity'];
                } else {
                    $cart_items_db[$key]['current_available_stock'] = (int)$item['product_stock_quantity'];
                }
            } else {
                error_log("Article corrompu dans le panier DB pour user_id {$user_id}: " . json_encode($item));
            }
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO dans cart.php lors de la récupération du panier: " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération de votre panier. Veuillez réessayer.";
    }
} else {
    $_SESSION['info_message'] = "Connectez-vous pour accéder à votre panier sauvegardé.";
}
// --- FIN DE LA RÉCUPÉRATION DU PANIER ---


// --- DÉBUT DU TRAITEMENT DES ACTIONS POST (Géré directement ici) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token_posted = $_POST['csrf_token'] ?? '';

    // Vérification du token CSRF pour TOUTES les actions POST
    if (!verifyCsrfToken($csrf_token_posted)) {
        $_SESSION['error_message'] = 'Jeton CSRF invalide. Veuillez réessayer.';
        error_log("CSRF Token Invalid for action '{$action}': Provided '{$csrf_token_posted}' vs Session '" . ($_SESSION['csrf_token'] ?? 'NOT SET') . "' on " . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));
        redirect('cart.php');
        exit();
    }

    if (!isLoggedIn()) {
        $_SESSION['error_message'] = "Vous devez être connecté pour effectuer cette action.";
        redirect('login.php');
        exit();
    }

    // Récupération et validation des données pour les actions
    $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    
    // Gérer variant_id comme null si c'est une chaîne vide ou "null"
    $variant_id = null;
    if (isset($_POST['variant_id'])) {
        if ($_POST['variant_id'] === null || $_POST['variant_id'] === '' || (is_string($_POST['variant_id']) && strtolower($_POST['variant_id']) === 'null')) {
            $variant_id = null;
        } else {
            $variant_id = filter_var($_POST['variant_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        }
    }

    try {
        $pdo->beginTransaction();

        switch ($action) {
            case 'increment_quantity':
            case 'decrement_quantity':
                if ($product_id === null || $product_id <= 0) {
                    $_SESSION['error_message'] = 'ID produit invalide pour la mise à jour de quantité.';
                    break;
                }

                // Récupérer l'article actuel du panier et son stock disponible
                $stmt = $pdo->prepare("SELECT ci.id, ci.quantity, p.stock_quantity as product_stock, pv.stock_quantity as variant_stock
                                       FROM cart_items ci
                                       INNER JOIN products p ON ci.product_id = p.id
                                       LEFT JOIN product_variants pv ON ci.variant_id = pv.id
                                       WHERE ci.user_id = :user_id AND ci.product_id = :product_id AND ci.variant_id <=> :variant_id");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt->bindValue(':variant_id', $variant_id, PDO::PARAM_INT);
                $stmt->execute();
                $item_in_cart = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item_in_cart) {
                    $_SESSION['error_message'] = 'Article non trouvé dans le panier.';
                    break;
                }

                $current_quantity = (int)$item_in_cart['quantity'];
                $available_stock = ($variant_id !== null) ? (int)$item_in_cart['variant_stock'] : (int)$item_in_cart['product_stock'];

                $new_quantity = $current_quantity;

                if ($action === 'increment_quantity') {
                    $new_quantity++;
                    if ($new_quantity > $available_stock) {
                        $_SESSION['error_message'] = 'Stock insuffisant. Maximum disponible: ' . $available_stock . '.';
                        break;
                    }
                } elseif ($action === 'decrement_quantity') {
                    $new_quantity--;
                    if ($new_quantity < 1) {
                        // Si la quantité passe à 0 ou moins, on supprime l'article
                        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = :item_id AND user_id = :user_id_check");
                        $stmt->bindParam(':item_id', $item_in_cart['id'], PDO::PARAM_INT);
                        $stmt->bindParam(':user_id_check', $user_id, PDO::PARAM_INT); // Ajout d'une vérification supplémentaire
                        $stmt->execute();
                        $_SESSION['success_message'] = 'Article supprimé du panier.';
                        $pdo->commit(); // Commit avant la redirection pour la suppression
                        redirect('cart.php'); // Rediriger immédiatement pour vider les POST
                        exit();
                    }
                }

                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = :new_quantity, updated_at = NOW() WHERE id = :item_id AND user_id = :user_id_check");
                $stmt->bindParam(':new_quantity', $new_quantity, PDO::PARAM_INT);
                $stmt->bindParam(':item_id', $item_in_cart['id'], PDO::PARAM_INT);
                $stmt->bindParam(':user_id_check', $user_id, PDO::PARAM_INT); // Ajout d'une vérification supplémentaire
                $stmt->execute();

                $_SESSION['success_message'] = 'Quantité mise à jour.';
                break;

            case 'remove':
                if ($product_id === null || $product_id <= 0) {
                    $_SESSION['error_message'] = 'ID produit invalide pour la suppression.';
                    break;
                }
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id AND product_id = :product_id AND variant_id <=> :variant_id");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt->bindValue(':variant_id', $variant_id, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = 'Article supprimé du panier.';
                break;

            case 'clear':
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                $_SESSION['success_message'] = 'Panier vidé avec succès.';
                break;

            default:
                $_SESSION['error_message'] = 'Action non reconnue.';
                break;
        }

        // Si la transaction n'a pas été déjà commit (par exemple, pour la suppression d'un item qui est à 0)
        // et qu'aucune erreur n'est survenue dans le switch, on commit ici.
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur PDO dans cart.php lors de l'action {$action}: " . $e->getMessage());
        $_SESSION['error_message'] = 'Une erreur de base de données est survenue lors du traitement de votre demande.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur générale dans cart.php lors de l'action {$action}: " . $e->getMessage());
        $_SESSION['error_message'] = 'Une erreur inattendue est survenue.';
    }

    // Après chaque action POST, rediriger pour éviter la soumission multiple et mettre à jour l'affichage
    if (!headers_sent()) {
        redirect('cart.php');
        exit();
    }
}
// --- FIN DU TRAITEMENT DES ACTIONS POST ---


// --- DÉBUT HTML ---
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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

            <?php if (isset($_SESSION['info_message'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['info_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['info_message']); ?>
            <?php endif; ?>

            <?php if (empty($cart_items_db)): ?>
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
                                            <?php foreach ($cart_items_db as $item): ?>
                                            <?php
                                            $display_name = htmlspecialchars($item['product_name']);
                                            if (!empty($item['variant_name'])) {
                                                $display_name .= ' (' . htmlspecialchars($item['variant_name']) . ')';
                                            }

                                            $image_url_to_display = !empty($item['variant_image_url']) ? $item['variant_image_url'] : $item['product_image_url'];
                                            
                                            $available_stock_for_item = $item['current_available_stock'] ?? 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($image_url_to_display)): ?>
                                                            <img src="<?= htmlspecialchars(UPLOAD_URL . $image_url_to_display) ?>" alt="<?= $display_name ?>" class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="img-thumbnail me-3 bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                                                <i class="bi bi-image fs-4 text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <span><?= $display_name ?></span>
                                                            <?php if ($item['quantity'] > $available_stock_for_item): ?>
                                                                <p class="text-danger small mb-0">Stock insuffisant ! (Dispo: <?= $available_stock_for_item ?>)</p>
                                                            <?php elseif ($available_stock_for_item < 5): ?>
                                                                <p class="text-warning small mb-0">Plus que <?= $available_stock_for_item ?> en stock !</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= number_format((float)$item['item_price'], 2, ',', ' ') ?> €</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <form method="POST" class="d-inline-flex me-1" action="cart.php">
                                                            <input type="hidden" name="action" value="decrement_quantity">
                                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                                            <input type="hidden" name="variant_id" value="<?= ($item['variant_id'] !== null ? (int)$item['variant_id'] : '') ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                    title="Diminuer la quantité"
                                                                    <?= ((int)$item['quantity'] <= 1) ? 'disabled' : '' ?>>
                                                                -
                                                            </button>
                                                        </form>

                                                        <input type="number"
                                                               value="<?= (int)$item['quantity'] ?>"
                                                               min="1"
                                                               class="form-control form-control-sm text-center mx-1"
                                                               style="width: 70px;"
                                                               readonly>

                                                        <form method="POST" class="d-inline-flex ms-1" action="cart.php">
                                                            <input type="hidden" name="action" value="increment_quantity">
                                                            <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                                            <input type="hidden" name="variant_id" value="<?= ($item['variant_id'] !== null ? (int)$item['variant_id'] : '') ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                                    title="Augmenter la quantité"
                                                                    <?= ((int)$item['quantity'] >= $available_stock_for_item) ? 'disabled' : '' ?>>
                                                                +
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                                <td><?= number_format((float)$item['item_price'] * (int)$item['quantity'], 2, ',', ' ') ?> €</td>
                                                <td>
                                                    <form method="POST" action="cart.php">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                                                        <input type="hidden" name="variant_id" value="<?= ($item['variant_id'] !== null ? (int)$item['variant_id'] : '') ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer cet article" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article du panier ?');">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end mt-3">
                                    <form method="POST" action="cart.php">
                                        <input type="hidden" name="action" value="clear">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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
                                    <span>Gratuite</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Total</span>
                                    <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                                </div>

                                <?php if (isLoggedIn()): ?>
                                    <a href="checkout.php" class="btn btn-primary w-100 mt-3" <?php echo empty($cart_items_db) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-credit-card"></i> Procéder au paiement
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