<?php
// order_details.php - Affiche les détails d'une commande spécifique pour un client

// Inclure le fichier de configuration (connexion DB, fonctions utilitaires)
require_once 'config.php';
require_once 'includes/functions.php'; // Assurez-vous que formatDate, isLoggedIn, redirect sont ici

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php'); // Redirige vers la page de connexion
    exit(); // Termine l'exécution après la redirection
}

// Vérifier que l'ID de la commande est passé dans l'URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Si l'ID est manquant ou invalide, rediriger vers la liste des commandes ou afficher une erreur
    $_SESSION['error_message'] = "ID de commande invalide.";
    redirect('mes_commandes.php'); // Ou la page qui liste les commandes
    exit();
}

$order_id = $_GET['id'];
// CORRECTION ICI : Assurez-vous que l'ID de l'utilisateur est bien pris de $_SESSION['user']['id']
$user_id = $_SESSION['user']['id'] ?? null;

// Vérification de sécurité supplémentaire pour l'ID utilisateur
if ($user_id === null) {
    error_log("User ID not found in session for logged in user on order_details.php.");
    $_SESSION['error_message'] = "Votre session utilisateur est invalide. Veuillez vous reconnecter.";
    redirect('login.php');
    exit();
}

$order_details = null;
$products_in_order = [];
$error_message = '';

