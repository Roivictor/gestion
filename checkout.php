<?php
require_once 'config.php'; // Inclut la configuration et les fonctions utilitaires

// Assurez-vous que l'utilisateur est connecté
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Veuillez vous connecter pour passer commande.";
    redirect(BASE_URL . 'login.php');
    exit();
}

// Assurez-vous que le panier n'est pas vide
if (empty($_SESSION['cart'])) {
    $_SESSION['warning_message'] = "Votre panier est vide. Veuillez ajouter des produits avant de passer commande.";
    redirect(BASE_URL . 'cart.php'); // Redirige vers le panier
    exit();
}

$user_id = $_SESSION['user']['id'];
$cart_items = $_SESSION['cart'];
$total_amount = 0;
$checkout_products = []; // Pour stocker les détails des produits avec stock vérifié

// Vérification des stocks et calcul du total avant affichage du formulaire de paiement
try {
    foreach ($cart_items as $product_id => $item) {
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, image_url FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            $_SESSION['error_message'] = "Le produit '" . htmlspecialchars($item['name']) . "' n'existe plus.";
            redirect(BASE_URL . 'cart.php');
            exit();
        }

        if ($product['stock_quantity'] < $item['quantity']) {
            $_SESSION['error_message'] = "Stock insuffisant pour '" . htmlspecialchars($product['name']) . "'. Disponible: " . $product['stock_quantity'] . ". Quantité demandée: " . $item['quantity'] . ".";
            redirect(BASE_URL . 'cart.php');
            exit();
        }

        $line_total = $item['quantity'] * $product['price'];
        $total_amount += $line_total;

        $checkout_products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'quantity' => $item['quantity'],
            'unit_price' => $product['price'],
            'image_url' => $product['image_url'],
            'line_total' => $line_total
        ];
    }
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la vérification du stock au checkout: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la vérification de votre commande. Veuillez réessayer.";
    redirect(BASE_URL . 'cart.php');
    exit();
}

// Générer le token CSRF pour le formulaire de paiement
$csrf_token = generateCsrfToken();

// Inclure l'en-tête (qui contient la balise meta CSRF et la navigation)
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css"> </head>
<body>
    <div class="container my-5">
        <h1 class="mb-4">Finaliser la commande</h1>

        <?php include 'includes/flash_messages.php'; // Pour afficher les messages de session ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">Récapitulatif de la commande</div>
                    <div class="card-body">
                        <ul class="list-group mb-3">
                            <?php foreach ($checkout_products as $product): ?>
                                <li class="list-group-item d-flex justify-content-between lh-sm">
                                    <div>
                                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'path/to/default_image.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                        <h6 class="my-0"><?= htmlspecialchars($product['name']) ?></h6>
                                        <small class="text-muted">Quantité: <?= $product['quantity'] ?> x <?= number_format($product['unit_price'], 2) ?> €</small>
                                    </div>
                                    <span class="text-muted"><?= number_format($product['line_total'], 2) ?> €</span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Total (EUR)</span>
                                <strong><?= number_format($total_amount, 2) ?> €</strong>
                            </li>
                        </ul>

                        <h5 class="mb-3">Informations de livraison et de facturation</h5>
                        <p>
                            Adresse de livraison: [À implémenter, par exemple depuis le profil utilisateur]<br>
                            Adresse de facturation: [À implémenter]
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Méthode de paiement</div>
                    <div class="card-body">
                        <form action="process_checkout.php" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="total_amount" value="<?= htmlspecialchars($total_amount) ?>">

                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Choisissez une méthode de paiement</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Sélectionnez...</option>
                                    <option value="card">Carte de crédit/débit</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="bank_transfer">Virement bancaire</option>
                                    </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Confirmer et Payer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script> </body>
</html>