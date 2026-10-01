<?php

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php'; // Assurez-vous que ce fichier contient checkEmployeeRole(), getFlashMessage(), setFlashMessage(), redirect(), generateCsrfToken(), time_elapsed_string(), getUnreadNotifications(), countUnreadNotifications()

checkEmployeeRole(); // Vérifie si l'utilisateur est connecté et a le rôle d'employé

try {
    // Récupérer les informations de l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, role, is_first_login, profile_picture FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
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
        // Note: Pour un système plus complet, vous pourriez vouloir charger plus que seulement non lues ici,
        // mais c'est un bon début pour le dropdown.
        $stmt = $pdo->prepare("SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 10");
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
    setFlashMessage('warning', "Veuillez changer votre mot de passe lors de la première connexion.");
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
    <title><?= htmlspecialchars($pageTitle ?? 'Tableau de bord Employé') ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Mon Application' ?></title>

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
            display: flex; /* Assurez-vous qu'elle est aussi un conteneur flex */
            align-items: center; /* Aligner les éléments verticalement au centre */
            justify-content: space-between; /* Distribue l'espace entre les éléments (brand, search, icons) */
        }
        /* Ajustement du padding du container-fluid dans la navbar-top */
        .navbar-top .container-fluid {
            padding-left: 20px;
            padding-right: 20px;
            padding-top: 10px;
            padding-bottom: 10px;
            width: 100%; /* S'assure que le container-fluid prend 100% de la largeur disponible */
            display: flex; /* Rendre le container-fluid aussi un conteneur flex pour bien aligner ses enfants */
            align-items: center; /* Aligner les éléments verticalement au centre */
            justify-content: space-between; /* Distribue l'espace entre les éléments (brand, search, icons) */
        }

        .navbar-top .search-bar {
            flex-grow: 1; /* Prend l'espace disponible */
            margin-left: 20px;
            position: relative;
            max-width: 500px; /* Limiter la largeur de la barre de recherche sur les grands écrans */
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
            /* margin-left: auto; est déjà géré par justify-content: space-between sur le parent */
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
            width: 100%; /* Assurez-vous que le conteneur du calendrier prend toute la largeur disponible */
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
        /* Très important pour corriger le débordement de FullCalendar si ses tableaux internes ont une largeur fixe */
        .fc-scrollgrid, .fc-col-header, .fc-daygrid-body, .fc-scrollgrid-sync-table {
            width: 100% !important; /* Force la largeur à 100% de son conteneur */
            table-layout: fixed; /* Aide à la répartition égale des colonnes */
        }
        .fc-scroller {
            overflow: hidden !important; /* Cache le défilement interne si le contenu est plus large que prévu */
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
        /* Styles de l'image de profil (dans la sidebar) */
        .profile-pic-container {
            position: relative;
            width: 100px; /* Taille désirée pour l'image de profil */
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 20px;
            border: 2px solid #007bff;
            cursor: pointer; /* Indique que l'élément est cliquable */
            display: flex; /* Centrer le contenu */
            justify-content: center;
            align-items: center;
        }
        .profile-pic-container .profile-pic-label {
            display: block; /* S'assure que le label prend la taille du conteneur */
            width: 100%;
            height: 100%;
            cursor: pointer;
            position: relative;
        }
        .profile-pic-container .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .profile-pic-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .profile-pic-container:hover .profile-pic-overlay {
            opacity: 1;
        }
        .profile-pic-overlay i {
            font-size: 2rem;
        }
        /* Ajustements responsifs pour les petits écrans */
        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
                transform: translateX(-200px); /* Cache la sidebar par défaut */
                transition: transform 0.3s ease;
            }
            .sidebar.active {
                transform: translateX(0); /* Affiche la sidebar */
            }
            .content {
                margin-left: 0; /* Pas de décalage si la sidebar est cachée */
                width: 100%;
            }
            .navbar-top .navbar-brand {
                margin-right: auto;
            }
            .navbar-top .search-bar {
                display: none; /* Cache la barre de recherche sur les petits écrans */
            }
            .navbar-top .navbar-icons {
                margin-left: 0;
            }
            .menu-toggle {
                display: block; /* Affiche le bouton de bascule du menu */
            }
        }

        /* Nouveaux styles pour le bouton de bascule du menu (hamburger) */
        .menu-toggle {
            display: none; /* Caché par défaut sur les grands écrans */
            background-color: transparent;
            border: none;
            font-size: 1.5rem;
            color: #555;
            cursor: pointer;
            padding: 0;
            margin-right: 20px; /* Espace à droite des icônes de la navbar */
        }
        .menu-toggle:focus {
            outline: none;
            box-shadow: none;
        }

        /* Styles pour le footer */
        footer {
            width: 100%;
            padding: 15px 20px;
            background-color: #ffffff;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 0.85rem;
            color: #6c757d;
            box-shadow: 0 -2px 4px rgba(0,0,0,0.02);
            margin-top: auto; /* Pousse le footer vers le bas si le contenu est court */
        }

        /* Styles spécifiques pour le toast de notification */
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000; /* Assure qu'il est au-dessus de tout */
        }
        .toast {
            min-width: 250px;
            background-color: #28a745; /* Couleur par défaut pour le succès */
            color: #fff;
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .toast.bg-danger {
            background-color: #dc3545 !important;
        }
        .toast.bg-warning {
            background-color: #ffc107 !important;
        }
        .toast.bg-info {
            background-color: #17a2b8 !important;
        }
        .toast-header {
            background-color: transparent;
            border-bottom: none;
            color: #fff;
        }
        .toast-body {
            word-break: break-word; /* Assure que le texte long ne déborde pas */
        }
        .toast .btn-close {
            filter: invert(1); /* Rend la croix de fermeture blanche */
        }

        /* Styles pour le profil utilisateur dans la barre de navigation */
        .user-profile-dropdown .dropdown-toggle::after {
            display: none; /* Cache la flèche par défaut de Bootstrap */
        }
        .user-profile-dropdown img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #007bff; /* Bordure bleue pour l'image de profil */
        }
        .user-profile-dropdown .dropdown-menu {
            right: 0;
            left: auto;
            min-width: 200px;
        }
        .user-profile-dropdown .dropdown-header {
            white-space: normal; /* Permet au texte de s'enrouler */
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 5px;
        }
        .user-profile-dropdown .dropdown-header .username {
            font-weight: 600;
            color: #333;
        }
        .user-profile-dropdown .dropdown-header .role {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .user-profile-dropdown .dropdown-item {
            padding: 8px 15px;
            font-size: 0.95rem;
        }
        .user-profile-dropdown .dropdown-item:active {
            background-color: #007bff;
            color: #fff;
        }

        /* Styles pour les champs d'input qui affichent des erreurs */
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            display: block; /* S'assure que le message d'erreur est visible */
        }

        /* Styles pour la barre de progression (ex: pour les uploads ou chargements) */
        .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            margin-top: 20px;
            display: none; /* Caché par défaut */
        }
        .progress-bar {
            width: 0%;
            height: 20px;
            background-color: #007bff;
            border-radius: 5px;
            text-align: center;
            color: white;
            line-height: 20px;
            font-size: 0.8em;
            transition: width 0.3s ease-in-out;
        }
    </style>
