<?php
require_once 'config.php';

// Vérifier si l'utilisateur est connecté et doit changer son mot de passe
if (!isLoggedIn() || !$_SESSION['must_change_password']) {
    redirect('login.php');
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = sanitize($_POST['current_password']);
    $new_password = sanitize($_POST['new_password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    
    // Validation
    if (empty($current_password)) $errors[] = "Le mot de passe actuel est requis.";
    if (empty($new_password)) $errors[] = "Le nouveau mot de passe est requis.";
    if ($new_password !== $confirm_password) $errors[] = "Les nouveaux mots de passe ne correspondent pas.";
    
    if (empty($errors)) {
        try {
            // Vérifier le mot de passe actuel
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Mettre à jour le mot de passe
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = FALSE WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                // Mettre à jour la session
                $_SESSION['must_change_password'] = false;
                
                $success = "Mot de passe changé avec succès!";
                
                // Rediriger en fonction du rôle
                if ($_SESSION['user_role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } elseif ($_SESSION['user_role'] === 'employee') {
                    redirect('employee/dashboard.php');
                } else {
                    redirect('index.php');
                }
            } else {
                $errors[] = "Le mot de passe actuel est incorrect.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur lors du changement de mot de passe: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Changer le mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($errors): ?>
                            <div class="alert alert-danger">
                                <ul>
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= $error ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mot de passe actuel</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Changer le mot de passe</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>