<?php
// includes/functions.php

// Fonctions utilitaires générales pour l'application
// Celles-ci complètent les fonctions qui pourraient déjà être définies dans config.php

// IMPORTANT : Les fonctions comme sanitize(), redirect(), generateCsrfToken(),
// verifyCsrfToken(), checkAdminRole(), setFlashMessage(), getFlashMessage(),
// et la fonction de traduction __() sont supposées être définies dans
// config.php ou un fichier similaire et ne sont donc PAS incluses ici
// pour éviter les duplications et les erreurs de redéfinition.

// --- DÉBUT : Fonctions CSRF à ajouter ici ---

/**
 * Génère et stocke un token CSRF dans la session.
 * @return string Le token CSRF généré.
 */

/**
 * Valide un token CSRF par rapport à celui stocké en session.
 * @param string $token_from_request Le token CSRF reçu de la requête.
 * @return bool Vrai si le token est valide, Faux sinon.
 */
function validateCsrfToken($token_from_request) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token']) || empty($token_from_request) || !hash_equals($_SESSION['csrf_token'], $token_from_request)) {
        // Optionnel : Pour le débogage, vous pouvez logger cette erreur
        // error_log("CSRF Token mismatch: Session token=" . ($_SESSION['csrf_token'] ?? 'N/A') . " | Request token=" . $token_from_request);
        return false;
    }
    // Optionnel : Unset le token après utilisation pour les requêtes à usage unique
    // unset($_SESSION['csrf_token']);
    return true;
}

// --- FIN : Fonctions CSRF à ajouter ici ---


/**
 * Vérifie si l'utilisateur connecté a le rôle 'employee'.
 * Redirige vers la page de connexion si ce n'est pas le cas ou si non connecté.
 * Dépend de : session_start(), $_SESSION, function_exists('__'), function_exists('redirect'), defined('BASE_URL').
 */
function checkEmployeeRole() {
    // Assurez-vous que la session est démarrée avant d'accéder à $_SESSION
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Si l'utilisateur n'est pas connecté ou n'a pas le rôle 'employee'
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'employee') {
        // Optionnel : stocker un message d'erreur en session
        // La fonction `__` et `redirect` doivent être définies ailleurs (probablement dans config.php)
        $_SESSION['error_message'] = function_exists('__') ? __('access_denied_employee_login') : 'Accès refusé pour les employés.';
        if (function_exists('redirect') && defined('BASE_URL')) {
            redirect(BASE_URL . 'login.php');
        } else {
            // Fallback si redirect() ou BASE_URL ne sont pas définis
            header("Location: /login.php"); // Tentative de redirection simple
        }
        exit();
    }
}

/**
 * Récupère les notifications non lues pour un utilisateur spécifique.
 * Limite à 5 notifications pour l'affichage dans le menu déroulant.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $userId L'ID de l'utilisateur pour lequel récupérer les notifications.
 * @return array Un tableau associatif des notifications, ou un tableau vide en cas d'erreur.
 * Dépend de : l'objet PDO ($pdo) passé en paramètre.
 */
function getUnreadNotifications(PDO $pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT id, message, created_at, is_read FROM notifications WHERE user_id = :user_id AND is_read = FALSE ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getUnreadNotifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Compte le nombre total de notifications non lues pour un utilisateur spécifique.
 * Utilisé pour afficher le badge de notification.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $userId L'ID de l'utilisateur pour lequel compter les notifications.
 * @return int Le nombre de notifications non lues, ou 0 en cas d'erreur.
 * Dépend de : l'objet PDO ($pdo) passé en paramètre.
 */
function countUnreadNotifications(PDO $pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur PDO dans countUnreadNotifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marque une notification spécifique comme lue.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $notificationId L'ID de la notification à marquer comme lue.
 * @param int $userId L'ID de l'utilisateur qui possède cette notification (pour des raisons de sécurité).
 * @return bool Vrai si la notification a été marquée comme lue avec succès, faux sinon.
 * Dépend de : l'objet PDO ($pdo) passé en paramètre.
 */
function markNotificationAsRead(PDO $pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :notification_id AND user_id = :user_id");
        $stmt->execute([':notification_id' => $notificationId, ':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans markNotificationAsRead: " . $e->getMessage());
        return false;
    }
}

/**
 * Marque toutes les notifications non lues d'un utilisateur comme lues.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $userId L'ID de l'utilisateur dont toutes les notifications doivent être marquées comme lues.
 * @return bool Vrai si au moins une notification a été marquée, faux sinon.
 * Dépend de : l'objet PDO ($pdo) passé en paramètre.
 */
function markAllNotificationsAsRead(PDO $pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur PDO dans markAllNotificationsAsRead: " . $e->getMessage());
        return false;
    }
}

/**
 * Formate une date/heure en une chaîne de temps écoulé lisible par l'homme (ex: "il y a 5 minutes", "il y a 2 jours").
 * @param string $datetime La date et l'heure au format string (ex: 'YYYY-MM-DD HH:MM:SS').
 * @param bool $full Si vrai, inclut toutes les unités (ex: "1 an, 2 mois"), sinon seulement la plus grande (ex: "1 an").
 * @return string La chaîne de temps écoulé.
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Correction pour la dépréciation DateInterval::$w
    $diff_weeks = floor($diff->d / 7);
    $diff_days_remaining = $diff->d % 7; // Jours restants après avoir retiré les semaines

    $string = [
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    ];

    $parts = []; // Utilisé pour stocker les parties de la chaîne de temps

    // Ordre de priorité: années, mois, semaines, jours, heures, minutes, secondes
    if ($diff->y) {
        $parts['y'] = $diff->y . ' ' . $string['y'] . ($diff->y > 1 ? 's' : '');
    }
    if ($diff->m) {
        $parts['m'] = $diff->m . ' ' . $string['m'] . ($diff->m > 1 ? 's' : '');
    }
    // Gérer les semaines avant les jours
    if ($diff_weeks) { // Utiliser notre variable calculée pour les semaines
        $parts['w'] = $diff_weeks . ' ' . $string['w'] . ($diff_weeks > 1 ? 's' : '');
    }
    if ($diff_days_remaining) { // Utiliser les jours restants
        $parts['d'] = $diff_days_remaining . ' ' . $string['d'] . ($diff_days_remaining > 1 ? 's' : '');
    }
    if ($diff->h) {
        $parts['h'] = $diff->h . ' ' . $string['h'] . ($diff->h > 1 ? 's' : '');
    }
    if ($diff->i) {
        $parts['i'] = $diff->i . ' ' . $string['i'] . ($diff->i > 1 ? 's' : '');
    }
    if ($diff->s) {
        $parts['s'] = $diff->s . ' ' . $string['s'] . ($diff->s > 1 ? 's' : '');
    }

    if (!$full) {
        // Si non 'full', ne prendre que la première partie significative
        $parts = array_slice($parts, 0, 1);
    }

    return $parts ? 'il y a ' . implode(', ', $parts) : 'à l\'instant';
}

// Autres fonctions utilitaires...

?>