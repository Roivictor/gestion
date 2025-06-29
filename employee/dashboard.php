<?php
// employee/dashboard.php

session_start(); // Démarre la session PHP si elle n'est pas déjà démarrée
require_once __DIR__ . '/../config.php'; // Chemin vers config.php (adapter si nécessaire)
require_once __DIR__ . '/../includes/functions.php'; // Fonctions utilitaires

// 1. Vérification de l'authentification et du rôle
// Vérifier si l'utilisateur est connecté et est un EMPLOYE
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'employee') {
    $_SESSION['error_message'] = __('access_denied_employee');
    redirect(BASE_URL . 'login.php');
    exit();
}

// 2. Récupérer les informations de l'utilisateur connecté
$currentUser = $_SESSION['user'];

// 3. Déterminer la page à afficher
$page = $_GET['page'] ?? 'overview';

// Vérifier si c'est la première connexion et forcer le changement de mot de passe
if ((bool)($currentUser['is_first_login'] ?? false) && $page !== 'change_password_first_login') {
    redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
    exit();
}

// Liste blanche des pages autorisées pour les employés
// 'my_orders' a été retiré car l'employé ne passe pas de commandes
$allowedEmployeePages = [
    'overview',                  // Aperçu du tableau de bord
    'customer_orders',           // Gérer les commandes clients
    'customers',                 // Gérer les clients
    'my_profile',                // Profil de l'employé
    'change_password',           // Changer le mot de passe habituel
    'change_password_first_login', // Page spécifique pour le premier login
    'sales_report',              // Rapports de vente
    'view_order',                // Pour voir les détails d'une commande (si implémenté)
    'edit_order'                 // Pour modifier une commande (si implémenté)
    // Ajoutez ici d'autres pages spécifiques à l'employé
];

// Vérifier si la page demandée est dans la liste blanche
if (!in_array($page, $allowedEmployeePages)) {
    $page = 'overview'; // Revenir à 'overview' si la page n'est pas autorisée
}

// Construction du chemin du fichier de contenu
// Les fichiers de contenu DOIVENT être dans le dossier 'employee/'
$contentFile = __DIR__ . '/' . $page . '.php';

// Si le fichier de contenu n'existe pas, charger la page d'aperçu par défaut
if (!file_exists($contentFile)) {
    $contentFile = __DIR__ . '/overview.php';
    $page = 'overview'; // Mettre à jour la variable $page pour refléter le fallback
}

// Debug: Affiche les erreurs PHP (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_language_code ?? 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? __('employee_dashboard_title')) ?> - <?= defined('SITE_NAME') ? SITE_NAME : 'Mon Application' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="../css/style.css" rel="stylesheet"> 
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            width: 250px;
            background-color: #f8f9fa;
            color: #343a40;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
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
        .content {
            margin-left: 250px;
            padding: 20px;
            flex-grow: 1;
        }
        .navbar-top {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 10px 20px;
            margin-left: 250px;
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-top .navbar-brand {
            font-weight: bold;
            color: #343a40;
        }
        .navbar-top .nav-item .nav-link {
            color: #495057;
        }
        .navbar-top .nav-item .nav-link:hover {
            color: #007bff;
        }
        .alert {
            margin-top: 15px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: none;
                padding-top: 0;
            }
            .content, .navbar-top {
                margin-left: 0;
            }
            .navbar-top {
                position: relative;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar d-flex flex-column">
            <h4 class="text-center mt-3 mb-4"><?= __('employee_panel_title') ?></h4>
            <div class="px-3 mb-4 text-center">
                <p class="text-muted mb-1"><?= __('connected_as') ?> :</p>
                <p class="fw-bold mb-1"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></p>
                <span class="badge bg-info text-dark"><?= htmlspecialchars(ucfirst($currentUser['role'])) ?></span>
                <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger btn-sm mt-3 w-100"><i class="bi bi-box-arrow-right"></i> <?= __('logout') ?></a>
            </div>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=overview" class="nav-link <?= ($page === 'overview' ? 'active' : '') ?>">
                        <i class="bi bi-house-door"></i> <?= __('overview') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=customer_orders" class="nav-link <?= ($page === 'customer_orders' ? 'active' : '') ?>">
                        <i class="bi bi-receipt"></i> <?= __('customer_orders') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=customers" class="nav-link <?= ($page === 'customers' ? 'active' : '') ?>">
                        <i class="bi bi-people"></i> <?= __('customers') ?>
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
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>employee/dashboard.php?page=sales_report" class="nav-link <?= ($page === 'sales_report' ? 'active' : '') ?>">
                        <i class="bi bi-graph-up"></i> <?= __('sales_report') ?>
                    </a>
                </li>
                </ul>
        </div>

        <div class="content d-flex flex-column w-100">
            <nav class="navbar navbar-expand-lg navbar-light bg-light navbar-top">
                <a class="navbar-brand" href="<?= BASE_URL ?>employee/dashboard.php"><?= SITE_NAME ?> - Employé</a>
                <div class="ms-auto">
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['username']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=my_profile"><?= __('my_profile') ?></a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>employee/dashboard.php?page=change_password"><?= __('change_password') ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>logout.php"><?= __('logout') ?></a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="flex-grow-1 p-3">
                <?php
                // Affichage des messages de succès ou d'erreur
                if (isset($_SESSION['success_message'])) {
                    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    unset($_SESSION['success_message']);
                }
                if (isset($_SESSION['error_message'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    unset($_SESSION['error_message']);
                }

                // INCLUSION DU CONTENU DE LA PAGE SPÉCIFIQUE DE L'EMPLOYÉ ICI
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
    <script src="../js/script.js"></script>
</body>
</html>