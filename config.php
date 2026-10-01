<?php
// config.php - Version sécurisée avec variables d'environnement

// ============================================================
// 1. CHARGEMENT DES VARIABLES D'ENVIRONNEMENT
// ============================================================

require_once __DIR__ . '/includes/EnvLoader.php';

try {
    EnvLoader::load(__DIR__ . '/.env');
} catch (RuntimeException $e) {
    error_log("Erreur critique .env: " . $e->getMessage());
    http_response_code(500);
    die("Erreur de configuration du serveur. Contactez l'administrateur.");
}

// ============================================================
// 2. GESTION DES ERREURS SELON L'ENVIRONNEMENT
// ============================================================

$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

if (EnvLoader::isProduction()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logsDir . '/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', $logsDir . '/php_errors.log');
}

// ============================================================
// 3. SESSION SÉCURISÉE
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '3600');
    ini_set('session.cookie_lifetime', '0');
    
    if (EnvLoader::isProduction() && !empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();
    
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// ============================================================
// 4. CONSTANTES DE CONFIGURATION
// ============================================================

// Base de données
define('DB_HOST', EnvLoader::get('DB_HOST', 'localhost'));
define('DB_USER', EnvLoader::get('DB_USER', ''));
define('DB_PASS', EnvLoader::get('DB_PASS', ''));
define('DB_NAME', EnvLoader::get('DB_NAME', ''));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

// SMTP
define('SMTP_HOST', EnvLoader::get('SMTP_HOST', ''));
define('SMTP_USER', EnvLoader::get('SMTP_USER', ''));
define('SMTP_PASS', EnvLoader::get('SMTP_PASS', ''));
define('SMTP_PORT', (int) EnvLoader::get('SMTP_PORT', 587));
define('SMTP_FROM_EMAIL', EnvLoader::get('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', EnvLoader::get('SMTP_FROM_NAME', 'Store CRM'));

// Application
define('SITE_NAME', EnvLoader::get('SITE_NAME', 'Store CRM'));
define('BASE_URL', EnvLoader::get('BASE_URL', 'http://localhost/'));
define('NEWS_API_KEY', EnvLoader::get('NEWS_API_KEY', ''));
define('NEWS_API_URL', 'https://newsapi.org/v2/top-headlines');
define('ADMIN_EMAIL', EnvLoader::get('ADMIN_EMAIL', ''));

// ============================================================
// 5. VALIDATION
// ============================================================

$requiredEnv = ['DB_HOST', 'DB_USER', 'DB_NAME'];
$missing = EnvLoader::validateRequired($requiredEnv);

if (!empty($missing)) {
    error_log("Variables d'environnement manquantes: " . implode(', ', $missing));
    if (EnvLoader::isDevelopment()) {
        die("Configuration incomplète. Variables manquantes: " . implode(', ', $missing));
    }
    die("Erreur de configuration. Contactez l'administrateur.");
}

// ============================================================
// 6. PHPMailer
// ============================================================

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// ============================================================
// 7. CONNEXION PDO
// ============================================================

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion PDO : " . $e->getMessage());
    if (EnvLoader::isDevelopment()) {
        die("Erreur DB: " . $e->getMessage());
    }
    die("Désolé, une erreur est survenue lors de la connexion à la base de données.");
}

// ============================================================
// 8. UPLOADS
// ============================================================

define('ROOT_PATH', __DIR__ . DIRECTORY_SEPARATOR);
define('UPLOADS_BASE_DIR', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOADS_BASE_URL', BASE_URL . 'uploads/');
define('UPLOAD_PRODUCTS_DIR', UPLOADS_BASE_DIR . 'products' . DIRECTORY_SEPARATOR);
define('UPLOAD_PRODUCTS_URL', UPLOADS_BASE_URL . 'products/');
define('UPLOAD_URL', UPLOAD_PRODUCTS_URL);

if (!is_dir(UPLOAD_PRODUCTS_DIR)) {
    if (!mkdir(UPLOAD_PRODUCTS_DIR, 0775, true)) {
        error_log("ERREUR: Échec création dossier upload : " . UPLOAD_PRODUCTS_DIR);
    }
}
if (!is_writable(UPLOAD_PRODUCTS_DIR)) {
    error_log("ERREUR: Dossier upload non inscriptible : " . UPLOAD_PRODUCTS_DIR);
}

// ============================================================
// 9. FONCTIONS FLASH
// ============================================================

if (!function_exists('setFlashMessage')) {
    function setFlashMessage(string $type, string $message): void {
        $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage(): ?array {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        return null;
    }
}

// ============================================================
// 10. FONCTIONS UTILITAIRES
// ============================================================

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            error_log("Warning: Headers already sent, redirecting to: " . $url);
            echo "<script>window.location.href='" . addslashes($url) . "';</script>";
            exit();
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $data): string {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user']['id']) 
            && is_numeric($_SESSION['user']['id']) 
            && $_SESSION['user']['id'] > 0;
    }
}

if (!function_exists('checkAdminRole')) {
    function checkAdminRole(): void {
        if (!isLoggedIn() 
            || !isset($_SESSION['user']['role']) 
            || $_SESSION['user']['role'] !== 'admin') {
            setFlashMessage('danger', 'Accès non autorisé. Vous devez être administrateur.');
            redirect(BASE_URL . 'login.php');
            exit();
        }
    }
}

if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            error_log("CSRF Error: Aucun jeton en session.");
            return false;
        }
        $is_valid = hash_equals($_SESSION['csrf_token'], $token);
        if (!$is_valid) {
            error_log("CSRF Error: Inadéquation du jeton.");
        }
        return $is_valid;
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail(string $to, string $subject, string $body): bool {
        if (empty(SMTP_HOST) || empty(SMTP_USER) || empty(SMTP_PASS)) {
            error_log("SMTP non configuré. Vérifiez .env");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = (SMTP_PORT == 465) 
                                ? PHPMailer::ENCRYPTION_SMTPS 
                                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erreur envoi email vers {$to} : " . $e->getMessage() 
                . " / Info: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('formatDate')) {
    function formatDate(?string $dateString, string $format = 'Y-m-d H:i:s'): string {
        if (empty($dateString) 
            || $dateString === '0000-00-00' 
            || $dateString === '0000-00-00 00:00:00') {
            return '';
        }
        try {
            $dateTime = new DateTime($dateString);
            return $dateTime->format($format);
        } catch (Exception $e) {
            error_log("Erreur formatage date : " . $e->getMessage());
            return '';
        }
    }
}

// ============================================================
// 11. PANIER
// ============================================================

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================================
// 12. FUSEAU HORAIRE
// ============================================================

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Africa/Lome');
}

// ============================================================
// 13. MARQUEUR
// ============================================================

define('CONFIG_LOADED', true);