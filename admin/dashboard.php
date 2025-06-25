<?php
// TOUT LE CODE PHP QUI PEUT MODIFIER LES EN-TÊTES DOIT ÊTRE ICI, AVANT TOUT ESPACE OU HTML.
require_once '../config.php'; // Inclut le fichier de configuration (connexion DB, fonctions utilitaires)

// Assurez-vous que la fonction de traduction est disponible ici si elle est définie dans config.php
// (Comme suggéré précédemment, la fonction __() et le chargement de $lang devraient être dans config.php)
if (!function_exists('__')) {
    // Fallback si __() n'est pas encore défini (mais il devrait l'être via config.php)
    function __($key) {
        global $lang;
        return isset($lang[$key]) ? $lang[$key] : $key;
    }
}

// Appel de la fonction de vérification checkAdminRole()
checkAdminRole();

// --- Logique pour gérer le changement de langue (Déplacée ici depuis settings.php) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (isset($_POST['language'])) {
        $selected_language = sanitize($_POST['language']);
        $_SESSION['user_language'] = $selected_language; // Stocke dans la session

        // Optionnel: Mettre à jour la langue préférée dans la base de données pour l'utilisateur
        if (isset($_SESSION['user']['id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET language_preference = :lang WHERE id = :user_id");
                $stmt->execute([':lang' => $selected_language, ':user_id' => $_SESSION['user']['id']]);
                $_SESSION['success_message'] = __('language_updated_success'); // Utilisez la traduction
            } catch (PDOException $e) {
                error_log("Erreur lors de la mise à jour de la langue: " . $e->getMessage());
                $_SESSION['error_message'] = __('language_update_error'); // Utilisez la traduction
            }
        }
        // Rediriger pour éviter la soumission multiple du formulaire
        header("Location: dashboard.php?page=settings");
        exit(); // TRÈS IMPORTANT : Terminer le script après une redirection
    }
}

// --- Logique pour inclure le contenu de la page demandée (existante) ---
// Par défaut, nous affichons le contenu de l'aperçu du tableau de bord
$requestedPage = isset($_GET['page']) ? sanitize($_GET['page']) : 'dashboard_overview';
$contentFilePath = __DIR__ . '/' . $requestedPage . '.php'; // Le chemin vers le fichier de contenu

// Vérifier si le fichier de contenu existe. Si non, revenir à l'aperçu par défaut.
if (!file_exists($contentFilePath)) {
    $contentFilePath = __DIR__ . '/dashboard_overview.php';
    $requestedPage = 'dashboard_overview'; // Mettre à jour la page demandée au cas où elle n'existe pas
}

// Le code PHP pour les statistiques doit rester ici car il est utilisé par 'dashboard_overview.php'
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
        SELECT o.id, o.date_commande, o.total_amount, o.status, u.first_name, u.last_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        ORDER BY o.date_commande DESC
        LIMIT 5
    ");
    $recent_orders = $recent_orders_stmt->fetchAll();

    $pending_reviews_stmt = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
    $pending_reviews_count = $pending_reviews_stmt->fetchColumn();

    $recent_feedback_stmt = $pdo->query("SELECT COUNT(*) FROM feedback");
    $recent_feedback_count = $recent_feedback_stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Erreur dans dashboard.php: " . $e->getMessage());
    // Gérer l'erreur, par exemple en affichant un message générique ou en redirigeant
}

// Définir le titre de la page en fonction du contenu chargé
$pageTitle = __('dashboard_title'); // Utilisez la traduction pour le titre par défaut
switch ($requestedPage) {
    case 'employees': $pageTitle = __('employee_management_title'); break; // Assurez-vous que ces clés existent
    case 'add_employee': $pageTitle = __('add_employee_title'); break;
    case 'settings': $pageTitle = __('settings_page_title'); break; // Ajoutez ceci pour la page paramètres
    // Ajoutez d'autres cas pour les autres pages si vous voulez des titres spécifiques
    // case 'orders': $pageTitle = __('order_management_title'); break;
    // ...
    default: $pageTitle = __('overview_dashboard_title'); break;
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_language_code ?? 'fr') ?>"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= SITE_NAME ?></title>
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
        <div class="sidebar d-flex flex-column">
            <h4 class="text-white text-center mb-4"><?= __('store_crm_title') ?></h4>
            <div class="px-3 mb-4">
                <p class="text-white-50 mb-1"><?= __('connected_as') ?> :</p>
                <p class="fw-bold text-white mb-1"><?= htmlspecialchars($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']) ?></p>
                <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($_SESSION['user']['role'])) ?></span>
                <a href="../logout.php" class="btn btn-danger btn-sm mt-3 w-100"><?= __('logout') ?></a>
            </div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="dashboard.php?page=dashboard_overview" class="nav-link <?= ($requestedPage === 'dashboard_overview' ? 'active' : '') ?>">
                        <i class="bi bi-speedometer2"></i> <?= __('dashboard_title') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=orders" class="nav-link <?= ($requestedPage === 'orders' ? 'active' : '') ?>">
                        <i class="bi bi-receipt"></i> <?= __('orders') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=clients" class="nav-link <?= ($requestedPage === 'clients' ? 'active' : '') ?>">
                        <i class="bi bi-people"></i> <?= __('clients') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=products" class="nav-link <?= ($requestedPage === 'products' ? 'active' : '') ?>">
                        <i class="bi bi-box-seam"></i> <?= __('products') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=categories" class="nav-link <?= ($requestedPage === 'categories' ? 'active' : '') ?>">
                        <i class="bi bi-tags"></i> <?= __('categories') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=reviews" class="nav-link <?= ($requestedPage === 'reviews' ? 'active' : '') ?>">
                        <i class="bi bi-star"></i> <?= __('reviews') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=feedback" class="nav-link <?= ($requestedPage === 'feedback' ? 'active' : '') ?>">
                        <i class="bi bi-chat-dots"></i> <?= __('feedback_service') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=employees" class="nav-link <?= ($requestedPage === 'employees' || $requestedPage === 'add_employee' || $requestedPage === 'edit_employee' ? 'active' : '') ?>">
                        <i class="bi bi-person-badge"></i> <?= __('employees') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?page=settings" class="nav-link <?= ($requestedPage === 'settings' ? 'active' : '') ?>">
                        <i class="bi bi-gear"></i> <?= __('settings') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-graph-up"></i> <?= __('reports') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-calendar-check"></i> <?= __('appointments') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-bell"></i> <?= __('notifications') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-info-circle"></i> <?= __('help') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-archive"></i> <?= __('archives') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="bi bi-card-checklist"></i> <?= __('tasks') ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="content flex-grow-1">
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