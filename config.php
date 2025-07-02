<?php
// config.php - Version Finale et Sécurisée et Corrigée

// Assure que la session est démarrée au tout début, si elle ne l'est pas déjà
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gestion des erreurs PHP (pour le DÉBOGAGE - À DÉSACTIVER EN PRODUCTION !)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // VOTRE MOT DE PASSE DE BASE DE DONNÉES ICI (laisser vide si pas de mot de passe)
define('DB_NAME', 'store_crm');

// Configuration de PHPMailer (SMTP) - Remplacez par vos vraies informations SMTP
define('SMTP_HOST', 'smtp.example.com');    // Ex: 'smtp.gmail.com' ou l'hôte de votre fournisseur
define('SMTP_USER', 'your_email@example.com'); // Ex: 'monemail@gmail.com'
define('SMTP_PASS', 'your_email_password'); // Le mot de passe de votre compte email SMTP
define('SMTP_PORT', 587);                   // 587 pour STARTTLS, 465 pour SMTPS (SSL)
define('SMTP_FROM', 'noreply@yourstore.com'); // Adresse email expéditeur
define('SMTP_FROM_NAME', 'Store CRM');       // Nom expéditeur

// Autres configurations globales
define('SITE_NAME', 'Store CRM');
define('BASE_URL', 'http://localhost/u/'); // Assurez-vous que c'est l'URL correcte de votre projet

define('NEWS_API_KEY', 'ebd75279445d4726800d7a0376165dd7');
define('NEWS_API_URL', 'https://newsapi.org/v2/top-headlines');

// ********************************************************************
// NOUVELLE LIGNE À AJOUTER OU MODIFIER : CHEMIN RELATIF POUR LES UPLOADS
// ********************************************************************
define('UPLOAD_URL_RELATIVE', 'uploads/products/'); // Chemin relatif à BASE_URL pour accéder aux images

define('ADMIN_EMAIL', 'admin@yourstore.com');       // Email pour les notifications admin

// Chargement de l'autoload de Composer (pour PHPMailer)
// Assurez-vous que ce chemin est correct par rapport à l'emplacement de config.php
// Si config.php est à la racine, c'est './vendor/autoload.php'
// Si config.php est dans un sous-dossier (ex: 'includes/'), ce serait '../vendor/autoload.php'
require_once __DIR__ . '/vendor/autoload.php';

// Connexion à la base de données avec PDO
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,   // Active le mode d'erreur pour les exceptions PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,    // Définit le mode de récupération par défaut (tableaux associatifs)
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion PDO : " . $e->getMessage()); // Enregistre l'erreur dans les logs du serveur
    // En production, affichez un message générique pour l'utilisateur
    die("Désolé, une erreur est survenue lors de la connexion à la base de données.");
}

// ======================================================================
// --- DÉBUT : Configuration des chemins pour les uploads ---
// ======================================================================

// Définit le chemin absolu vers la racine du projet
// Supposons que config.php est à la racine ou dans un dossier directement sous la racine (ex: 'includes', 'admin')
// Si config.php est à la racine (ex: /var/www/html/my_project/config.php), utilisez __DIR__
// Si config.php est dans /var/www/html/my_project/includes/config.php, ce serait '../vendor/autoload.php' pour Composer
// et pour ROOT_PATH, ça serait dirname(__DIR__)
// Ajustez selon votre structure :
define('ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR); // Si config.php est à la racine de votre projet
// define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR); // Si config.php est dans un sous-dossier (ex: 'includes')

// Définit le répertoire où les images des produits seront stockées (chemin absolu sur le serveur)
// Ce chemin sera : [ROOT_PATH]/uploads/products/
define('UPLOAD_DIR', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR);

// Vérifier si le dossier d'upload existe et est accessible en écriture
if (!is_dir(UPLOAD_DIR)) {
    // Tenter de créer le répertoire récursivement avec des permissions 0775
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        error_log("CRITICAL ERROR: Failed to create upload directory: " . UPLOAD_DIR);
        // Vous pouvez choisir de stopper l'exécution ici si l'upload est essentiel
        // die("Server configuration error: Upload directory cannot be created. Please contact support.");
    }
}
// Vérifier que le répertoire est inscriptible
if (!is_writable(UPLOAD_DIR)) {
    error_log("CRITICAL ERROR: Upload directory is not writable: " . UPLOAD_DIR);
    // die("Server configuration error: Upload directory is not writable. Please contact support.");
}

