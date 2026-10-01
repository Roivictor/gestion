<?php
// admin/add_employee.php
// Ce fichier est maintenant inclus DANS dashboard.php.
// Il ne doit PAS contenir les balises <html>, <head>, <body>, ni les require_once de config.php ou checkAdminRole().
// Ces éléments sont déjà gérés par dashboard.php.

// Le Pdo et les fonctions comme sanitize, redirect, generateCsrfToken, __() sont déjà disponibles.

// Logique de traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = __('csrf_error_message');
        redirect('dashboard.php?page=add_employee');
        exit();
    }

    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $username = sanitize($_POST['username'] ?? ''); // NOUVEAU: Récupérer le nom d'utilisateur
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $position = sanitize($_POST['position'] ?? '');
    $hire_date = sanitize($_POST['hire_date'] ?? '');
    $role = 'employee'; // Rôle par défaut pour un nouvel employé

    $errors = [];

    // Validations
    if (empty($first_name)) { $errors[] = __('first_name_required'); }
    if (empty($last_name)) { $errors[] = __('last_name_required'); }
    if (empty($username)) { $errors[] = __('username_required'); } // NOUVEAU: Validation du nom d'utilisateur
    if (strlen($username) < 3) { $errors[] = __('username_min_length'); } // NOUVEAU: Longueur min nom d'utilisateur
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = __('invalid_email_format'); }
    if (empty($password)) { $errors[] = __('password_required'); }
    if (strlen($password) < 6) { $errors[] = __('password_min_length'); }
    if (empty($position)) { $errors[] = __('position_required'); }
    if (empty($hire_date)) { $errors[] = __('hire_date_required'); }

    // Vérifier si l'email existe déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = __('email_already_exists');
            }
        } catch (PDOException $e) {
            error_log("Erreur de vérification d'email (add_employee): " . $e->getMessage());
            $errors[] = __('email_check_error');
        }
    }

    // NOUVEAU: Vérifier si le nom d'utilisateur existe déjà
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                $errors[] = __('username_already_exists');
            }
        } catch (PDOException $e) {
            error_log("Erreur de vérification du nom d'utilisateur (add_employee): " . $e->getMessage());
            $errors[] = __('username_check_error');
        }
    }

    if (empty($errors)) {
        // Hacher le mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction(); // Démarre une transaction

            // 1. Insérer dans la table users (inclut maintenant 'username')
            $stmt_user = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, password, role, created_at, updated_at) VALUES (:first_name, :last_name, :username, :email, :password, :role, NOW(), NOW())");
            $stmt_user->execute([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username, // NOUVEAU
                'email' => $email,
                'password' => $hashed_password,
                'role' => $role
            ]);

            $user_id = $pdo->lastInsertId(); // Récupère l'ID de l'utilisateur nouvellement inséré

            // 2. Insérer dans la table employees (aucune modification ici)
            $stmt_employee = $pdo->prepare("INSERT INTO employees (user_id, position, hire_date) VALUES (:user_id, :position, :hire_date)");
            $stmt_employee->execute([
                'user_id' => $user_id,
                'position' => $position,
                'hire_date' => $hire_date
            ]);

            $pdo->commit(); // Confirme la transaction

            $_SESSION['success_message'] = __('employee_added_success');
            redirect('dashboard.php?page=employees');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack(); // Annule la transaction
            error_log("Erreur PDO lors de l'ajout d'un employé (add_employee - insertion multi-tables): " . $e->getMessage());
            $_SESSION['error_message'] = __('employee_add_error');
            redirect('dashboard.php?page=add_employee');
            exit();
        }
    } else {
        $_SESSION['error_message'] = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
        redirect('dashboard.php?page=add_employee');
        exit();
    }
}

// Récupérer les données du formulaire en cas d'erreur de validation
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // Nettoyer après utilisation

// Générer un token CSRF
$csrfToken = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('add_employee_title') ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php?page=employees" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> <?= __('back_to_employee_list') ?>
        </a>
    </div>
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
        <form action="dashboard.php?page=add_employee" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label"><?= __('first_name') ?></label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label"><?= __('last_name') ?></label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label"><?= __('username') ?></label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($formData['username'] ?? '') ?>" required>
                    <small class="form-text text-muted"><?= __('username_min_length_hint') ?></small>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label"><?= __('email_address') ?></label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label"><?= __('password') ?></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <small class="form-text text-muted"><?= __('password_min_length_hint') ?></small>
                </div>
                <div class="col-md-6">
                    <label for="position" class="form-label"><?= __('position') ?></label>
                    <input type="text" class="form-control" id="position" name="position" value="<?= htmlspecialchars($formData['position'] ?? '') ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="hire_date" class="form-label"><?= __('hire_date') ?></label>
                    <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?= htmlspecialchars($formData['hire_date'] ?? '') ?>" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><?= __('add_employee_button') ?></button>
        </form>
    </div>
</div>