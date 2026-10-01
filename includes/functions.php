<?php

declare(strict_types=1); // Exige un typage strict pour les fonctions

// includes/functions.php

// Fonctions utilitaires générales pour l'application

// --- GESTION DE SESSION ---
// Il est recommandé de démarrer la session une seule fois, idéalement dans un fichier
// de configuration global (ex: config.php) qui est inclus au tout début de chaque script.
// Cette vérification est ici pour la robustesse, mais une gestion centralisée est préférable.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Vérifie si un utilisateur est actuellement connecté.
 *
 * @return bool Vrai si un utilisateur est connecté, faux sinon.
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        // Un utilisateur est considéré comme connecté si son ID est défini dans la session.
        // Assurez-vous que cette vérification est cohérente avec la façon dont vous définissez
        // l'utilisateur dans la session lors de la connexion.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user']['id']) && !empty($_SESSION['user']['id']);
    }
}

// --- FONCTIONS DE BASE REQUISES (sans traduction) ---

/**
 * Définit la fonction __() pour simplement retourner la clé.
 * Utilisée pour satisfaire les appels à __() sans introduire de logique de traduction.
 *
 * @param string $key La clé ou la chaîne à "traduire" (simplement retourner).
 * @param string|null $default Le message par défaut (sera ignoré si la fonction retourne la clé).
 * @return string La clé ou la chaîne originale.
 */
if (!function_exists('__')) {
    function __(string $key, ?string $default = null): string
    {
        return $key; // Retourne simplement la clé, sans traduction.
    }
}

/**
 * Formate une date selon le format spécifié.
 * Gère les cas où la date est nulle ou invalide.
 *
 * @param string|null $dateString La chaîne de date à formater.
 * @param string $format Le format de sortie (par défaut 'd/m/Y H:i:s').
 * @return string La date formatée ou une chaîne vide si la date est nulle/invalide.
 */
if (!function_exists('formatDate')) {
    function formatDate(?string $dateString, string $format = 'd/m/Y H:i:s'): string
    {
        if (empty($dateString) || $dateString === '0000-00-00 00:00:00') {
            return '';
        }
        try {
            $date = new DateTime($dateString);
            return $date->format($format);
        } catch (Exception $e) {
            error_log("Erreur de formatage de date : " . $e->getMessage() . " pour la chaîne : " . $dateString);
            return ''; // Ou 'Date invalide'
        }
    }
}

/**
 * Génère une URL basée sur l'URL de base de l'application.
 *
 * @param string $path Le chemin relatif à ajouter à l'URL de base.
 * @return string L'URL complète.
 */
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        // Assurez-vous que BASE_URL est défini dans config.php
        return BASE_URL . ltrim($path, '/');
    }
}

/**
 * Redirige l'utilisateur vers une URL donnée et termine l'exécution du script.
 *
 * @param string $url L'URL vers laquelle rediriger.
 */
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header("Location: " . $url);
        exit();
    }
}

// --- GESTION DES MESSAGES FLASH (notifications temporaires) ---

/**
 * Définit un message flash à afficher une seule fois.
 *
 * @param string $type Le type de message (ex: 'success', 'danger', 'warning', 'info').
 * @param string $message Le contenu du message.
 */
if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $type, string $message): void
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        // C'est une bonne pratique si cette fonction peut être appelée avant session_start() globalement.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    }
}

/**
 * Récupère et efface un message flash.
 *
 * @return array|null Un tableau contenant le type et le message, ou null s'il n'y en a pas.
 */
if (!function_exists('getFlashMessage')) {
    function getFlashMessage(): ?array
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
}

/**
 * Affiche un message flash s'il en existe un.
 * Cette fonction est destinée à être appelée dans le code HTML de votre page.
 */
if (!function_exists('displayFlashMessages')) {
    function displayFlashMessages(): void
    {
        $flashMessage = getFlashMessage();
        if ($flashMessage) {
            $type = htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8');
            echo "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">";
            echo $message;
            echo "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button>";
            echo "</div>";
        }
    }
}

// --- SÉCURITÉ (CSRF) ---

