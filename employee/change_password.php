<?php
// Inclure le fichier de configuration qui démarre la session et configure la connexion PDO
require_once '../config.php';

// Utiliser la fonction checkRole() du config.php pour gérer l'accès
// L'admin a également accès à cette page pour changer son propre mot de passe.
checkRole(['employee', 'admin']); 

// Récupérer et vider les messages de session (pour les alertes)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Récupérer les informations de l'utilisateur pour la sidebar (si nécessaire)
// Vous pouvez réutiliser la logique de récupération du profil de profile.php ici
$employee_user_id = $_SESSION['user']['id'];
$employee_profile = ['first_name' => 'Utilisateur', 'last_name' => 'Inconnu', 'position' => 'Membre']; // Valeurs par défaut

try {
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.phone_number, u.address, u.role,
               e.position, e.hire_date
        FROM users u
        LEFT JOIN employees e ON u.id = e.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$employee_user_id]);
    $fetched_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fetched_profile) {
        $employee_profile = $fetched_profile;
    }
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération du profil pour la sidebar dans change_password.php: " . $e->getMessage());
    // Ne pas interrompre l'exécution, juste utiliser les valeurs par défaut
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le mot de passe - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/employee.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-person fs-3 text-dark"></i>
                        </div>
                        <h6 class="mt-2 text-white"><?= htmlspecialchars($employee_profile['first_name'] . ' ' . $employee_profile['last_name']) ?></h6>
                        <p class="text-muted small"><?= htmlspecialchars($employee_profile['position'] ?? 'N/A') ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="bi bi-box-seam me-2"></i>
                                Produits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="bi bi-cart me-2"></i>
                                Commandes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="bi bi-people me-2"></i>
                                Clients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person me-2"></i>
                                Profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="change_password.php">
                                <i class="bi bi-lock me-2"></i>
                                Changer mot de passe
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-light">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Changer votre mot de passe</h1>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        Formulaire de changement de mot de passe
                    </div>
                    <div class="card-body">
                        <form action="process_change_password.php" method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="form-text text-muted">Le mot de passe doit contenir au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_new_password" class="form-label">Confirmer nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                            </div>
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/employee.js"></script>
</body>
</html>