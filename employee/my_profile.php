<?php
// employee/my_profile.php
// Ce fichier est inclus par employee/dashboard.php

// S'assurer que $pdo est disponible (via config.php) et que l'utilisateur est connecté.
// La vérification de rôle est déjà faite dans dashboard.php.
if (!isset($pdo) || !isset($_SESSION['user_id']) || !isset($_SESSION['user']['role'])) {
    error_log("Access denied or PDO not available in employee/my_profile.php");
    echo '<div class="alert alert-danger" role="alert">Erreur d\'accès ou de configuration.</div>';
    return;
}

$currentUser = $_SESSION['user']; // Les infos de l'utilisateur sont déjà chargées en session dans dashboard.php
$employee_id = $currentUser['id']; // L'ID de l'employé connecté

$success_message = '';
$error_message = '';

// Traitement du formulaire de mise à jour du profil (si soumis)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Vérification du jeton CSRF (TRÈS IMPORTANT POUR LA SÉCURITÉ)
    if (!isset($_POST['csrf_token']) || !function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_message = __('csrf_token_invalid');
    } else {
        // Nettoyage des données soumises
        $first_name = function_exists('sanitize') ? sanitize($_POST['first_name']) : trim($_POST['first_name']);
        $last_name = function_exists('sanitize') ? sanitize($_POST['last_name']) : trim($_POST['last_name']);
        $email = function_exists('sanitize') ? sanitize($_POST['email']) : trim($_POST['email']);
        $phone = function_exists('sanitize') ? sanitize($_POST['phone']) : trim($_POST['phone']);
        $address = function_exists('sanitize') ? sanitize($_POST['address']) : trim($_POST['address']);

        // Validation simple (ajoutez des validations plus robustes si nécessaire)
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = __('all_fields_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = __('invalid_email_format');
        } else {
            try {
                // Mettre à jour les informations de l'employé dans la base de données
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        address = :address
                    WHERE id = :id AND role = 'employee'
                ");
                $stmt->execute([
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':id' => $employee_id
                ]);

                if ($stmt->rowCount() > 0) {
                    $success_message = __('profile_updated_success');
                    // Mettre à jour les informations en session pour qu'elles soient à jour immédiatement
                    $_SESSION['user']['first_name'] = $first_name;
                    $_SESSION['user']['last_name'] = $last_name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['address'] = $address;
                    $currentUser = $_SESSION['user']; // Mettre à jour la variable locale
                } else {
                    $error_message = __('no_changes_made_or_error');
                }

            } catch (PDOException $e) {
                $error_message = __('profile_update_error') . " : " . $e->getMessage();
                error_log("PDO Error updating employee profile: " . $e->getMessage());
            }
        }
    }
}

// Générer un nouveau jeton CSRF pour le formulaire
$csrf_token = function_exists('generateCsrfToken') ? generateCsrfToken() : '';

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('my_profile_title') ?></h1>
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

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <?= __('profile_information') ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="mb-3">
                <label for="username" class="form-label"><?= __('username') ?> :</label>
                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" disabled>
                <div class="form-text"><?= __('username_cannot_be_changed') ?></div>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label"><?= __('role') ?> :</label>
                <input type="text" class="form-control" id="role" value="<?= htmlspecialchars(ucfirst($currentUser['role'] ?? '')) ?>" disabled>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label"><?= __('first_name') ?> :</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label"><?= __('last_name') ?> :</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label"><?= __('email') ?> :</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label"><?= __('phone') ?> :</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="address" class="form-label"><?= __('address') ?> :</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="bi bi-save"></i> <?= __('save_changes') ?>
            </button>
            <a href="<?= BASE_URL ?>employee/dashboard.php?page=change_password" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-key"></i> <?= __('change_password_link') ?>
            </a>
        </form>
    </div>
</div>