/**
 * Génère un jeton CSRF et le stocke en session.
 *
 * @return string Le jeton CSRF généré.
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un jeton de 64 caractères hexadécimaux
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Vérifie si le jeton CSRF soumis correspond à celui en session.
 *
 * @param string $token Le jeton CSRF soumis par le formulaire.
 * @return bool Vrai si les jetons correspondent, faux sinon.
 */
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Utilise hash_equals pour une comparaison sécurisée contre les attaques temporelles
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// --- SANITIZATION & VALIDATION ---

/**
 * Nettoie une chaîne de caractères pour prévenir les attaques XSS.
 *
 * @param string|null $data La chaîne à nettoyer.
 * @return string La chaîne nettoyée.
 */
if (!function_exists('sanitize')) {
    function sanitize(?string $data): string
    {
        return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Valide une adresse e-mail.
 *
 * @param string $email L'adresse e-mail à valider.
 * @return string|false L'e-mail validé ou false si invalide.
 */
if (!function_exists('validateEmail')) {
    function validateEmail(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

/**
 * Hache un mot de passe.
 *
 * @param string $password Le mot de passe en texte brut.
 * @return string Le mot de passe haché.
 */
if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

// --- GESTION DES RÔLES ET AUTHENTIFICATION ---

/**
 * Vérifie si l'utilisateur actuel est connecté et a un rôle spécifique.
 * Redirige vers la page de connexion si non autorisé.
 *
 * @param string|array $requiredRole Le rôle (ou un tableau de rôles) requis pour accéder à la page.
 * @param bool $strict Si vrai, la fonction vérifie si le rôle de l'utilisateur correspond EXACTEMENT au rôle requis.
 * Si faux, la fonction vérifie si le rôle de l'utilisateur fait partie des rôles requis (si un tableau est donné).
 */
if (!function_exists('checkUserRole')) {
    function checkUserRole(string|array $requiredRole, bool $strict = true): void
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Vérifier si l'utilisateur est connecté
        if (!isset($_SESSION['user']['id'])) { // Utilise $_SESSION['user']['id']
            redirect(BASE_URL . 'login.php');
            exit();
        }

        $userRole = strtolower($_SESSION['user']['role']);

        if (is_array($requiredRole)) {
            // Si $requiredRole est un tableau, vérifier si le rôle de l'utilisateur est inclus.
            $requiredRole = array_map('strtolower', $requiredRole); // Convertir tous les rôles requis en minuscules
            if (!in_array($userRole, $requiredRole)) {
                setFlashMessage('danger', __('access_denied_role')); // 'Accès refusé. Rôle insuffisant.'
                redirect(BASE_URL . 'index.php'); // Rediriger vers l'accueil ou une page d'erreur
                exit();
            }
        } else {
            // Si $requiredRole est une chaîne, vérifier si le rôle de l'utilisateur correspond.
            $requiredRole = strtolower($requiredRole);
            if ($userRole !== $requiredRole) {
                setFlashMessage('danger', __('access_denied_role')); // 'Accès refusé. Rôle insuffisant.'
                redirect(BASE_URL . 'index.php'); // Rediriger vers l'accueil ou une page d'erreur
                exit();
            }
        }
    }
}

/**
 * Vérifie si l'utilisateur est connecté et a le rôle d'employé.
 * Redirige vers la page de connexion si non connecté ou rôle incorrect.
 */
if (!function_exists('checkEmployeeRole')) {
    function checkEmployeeRole(): void
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Correction ici: Utilisation de $_SESSION['user']['id']
        if (!isset($_SESSION['user']['id']) || strtolower($_SESSION['user']['role'] ?? '') !== 'employee') {
            redirect(BASE_URL . 'login.php');
            exit();
        }
    }
}


/**
 * Récupère le nom complet de l'utilisateur connecté ou 'Invité'.
 *
 * @return string Le nom complet ou 'Invité'.
 */
if (!function_exists('getCurrentUserName')) {
    function getCurrentUserName(): string
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user']['first_name']) && isset($_SESSION['user']['last_name'])) {
            return sanitize($_SESSION['user']['first_name']) . ' ' . sanitize($_SESSION['user']['last_name']);
        } elseif (isset($_SESSION['user']['username'])) {
            return sanitize($_SESSION['user']['username']);
        }
        return __('guest'); // 'Invité'
    }
}

/**
 * Récupère le rôle de l'utilisateur connecté.
 *
 * @return string Le rôle de l'utilisateur ou 'guest'.
 */
