<?php
// Désactiver l'affichage des erreurs en production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../config.php'; // Assurez-vous que config.php gère session_start() et la connexion PDO

// Vérification de session
if (!isset($_SESSION['user']['id']) || !in_array(strtolower($_SESSION['user']['role']), ['employee'])) {
    $_SESSION['error_message'] = "Accès non autorisé.";
    header('Location: ../login.php');
    exit();
}

// Récupérer l'ID de l'employé depuis la session
$employee_user_id = $_SESSION['user']['id'];

// Récupérer les informations complètes de l'employé pour la sidebar et les requêtes
try {
    $stmt_employee = $pdo->prepare("
        SELECT e.id AS employee_db_id, u.first_name, u.last_name, e.position
        FROM employees e
        JOIN users u ON e.user_id = u.id
        WHERE e.user_id = ?
    ");
    $stmt_employee->execute([$employee_user_id]);
    $employee_info = $stmt_employee->fetch(PDO::FETCH_ASSOC);

    if (!$employee_info) {
        $_SESSION['error_message'] = "Vos informations d'employé sont introuvables. Veuillez contacter l'administrateur.";
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des infos employé pour les commandes: " . $e->getMessage());
    $_SESSION['error_message'] = "Erreur système. Veuillez réessayer plus tard.";
    header('Location: ../login.php');
    exit();
}

$employee_db_id = $employee_info['employee_db_id']; // L'ID de la table 'employees'

// Récupérer toutes les commandes assignées à cet employé
$orders = [];
try {
    $stmt_orders = $pdo->prepare("
        SELECT o.id, o.order_date, o.total_amount, o.status, o.payment_status,
               CONCAT(u.first_name, ' ', u.last_name) AS customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE o.employee_id = ?
        ORDER BY o.order_date DESC
    ");
    $stmt_orders->execute([$employee_db_id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des commandes: " . $e->getMessage());
    $error_message = "Erreur lors du chargement des commandes. Veuillez réessayer plus tard.";
}

// Pour le message de succès/erreur
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $error_message ?? ($_SESSION['error_message'] ?? '');
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - Employé - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/employee.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-person fs-3 text-dark"></i>
                        </div>
                        <h6 class="mt-2 text-white"><?= htmlspecialchars($employee_info['first_name'] . ' ' . $employee_info['last_name']) ?></h6>
                        <p class="text-muted small"><?= htmlspecialchars($employee_info['position'] ?? 'N/A') ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="bi bi-box-seam me-2"></i>
                                Produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="orders.php">
                                <i class="bi bi-cart me-2"></i>
                                Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="bi bi-people me-2"></i>
                                Clients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person me-2"></i>
                                Profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="change_password.php">
                                <i class="bi bi-lock me-2"></i>
                                Changer mot de passe
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-light">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Mes Commandes</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create_order.php" class="btn btn-sm btn-success">
                            <i class="bi bi-plus-circle me-2"></i>Créer une nouvelle commande
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5>Toutes les commandes qui vous sont assignées</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Date Commande</th>
                                        <th>Montant Total</th>
                                        <th>Statut Paiement</th>
                                        <th>Statut Commande</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orders)): ?>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['id']) ?></td>
                                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['order_date']))) ?></td>
                                                <td><?= htmlspecialchars(number_format($order['total_amount'], 2)) ?> €</td>
                                                <td>
                                                    <span class="badge <?= $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning' ?>">
                                                        <?= htmlspecialchars(ucfirst($order['payment_status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge
                                                        <?= $order['status'] === 'pending' ? 'bg-warning' :
                                                            ($order['status'] === 'processing' ? 'bg-info' :
                                                            ($order['status'] === 'shipped' ? 'bg-primary' :
                                                            ($order['status'] === 'delivered' ? 'bg-success' : 'bg-danger'))) ?>">
                                                        <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="order_details.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): // Exemple: permettre de changer le statut ?>
                                                        <a href="process_order.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-outline-success" title="Traiter/Mettre à jour">
                                                            <i class="bi bi-arrow-right-circle"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucune commande trouvée pour vous.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/employee.js"></script>
</body>
</html>