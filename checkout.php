<?php
// checkout.php - Page de finalisation de commande

// 1. Démarrage de la session et inclusion des fichiers essentiels
session_start(); 
// DOIT être la première chose dans chaque script PHP qui utilise les sessions

// Affichage des erreurs pour le débogage (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'includes/functions.php'; // Ce fichier doit contenir isLoggedIn(), setFlashMessage(), redirect(), generateCsrfToken()

// 2. Vérification de l'authentification de l'utilisateur
if (!isLoggedIn()) {
    setFlashMessage('danger', "Vous devez être connecté pour accéder à la page de paiement.");
    redirect(BASE_URL . 'login.php'); // Utilisez BASE_URL pour une redirection robuste
    exit();
}

// 3. Récupération et validation de l'ID utilisateur depuis la session
$user_id = $_SESSION['user']['id'] ?? null;

if (is_null($user_id)) {
    // Loggez l'erreur pour le débogage (ceci ne devrait pas arriver si isLoggedIn() est correct)
    error_log("ERREUR SESSION: user['id'] non trouvé dans la session sur checkout.php après isLoggedIn().");
    setFlashMessage('danger', "Erreur de session. Veuillez vous reconnecter.");
    redirect(BASE_URL . 'login.php');
    exit();
}

// Ajout de log pour vérifier la valeur de user_id (utile pour le débogage)
error_log("DEBUG CHECKOUT: user_id récupéré de la session: " . $user_id);

// 4. Initialisation des variables
$cart_items_db = [];
$total = 0;
$customer_id = null;
$customer_info = []; // Variable pour stocker toutes les infos du client
$customer_address_from_db = '';

// 5. Récupération des informations du panier et des informations complètes du client
try {
    // Récupérer les informations complètes du client (y compris prénom, nom, email)
    $stmt_customer = $pdo->prepare("SELECT id, first_name, last_name, email, phone, address, city, zip_code, country FROM customers WHERE user_id = :user_id");
    $stmt_customer->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_customer->execute();
    $customer_info = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    // --- Validation complète des informations client ---
    if (!$customer_info ||
        empty($customer_info['first_name']) ||
        empty($customer_info['last_name']) ||
        empty($customer_info['email']) ||
        empty($customer_info['address']) ||
        empty($customer_info['city']) ||
        empty($customer_info['country'])) {

        setFlashMessage('danger', "Vos informations client sont manquantes ou incomplètes. Veuillez mettre à jour votre profil.");
        redirect(BASE_URL . 'profile.php');
        exit();
    }
    // --- Fin de la validation complète ---

    $customer_id = $customer_info['id']; // Assurez-vous que l'ID du client est défini

    // Construire l'adresse complète pour l'affichage
    $address_parts = [];
    if (!empty($customer_info['address'])) $address_parts[] = $customer_info['address'];
    if (!empty($customer_info['city'])) $address_parts[] = $customer_info['city'];
    if (!empty($customer_info['zip_code'])) $address_parts[] = $customer_info['zip_code']; // zip_code peut être vide, mais inclus si présent
    if (!empty($customer_info['country'])) $address_parts[] = $customer_info['country'];
    $customer_address_from_db = implode(", ", $address_parts);

    // Récupérer les articles du panier
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

    error_log("DEBUG CHECKOUT: Nombre d'articles récupérés du panier pour user_id {$user_id}: " . count($cart_items_db));

    if (empty($cart_items_db)) {
        setFlashMessage('warning', "Votre panier est vide. Impossible de procéder au paiement.");
        redirect(BASE_URL . 'cart.php');
        exit();
    }

    // Calcul du total et vérification des stocks
    $total = 0;
    $stock_issues = false; // Variable pour suivre les problèmes de stock
    foreach ($cart_items_db as $key => $item) {
        if (isset($item['item_price'], $item['quantity']) && is_numeric($item['item_price']) && is_numeric($item['quantity'])) {
            $total += (float)$item['item_price'] * (int)$item['quantity'];

            // Déterminer le stock disponible (produit ou variante)
            if ($item['variant_id'] !== null) {
                $cart_items_db[$key]['current_available_stock'] = (int)$item['variant_stock_quantity'];
            } else {
                $cart_items_db[$key]['current_available_stock'] = (int)$item['product_stock_quantity'];
            }

            // Vérifier si la quantité commandée est supérieure au stock disponible
            if ($item['quantity'] > $cart_items_db[$key]['current_available_stock']) {
                $stock_issues = true;
                setFlashMessage('danger', "Stock insuffisant pour l'article '" . htmlspecialchars($item['product_name']) . (!empty($item['variant_name']) ? ' (' . htmlspecialchars($item['variant_name']) . ')' : '') . "'. Veuillez ajuster les quantités avant de commander.");
                redirect(BASE_URL . 'cart.php'); // Rediriger immédiatement si un problème de stock est trouvé
                exit();
            }

        } else {
            // Loggez les articles corrompus pour investigation
            error_log("Article corrompu dans le panier DB pour user_id {$user_id}: " . json_encode($item));
            setFlashMessage('danger', "Une erreur est survenue avec un article de votre panier. Veuillez le vider et réessayer.");
            redirect(BASE_URL . 'cart.php');
            exit();
        }
    }

} catch (PDOException $e) {
    // Loggez l'erreur de base de données
    error_log("Erreur PDO dans checkout.php lors de la récupération du panier ou des infos client: " . $e->getMessage());
    setFlashMessage('danger', "Une erreur est survenue lors du chargement de la page de paiement. Veuillez réessayer.");
    redirect(BASE_URL . 'cart.php');
    exit();
}

