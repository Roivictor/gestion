<?php
// TOUT LE CODE PHP QUI PEUT MODIFIER LES EN-TÊTES DOIT ÊTRE ICI, AVANT TOUT ESPACE OU HTML.

// IMPORTANT : Démarrer la session au tout début.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php'; // Inclut le fichier de configuration (connexion DB, fonctions utilitaires)

// Appel de la fonction de vérification checkAdminRole()
// Déplacé ici pour une vérification précoce de l'accès.
checkAdminRole();

// Le code PHP pour les statistiques
$total_orders = 0;
$total_clients = 0;
$total_products = 0;
$total_employees = 0;
$recent_orders = [];
$pending_reviews_count = 0;
$recent_feedback_count = 0;

try {
    $stmt_orders = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $stmt_orders->fetchColumn();

    $stmt_clients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'");
    $total_clients = $stmt_clients->fetchColumn();

    $stmt_products = $pdo->query("SELECT COUNT(*) FROM products");
    $total_products = $stmt_products->fetchColumn();

    $stmt_employees = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('employee', 'admin')");
    $total_employees = $stmt_employees->fetchColumn();

    $recent_orders_stmt = $pdo->query("
        SELECT o.id, o.order_date, o.total_amount, o.status, u.first_name, u.last_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    $pending_reviews_stmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
    $pending_reviews_count = $pending_reviews_stmt->fetchColumn();

    // CORRECTION APPLIQUÉE ICI : Utilisation de 'service_feedbacks' d'après la capture d'écran.
    $recent_feedback_stmt = $pdo->query("SELECT COUNT(*) FROM service_feedbacks");
    $recent_feedback_count = $recent_feedback_stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Erreur dans dashboard.php lors du chargement des statistiques: " . $e->getMessage());
    // Utilisation de la fonction setFlashMessage() pour afficher l'erreur à l'utilisateur
    setFlashMessage('danger', "Une erreur est survenue lors du chargement des statistiques du tableau de bord.");
}


// --- Logique pour inclure le contenu de la page demandée (existante) ---
// Par défaut, nous affichons le contenu de l'aperçu du tableau de bord
$requestedPage = isset($_GET['page']) ? sanitize($_GET['page']) : 'dashboard_overview';

// Liste blanche des pages autorisées pour les admins (RÉINTRODUIT POUR SÉCURITÉ)
$allowedAdminPages = [
    'dashboard_overview',
    'employees',
    'add_employee',
    'edit_employee', // Ajouté à la liste blanche si c'est une page éditable
    'settings',
    'orders',
    'clients',
    'products',
    'categories',
    'reviews',
    'feedback',
    'send_notification', // Ajouté à la liste blanche si cette page existe
    'profile',           // Ajouté à la liste blanche si cette page existe
    'change_password_admin' // Ajouté à la liste blanche si cette page existe
];

// Vérifier si la page demandée est dans la liste blanche
if (!in_array($requestedPage, $allowedAdminPages)) {
    $requestedPage = 'dashboard_overview'; // Revenir à l'aperçu si la page n'est pas autorisée
    setFlashMessage('warning', "La page demandée n'existe pas ou n'est pas autorisée.");
}

$contentFilePath = __DIR__ . '/' . $requestedPage . '.php'; // Le chemin vers le fichier de contenu

// Si le fichier de contenu n'existe pas (même après la vérification in_array si le fichier est manquant)
if (!file_exists($contentFilePath)) {
    $contentFilePath = __DIR__ . '/dashboard_overview.php'; // Repli sur l'aperçu
    $requestedPage = 'dashboard_overview'; // Met à jour $requestedPage pour refléter le repli
    setFlashMessage('danger', "Le fichier de contenu pour la page demandée est introuvable. Affichage de l'aperçu.");
}


// Définir le titre de la page en fonction du contenu chargé
$pageTitle = 'Tableau de bord';
switch ($requestedPage) {
    case 'employees': $pageTitle = 'Gestion des employés'; break;
    case 'add_employee': $pageTitle = 'Ajouter un employé'; break;
    case 'edit_employee': $pageTitle = 'Modifier un employé'; break; // Titre ajouté
    case 'settings': $pageTitle = 'Paramètres'; break;
    case 'orders': $pageTitle = 'Gestion des commandes'; break;
    case 'clients': $pageTitle = 'Gestion des clients'; break;
    case 'products': $pageTitle = 'Gestion des produits'; break;
    case 'categories': $pageTitle = 'Gestion des catégories'; break;
    case 'reviews': $pageTitle = 'Gestion des avis'; break;
    case 'feedback': $pageTitle = 'Gestion des retours'; break;
    case 'send_notification': $pageTitle = 'Envoyer une notification'; break; // Titre ajouté
    case 'profile': $pageTitle = 'Mon Profil Administrateur'; break; // Titre ajouté
    case 'change_password_admin': $pageTitle = 'Changer le mot de passe'; break; // Titre ajouté
    default: $pageTitle = 'Aperçu du Tableau de bord'; break;
}

// *** GÉNÉRATION DU TOKEN CSRF ICI, AVANT LE DÉBUT DU HTML ***
$csrf_token = generateCsrfToken(); // Cette fonction doit être définie dans config.php

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= SITE_NAME ?></title>

    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        /* Styles spécifiques au tableau de bord admin */
        body { background-color: #f3f4f6; } /* Fond légèrement gris */
        .sidebar {
            width: 250px;
            background-color: #343a40; /* Couleur sombre pour la barre latérale */
            color: white;
            height: 100vh; /* Utiliser 'height' au lieu de 'min-height' */
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 20px;
            overflow-y: auto; /* Permet le défilement vertical si le contenu dépasse */
            display: flex; /* Utilisé avec flex-column, s'assure que le contenu s'étend correctement */
            flex-direction: column; /* S'assure que les éléments s'empilent verticalement */
        }

        /* Personnalisation de la barre de défilement pour toute la sidebar (optionnel) */
        .sidebar::-webkit-scrollbar {
            width: 8px; /* Largeur de la barre */
        }

        .sidebar::-webkit-scrollbar-track {
            background: #343a40; /* Fond de la piste */
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #888; /* Couleur de la poignée */
            border-radius: 4px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #555; /* Couleur de la poignée au survol */
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
            flex-grow: 1; /* Permet au contenu de prendre la largeur restante */
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
    <div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100"></div>

    <div class="d-flex">
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
                    <a href="dashboard.php?page=dashboard_overview" class="nav-link <?= ($requestedPage === 'dashboard_overview' ? 'active' : '') ?>">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=orders" class="nav-link <?= ($requestedPage === 'orders' ? 'active' : '') ?>">
                        <i class="bi bi-receipt"></i> Commandes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=clients" class="nav-link <?= ($requestedPage === 'clients' ? 'active' : '') ?>">
                        <i class="bi bi-people"></i> Clients
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=products" class="nav-link <?= ($requestedPage === 'products' ? 'active' : '') ?>">
                        <i class="bi bi-box-seam"></i> Produits
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=categories" class="nav-link <?= ($requestedPage === 'categories' ? 'active' : '') ?>">
                        <i class="bi bi-tags"></i> Catégories
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=reviews" class="nav-link <?= ($requestedPage === 'reviews' ? 'active' : '') ?>">
                        <i class="bi bi-star"></i> Avis
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=feedback" class="nav-link <?= ($requestedPage === 'feedback' ? 'active' : '') ?>">
                        <i class="bi bi-chat-dots"></i> Retours clients
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=employees" class="nav-link <?= ($requestedPage === 'employees' || $requestedPage === 'add_employee' || $requestedPage === 'edit_employee' ? 'active' : '') ?>">
                        <i class="bi bi-person-badge"></i> Employés
                    </a>
                </li>
                   <li class="nav-item">
                    <a href="dashboard.php?page=send_notification" class="nav-link <?= ($requestedPage === 'send_notification' ? 'active' : '') ?>">
                        <i class="bi bi-send"></i> Envoyer notification
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=settings" class="nav-link <?= ($requestedPage === 'settings' ? 'active' : '') ?>">
                        <i class="bi bi-gear"></i> Paramètres
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-graph-up"></i> Rapports
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-calendar-check"></i> Rendez-vous
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-bell"></i> Notifications (Admin)
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-info-circle"></i> Aide
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-archive"></i> Archives
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-card-checklist"></i> Tâches
                    </a>
                </li>
            </ul>
        </div>

        <div class="content flex-grow-1">
            <?php
            // Afficher les messages flash (success/error/warning/info) via getFlashMessage()
            $flash_message = getFlashMessage();
            if ($flash_message) {
                echo '<div class="alert alert-' . htmlspecialchars($flash_message['type']) . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($flash_message['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            }
            ?>
            <?php
            // Inclure le fichier de contenu dynamique
            include $contentFilePath;
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>