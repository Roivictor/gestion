<?php
// employee/my_profile.php - Corrigé et avec des logs de débogage supplémentaires

// S'assurer que $pdo est disponible (via config.php) et que l'utilisateur est connecté.
// La vérification de rôle est déjà faite dans dashboard.php.
if (!isset($pdo) || !isset($_SESSION['user_id']) || !isset($_SESSION['user']['role'])) {
    error_log("DEBUG: my_profile.php - Accès refusé ou PDO non disponible. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", Role: " . ($_SESSION['user']['role'] ?? 'N/A'));
    echo '<div class="alert alert-danger" role="alert">Erreur d\'accès ou de configuration.</div>';
    return;
}

$currentUser = $_SESSION['user']; // Les infos de l'utilisateur sont déjà chargées en session dans dashboard.php
$employee_id = $currentUser['id']; // L'ID de l'employé connecté

// Traitement du formulaire de mise à jour du profil (si soumis)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    error_log("DEBUG: my_profile.php - Formulaire de mise à jour du profil soumis (POST).");
    error_log("DEBUG: my_profile.php - Contenu de \$_POST: " . print_r($_POST, true));

    // Vérification du jeton CSRF (TRÈS IMPORTANT POUR LA SÉCURITÉ)
    // Vérifier si le token est présent ET si la fonction verifyCsrfToken existe ET si le token est valide
    if (!isset($_POST['csrf_token']) || !function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("DEBUG: my_profile.php - Erreur CSRF: Token manquant ou invalide. Submitted: " . ($_POST['csrf_token'] ?? 'N/A'));
        setFlashMessage('danger', __('csrf_token_invalid'));
        redirect(BASE_URL . 'employee/dashboard.php?page=my_profile');
        exit(); // Arrêter l'exécution après redirection
    } else {
        error_log("DEBUG: my_profile.php - CSRF Token VÉRIFIÉ avec succès.");

        // Nettoyage des données soumises
        $first_name = function_exists('sanitize') ? sanitize($_POST['first_name']) : trim($_POST['first_name']);
        $last_name = function_exists('sanitize') ? sanitize($_POST['last_name']) : trim($_POST['last_name']);
        $email = function_exists('sanitize') ? sanitize($_POST['email']) : trim($_POST['email']);
        $phone = function_exists('sanitize') ? sanitize($_POST['phone']) : trim($_POST['phone']);
        $address = function_exists('sanitize') ? sanitize($_POST['address']) : trim($_POST['address']);

        // Validation simple (ajoutez des validations plus robustes si nécessaire)
        if (empty($first_name) || empty($last_name) || empty($email)) {
            setFlashMessage('danger', __('all_fields_required'));
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('danger', __('invalid_email_format'));
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
                    setFlashMessage('success', __('profile_updated_success'));
                    // Mettre à jour les informations en session pour qu'elles soient à jour immédiatement
                    $_SESSION['user']['first_name'] = $first_name;
                    $_SESSION['user']['last_name'] = $last_name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['address'] = $address;
                    $currentUser = $_SESSION['user']; // Mettre à jour la variable locale
                    error_log("DEBUG: my_profile.php - Profil mis à jour avec succès. Session updated.");
                } else {
                    setFlashMessage('info', __('no_changes_made_or_error'));
                    error_log("DEBUG: my_profile.php - Aucune modification détectée ou erreur silencieuse.");
                }

            } catch (PDOException $e) {
                setFlashMessage('danger', __('profile_update_error') . " : " . $e->getMessage());
                error_log("ERROR: PDO Error updating employee profile: " . $e->getMessage());
            }
        }
        // Rediriger après le traitement du POST pour éviter la resoumission du formulaire
        redirect(BASE_URL . 'employee/dashboard.php?page=my_profile');
        exit(); // Arrêter l'exécution après redirection
    }
}

// Générer un nouveau jeton CSRF pour le formulaire (pour les requêtes GET)
// Ce token sera affiché dans le champ caché du formulaire
$csrf_token = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
error_log("DEBUG: my_profile.php - CSRF Token généré pour le formulaire (GET): " . $csrf_token);

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('my_profile_title') ?></h1>
</div>

<?php
// Les messages flash seront affichés par dashboard.php, donc pas besoin ici
// if ($success_message):
//     echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($success_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
// endif;
// if ($error_message):
//     echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
// endif;
?>

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