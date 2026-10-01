<?php
// logout.php

// Inclure le fichier de configuration pour avoir accès à la fonction de redirection ou gérer la session
// Note: 'config.php' doit être capable de démarrer la session si elle n'est pas déjà active.
// Si config.php démarre déjà la session, le block if (session_status() !== PHP_SESSION_ACTIVE) n'est pas nécessaire.
require_once 'config.php';

// Démarrer la session si elle n'est pas déjà active (très important pour accéder à $_SESSION et la détruire)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Journalisation (pour debug) - Utile pour savoir qui se déconnecte.
// error_log("Tentative de déconnexion pour l'utilisateur: " . ($_SESSION['username'] ?? 'inconnu'));

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous utilisez des cookies de session, détruisez le cookie de session en le rendant expiré
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruit la session du serveur
session_destroy();

// Il n'est pas nécessaire d'appeler session_write_close() après session_destroy() si vous redirigez immédiatement,
// car session_destroy() se charge de l'écriture.

// Confirmation (cette variable de session ne sera pas visible après la redirection)
// Si vous voulez afficher un message de succès après la déconnexion, il faudrait le faire sur la page de connexion
// ou via un système de "flash messages" avant de détruire la session.
// Pour l'instant, je retire cette ligne car elle n'aura pas l'effet désiré.
// $_SESSION['logout_message'] = "Vous avez été déconnecté avec succès.";

// Redirection vers la page de connexion
// Assurez-vous que le chemin 'login.php' est correct par rapport à l'emplacement de logout.php
// logout.php se trouve dans 'u/', donc 'login.php' doit être dans 'u/' également.
header('Location: login.php');
exit(); // Toujours appeler exit() après une redirection pour s'assurer que le script s'arrête.

?>