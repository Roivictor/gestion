<?php
// mes_commandes.php (ou orders.php si c'est la page du client)

// Assurez-vous que config.php est à la racine du projet, au même niveau que ce fichier
require_once 'config.php'; 

// Vérifier si l'utilisateur est connecté. La fonction isLoggedIn() est dans config.php.
if (!isLoggedIn()) {
    redirect('login.php'); // Redirige vers la page de connexion si l'utilisateur n'est pas connecté
}

// Vérifier que l'ID de l'utilisateur est bien défini en session
if (!isset($_SESSION['user']['id'])) {
    // Ceci ne devrait pas arriver si isLoggedIn() est TRUE, mais c'est une bonne précaution.
    // Rediriger ou afficher une erreur si l'ID utilisateur n'est pas en session.
    error_log("User ID not found in session for logged in user.");
    redirect('login.php'); // Rediriger l'utilisateur vers la page de connexion
}

// L'ID de l'utilisateur connecté depuis la session.
// D'après nos discussions précédentes, c'est généralement $_SESSION['user']['id'].
$user_id = $_SESSION['user']['id']; 

// Récupérer les commandes du client
try {
    // SCÉNARIO LE PLUS COURANT: orders.customer_id fait directement référence à users.id
    $stmt = $pdo->prepare("SELECT o.id, o.order_date, o.total_amount, o.status 
                           FROM orders o 
                           WHERE o.customer_id = ? 
                           ORDER BY o.order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();

    /*
    // SCÉNARIO ALTERNATIF: Si vous avez bien une table 'customers' séparée où 'customers.user_id'
    // référence 'users.id' ET 'orders.customer_id' référence 'customers.id'.
    // Si c'est votre cas, décommentez le code ci-dessous et commentez le précédent.
    $stmt = $pdo->prepare("SELECT o.id, o.order_date, o.total_amount, o.status 
                           FROM orders o 
                           JOIN customers c ON o.customer_id = c.id
                           WHERE c.user_id = ? 
                           ORDER BY o.order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    */

} catch (PDOException $e) {
    // Gérer l'erreur de base de données
    error_log("Erreur PDO lors de la récupération des commandes: " . $e->getMessage());
    $orders = []; // Assurez-vous que $orders est un tableau vide en cas d'erreur
    echo "<div class='alert alert-danger'>Une erreur est survenue lors du chargement de vos commandes. Veuillez réessayer plus tard.</div>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes commandes - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php 
    // Assurez-vous que le chemin est correct. Si ce fichier est dans 'u/', alors 'includes/header.php' est bon.
    // Si ce fichier est dans 'u/customer/', il faudrait '../includes/header.php'.
    include 'includes/header.php'; 
    ?>
    
    <div class="container my-5">
        <h2 class="mb-4">Mes commandes</h2>
        
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                Vous n'avez pas encore passé de commande.
                <a href="products.php" class="alert-link">Parcourir nos produits</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['id']) ?></td>
                            <td><?= formatDate($order['order_date']) ?></td> <td><?= number_format($order['total_amount'], 2, ',', ' ') ?> €</td>
                            <td>
                                <span class="badge bg-<?= 
                                    $order['status'] === 'pending' ? 'warning' :
                                    ($order['status'] === 'processing' ? 'primary' : // Ajouté 'processing'
                                    ($order['status'] === 'shipped' ? 'info' :
                                    ($order['status'] === 'delivered' ? 'success' : 'secondary')))
                                ?>">
                                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="order_details.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    Détails
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>