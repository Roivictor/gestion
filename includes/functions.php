<?php
// includes/functions.php

// Fonctions utilitaires générales pour l'application
// Celles-ci complètent les fonctions qui pourraient déjà être définies dans config.php

// Les fonctions sanitize(), redirect(), generateCsrfToken(), verifyCsrfToken()
// sont supposées être définies dans config.php ou ailleurs et ne sont donc PAS incluses ici.

// La fonction checkAdminRole() est déjà dans config.php, elle ne DOIT PAS être ici.
// /**
//  * Vérifie si l'utilisateur connecté a le rôle 'admin'.
//  * Redirige vers la page de connexion si ce n'est pas le cas ou si non connecté.
//  */
// function checkAdminRole() {
//     if (session_status() == PHP_SESSION_NONE) {
//         session_start();
//     }
//     if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
//         $_SESSION['error_message'] = function_exists('__') ? __('access_denied') : 'Accès refusé.';
//         if (function_exists('redirect') && defined('BASE_URL')) {
//             redirect(BASE_URL . 'login.php');
//         } else {
//             header("Location: /login.php");
//         }
//         exit();
//     }
// }

/**
 * Vérifie si l'utilisateur connecté a le rôle 'employee'.
 * Redirige vers la page de connexion si ce n'est pas le cas ou si non connecté.
 */
function checkEmployeeRole() {
    // Assurez-vous que la session est démarrée avant d'accéder à $_SESSION
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Si l'utilisateur n'est pas connecté ou n'a pas le rôle 'employee'
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'employee') {
        // Optionnel : stocker un message d'erreur en session
        $_SESSION['error_message'] = function_exists('__') ? __('access_denied_employee_login') : 'Accès refusé pour les employés.';
        // La fonction `redirect` doit être définie ailleurs (probablement dans config.php)
        if (function_exists('redirect') && defined('BASE_URL')) {
            redirect(BASE_URL . 'login.php');
        } else {
            // Fallback si redirect() ou BASE_URL ne sont pas définis
            header("Location: /login.php"); // Tentative de redirection simple
        }
        exit();
    }
}

// Ajoutez d'autres fonctions utilitaires ici si nécessaire et si elles ne sont pas déjà dans config.php
// Par exemple:
// function hashPassword($password) { return password_hash($password, PASSWORD_DEFAULT); }
// function verifyPassword($password, $hash) { return password_verify($password, $hash); }

?>