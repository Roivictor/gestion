<?php
// employee/change_password_first_login.php
// Ce fichier est inclus par employee/dashboard.php.
// $pdo, $currentUser, et les fonctions sont disponibles.

// Cette page est spécifique au premier login forcé, donc on ne doit pas pouvoir y accéder si is_first_login est FALSE
if (!$currentUser['is_first_login']) {
    redirect(BASE_URL . 'employee/dashboard.php?page=home');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = __('csrf_error_message');
        redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
        exit();
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_id = $currentUser['id'];

    $errors = [];

    if (empty($new_password) || empty($confirm_password)) {
        $errors[] = __('password_fields_required');
    }
    if ($new_password !== $confirm_password) {
        $errors[] = __('passwords_do_not_match');
    }
    if (strlen($new_password) < 6) {
        $errors[] = __('password_min_length');
    }

    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = :password, is_first_login = FALSE, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':password' => $hashed_password, ':id' => $user_id]);

            // Mettre à jour la session pour refléter le changement
            $_SESSION['user']['is_first_login'] = false; // Important pour ne plus forcer le changement

            $_SESSION['success_message'] = __('password_changed_success');
            redirect(BASE_URL . 'employee/dashboard.php?page=home'); // Rediriger vers le dashboard après le changement
            exit();
        } catch (PDOException $e) {
            error_log("Erreur de changement de mot de passe (first_login - employee/change_password_first_login.php): " . $e->getMessage());
            $_SESSION['error_message'] = __('password_change_error');
            redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
            exit();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
        // Ne pas rediriger, rester sur la page pour afficher les erreurs
    }
}

$csrfToken = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('change_password_first_login_title') ?></h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $_SESSION['success_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <p class="text-danger"><?= __('please_change_password_first_login') ?></p>
        <form action="<?= BASE_URL ?>employee/dashboard.php?page=change_password_first_login" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="mb-3">
                <label for="new_password" class="form-label"><?= __('new_password') ?></label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
                <small class="form-text text-muted"><?= __('password_min_length_hint') ?></small>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label"><?= __('confirm_new_password') ?></label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary"><?= __('change_password_button') ?></button>
        </form>
    </div>
</div>