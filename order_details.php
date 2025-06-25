<?php
// order_details.php - Affiche les détails d'une commande spécifique pour un client

// Inclure le fichier de configuration (connexion DB, fonctions utilitaires)
require_once 'config.php'; 

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('login.php'); // Redirige vers la page de connexion
}

// Vérifier que l'ID de la commande est passé dans l'URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Si l'ID est manquant ou invalide, rediriger vers la liste des commandes ou afficher une erreur
    $_SESSION['error_message'] = "ID de commande invalide.";
    redirect('mes_commandes.php'); // Ou la page qui liste les commandes
    exit();
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user']['id']; // L'ID de l'utilisateur connecté

$order_details = null;
$products_in_order = [];
$error_message = '';

try {
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

    // 2. Récupérer les produits inclus dans cette commande
    $stmt_products = $pdo->prepare("
        SELECT
            od.quantity,
            od.unit_price,
            p.name AS product_name,
            p.description AS product_description,
            p.image_url AS product_image
        FROM
            order_details od
        JOIN
            products p ON od.product_id = p.id
        WHERE
            od.order_id = :order_id
    ");
    $stmt_products->execute([':order_id' => $order_id]);
    $products_in_order = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des détails de commande: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des détails de la commande. Veuillez réessayer plus tard.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails de la commande #<?= htmlspecialchars($order_id) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Détails de la commande #<?= htmlspecialchars($order_id) ?></h2>
        
        <?php if ($error_message): ?>
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
                            ($order_details['status'] === 'delivered' ? 'success' : 'secondary')))
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
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($product['product_name']) ?></h5>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($product['product_description']) ?></p>
                                        <small class="text-muted">Prix unitaire : <?= number_format($product['unit_price'], 2, ',', ' ') ?> €</small>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill">Quantité : <?= htmlspecialchars($product['quantity']) ?></span>
                                    <span class="badge bg-info rounded-pill">Total : <?= number_format($product['quantity'] * $product['unit_price'], 2, ',', ' ') ?> €</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <a href="mes_commandes.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour à Mes commandes</a>

        <?php else: ?>
            <div class="alert alert-warning" role="alert">
                Impossible de charger les détails de cette commande.
            </div>
            <a href="mes_commandes.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Retour à Mes commandes</a>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>