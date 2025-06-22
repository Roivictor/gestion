<?php
require_once '../config.php'; // 1. Inclut le fichier de configuration (connexion DB, fonctions utilitaires)
// Assurez-vous que le chemin est correct depuis le dossier 'admin' vers 'config.php'

// Appel de la fonction de vérification checkAdminRole() qui est maintenant définie dans config.php
checkAdminRole();

// 3. Récupération des statistiques (nombre de...)
try {
    $stmt_orders = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt_orders->fetchColumn(); // Récupère le nombre total de commandes

    $stmt_clients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'"); // Compte seulement les utilisateurs avec le rôle 'client'
    $total_clients = $stmt_clients->fetchColumn();

    $stmt_products = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt_products->fetchColumn();

    $stmt_employees = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('employee', 'admin')"); // Compte les employés et les admins
    $total_employees = $stmt_employees->fetchColumn();

    // 4. Récupération des commandes récentes (par exemple, les 5 dernières)
    $recent_orders_stmt = $pdo->query("
        SELECT o.id, o.date_commande, o.total_amount, o.status, u.first_name, u.last_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        ORDER BY o.date_commande DESC
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    // 5. Récupération des avis en attente de modération
    $pending_reviews_stmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
    $pending_reviews_count = $pending_reviews_stmt->fetchColumn();

    // 6. Récupération du nombre de feedbacks récents (exemple simple)
    // Supposons une table 'feedback' avec des retours
    $recent_feedback_stmt = $pdo->query("SELECT COUNT(*) FROM feedback"); // Supposons une table 'feedback'
    $recent_feedback_count = $recent_feedback_stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Erreur dans dashboard.php: " . $e->getMessage());
    // Gérer l'erreur, par exemple en affichant un message générique ou en redirigeant
    $total_orders = $total_clients = $total_products = $total_employees = 0;
    $recent_orders = [];
    $pending_reviews_count = 0;
    $recent_feedback_count = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet"> <!-- Chemin vers votre CSS principal -->
    <style>
        /* Styles spécifiques au tableau de bord admin */
        body { background-color: #f3f4f6; } /* Fond légèrement gris */
        .sidebar {
            width: 250px;
            background-color: #343a40; /* Couleur sombre pour la barre latérale */
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 10px 15px;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: #495057;
            border-left: 3px solid #0d6efd; /* Bordure active */
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .content {
            margin-left: 250px; /* Décale le contenu principal pour la barre latérale */
            padding: 20px;
        }
        .stat-card {
            background: linear-gradient(45deg, #0d6efd, #0b5ed7); /* Dégradé pour Commandes */
            color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card.clients { background: linear-gradient(45deg, #198754, #157347); } /* Vert pour Clients */
        .stat-card.products { background: linear-gradient(45deg, #0dcaf0, #0aa3c2); } /* Bleu ciel pour Produits */
        .stat-card.employees { background: linear-gradient(45deg, #ffc107, #d39e00); } /* Jaune pour Employés */
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Barre latérale de navigation -->
        <div class="sidebar d-flex flex-column">
            <h4 class="text-white text-center mb-4">Store CRM</h4>
            <div class="px-3 mb-4">
                <p class="text-white-50 mb-1">Connecté en tant que :</p>
                <p class="fw-bold text-white mb-1"><?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?></p>
                <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($_SESSION['user']['role'])) ?></span>
                <a href="../logout.php" class="btn btn-danger btn-sm mt-3 w-100">Déconnexion</a>
            </div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <i class="bi bi-receipt"></i> Commandes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="clients.php" class="nav-link">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <i class="bi bi-box-seam"></i> Produits
                    </a>
                </li>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link">
                        <i class="bi bi-tags"></i> Catégories
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reviews.php" class="nav-link">
                        <i class="bi bi-star"></i> Avis
                    </a>
                </li>
                <li class="nav-item">
                    <a href="feedback.php" class="nav-link">
                        <i class="bi bi-chat-dots"></i> Feedback service
                    </a>
                </li>
                <li class="nav-item">
                    <a href="employees.php" class="nav-link">
                        <i class="bi bi-person-badge"></i> Employés
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="bi bi-gear"></i> Paramètres
                    </a>
                </li>
            </ul>
        </div>

        <!-- Contenu principal du tableau de bord -->
        <div class="content flex-grow-1">
            <h1 class="mb-4">Tableau de bord</h1>

            <!-- Cartes de statistiques -->
            <div class="row g-4 mb-5">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fs-6 opacity-75">Commandes</div>
                                <div class="display-4 fw-bold"><?= $total_orders ?></div>
                            </div>
                            <i class="bi bi-cart fs-1 opacity-50"></i>
                        </div>
                        <hr class="opacity-50">
                        <small class="opacity-75">Nombre total de commandes</small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card clients">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fs-6 opacity-75">Clients</div>
                                <div class="display-4 fw-bold"><?= $total_clients ?></div>
                            </div>
                            <i class="bi bi-people fs-1 opacity-50"></i>
                        </div>
                        <hr class="opacity-50">
                        <small class="opacity-75">Nombre total de clients</small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card products">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fs-6 opacity-75">Produits</div>
                                <div class="display-4 fw-bold"><?= $total_products ?></div>
                            </div>
                            <i class="bi bi-box-seam fs-1 opacity-50"></i>
                        </div>
                        <hr class="opacity-50">
                        <small class="opacity-75">Nombre total de produits</small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card employees">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fs-6 opacity-75">Employés</div>
                                <div class="display-4 fw-bold"><?= $total_employees ?></div>
                            </div>
                            <i class="bi bi-person-badge fs-1 opacity-50"></i>
                        </div>
                        <hr class="opacity-50">
                        <small class="opacity-75">Nombre total d'employés</small>
                    </div>
                </div>
            </div>

            <!-- Sections des activités récentes -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Commandes récentes</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">Voir toutes</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_orders)): ?>
                                <p class="text-muted text-center">Aucune commande récente.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Client</th>
                                                <th>Montant</th>
                                                <th>Statut</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>#<?= $order['id'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($order['date_commande'])) ?></td>
                                                <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                                <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                                <td><span class="badge bg-<?= ($order['status'] == 'delivered' ? 'success' : ($order['status'] == 'processing' ? 'info' : 'warning')) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span></td>
                                                <td>
                                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Avis en attente</h5>
                            <a href="reviews.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                        </div>
                        <div class="card-body">
                            <?php if ($pending_reviews_count > 0): ?>
                                <div class="alert alert-warning text-center mb-0">
                                    Vous avez <strong><?= $pending_reviews_count ?></strong> avis en attente de modération.
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">Aucun avis en attente.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Feedback service</h5>
                            <a href="feedback.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_feedback_count > 0): ?>
                                <div class="alert alert-info text-center mb-0">
                                    Vous avez <strong><?= $recent_feedback_count ?></strong> nouveaux feedbacks.
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">Aucun feedback récent.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>