// ======================================================================
// --- FIN : Configuration des chemins pour les uploads ---
// ======================================================================


// ======================================================================
// --- NOUVEAU BLOC : Logique de gestion de la langue ---
// ======================================================================

// Priorité 2: Langue stockée dans la base de données pour l'utilisateur connecté
// Cette partie s'exécute APRÈS la connexion PDO et suppose que $_SESSION['user']['id'] est défini
// (ce qui se fait après l'authentification de l'utilisateur).
// Elle met à jour la langue de la session si une préférence est trouvée en DB.
if (isset($_SESSION['user']['id']) && $pdo) {
    try {
        $stmt_lang = $pdo->prepare("SELECT language_preference FROM users WHERE id = :user_id");
        $stmt_lang->execute([':user_id' => $_SESSION['user']['id']]);
        $db_lang = $stmt_lang->fetchColumn();
        if ($db_lang) {
            $current_language_code = $db_lang;
            $_SESSION['user_language'] = $db_lang; // Met à jour la session avec la DB
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la langue de l'utilisateur depuis la DB: " . $e->getMessage());
        // En cas d'erreur DB, on continue avec la langue par défaut ou celle de la session
    }
}
// Fonction d'aide pour les traductions
// S'assure que la fonction est définie une seule fois
if (!function_exists('__')) {
    function __($key) {
        global $lang; // Permet d'accéder au tableau $lang défini globalement
        return isset($lang[$key]) ? $lang[$key] : '[' . $key . ']'; // Retourne la clé entre crochets si la traduction manque
    }
}
// ======================================================================
// --- FIN DU NOUVEAU BLOC LANGUE ---
// ======================================================================


// ======================================================================
// --- AJOUT : Fonctions de gestion des messages flash ---
// ======================================================================

/**
 * Définit un message flash en session.
 * @param string $type Le type de message (ex: 'success', 'danger', 'info', 'warning').
 * @param string $message Le texte du message à afficher.
 */
if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $type, string $message): void {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

/**
 * Récupère le message flash de la session et le supprime.
 * @return array|null Un tableau contenant 'type' et 'message', ou null si aucun message.
 */
if (!function_exists('getFlashMessage')) {
    function getFlashMessage(): ?array {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']); // Supprime le message après l'avoir récupéré
            return $message;
        }
        return null;
    }
}

// ======================================================================
// --- FIN DE L'AJOUT DES FONCTIONS FLASH ---
// ======================================================================


// Fonction pour la redirection HTTP ou JavaScript
function redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        // Fallback JavaScript si les en-têtes ont déjà été envoyés (moins propre mais utile)
        echo "<script>window.location.href='" . $url . "';</script>";
        exit();
    }
}

// Fonction pour nettoyer et sécuriser les entrées utilisateur
function sanitize(string $data): string {
    // Supprime les espaces en début et fin, supprime les balises HTML, échappe les caractères spéciaux
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Fonction pour vérifier si un utilisateur est connecté
function isLoggedIn(): bool {
    // Vérifie si la clé 'user' et 'id' existent dans la session
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

// Fonction pour vérifier le rôle de l'utilisateur (utilisée pour l'accès aux pages admin)
function checkAdminRole(): void {
    // S'assure que la session est démarrée (même si elle devrait déjà l'être)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Vérifie si l'utilisateur est connecté et si son rôle est 'admin'
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        // Stocke un message d'erreur et redirige vers la page de connexion
        // UTILISATION DE setFlashMessage() ici
        setFlashMessage('danger', __('unauthorized_access_admin'));
        redirect(BASE_URL . 'login.php');
        exit();
    }
}

// Vérification et initialisation du panier en session
// S'assure que la variable de session pour le panier existe
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fonctions pour la protection CSRF (Cross-Site Request Forgery)
// Génère un token CSRF et le stocke en session si il n'existe pas déjà
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un token aléatoire et cryptographiquement sûr
        error_log("CSRF Debug (generate): New token generated: " . $_SESSION['csrf_token']); // Débogage
    }
    return $_SESSION['csrf_token'];
}

