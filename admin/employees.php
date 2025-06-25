<?php
// admin/employees.php (Ceci est le fichier qui est INCLUS dans dashboard.php)
// Il n'a PAS besoin de require_once '../config.php'; car dashboard.php l'a déjà fait.
// Il n'a PAS besoin de checkAdminRole(); car dashboard.php l'a déjà fait.
// Les fonctions comme sanitize, redirect, generateCsrfToken, verifyCsrfToken, __ sont disponibles via config.php.

// Vérifier si la variable $pdo (connexion à la base de données) est disponible
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Si $pdo n'est pas disponible, c'est une erreur critique d'intégration.
    error_log("Erreur: La connexion PDO n'est pas disponible dans employees.php (via dashboard.php)");
    $_SESSION['error_message'] = __('db_connection_error_internal'); // Traduire ce message
    $employees = []; // Assurez-vous que $employees est toujours un tableau
} else {
    // Logique pour la suppression d'un employé
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $employee_id = (int)$_GET['id'];
        $csrf_token = $_GET['csrf_token'] ?? ''; // Récupérer le token CSRF de l'URL

        // Assurez-vous que la fonction verifyCsrfToken est disponible via config.php
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($csrf_token)) {
            error_log("Erreur CSRF: Tentative de suppression non autorisée ou fonction verifyCsrfToken non définie.");
            $_SESSION['error_message'] = __('csrf_error_message_delete'); // Traduire ce message
        } else {
            try {
                $pdo->beginTransaction();

                // Récupérer l'user_id avant de supprimer de la table 'employees'
                $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = :id");
                $stmt->execute([':id' => $employee_id]);
                $employee_data = $stmt->fetch(PDO::FETCH_ASSOC); // Utilisez PDO::FETCH_ASSOC pour les noms de colonnes

                if ($employee_data) {
                    // Supprimer l'employé de la table 'employees'
                    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = :id");
                    $stmt->execute([':id' => $employee_id]);

                    // Supprimer l'utilisateur associé de la table 'users'
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
                    $stmt->execute([':user_id' => $employee_data['user_id']]);

                    $_SESSION['success_message'] = __('employee_deleted_success'); // Traduire ce message
                } else {
                    $_SESSION['error_message'] = __('employee_not_found_or_deleted'); // Traduire ce message
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erreur de suppression employé (employees.php): " . $e->getMessage());
                $_SESSION['error_message'] = __('employee_delete_error'); // Traduire ce message
            }
        }
        // Redirige toujours vers la page des employés via le dashboard
        redirect(BASE_URL . 'admin/dashboard.php?page=employees');
        exit(); // Important pour arrêter l'exécution après la redirection
    }

    // Récupération des employés
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $employees = []; // Initialisation

    try {
        // La requête jointe utilise la table 'employees' et 'users'
        // Assurez-vous que votre base de données a une table 'employees' avec une colonne 'user_id'
        // et une table 'users' avec 'id', 'first_name', 'last_name', 'email', 'role'.
        $query = "SELECT e.id, e.position, e.hire_date, u.id as user_id, u.first_name, u.last_name, u.email, u.created_at
                  FROM employees e
                  JOIN users u ON e.user_id = u.id
                  WHERE u.role IN ('employee', 'admin')"; // Correction: Inclure aussi les admins si désiré
                                                        // Votre ancien code avait 'u.role = 'employee'', adaptez selon vos besoins.
                                                        // J'ai mis 'employee', 'admin' pour correspondre à notre discussion précédente.
        $params = [];

        if (!empty($search)) {
            $query .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR e.position LIKE :search)";
            $params[':search'] = '%' . $search . '%'; // Utilisation de bindValue pour le LIKE
        }

        $query .= " ORDER BY u.last_name, u.first_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch en tableau associatif
        
    } catch (PDOException $e) {
        error_log("Erreur de récupération des employés (employees.php): " . $e->getMessage());
        $_SESSION['error_message'] = __('employee_loading_error'); // Traduire ce message
        $employees = []; // S'assurer que $employees est vide en cas d'erreur
    }
}

// Compte le nombre total d'employés *affichés* après la recherche
$total_employees_display = count($employees);

// Générer un token CSRF (assurez-vous que generateCsrfToken() est dans config.php)
$csrfToken = '';
if (function_exists('generateCsrfToken')) {
    $csrfToken = generateCsrfToken();
} else {
    error_log("Fonction generateCsrfToken non définie dans employees.php.");
    // Vous pourriez aussi définir un message d'erreur pour l'utilisateur ici si nécessaire
}

// Les balises d'écho temporaires pour formatDate() sont retirées
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('employee_management_title') ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge bg-secondary me-3"><?= __('total_employees_display') ?> : <?= $total_employees_display ?></span>
        <a href="dashboard.php?page=add_employee" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> <?= __('add_employee_button') ?>
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

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="employees">
            <div class="col-md-8">
                <label for="search" class="form-label"><?= __('search') ?></label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> <?= __('search_button') ?>
                </button>
                <a href="dashboard.php?page=employees" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> <?= __('reset_button') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th><?= __('employee_id') ?></th>
                        <th><?= __('name') ?></th>
                        <th><?= __('email_address') ?></th>
                        <th><?= __('position') ?></th>
                        <th><?= __('hire_date') ?></th>
                        <th><?= __('creation_date_user') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="7" class="text-center"><?= __('no_employees_found') ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><?= htmlspecialchars($employee['id']) ?></td>
                            <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                            <td><?= htmlspecialchars($employee['email']) ?></td>
                            <td><?= htmlspecialchars($employee['position']) ?></td>
                            <td><?= formatDate($employee['hire_date'], 'd/m/Y') ?></td>
                            <td><?= formatDate($employee['created_at'], 'd/m/Y H:i') ?></td>
                            <td>
                                <a href="dashboard.php?page=edit_employee&id=<?= htmlspecialchars($employee['id']) ?>" class="btn btn-sm btn-outline-primary" title="<?= __('edit_button_title') ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="dashboard.php?page=employees&action=delete&id=<?= htmlspecialchars($employee['id']) ?>&csrf_token=<?= $csrfToken ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('<?= __('confirm_delete_employee') ?>')"
                                    title="<?= __('delete_button_title') ?>">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>