</head>
<body>

    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3">
        </div>

    <nav id="sidebar" class="sidebar">
        <div class="d-flex flex-column h-100">
            <h1 class="hr-management-title">HR<span>Manager</span></h1>
            <ul class="nav flex-column mb-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'overview' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=overview">
                        <i class="bi bi-grid-1x2"></i>
                        <?= __('dashboard') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'customer_orders' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=customer_orders">
                        <i class="bi bi-receipt"></i>
                        <?= __('customer_orders') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'customers' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=customers">
                        <i class="bi bi-people"></i>
                        <?= __('customers') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'sales_report' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=sales_report">
                        <i class="bi bi-bar-chart"></i>
                        <?= __('sales_reports') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'calendar' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=calendar">
                        <i class="bi bi-calendar-event"></i>
                        <?= __('calendar') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'planning' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=planning">
                        <i class="bi bi-clipboard-check"></i>
                        <?= __('planning') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'leave_request' ? 'active' : '' ?>" href="<?= BASE_URL ?>employee/dashboard.php?page=leave_request">
                        <i class="bi bi-box-arrow-right"></i>
                        <?= __('leave_request') ?>
                    </a>
                </li>
            </ul>

            <div class="mt-auto p-3">
                <hr>
                <div class="d-flex align-items-center mb-2">
                    <img src="<?= htmlspecialchars($currentUser['profile_picture'] ?? BASE_URL . 'public/images/default_profile.png') ?>" alt="Profile Picture" class="top-profile-pic me-2">
                    <div>
                        <div class="username text-break"><?= htmlspecialchars(getCurrentUserName($currentUser)) ?></div>
                        <div class="role text-muted"><?= htmlspecialchars(ucfirst(getCurrentUserRole($currentUser))) ?></div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger w-100">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    <?= __('logout') ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="content">
        <header class="navbar-top navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="menu-toggle btn" id="menuToggle">
                    <i class="bi bi-list"></i>
                </button>
                <form class="d-flex search-bar" role="search" onsubmit="event.preventDefault();">
                    <i class="bi bi-search"></i>
                    <input class="form-control me-2" type="search" placeholder="<?= __('search') ?>" aria-label="Search">
                </form>
                <div class="navbar-icons d-flex align-items-center">
                    <div class="dropdown">
                        <i class="bi bi-bell dropdown-toggle" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge">
                                <?= $unreadNotificationsCount > 0 ? $unreadNotificationsCount : '' ?>
                            </span>
                        </i>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown" id="notificationList">
                            <?php if (!empty($unreadNotifications)): ?>
                                <?php foreach ($unreadNotifications as $notification): ?>
                                    <li class="notification-item <?= $notification['is_read'] ? 'read' : 'unread' ?>" data-notification-id="<?= $notification['id'] ?>">
                                        <a href="<?= htmlspecialchars($notification['link'] ?? '#') ?>" class="text-decoration-none text-dark d-block">
                                            <div class="message"><?= htmlspecialchars($notification['message']) ?></div>
                                            <small class="time text-muted"><?= htmlspecialchars(time_elapsed_string($notification['created_at'])) ?></small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><div class="notification-empty"><?= __('no_new_notifications') ?></div></li>
                            <?php endif; ?>
                            <?php if ($unreadNotificationsCount > 0): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center text-primary" href="#"><?= __('view_all_notifications') ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="dropdown user-profile-dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= htmlspecialchars($currentUser['profile_picture'] ?? BASE_URL . 'public/images/default_profile.png') ?>" alt="Profile Picture" class="top-profile-pic">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li>
                                <h6 class="dropdown-header">
                                    <div class="username"><?= htmlspecialchars(getCurrentUserName($currentUser)) ?></div>
                                    <div class="role text-muted"><?= htmlspecialchars(ucfirst(getCurrentUserRole($currentUser))) ?></div>
                                </h6>
                            </li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=my_profile"><i class="bi bi-person-circle me-2"></i><?= __('my_profile') ?></a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=change_password"><i class="bi bi-key me-2"></i><?= __('change_password') ?></a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i><?= __('logout') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="dashboard-content flex-grow-1">
            <?php
            if (isset($_SESSION['flash_message'])) {
                $message = getFlashMessage();
                echo "<script type=\"text/javascript\">";
                echo "document.addEventListener('DOMContentLoaded', function() {";
                echo "    showToast('" . addslashes($message['type']) . "', '" . addslashes($message['message']) . "');";
                echo "});";
                echo "</script>";
            }

            include $contentFile;
            ?>
        </main>

        <footer>
            &copy; <?= date('Y') ?> <?= defined('SITE_NAME') ? SITE_NAME : 'Mon Application' ?>. Tous droits réservés.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <script src="../js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            const notificationDropdownLink = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationBadge');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }

            // Fonction pour mettre à jour les notifications
            function fetchNotifications() {
                fetch('<?= BASE_URL ?>api/get_notifications.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau lors de la récupération des notifications.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            updateNotificationDropdown(data.notifications, data.unreadCount);
                        } else {
                            console.error('Erreur lors de la récupération des notifications :', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erreur :', error);
                    });
            }

            function updateNotificationDropdown(notifications, unreadCount) {
                notificationList.innerHTML = ''; // Nettoie la liste actuelle

                if (notifications.length > 0) {
                    notifications.forEach(notification => {
                        const li = document.createElement('li');
                        li.classList.add('notification-item');
                        if (notification.is_read) {
                            li.classList.add('read');
                        } else {
                            li.classList.add('unread');
                        }
                        li.dataset.notificationId = notification.id;

                        // Créer un lien pour rendre l'élément cliquable
                        const link = document.createElement('a');
                        link.href = notification.link ? notification.link : '#'; // Utiliser le lien de la notification ou '#'
                        link.classList.add('text-decoration-none', 'text-dark', 'd-block');

                        link.innerHTML = `
                            <div class="message">${htmlspecialchars(notification.message)}</div>
                            <small class="time text-muted">${htmlspecialchars(timeAgo(notification.created_at))}</small>
                        `;
                        li.appendChild(link);
                        notificationList.appendChild(li);

                        // Ajoutez un écouteur de clic pour marquer comme lu
                        if (!notification.is_read) {
                            li.addEventListener('click', function() {
                                markNotificationAsRead(notification.id);
                            });
                        }
                    });

                    // Ajouter le lien "Voir toutes les notifications" si nécessaire
                    if (notifications.length > 0) {
                        const divider = document.createElement('li');
                        divider.innerHTML = '<hr class="dropdown-divider">';
                        notificationList.appendChild(divider);

                        const viewAllLi = document.createElement('li');
                        const viewAllLink = document.createElement('a');
                        viewAllLink.classList.add('dropdown-item', 'text-center', 'text-primary');
                        viewAllLink.href = '<?= BASE_URL ?>employee/dashboard.php?page=notifications'; // Lien vers la page de toutes les notifications
                        viewAllLink.textContent = '<?= __('view_all_notifications') ?>';
                        viewAllLi.appendChild(viewAllLink);
                        notificationList.appendChild(viewAllLi);
                    }

                } else {
                    const li = document.createElement('li');
                    li.innerHTML = `<div class="notification-empty"><?= __('no_new_notifications') ?></div>`;
                    notificationList.appendChild(li);
                }

                // Mettre à jour le badge de notifications non lues
                if (unreadCount > 0) {
                    notificationBadge.textContent = unreadCount;
                } else {
                    notificationBadge.textContent = ''; // Vide le contenu pour masquer le badge via CSS
                }
            }

            // Petite fonction helper pour échapper les caractères HTML
            function htmlspecialchars(str) {
                if (typeof str !== 'string') {
                    return str;
                }
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return str.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // Fonction pour le formatage du temps (similaire à time_elapsed_string en PHP)
            function timeAgo(dateString) {
                const now = new Date();
                const past = new Date(dateString);
                const seconds = Math.floor((now - past) / 1000);

                let interval = seconds / 31536000;
                if (interval > 1) return Math.floor(interval) + " ans";
                interval = seconds / 2592000;
                if (interval > 1) return Math.floor(interval) + " mois";
                interval = seconds / 86400;
                if (interval > 1) return Math.floor(interval) + " jours";
                interval = seconds / 3600;
                if (interval > 1) return Math.floor(interval) + " heures";
                interval = seconds / 60;
                if (interval > 1) return Math.floor(interval) + " minutes";
                return Math.floor(seconds) + " secondes";
            }

            // Fonction pour marquer une notification comme lue via AJAX
            function markNotificationAsRead(notificationId) {
                fetch('<?= BASE_URL ?>api/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ notification_id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Notification ' + notificationId + ' marquée comme lue.');
                        fetchNotifications(); // Rafraîchir les notifications après avoir marqué comme lu
                    } else {
                        console.error('Échec de marquer la notification comme lue :', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur réseau lors du marquage comme lu :', error);
                });
            }

            // Actualiser les notifications toutes les 30 secondes (ou plus souvent si nécessaire)
            setInterval(fetchNotifications, 30000);
            // Appeler la fonction une fois au chargement de la page
            fetchNotifications();

            // Ajout d'un écouteur pour rafraîchir les notifications quand le dropdown s'ouvre
            if (notificationDropdownLink) {
                notificationDropdownLink.addEventListener('click', function() {
                    // Fetch les notifications chaque fois que l'utilisateur clique sur la cloche pour voir le menu déroulant
                    fetchNotifications();
                });
            }
        });
    </script>
</body>
</html>