<?php
require_once 'config.php';
$page_title = "Contact - " . SITE_NAME;

// Traitement du formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    
    // Validation simple
    $errors = [];
    if (empty($name)) $errors[] = "Le nom est requis";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (empty($subject)) $errors[] = "Le sujet est requis";
    if (empty($message)) $errors[] = "Le message est requis";
    
    if (empty($errors)) {
        // Envoyer l'email (avec PHPMailer)
        $mail_body = "
            <h2>Nouveau message de contact</h2>
            <p><strong>Nom:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Sujet:</strong> $subject</p>
            <p><strong>Message:</strong></p>
            <p>$message</p>
        ";
        
        if (sendEmail(ADMIN_EMAIL, "Contact: $subject", $mail_body)) {
            $_SESSION['success_message'] = "Votre message a été envoyé avec succès!";
            redirect('contact.php');
        } else {
            $errors[] = "Erreur lors de l'envoi du message. Veuillez réessayer.";
        }
    }
}
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
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success">
                                    <?= $_SESSION['success_message'] ?>
                                    <?php unset($_SESSION['success_message']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= $error ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Nom complet</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Sujet</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
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
                                    <p>123 Avenue syl olympio<br>Agoé,LOME</p>
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