// 6. Génération du jeton CSRF pour le formulaire de paiement
// Il est recommandé d'avoir une fonction dédiée dans functions.php qui gère cela.
$csrf_token = generateCsrfToken(); // Assurez-vous que cette fonction existe et gère la session.

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <h2 class="mb-4">Finaliser votre commande</h2>

            <?php include 'includes/flash_messages.php'; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Récapitulatif de votre panier</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($cart_items_db as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <?= htmlspecialchars($item['product_name']) ?>
                                            <?php if (!empty($item['variant_name'])): ?>
                                                <small class="text-muted">(<?= htmlspecialchars($item['variant_name']) ?>)</small>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted"><?= (int)$item['quantity'] ?> x <?= number_format((float)$item['item_price'], 2, ',', ' ') ?> €</small>
                                        </div>
                                        <span><?= number_format((float)$item['item_price'] * (int)$item['quantity'], 2, ',', ' ') ?> €</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="d-flex justify-content-between fw-bold mt-3">
                                <span>Total du panier</span>
                                <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Informations de livraison et de paiement</h5>
                        </div>
                        <div class="card-body">
                            <form action="process_checkout.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="total_amount" value="<?= htmlspecialchars($total) ?>">
                                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">

                                <div class="mb-3">
                                    <label for="delivery_address_display" class="form-label">Adresse de livraison</label>
                                    <textarea class="form-control" id="delivery_address_display" rows="3" readonly><?= htmlspecialchars($customer_info['first_name'] . ' ' . $customer_info['last_name'] . PHP_EOL . $customer_info['email'] . PHP_EOL . $customer_info['phone'] . PHP_EOL . $customer_address_from_db) ?></textarea>
                                    <small class="form-text text-muted">Ceci est l'adresse enregistrée dans votre profil. Pour la modifier, veuillez <a href="profile.php">mettre à jour votre profil</a>.</small>
                                    <input type="hidden" name="delivery_first_name" value="<?= htmlspecialchars($customer_info['first_name']) ?>">
                                    <input type="hidden" name="delivery_last_name" value="<?= htmlspecialchars($customer_info['last_name']) ?>">
                                    <input type="hidden" name="delivery_email" value="<?= htmlspecialchars($customer_info['email']) ?>">
                                    <input type="hidden" name="delivery_phone" value="<?= htmlspecialchars($customer_info['phone']) ?>">
                                    <input type="hidden" name="delivery_address" value="<?= htmlspecialchars($customer_info['address']) ?>">
                                    <input type="hidden" name="delivery_city" value="<?= htmlspecialchars($customer_info['city']) ?>">
                                    <input type="hidden" name="delivery_zip_code" value="<?= htmlspecialchars($customer_info['zip_code']) ?>">
                                    <input type="hidden" name="delivery_country" value="<?= htmlspecialchars($customer_info['country']) ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Méthode de paiement</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Choisissez une méthode de paiement</option>
                                        <option value="cash_on_delivery">Paiement à la livraison</option>
                                        <option value="card">Carte bancaire (en ligne)</option>
                                        <option value="paypal">PayPal</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-bag-check"></i> Confirmer la commande
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Besoin d'aide ?</h5>
                            <p class="card-text">Contactez notre service client pour toute question concernant votre commande.</p>
                            <a href="contact.php" class="btn btn-outline-info w-100">Contactez-nous</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>