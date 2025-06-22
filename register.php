<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    
    // Validation
    if (empty($username)) $errors[] = "Le nom d'utilisateur est requis.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Une adresse email valide est requise.";
    if (empty($password)) $errors[] = "Le mot de passe est requis.";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas.";
    if (empty($first_name)) $errors[] = "Le prénom est requis.";
    if (empty($last_name)) $errors[] = "Le nom de famille est requis.";
    if (empty($address)) $errors[] = "L'adresse est requise.";
    if (empty($phone)) $errors[] = "Le numéro de téléphone est requis.";
    
    if (empty($errors)) {
        // Vérifier si l'utilisateur existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $errors[] = "Le nom d'utilisateur ou l'email existe déjà.";
        } else {
            // Hasher le mot de passe
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            try {
                // Commencer une transaction
                $pdo->beginTransaction();
                
                // Insérer l'utilisateur
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name]);
                $user_id = $pdo->lastInsertId();
                
                // Insérer le client
                $stmt = $pdo->prepare("INSERT INTO customers (user_id, address, phone) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $address, $phone]);
                
                // Valider la transaction
                $pdo->commit();
                
                $success = "Compte créé avec succès! Vous pouvez maintenant vous connecter.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de la création du compte: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Créer un compte</h3>
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
                            <div class="text-center">
                                <a href="login.php" class="btn btn-primary">Se connecter</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">Prénom</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Nom</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nom d'utilisateur</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Mot de passe</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">S'inscrire</button>
                            </form>
                            
                            <div class="mt-3 text-center">
                                <a href="login.php">Déjà un compte? Se connecter</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>