try {
    // Vérifier que la connexion PDO est bien établie
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Erreur de configuration : la connexion à la base de données n\'est pas disponible.');
    }

    // 1. Récupérer les détails de la commande et les informations du client/utilisateur
    // Nous utilisons les jointures appropriées : orders -> customers -> users
    $stmt_order = $pdo->prepare("
        SELECT
            o.id AS order_id,
            o.order_date,
            o.total_amount,
            o.status,
            o.payment_method,
            o.payment_status,
            c.address AS customer_address,
            c.phone AS customer_phone,
            u.first_name AS customer_first_name,
            u.last_name AS customer_last_name,
            u.username AS customer_username
        FROM
            orders o
        JOIN
            customers c ON o.customer_id = c.id
        JOIN
            users u ON c.user_id = u.id
        WHERE
            o.id = :order_id AND u.id = :user_id -- Très important : s'assurer que la commande appartient bien à l'utilisateur connecté
    ");
    $stmt_order->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order_details = $stmt_order->fetch(PDO::FETCH_ASSOC);

    // Si la commande n'est pas trouvée ou n'appartient pas à l'utilisateur
    if (!$order_details) {
        $_SESSION['error_message'] = "Commande introuvable ou vous n'avez pas l'autorisation d'y accéder.";
        redirect('mes_commandes.php');
        exit();
    }

    // 2. Récupérer les produits inclus dans cette commande, y compris les détails des VARIANTE
    // Assurez-vous que votre table `order_details` a une colonne `variant_id` (NULL si pas de variante)
    // Et que `product_variants` a `name`, `color_name`, `size_name` (ou des colonnes similaires)
    // CORRECTION : Utilisation des colonnes color_name et size_name directement de product_variants
    // et suppression des jointures sur product_colors et product_sizes.
    $stmt_products = $pdo->prepare("
        SELECT
            od.quantity,
            od.unit_price,
            p.name AS product_name,
            p.description AS product_description,
            p.image_url AS product_image,
            pv.name AS variant_name,          -- Nom de la variante (ex: 'Rouge L')
            pv.sku AS variant_sku,            -- SKU de la variante
            pv.color_name AS variant_color,   -- Nom de la couleur de la variante (pris directement de product_variants)
            pv.size_name AS variant_size      -- Nom de la taille de la variante (pris directement de product_variants)
        FROM
            order_details od
        JOIN
            products p ON od.product_id = p.id
        LEFT JOIN                                -- Utilisez LEFT JOIN car tous les produits n'ont pas forcément de variantes
            product_variants pv ON od.variant_id = pv.id -- CORRIGÉ ICI : 'od.variant_id' au lieu de 'od.product_variant_id'
        WHERE
            od.order_id = :order_id
    ");
    $stmt_products->execute([':order_id' => $order_id]);
    $products_in_order = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

    // --- DÉBOGAGE TEMPORAIRE : AJOUTEZ CES LIGNES --- (COMMENTÉES/SUPPRIMÉES CAR PLUS NÉCESSAIRES)
    // echo '<pre>DEBUG: order_id: ' . htmlspecialchars($order_id) . '</pre>';
    // echo '<pre>DEBUG: user_id: ' . htmlspecialchars($user_id) . '</pre>';
    // echo '<pre>DEBUG: Contenu de $order_details :<br>';
    // var_dump($order_details);
    // echo '</pre>';
    // echo '<pre>DEBUG: Contenu de $products_in_order :<br>';
    // var_dump($products_in_order);
    // echo '</pre>';
    // --- FIN DÉBOGAGE TEMPORAIRE ---

} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des détails de commande sur order_details.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $error_message = "Une erreur est survenue lors du chargement des détails de la commande. Veuillez réessayer plus tard.";
} catch (Exception $e) {
    error_log("Erreur générale sur order_details.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $error_message = "Une erreur inattendue est survenue. Veuillez réessayer plus tard.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la commande #<?= htmlspecialchars($order_id) ?> - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> </head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container my-5">
        <h2 class="mb-4">Détails de la commande #<?= htmlspecialchars($order_id) ?></h2>

        <?php
        // Affiche les messages d'erreur stockés en session ou générés par le script
        if (isset($_SESSION['error_message'])) {
            $error_message = $_SESSION['error_message'];
            unset($_SESSION['error_message']); // Supprime le message après l'affichage
        }
        if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($order_details): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    Informations générales
                </div>
                <div class="card-body">
                    <p><strong>Date de commande :</strong> <?= formatDate($order_details['order_date'], 'd/m/Y H:i') ?></p>
                    <p><strong>Montant total :</strong> <?= number_format($order_details['total_amount'], 2, ',', ' ') ?> €</p>
                    <p>
                        <strong>Statut :</strong>
                        <span class="badge bg-<?=
                            $order_details['status'] === 'pending' ? 'warning text-dark' :
                            ($order_details['status'] === 'processing' ? 'info text-white' :
                            ($order_details['status'] === 'shipped' ? 'primary' :
                            ($order_details['status'] === 'delivered' ? 'success' :
                            ($order_details['status'] === 'cancelled' ? 'danger' : 'secondary'))))
                        ?>">
                            <?= htmlspecialchars(ucfirst($order_details['status'])) ?>
                        </span>
                    </p>
                    <p><strong>Méthode de paiement :</strong> <?= htmlspecialchars(ucfirst($order_details['payment_method'])) ?></p>
                    <p>
                        <strong>Statut du paiement :</strong>
                        <span class="badge bg-<?=
                            $order_details['payment_status'] === 'pending' ? 'warning text-dark' :
                            ($order_details['payment_status'] === 'paid' ? 'success' :
                            ($order_details['payment_status'] === 'refunded' ? 'secondary' : 'danger'))
                        ?>">
                            <?= htmlspecialchars(ucfirst($order_details['payment_status'])) ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    Adresse de livraison
                </div>
                <div class="card-body">
                    <p><?= htmlspecialchars($order_details['customer_first_name'] . ' ' . $order_details['customer_last_name']) ?></p>
                    <p><?= htmlspecialchars($order_details['customer_address']) ?></p>
                    <p>Tél : <?= htmlspecialchars($order_details['customer_phone']) ?></p>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    Produits commandés
                </div>
                <div class="card-body">
                    <?php if (empty($products_in_order)): ?>
                        <div class="alert alert-info">Aucun produit trouvé pour cette commande.</div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($products_in_order as $product): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h5 class="mb-1">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                            <?php
                                            // Afficher le nom de la variante si elle existe
                                            $variant_display = [];
                                            // Priorité à variant_name s'il est utilisé (e.g., "Pull Large")
                                            if (!empty($product['variant_name'])) {
                                                $variant_display[] = htmlspecialchars($product['variant_name']);
                                            } else {
                                                // Sinon, utiliser color_name et size_name s'ils existent
                                                if (!empty($product['variant_color'])) {
                                                    $variant_display[] = htmlspecialchars($product['variant_color']);
                                                }
                                                if (!empty($product['variant_size'])) {
                                                    $variant_display[] = htmlspecialchars($product['variant_size']);
                                                }
                                            }
                                            if (!empty($variant_display)): ?>
                                                <small class="text-muted">(<?= implode(', ', $variant_display) ?>)</small>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($product['product_description']) ?></p>
                                        <small class="text-muted">Prix unitaire : <?= number_format($product['unit_price'], 2, ',', ' ') ?> €</small>
                                    </div>
                                    <div class="d-flex align-items-center mt-2 mt-md-0">
                                        <span class="badge bg-secondary rounded-pill me-2">Qté : <?= htmlspecialchars($product['quantity']) ?></span>
                                        <span class="badge bg-info rounded-pill">Total : <?= number_format($product['quantity'] * $product['unit_price'], 2, ',', ' ') ?> €</span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <a href="mes_commandes.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour à Mes commandes</a>

        <?php else: // Si $order_details est null, c'est que la commande n'a pas été trouvée ou une erreur est survenue ?>
            <div class="alert alert-warning" role="alert">
                Impossible de charger les détails de cette commande. Veuillez vérifier l'ID ou vos autorisations.
            </div>
            <a href="mes_commandes.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour à Mes commandes</a>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>