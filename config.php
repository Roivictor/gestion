<?php
// config.php - Version Corrigée

// Assure que la session est démarrée au tout début, si elle ne l'est pas déjà
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gestion des erreurs PHP (pour le DÉBOGAGE - à désactiver en production !)
ini_set('display_errors', 1);       // Affiche les erreurs directement sur la page
ini_set('display_startup_errors', 1); // Affiche les erreurs survenues au démarrage
error_reporting(E_ALL);             // Rapporte tous les types d'erreurs

// LIGNE CORRIGÉE : Suppression de l'inclusion récursive de config.php sur lui-même.
// Si vous aviez une ligne comme 'require_once 'config.php';' ici, retirez-la.

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // VOTRE MOT DE PASSE DE BASE DE DONNÉES ICI (laisser vide si pas de mot de passe)
define('DB_NAME', 'store_crm');

// Configuration de PHPMailer (SMTP) - Remplacez par vos vraies informations SMTP
define('SMTP_HOST', 'smtp.example.com');     // Ex: 'smtp.gmail.com' ou l'hôte de votre fournisseur
define('SMTP_USER', 'your_email@example.com'); // Ex: 'monemail@gmail.com'
define('SMTP_PASS', 'your_email_password'); // Le mot de passe de votre compte email SMTP
define('SMTP_PORT', 587);                    // 587 pour STARTTLS, 465 pour SMTPS (SSL)
define('SMTP_FROM', 'noreply@yourstore.com'); // Adresse email expéditeur
define('SMTP_FROM_NAME', 'Store CRM');       // Nom expéditeur

// Autres configurations globales
define('SITE_NAME', 'Store CRM');
define('BASE_URL', 'http://localhost/u/'); // Assurez-vous que c'est l'URL correcte de votre projet
define('ADMIN_EMAIL', 'admin@yourstore.com');     // Email pour les notifications admin

// Chargement de l'autoload de Composer (pour PHPMailer)
require_once __DIR__ . '/vendor/autoload.php'; // Chemin vers le fichier autoload.php de Composer

// Connexion à la base de données avec PDO
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Active le mode d'erreur pour les exceptions PDO
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Définit le mode de récupération par défaut (tableaux associatifs)
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur de connexion PDO : " . $e->getMessage()); // Enregistre l'erreur dans les logs du serveur
    // En production, ne pas afficher le message détaillé à l'utilisateur
    die("Désolé, une erreur est survenue lors de la connexion à la base de données.");
}

// Fonction pour la redirection HTTP ou JavaScript
function redirect(string $url): void {
    if (!headers_sent()) { // Vérifie si les en-têtes HTTP n'ont pas encore été envoyés
        header("Location: " . $url); // Effectue une redirection HTTP
        exit(); // Arrête l'exécution du script après la redirection
    } else {
        // Fallback en JavaScript si les en-têtes ont déjà été envoyés
        echo "<script>window.location.href='" . $url . "';</script>";
        exit(); // Arrête l'exécution du script après la redirection JavaScript
    }
}

// Fonction pour nettoyer et sécuriser les entrées utilisateur
function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Fonction pour vérifier si un utilisateur est connecté
function isLoggedIn(): bool {
    // Vérifie si l'array 'user' existe en session et s'il contient un 'id'
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

// Fonction pour vérifier le rôle de l'utilisateur (utilisée pour l'accès aux pages admin)
function checkAdminRole(): void {
    // Si la session n'est pas démarrée, on la démarre (bien que le début du fichier le fasse déjà)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Vérifie si l'utilisateur n'est PAS connecté OU si son rôle n'est PAS 'admin'
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
        // Stocke un message d'erreur en session pour l'afficher sur la page de connexion
        $_SESSION['error_message'] = "Accès non autorisé. Veuillez vous connecter avec un compte administrateur.";
        // Redirige l'utilisateur vers la page de connexion
        redirect(BASE_URL . 'login.php'); // Utilise BASE_URL pour une URL absolue
        exit(); // Arrête l'exécution du script pour empêcher l'accès non autorisé
    }
}


// Vérification et initialisation du panier en session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fonctions pour la protection CSRF (Cross-Site Request Forgery)
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère un nouveau token aléatoire
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    // Comparaison sécurisée contre les attaques de temporisation
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Import des classes PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fonction pour envoyer un email avec PHPMailer
function sendEmail(string $to, string $subject, string $body): bool {
    $mail = new PHPMailer(true); // 'true' active les exceptions pour un meilleur débogage des erreurs SMTP
    try {
        // Configuration du serveur SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        // Utilisez PHPMailer::ENCRYPTION_SMTPS pour le port 465 (SSL)
        // Utilisez PHPMailer::ENCRYPTION_STARTTLS pour le port 587 (TLS)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Ou PHPMailer::ENCRYPTION_SMTPS
        $mail->Port       = SMTP_PORT;

        // Expéditeur et destinataire
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to); // Ajoute le destinataire principal

        // Contenu de l'email
        $mail->isHTML(true);       // Définit le format de l'email sur HTML
        $mail->Subject = $subject; // Sujet de l'email
        $mail->Body    = $body;    // Contenu HTML de l'email

        $mail->send(); // Envoie l'email
        return true;   // Retourne vrai si l'envoi réussit
    } catch (Exception $e) {
        // Enregistre l'erreur détaillée de PHPMailer dans les logs du serveur
        error_log("Erreur d'envoi d'email via PHPMailer : " . $mail->ErrorInfo);
        return false; // Retourne faux si l'envoi échoue
    }
}
?>