if (!function_exists('getCurrentUserRole')) {
    function getCurrentUserRole(): string
    {
        // Vérification de session_status() pour assurer que la session est démarrée.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return strtolower($_SESSION['user']['role'] ?? __('guest'));
    }
}


// --- Fonctions de date et heure ---
/**
 * Calcule le temps écoulé depuis une date donnée sous forme lisible.
 *
 * @param string $datetime La date/heure au format MySQL (YYYY-MM-DD HH:MM:SS).
 * @param bool $full Retourne la chaîne complète (ex: "il y a 1 an, 2 mois, 3 jours") ou la plus courte.
 * @return string Le temps écoulé formaté.
 */
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false)
    {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'an',
            'm' => 'mois',
            'w' => 'semaine',
            'd' => 'jour',
            'h' => 'heure',
            'i' => 'minute',
            's' => 'seconde',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}


// --- Fonctions de notifications ---

/**
 * Récupère les notifications non lues pour un utilisateur donné.
 *
 * @param PDO $pdo L'instance PDO pour la connexion à la base de données.
 * @param int $userId L'ID de l'utilisateur.
 * @param int $limit Le nombre maximum de notifications à récupérer.
 * @return array Un tableau de notifications.
 */
if (!function_exists('getUnreadNotifications')) {
    function getUnreadNotifications(PDO $pdo, int $userId, int $limit = 5): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT id, message, created_at, is_read
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
                LIMIT :limit
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur de base de données lors de la récupération des notifications : " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Compte le nombre de notifications non lues pour un utilisateur donné.
 *
 * @param PDO $pdo L'instance PDO pour la connexion à la base de données.
 * @param int $userId L'ID de l'utilisateur.
 * @return int Le nombre de notifications non lues.
 */
if (!function_exists('countUnreadNotifications')) {
    function countUnreadNotifications(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(id) AS unread_count
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['unread_count'] : 0;
        } catch (PDOException $e) {
            error_log("Erreur de base de données lors du comptage des notifications : " . $e->getMessage());
            return 0;
        }
    }
}


/**
 * Récupère le prix actuel d'un produit en tenant compte d'une éventuelle promotion.
 *
 * @param array $product Le tableau associatif du produit, doit contenir 'price', 'promo_price', 'promo_start_date', 'promo_end_date'.
 * @return array Un tableau associatif avec 'display_price', 'original_price', et 'is_promo'.
 * - 'display_price': Le prix final à afficher (prix promotionnel si une promo est active).
 * - 'original_price': Le prix initial du produit.
 * - 'is_promo': Booléen indiquant si une promotion est active.
 */
if (!function_exists('getProductCurrentPrice')) {
    function getProductCurrentPrice(array $product): array
    {
        // S'assurer que les prix sont des nombres, même s'ils sont null dans la base de données
        $basePrice = (float)($product['price'] ?? 0.0);
        $promoPrice = (float)($product['promo_price'] ?? 0.0);

        // Initialiser les dates de promotion à null si elles ne sont pas définies ou vides
        $promoStartDate = !empty($product['promo_start_date']) ? new DateTime($product['promo_start_date']) : null;
        $promoEndDate = !empty($product['promo_end_date']) ? new DateTime($product['promo_end_date']) : null;

        $currentDate = new DateTime();

        // Le prix à afficher par défaut est le prix de base.
        $displayPrice = $basePrice;
        // Le prix original pour l'affichage (qui peut être barré s'il y a une promo)
        $originalPrice = $basePrice;
        $isPromoActive = false;

        // Vérifier si une promotion est valide et active
        if ($promoPrice > 0.0 && $promoStartDate && $promoEndDate) {
            try {
                // Vérifie si la date actuelle est entre la date de début et la date de fin (incluses)
                if ($currentDate >= $promoStartDate && $currentDate <= $promoEndDate) {
                    $displayPrice = $promoPrice;
                    $isPromoActive = true;
                }
            } catch (Exception $e) {
                error_log("Erreur de date de promotion pour le produit ID " . ($product['id'] ?? 'N/A') . ": " . $e->getMessage());
                // En cas d'erreur de date, on ne considère pas la promotion comme active.
            }
        }

        return [
            'display_price' => $displayPrice, // Ancien 'current_price' devient 'display_price'
            'original_price' => $originalPrice,
            'is_promo' => $isPromoActive
        ];
    }
}