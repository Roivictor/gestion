<?php
session_start(); // Démarrage de la session, doit être la première chose

// --- PARAMÈTRES DE DÉBOGAGE (À DÉSACTIVER EN PRODUCTION) ---
// Supprimez toutes les lignes echo/var_dump/print_r qui affichent des variables de session
// ou d'autres données sensibles directement sur la page.
// Les lignes ini_set sont bonnes pour le débogage, mais à régler en production.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DES PARAMÈTRES DE DÉBOGAGE ---

require_once 'config.php';
require_once 'includes/functions.php'; // Inclut les fonctions utilitaires, notamment sanitize, setFlashMessage, generateCsrfToken, verifyCsrfToken, getFlashMessage, redirect

$page_title = "Contact - " . SITE_NAME;

// Initialisation des variables pour conserver les valeurs du formulaire en cas d'erreur
// Utilisez sanitize() uniquement si les données ne sont pas POSTées ou si vous réinitialisez les valeurs.
// Pour les valeurs initiales, pas besoin de sanitize.
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$message_content = $_POST['message'] ?? ''; // Renommé pour éviter tout conflit potentiel

// Générer le jeton CSRF pour le formulaire
// Le jeton doit être régénéré à chaque affichage du formulaire si celui-ci a été soumis (échec ou succès)
// ou s'il n'existe pas encore.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    $csrf_token_posted = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrf_token_posted)) {
        setFlashMessage('danger', 'Jeton de sécurité invalide. Veuillez réessayer.');
        redirect(BASE_URL . 'contact.php');
        exit();
    }

    // Assainissement des entrées
    // Ces variables sont maintenant sécurisées, ne pas appliquer htmlspecialchars à nouveau.
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message_content = sanitize($_POST['message'] ?? '');

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = "Le nom est requis.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email est invalide.";
    }
    if (empty($subject)) {
        $errors[] = "Le sujet est requis.";
    }
    if (empty($message_content)) {
        $errors[] = "Le message est requis.";
    }

    if (empty($errors)) {
        // Préparation du corps de l'email en HTML
        // ATTENTION : Ne pas appliquer htmlspecialchars() ici car les variables sont déjà "sanitized"
        // avec htmlspecialchars() dans la fonction sanitize().
        // nl2br pour préserver les sauts de ligne dans l'email HTML
        $mail_body = "
            <h2>Nouveau message de contact</h2>
            <p><strong>Nom:</strong> " . $name . "</p>
            <p><strong>Email:</strong> " . $email . "</p>
            <p><strong>Sujet:</strong> " . $subject . "</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br($message_content) . "</p>
        ";
        
        // Envoyer l'email (avec PHPMailer via la fonction sendEmail de config.php)
        // ADMIN_EMAIL doit être défini dans config.php
        // ATTENTION : Ne pas appliquer htmlspecialchars() sur le sujet ici non plus, il est déjà "sanitized".
        if (sendEmail(ADMIN_EMAIL, "Contact: " . $subject, $mail_body)) {
            setFlashMessage('success', "Votre message a été envoyé avec succès ! Nous vous recontacterons bientôt.");
            // Réinitialiser les champs après un envoi réussi
            $name = $email = $subject = $message_content = ''; // Vide les champs du formulaire après succès
        } else {
            // sendEmail loggue déjà les erreurs détaillées
            setFlashMessage('danger', "Désolé, une erreur est survenue lors de l'envoi de votre message. Veuillez réessayer.");
        }
        redirect(BASE_URL . 'contact.php'); // Rediriger pour éviter la soumission multiple
        exit();
    } else {
        // Stocker les erreurs dans une variable de session flash pour les afficher après redirection
        // Implode convertit le tableau d'erreurs en une chaîne
        setFlashMessage('danger', implode('<br>', $errors));
        // Pas de redirection ici pour permettre à l'utilisateur de corriger le formulaire
        // Les valeurs des champs seront conservées grâce aux variables $name, $email, etc.
    }
}

// Fonction pour afficher les messages flash (si elle n'est pas déjà dans includes/functions.php)
// C'est un exemple, vous pouvez l'intégrer dans votre header/footer ou directement ici.
// Note: Le nom de la variable de session pour les messages flash est $_SESSION['flash_message'],
// pas $_SESSION['flash_messages'] comme utilisé dans la fonction displayFlashMessages ci-dessous.
// J'ai corrigé la fonction pour utiliser getFlashMessage().
if (!function_exists('displayFlashMessages')) {
    function displayFlashMessages(): void {
        $message = getFlashMessage(); // Utilise la fonction getFlashMessage pour récupérer le message unique
        if ($message) {
            echo '<div class="alert alert-' . htmlspecialchars($message['type']) . '">' . nl2br(htmlspecialchars($message['message'])) . '</div>';
        }
    }
}

// Récupération des messages flash (optionnel ici si displayFlashMessages est utilisé)
// $success_message = getFlashMessage('success');
// $error_message = getFlashMessage('danger'); 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-primary text-white">
                            <h2 class="mb-0"><i class="bi bi-envelope"></i> Contactez-nous</h2>
                        </div>
                        <div class="card-body">
                            <?php 
                            // Affiche les messages flash (succès ou erreur)
                            displayFlashMessages(); 
                            ?>
                            
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Nom complet</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Sujet</label>
                                    <input type="text" class="form-control" id="subject" name="subject" value="<?= htmlspecialchars($subject) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?= htmlspecialchars($message_content) ?></textarea>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary px-4">
                                        <i class="bi bi-send"></i> Envoyer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card shadow mt-4">
                        <div class="card-body">
                            <h3 class="mb-4"><i class="bi bi-info-circle"></i> Informations de contact</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="bi bi-geo-alt"></i> Adresse</h5>
                                    <p>123 Avenue syl olympio<br>Agoé, LOME</p>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="bi bi-telephone"></i> Téléphone</h5>
                                    <p>0022870512027</p>
                                    
                                    <h5><i class="bi bi-envelope"></i> Email</h5>
                                    <p>contact@<?= strtolower(SITE_NAME) ?>.com</p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <iframe src="https://www.google.com/maps/place/Le+PATIO/@6.1799399,1.2162319,17z/data=!3m1!4b1!4m9!3m8!1s0x1023e24010000001:0xe1b0172d89893dd6!5m2!4m1!1i2!8m2!3d6.1799399!4d1.2162319!16s%2Fg%2F11cjr5tpwr?entry=ttu&g_ep=EgoyMDI1MDYxNy4wIKXMDSoASAFQAw%3D%3D" 
                                        width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>