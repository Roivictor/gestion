<?php
// employee/change_password.php
// Ce fichier est inclus par employee/dashboard.php

// S'assurer que $pdo est disponible et que l'utilisateur est connecté.
// La vérification de rôle est déjà faite dans dashboard.php.
if (!isset($pdo) || !isset($_SESSION['user_id'])) {
    error_log("Access denied or PDO not available in employee/change_password.php");
    // Redirection de secours si l'accès n'est pas correct
    if (function_exists('redirect') && defined('BASE_URL')) {
        $_SESSION['error_message'] = __('access_denied');
        redirect(BASE_URL . 'login.php');
    } else {
        header("Location: /login.php");
    }
    exit();
}

$currentUser = $_SESSION['user']; // Informations de l'utilisateur connecté
$user_id = $currentUser['id'];

$success_message = '';
$error_message = '';
$is_first_login = (bool)($currentUser['is_first_login'] ?? false);

// Traitement du formulaire de changement de mot de passe (si soumis)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // 1. Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_message = __('csrf_token_invalid');
    } else {
        // 2. Nettoyage et récupération des données du formulaire
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_new_password'] ?? '';

        // 3. Validation des entrées
        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $error_message = __('all_password_fields_required');
        } elseif ($new_password !== $confirm_new_password) {
            $error_message = __('new_password_mismatch');
        } elseif (strlen($new_password) < 8) { // Exemple: Le mot de passe doit avoir au moins 8 caractères
            $error_message = __('password_too_short');
        }
        // Vous pouvez ajouter d'autres validations de complexité ici (majuscules, chiffres, caractères spéciaux)
        // elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $new_password)) {
        //     $error_message = __('password_complexity_required');
        // }
        else {
            try {
                // 4. Récupérer le mot de passe haché actuel de l'utilisateur depuis la base de données
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
                $stmt->execute([':id' => $user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && function_exists('password_verify')) { // Assurez-vous que password_verify existe
                    // 5. Vérifier si l'ancien mot de passe fourni correspond au mot de passe haché stocké
                    if (password_verify($current_password, $user['password'])) {
                        // 6. Hacher le nouveau mot de passe
                        if (function_exists('password_hash')) { // Assurez-vous que password_hash existe
                            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

                            // 7. Mettre à jour le mot de passe dans la base de données
                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET password = :new_password_hash,
                                    is_first_login = 0  -- Réinitialiser le flag de première connexion
                                WHERE id = :id
                            ");
                            $stmt->execute([
                                ':new_password_hash' => $hashed_new_password,
                                ':id' => $user_id
                            ]);

                            // 8. Succès de la mise à jour
                            $success_message = __('password_changed_success');
                            $_SESSION['user']['is_first_login'] = 0; // Mettre à jour la session
                            $is_first_login = false; // Mettre à jour la variable locale

                            // Rediriger vers le tableau de bord standard si c'était la première connexion
                            if (isset($_GET['page']) && $_GET['page'] === 'change_password_first_login' && function_exists('redirect') && defined('BASE_URL')) {
                                redirect(BASE_URL . 'employee/dashboard.php?page=overview');
                                exit();
                            }

                        } else {
                            $error_message = "Erreur: La fonction password_hash() n'est pas disponible.";
                        }
                    } else {
                        $error_message = __('current_password_incorrect');
                    }
                } else {
                    $error_message = "Erreur: Informations utilisateur introuvables ou fonction password_verify() manquante.";
                }

            } catch (PDOException $e) {
                $error_message = __('password_change_error') . " : " . $e->getMessage();
                error_log("PDO Error updating password: " . $e->getMessage());
            }
        }
    }
}

// Générer un nouveau jeton CSRF pour le formulaire
$csrf_token = function_exists('generateCsrfToken') ? generateCsrfToken() : '';

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php
        // Affiche un titre différent si c'est la première connexion
        if ($is_first_login) {
            echo __('change_password_first_login_title');
        } else {
            echo __('change_password_title');
        }
        ?>
    </h1>
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

<?php if ($is_first_login): ?>
    <div class="alert alert-warning" role="alert">
        <strong><?= __('important') ?>:</strong> <?= __('first_login_password_change_prompt') ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <?= __('password_change_form_title') ?>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="change_password" value="1"> <div class="mb-3">
                <label for="current_password" class="form-label"><?= __('current_password') ?> :</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label"><?= __('new_password') ?> :</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
                <div class="form-text"><?= __('password_requirements') ?></div>
            </div>

            <div class="mb-3">
                <label for="confirm_new_password" class="form-label"><?= __('confirm_new_password') ?> :</label>
                <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-key"></i> <?= __('change_password_button') ?>
            </button>
        </form>
    </div>
</div>