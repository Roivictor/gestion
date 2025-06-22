<?php
// admin/employees.php
require_once '../config.php'; // Chemin corrigé pour inclure config.php
checkRole(['admin']); // Vérifie si l'utilisateur a le rôle 'admin'

// Gestion des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    // Vérification du token CSRF pour les actions de suppression (bonne pratique de sécurité)
    // if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    //     $_SESSION['error_message'] = "Erreur de sécurité : token CSRF invalide.";
    //     redirect('employees.php');
    // }

    try {
        if ($action === 'delete' && $id > 0) {
            // Commencer une transaction
            $pdo->beginTransaction();
            
            // Récupérer l'user_id avant de supprimer
            $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            
            if ($employee) {
                // Supprimer l'employé
                $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                
                // Supprimer l'utilisateur associé (dans la table users)
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$employee['user_id']]);
            }
            
            // Valider la transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Employé supprimé avec succès.";
            redirect('employees.php');
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Erreur lors de la suppression: " . $e->getMessage();
        redirect('employees.php');
    }
}

// Récupérer la liste des employés
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$query = "SELECT e.*, u.first_name, u.last_name, u.email, u.created_at 
          FROM employees e 
          JOIN users u ON e.user_id = u.id 
          WHERE u.role = 'employee'"; // S'assure de ne récupérer que les utilisateurs avec le rôle 'employee'
$params = [];

if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR e.position LIKE ?)";
    $params = array_fill(0, 4, "%$search%");
}

$query .= " ORDER BY u.last_name, u.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des employés - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; // Chemin corrigé ?>
    
    <div class="container-fluid">
        <div class="row">
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des employés</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_employee.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Ajouter un employé
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success_message'] ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error_message'] ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-8">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i> Rechercher
                                </button>
                                <a href="employees.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
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
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Poste</th>
                                        <th>Date embauche</th>
                                        <th>Date création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($employees)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Aucun employé trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($employees as $employee): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($employee['id']) ?></td>
                                            <td><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                                            <td><?= htmlspecialchars($employee['email']) ?></td>
                                            <td><?= htmlspecialchars($employee['position']) ?></td>
                                            <td><?= formatDate($employee['hire_date'], 'd/m/Y') ?></td> <td><?= formatDate($employee['created_at'], 'd/m/Y H:i') ?></td> <td>
                                                <a href="edit_employee.php?id=<?= htmlspecialchars($employee['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="employees.php?action=delete&id=<?= htmlspecialchars($employee['id']) ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet employé?')">
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
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/admin.js"></script>
</body>
</html>