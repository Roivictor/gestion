<?php
// employee/dashboard.php - Version améliorée avec gestion des notifications

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php'; // Assurez-vous que ce fichier contient checkEmployeeRole(), getFlashMessage(), setFlashMessage(), redirect(), generateCsrfToken(), time_elapsed_string(), getUnreadNotifications(), countUnreadNotifications()

checkEmployeeRole(); // Vérifie si l'utilisateur est connecté et a le rôle d'employé

try {
    // Récupérer les informations de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, role, is_first_login, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        session_destroy();
        redirect(BASE_URL . 'login.php');
        exit();
    }
    $_SESSION['user'] = $currentUser; // Mettre à jour la session avec les dernières infos
} catch (PDOException $e) {
    error_log("Erreur de base de données lors de la récupération des informations utilisateur : " . $e->getMessage());
    setFlashMessage('danger', "Erreur de connexion à la base de données lors de la récupération des informations utilisateur.");
    redirect(BASE_URL . 'login.php');
    exit();
}

// --- Gestion des notifications ---
$loggedInUserId = $_SESSION['user']['id'] ?? 0;
$unreadNotifications = [];
$unreadNotificationsCount = 0;

if ($loggedInUserId > 0) {
    try {
        // Récupérer les 10 dernières notifications non lues (ou un nombre que vous jugez pertinent)
        $stmt = $pdo->prepare("SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = :user_id AND is_read = FALSE ORDER BY created_at DESC LIMIT 10");
        $stmt->execute([':user_id' => $loggedInUserId]);
        $unreadNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Compter toutes les notifications non lues (pour le badge)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':user_id' => $loggedInUserId]);
        $unreadNotificationsCount = $stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération/comptage des notifications : " . $e->getMessage());
        // En cas d'erreur, les variables resteront vides ou à 0
    }
}
// --- Fin gestion des notifications ---


$page = $_GET['page'] ?? 'overview';

// Redirection si c'est la première connexion et non sur la page de changement de mot de passe
if ((bool)($currentUser['is_first_login'] ?? false) && $page !== 'change_password_first_login') {
    setFlashMessage('warning', __('please_change_password_first_login'));
    redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
    exit();
}

$allowedEmployeePages = [
    'overview',
    'customer_orders',
    'customers',
    'my_profile',
    'change_password',
    'change_password_first_login',
    'sales_report',
    'view_order',
    'edit_order',
    'calendar',
    'planning',
    'leave_request',
];

if (!in_array($page, $allowedEmployeePages)) {
    $page = 'overview';
}

$contentFile = __DIR__ . '/' . $page . '.php';

if (!file_exists($contentFile)) {
    $contentFile = __DIR__ . '/overview.php';
    $page = 'overview';
}

$csrf_token = generateCsrfToken();