// Vérifie un token CSRF soumis par l'utilisateur
function verifyCsrfToken(string $token): bool {
    if (!isset($_SESSION['csrf_token'])) {
        // Le token n'existe pas en session (peut-être session expirée, ou jamais généré)
        error_log("CSRF Error (verify): No token in session. Submitted token: " . ($token ?? 'NULL')); // Débogage
        return false;
    }

    $storedToken = $_SESSION['csrf_token']; // Récupère le token stocké en session

    // Utilise hash_equals pour une comparaison sécurisée contre les attaques de temporisation
    $is_valid = hash_equals($storedToken, $token);

    if (!$is_valid) {
        // Si les tokens ne correspondent pas
        error_log("CSRF Error (verify): Token mismatch. Submitted: '{$token}', Stored: '{$storedToken}'"); // Débogage
    } else {
        // Si les tokens correspondent, C'EST LA LIGNE CRUCIALE pour les requêtes AJAX.
        // NE PAS UNSET($_SESSION['csrf_token']) ici pour les requêtes AJAX.
        // Le token doit rester valide pour les requêtes AJAX successives provenant de la même page.
        // Si c'était pour un formulaire HTML POST classique (qui recharge la page), vous pourriez le faire ici :
        // unset($_SESSION['csrf_token']); // Décommentez si vous voulez un token à usage unique pour les FORMULAIRES NON-AJAX.
        error_log("CSRF Debug (verify): Token matched successfully. Token REMAINS in session (suitable for AJAX)."); // Débogage
    }

    return $is_valid;
}

// Import des classes PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fonction pour envoyer un email avec PHPMailer
function sendEmail(string $to, string $subject, string $body): bool {
    $mail = new PHPMailer(true);
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Utilisez ENCRYPTION_SMTPS pour le port 465
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8'; // S'assurer que les caractères spéciaux sont bien encodés

        // Destinataires et expéditeurs
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to); // Ajoute un destinataire

        // Contenu de l'email
        $mail->isHTML(true); // Définit le format de l'email à HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        // $mail->AltBody = strip_tags($body); // Corps en texte brut pour les clients non-HTML

        $mail->send(); // Envoi de l'email
        return true;
    } catch (Exception $e) {
        // Enregistre l'erreur dans les logs du serveur
        error_log("Erreur d'envoi d'email via PHPMailer : " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Formate une chaîne de date en un format lisible.
 * @param string|null $dateString La chaîne de date à formater.
 * @param string $format Le format de sortie désiré (par défaut 'Y-m-d H:i:s').
 * @return string La date formatée, ou une chaîne vide si la date est vide ou invalide.
 */
function formatDate(?string $dateString, string $format = 'Y-m-d H:i:s'): string {
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        $dateTime = new DateTime($dateString);
        return $dateTime->format($format);
    } catch (Exception $e) {
        error_log("Erreur de formatage de date : " . $e->getMessage() . " pour la date: " . $dateString);
        return '';
    }
}

// Définir le fuseau horaire (si ce n'est pas déjà fait ailleurs)
date_default_timezone_set('Africa/Lome'); // Ou tout autre fuseau horaire approprié pour votre localisation

// Message de débogage pour confirmer le chargement de config.php
error_log("DEBUG: config.php a été chargé et exécuté avec succès.");