if (isset($_SESSION['error_message']) && $_SESSION['error_message'] === "Erreur de sécurité : Token CSRF manquant au chargement. Certaines fonctions peuvent être désactivées.") {
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_language_code ?? 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? __('employee_dashboard_title')) ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Mon Application' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css' rel='stylesheet' />
    <link href="../css/style.css" rel="stylesheet">

    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">

    <style>
        /* Styles généraux */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            display: flex; /* Utilise flexbox pour la disposition sidebar + content */
        }
        .sidebar {
            width: 250px;
            background-color: #ffffff;
            color: #343a40;
            height: 100vh;
            position: fixed; /* Reste fixe lors du défilement */
            top: 0;
            left: 0;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto; /* Permet le défilement si le contenu de la sidebar est trop long */
            display: flex;
            flex-direction: column;
            z-index: 1030; /* S'assure que la sidebar est au-dessus du contenu si nécessaire */
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-left: 3px solid transparent; /* Bordure pour l'état actif/hover */
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            color: #007bff;
            background-color: #e9ecef;
            border-left-color: #007bff;
        }
        .sidebar .nav-link.active {
            color: #007bff;
            background-color: #e2f0ff;
            border-left-color: #007bff;
            font-weight: 600;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }
        .sidebar h4, .sidebar p {
            padding: 0 15px;
        }
        .sidebar .hr-management-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #343a40;
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar .hr-management-title span {
            color: #007bff; /* Couleur pour le "Management" */
        }
        .sidebar .profile-section { /* Section de profil cachée dans cet exemple, mais maintenue pour référence */
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .sidebar .profile-section img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            border: 1px solid #ddd;
        }
        .sidebar .profile-section .username {
            font-weight: 600;
            color: #343a40;
        }
        .sidebar .profile-section .role {
            font-size: 0.85rem;
            color: #6c757d;
        }

        /* Styles de l'image de profil dans la barre de navigation supérieure */
        .top-profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #007bff;
        }

        /* Contenu principal et barre de navigation supérieure */
        .content {
            margin-left: 250px; /* Décale le contenu principal pour laisser la place à la sidebar fixe */
            padding: 0; /* Pas de padding ici, le padding sera sur le <main> */
            flex-grow: 1; /* Permet au contenu de prendre toute la largeur restante */
            /* width: calc(100% - 250px); */ /* Cette ligne est redondante avec flex-grow: 1 et peut être source de problèmes parfois. */
            min-height: 100vh; /* S'assure que le contenu prend au moins toute la hauteur de la fenêtre */
            display: flex; /* IMPORTANT : le conteneur .content doit être un flexbox */
            flex-direction: column; /* Pour empiler la navbar, le main et le footer verticalement */
            overflow-x: hidden; /* Empêche le défilement horizontal global si non désiré */
        }
        .navbar-top {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky; /* Reste en haut lors du défilement du contenu */
            top: 0;
            z-index: 1000; /* Assure qu'elle est au-dessus des autres éléments */
            border-bottom: 1px solid #eee;
            width: 100%; /* S'assure que la navbar prend 100% de la largeur de son parent (.content) */
        }
        /* Ajustement du padding du container-fluid dans la navbar-top */
        .navbar-top .container-fluid {
            padding-left: 20px;
            padding-right: 20px;
            padding-top: 10px;
            padding-bottom: 10px;
            width: 100%; /* S'assure que le container-fluid prend 100% de la largeur disponible */
        }

        .navbar-top .search-bar {
            flex-grow: 1; /* Prend l'espace disponible */
            margin-left: 20px;
            position: relative;
        }
        .navbar-top .search-bar input {
            border-radius: 20px;
            padding-left: 40px; /* Espace pour l'icône de recherche */
            border-color: #e0e0e0;
            background-color: #f5f5f5;
            width: 100%; /* Assure que la barre de recherche prend toute la largeur disponible */
        }
        .navbar-top .search-bar .bi-search {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        .navbar-top .navbar-icons {
            display: flex;
            align-items: center;
            margin-left: auto; /* Pousse les icônes à droite */
        }
        .navbar-top .navbar-icons .bi {
            font-size: 1.3rem;
            color: #555;
            margin-left: 20px;
            cursor: pointer;
            position: relative; /* Pour le positionnement du badge */
        }
        .navbar-top .navbar-icons .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.6em;
            padding: .3em .6em;
            border-radius: 50%;
        }

        /* Styles pour le menu déroulant des notifications */
        .notification-dropdown {
            width: 300px; /* Largeur fixe du panneau de notifications */
            max-height: 400px; /* Hauteur maximale avec défilement */
            overflow-y: auto;
            right: 0; /* Positionne le dropdown à droite de son parent */
            left: auto; /* Annule tout positionnement 'left' */
        }
        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            /* Ajout pour le pointer, car c'est maintenant un <a> vide pour le clic JS */
            cursor: pointer; 
            transition: background-color 0.2s ease;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        .notification-item .message {
            font-size: 0.9rem;
            color: #333;
        }
        .notification-item .time {
            font-size: 0.75rem;
            color: #888;
            margin-top: 5px;
            display: block;
        }
        .notification-empty {
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }

        /* Nouveaux styles pour les notifications lues */
        .notification-item.read {
            background-color: #f0f0f0;
            color: #888;
        }
        .notification-item.read .message,
        .notification-item.read .time {
            color: #888;
        }
        .notification-item.read:hover {
            background-color: #e9e9e9; /* Un peu plus sombre au survol */
        }

        /* Masquer le badge si le texte est vide (count est 0) */
        #notificationBadge:empty {
            display: none;
        }

        /* Contenu principal du tableau de bord */
        main.dashboard-content {
            padding: 20px; /* Padding pour le contenu réel, ajustez si vous voulez moins d'espace autour */
            background-color: #f8f9fa;
            flex-grow: 1; /* Permet au main de prendre l'espace restant verticalement et horizontalement */
            width: 100%; /* S'assure que le main prend 100% de la largeur disponible de son parent (.content) */
            box-sizing: border-box; /* S'assure que le padding est inclus dans la largeur */
            overflow-x: hidden; /* Empêche le défilement horizontal du main lui-même */
        }

        /* Styles de carte spécifiques au tableau de bord (exemple, si vous avez des cartes) */
        .dashboard-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            border: none;
            height: 100%;
        }
        .dashboard-card .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #343a40;
        }
        .dashboard-card .card-header {
            background-color: transparent;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-card .card-header .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Styles du calendrier FullCalendar */
        #calendar {
            font-size: 0.9em;
        }
        .fc .fc-toolbar-title {
            font-size: 1.5em;
        }
        .fc .fc-button-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .fc .fc-button-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        /* Styles du widget des tâches (exemple) */
        .task-list .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border: none;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }
        .task-list .list-group-item:last-child {
            border-bottom: none;
        }
        .task-list .task-details {
            display: flex;
            flex-direction: column;
        }
        .task-list .task-details .task-date {
            font-size: 0.8rem;
            color: #888;
        }
        .task-list .task-status .badge {
            font-size: 0.75rem;
            padding: 0.4em 0.7em;
        }
        .task-list .task-actions .dropdown-toggle::after {
            display: none;
        }

        /* Styles du widget d'horloge (pointage, exemple) */
        .clock-widget .time-display {
            font-size: 2.5rem;
            font-weight: 700;
            color: #343a40;
            text-align: center;
            margin-bottom: 15px;
        }
        .clock-widget .date-display {
            font-size: 1rem;
            color: #6c757d;
            text-align: center;
            margin-bottom: 25px;
        }
        .clock-widget .btn-clock {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
        }
        .clock-status {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .clock-status div {
            flex: 1;
        }
        .clock-status .label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 5px;
        }
        .clock-status .value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
        }

        /* Ajustements responsifs pour les petits écrans */
        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
            }
            .content {
                margin-left: 200px;
            }
            .navbar-top .search-bar {
                margin-left: 10px;
            }
            .navbar-top .navbar-icons .bi {
                margin-left: 15px;
            }
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative; /* La sidebar n'est plus fixe sur mobile */
                box-shadow: none;
                padding-top: 0;
            }
            .content {
                margin-left: 0; /* Le contenu n'a plus de marge à gauche */
                width: 100%; /* Le contenu prend toute la largeur */
            }
            .navbar-top {
                position: relative;
                flex-wrap: wrap; /* Permet aux éléments de passer à la ligne */
            }
            .navbar-top .search-bar {
                order: 3; /* Place la barre de recherche en bas sur mobile */
                width: 100%;
                margin: 10px 0;
            }
            .navbar-top .navbar-brand {
                order: 1;
            }
            .navbar-top .navbar-icons {
                order: 2;
                margin-left: 10px;
            }
        }
        @media (max-width: 576px) {
            .dashboard-card {
                padding: 15px;
            }
            .dashboard-card .card-title {
                font-size: 1rem;
            }
            .clock-widget .time-display {
                font-size: 2rem;
            }
            .clock-widget .btn-clock {
                padding: 10px;
                font-size: 1rem;
            }
            .fc .fc-toolbar-title {
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column">
            <h4 class="hr-management-title">HR-<span>Management</span></h4>

            <div class="profile-pic-container mb-4 mx-auto" style="display: none;">
                <img id="profileImage" src="<?= BASE_URL . htmlspecialchars($currentUser['profile_picture'] ?? 'assets/img/default_profile.png') ?>" alt="Photo de profil" class="profile-pic">
                <label for="profilePictureUpload" class="profile-pic-overlay">
                    <i class="bi bi-camera-fill"></i>
                </label>
                <input type="file" id="profilePictureUpload" name="profile_picture" accept="image/*" class="d-none">
            </div>

            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=overview" class="nav-link <?= ($page === 'overview' ? 'active' : '') ?>">
                        <i class="bi bi-grid-1x2"></i> Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=calendar" class="nav-link <?= ($page === 'calendar' ? 'active' : '') ?>">
                        <i class="bi bi-calendar-event"></i> Calendrier
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= (in_array($page, ['customer_orders', 'customers', 'sales_report']) ? 'active' : '') ?>" href="#" id="companyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-building"></i> Entreprise
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="companyDropdown">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=customer_orders"><?= __('customer_orders') ?></a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=customers"><?= __('customers') ?></a></li>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=sales_report"><?= __('sales_report') ?></a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=planning" class="nav-link <?= ($page === 'planning' ? 'active' : '') ?>">
                        <i class="bi bi-list-task"></i> Planning
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=leave_request" class="nav-link <?= ($page === 'leave_request' ? 'active' : '') ?>">
                        <i class="bi bi-file-earmark-person"></i> Demande de congé
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=my_profile" class="nav-link <?= ($page === 'my_profile' ? 'active' : '') ?>">
                        <i class="bi bi-person"></i> <?= __('my_profile') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=change_password" class="nav-link <?= ($page === 'change_password' ? 'active' : '') ?>">
                        <i class="bi bi-key"></i> <?= __('change_password') ?>
                    </a>
                </li>
            </ul>
            <div class="mt-auto p-3 border-top">
                <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-box-arrow-right"></i> <?= __('logout') ?></a>
            </div>
        </div>

        <div class="content d-flex flex-column">
            <nav class="navbar navbar-expand-lg navbar-light bg-light navbar-top w-100">
                <div class="container-fluid px-0">
                    <a class="navbar-brand d-none d-md-block" href="<?= BASE_URL ?>employee/dashboard.php">HR-Management</a>
                    
                    <div class="search-bar flex-grow-1 mx-3">
                        <input type="text" class="form-control w-100" placeholder="Rechercher quelque chose...">
                        <i class="bi bi-search"></i>
                    </div>
                    
                    <div class="navbar-icons flex-shrink-0">
                        <div class="dropdown">
                            <i class="bi bi-bell position-relative" id="notificationBell" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="badge bg-danger position-absolute top-0 start-100 translate-middle rounded-pill" id="notificationBadge">
                                    <?= $unreadNotificationsCount > 0 ? htmlspecialchars($unreadNotificationsCount) : '' ?>
                                </span>
                            </i>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBell">
                                <?php if (!empty($unreadNotifications)): ?>
                                    <?php foreach ($unreadNotifications as $notification): ?>
                                        <li>
                                            <a class="dropdown-item notification-item <?= $notification['is_read'] ? 'read' : '' ?>" href="<?= htmlspecialchars($notification['link'] ?? '#') ?>" data-notification-id="<?= htmlspecialchars($notification['id']) ?>">
                                                <span class="message"><?= htmlspecialchars($notification['message']) ?></span>
                                                <span class="time text-muted"><?= time_elapsed_string($notification['created_at']) ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center text-primary" href="#" id="markAllRead"><?= __('mark_all_as_read') ?></a></li>
                                <?php else: ?>
                                    <li><span class="dropdown-item notification-empty"><?= __('no_new_notifications') ?></span></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <span class="ms-3 me-2"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></span>
                        <img src="<?= BASE_URL . htmlspecialchars($currentUser['profile_picture'] ?? 'assets/img/default_profile.png') ?>" alt="Photo de profil" class="top-profile-pic">
                    </div>
                </div>
            </nav>

            <div id="toast-container" aria-live="polite" aria-atomic="true" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1050;">
            </div>

            <main class="flex-grow-1 dashboard-content">
                <?php
                $flash_message = getFlashMessage();
                if ($flash_message) {
                    echo '<div class="alert alert-' . htmlspecialchars($flash_message['type']) . ' alert-dismissible fade show mt-3" role="alert">' . htmlspecialchars($flash_message['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                }

                include $contentFile;
                ?>
            </main>

            <footer class="footer mt-auto py-3 bg-light border-top">
                <div class="container text-center">
                    <span class="text-muted">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tous droits réservés.</span>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script> <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/fr.global.min.js'></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileImage = document.getElementById('profileImage');
            const profilePictureUpload = document.getElementById('profilePictureUpload');

            if (profilePictureUpload) {
                profilePictureUpload.addEventListener('change', function(event) {
                    if (event.target.files && event.target.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            profileImage.src = e.target.result;
                        };
                        reader.readAsDataURL(event.target.files[0]);

                        const formData = new FormData();
                        formData.append('profile_picture', event.target.files[0]);

                        const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                        let csrf_token_ajax = '';
                        if (csrfTokenMeta) {
                            csrf_token_ajax = csrfTokenMeta.getAttribute('content');
                        }
                        formData.append('csrf_token', csrf_token_ajax);

                        fetch('<?= BASE_URL ?>employee/upload_profile_picture.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('Photo de profil téléchargée avec succès !', 'success');
                                // Optionnel: recharger la page pour mettre à jour toutes les images de profil si nécessaire
                                // window.location.reload(); 
                            } else {
                                showToast('Erreur lors du téléchargement de la photo : ' + data.message, 'danger');
                                profileImage.src = '<?= BASE_URL . htmlspecialchars($currentUser['profile_picture'] ?? 'assets/img/default_profile.png') ?>';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur lors de l\'upload :', error);
                            showToast('Une erreur réseau est survenue lors du téléchargement de la photo.', 'danger');
                            profileImage.src = '<?= BASE_URL . htmlspecialchars($currentUser['profile_picture'] ?? 'assets/img/default_profile.png') ?>';
                        });
                    }
                });
            }

            // --- Logique de gestion des notifications ---
            const notificationBell = document.getElementById('notificationBell');
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationDropdown = document.querySelector('.notification-dropdown'); // The UL element

            // Initialize badge display based on initial count
            if (parseInt(notificationBadge.textContent.trim()) === 0) {
                notificationBadge.style.display = 'none';
            }

            // Add event listeners to individual notification items
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior
                    const notificationId = this.dataset.notificationId;

                    if (notificationId) {
                        // Check if already marked as read
                        // if (this.classList.contains('read')) { // If you want to use 'read' class visually
                        //     return; // Already read, do nothing
                        // }
                        
                        // Mark as read via AJAX
                        markNotificationAsRead(notificationId, this); // Pass the element for immediate visual update
                    }
                });
            });

            // Add event listener for "Mark All As Read" button
            const markAllReadButton = document.getElementById('markAllRead');
            if (markAllReadButton) {
                markAllReadButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAllNotificationsAsRead();
                });
            }

            function markNotificationAsRead(notificationId, elementToUpdate) {
                const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrf_token_ajax = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

                fetch('<?= BASE_URL ?>api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf_token_ajax // Important for CSRF protection
                    },
                    body: JSON.stringify({ notification_id: notificationId, action: 'read_one' })
                })
                .then(response => {
                    if (!response.ok) {
                        // Handle HTTP errors
                        return response.text().then(text => { throw new Error(text) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Remove the notification from the dropdown
                        if (elementToUpdate) {
                            const listItem = elementToUpdate.closest('li'); // Get the parent <li>
                            if (listItem) {
                                listItem.remove();
                            }
                        }
                        updateNotificationCount(); // Recalculate count
                        showToast('Notification marquée comme lue.', 'success');

                        // If all notifications are read, display "No new notifications"
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            if (notificationDropdown) {
                                notificationDropdown.innerHTML = `<li><span class="dropdown-item notification-empty"><?= __('no_new_notifications') ?></span></li>`;
                            }
                        }
                    } else {
                        showToast('Erreur lors de la mise à jour de la notification : ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Erreur Fetch :', error);
                    showToast('Erreur réseau ou du serveur : ' + error.message, 'danger');
                });
            }

            function markAllNotificationsAsRead() {
                const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrf_token_ajax = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

                fetch('<?= BASE_URL ?>api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf_token_ajax
                    },
                    body: JSON.stringify({ action: 'read_all' })
                })
                .then(response => {
                     if (!response.ok) {
                        return response.text().then(text => { throw new Error(text) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Clear all notification items from the dropdown
                        if (notificationDropdown) {
                            notificationDropdown.innerHTML = `<li><span class="dropdown-item notification-empty"><?= __('no_new_notifications') ?></span></li>`;
                        }
                        updateNotificationCount(0); // Set count to 0
                        showToast('Toutes les notifications ont été marquées comme lues.', 'success');
                    } else {
                        showToast('Erreur lors de la mise à jour des notifications : ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Erreur Fetch :', error);
                    showToast('Erreur réseau ou du serveur : ' + error.message, 'danger');
                });
            }

            function updateNotificationCount(newCount = null) {
                // If newCount is explicitly provided (e.g., 0 for mark all read), use it
                // Otherwise, count the remaining notification items in the DOM
                const count = newCount !== null ? newCount : document.querySelectorAll('.notification-item').length;

                if (notificationBadge) {
                    if (count > 0) {
                        notificationBadge.textContent = count;
                        notificationBadge.style.display = ''; // Show the badge
                    } else {
                        notificationBadge.textContent = ''; // Clear text
                        notificationBadge.style.display = 'none'; // Hide the badge
                    }
                }
            }

            // You might have showToast defined in ../js/script.js.
            // If not, include it here or ensure it's loaded.
            // Example of showToast (if not in script.js):
            function showToast(message, type = 'info') {
                const toastContainer = document.getElementById('toast-container');
                if (!toastContainer) return;

                const toast = document.createElement('div');
                toast.className = `toast align-items-center text-bg-${type} border-0 fade show`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;

                toastContainer.appendChild(toast);

                const bsToast = new bootstrap.Toast(toast, {
                    autohide: true,
                    delay: 5000
                });
                bsToast.show();

                toast.addEventListener('hidden.bs.toast', function () {
                    toast.remove();
                });
            }

            // --- FullCalendar Initialization (from overview.php, if that page is loaded) ---
            // This part should technically be in overview.php, but if you want it globally
            // or if overview.php is always the initial page.
            // Make sure the calendar element exists before initializing.
            if (document.getElementById('calendar')) {
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'fr',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    events: [
                        // Exemple d'événements (à remplacer par vos données dynamiques depuis une base de données)
                        {
                            title: 'Réunion d\'équipe',
                            start: '2025-06-30T10:00:00',
                            end: '2025-06-30T11:00:00',
                            color: '#007bff'
                        },
                        {
                            title: 'Présentation client',
                            start: '2025-07-02T14:00:00',
                            color: '#28a745'
                        },
                        {
                            title: 'Date limite rapport',
                            start: '2025-07-05',
                            allDay: true,
                            color: '#dc3545'
                        }
                    ],
                    eventClick: function(info) {
                        alert('Événement: ' + info.event.title + '\nDate: ' + info.event.start.toLocaleDateString());
                    }
                });
                calendar.render();
            }

            // --- Digital Clock Initialization (from overview.php, if that page is loaded) ---
            // This part should technically be in overview.php.
            // Ensure the clock elements exist before trying to update them.
            if (document.getElementById('clockTime') && document.getElementById('clockDate')) {
                function updateClock() {
                    const now = new Date();
                    const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
                    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

                    document.getElementById('clockTime').textContent = now.toLocaleTimeString('fr-FR', timeOptions);
                    document.getElementById('clockDate').textContent = now.toLocaleDateString('fr-FR', dateOptions);
                }
                setInterval(updateClock, 1000);
                updateClock(); // Initial call
            }
        });
    </script>
</body>
</html>