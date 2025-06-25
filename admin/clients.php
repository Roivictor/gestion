<?php
// admin/clients.php (Ceci est le fichier qui est INCLUS dans dashboard.php)
// Il n'a PAS besoin de require_once '../config.php'; car dashboard.php l'a déjà fait.
// Il n'a PAS besoin de checkAdminRole(); car dashboard.php l'a déjà fait.

// Vérifier si la variable $pdo (connexion à la base de données) est disponible
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur: La connexion PDO n'est pas disponible dans clients.php");
    $_SESSION['error_message'] = "Erreur interne: Impossible d'accéder à la base de données.";
    $clients = []; // Assurez-vous que $clients est toujours un tableau
    $total_clients_display = 0;
} else {
    // --- Logique pour la suppression d'un client ---
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        // L'ID passé est maintenant l'ID de l'utilisateur (user.id), pas nécessairement customer.id
        $user_id_to_delete = (int)$_GET['id'];
        $csrf_token = $_GET['csrf_token'] ?? '';

        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($csrf_token)) {
            $_SESSION['error_message'] = "Erreur de sécurité : token CSRF invalide.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Supprimer l'entrée du client de la table 'customers' si elle existe
                $stmt_customer = $pdo->prepare("DELETE FROM customers WHERE user_id = :user_id");
                $stmt_customer->execute([':user_id' => $user_id_to_delete]);

                // 2. Supprimer l'utilisateur associé de la table 'users'
                // S'assurer que le rôle est 'customer' pour éviter la suppression d'admins/employés
                $stmt_user = $pdo->prepare("DELETE FROM users WHERE id = :user_id AND role = 'customer'"); // MODIFIÉ ICI: 'client' -> 'customer'
                $stmt_user->execute([':user_id' => $user_id_to_delete]);

                if ($stmt_user->rowCount() > 0 || $stmt_customer->rowCount() > 0) {
                    $_SESSION['success_message'] = "Client et informations associées supprimés avec succès.";
                } else {
                    $_SESSION['error_message'] = "Erreur: Client non trouvé ou ne peut pas être supprimé (rôle non 'customer').";
                }

                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erreur de suppression client: " . $e->getMessage());
                $_SESSION['error_message'] = "Erreur lors de la suppression du client : " . $e->getMessage();
            }
        }
        redirect(BASE_URL . 'admin/dashboard.php?page=clients');
        exit();
    }

    // --- Récupération de la liste des clients ---
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $clients = []; // Initialisation
    $total_clients_display = 0;

    try {
        // MODIFICATION ICI: Utilisation de LEFT JOIN et rôle 'customer'
        $sql = "SELECT u.id AS user_id, u.first_name, u.last_name, u.email, u.created_at,
                       c.id AS customer_detail_id, c.phone, c.address
                FROM users u
                LEFT JOIN customers c ON u.id = c.user_id
                WHERE u.role = 'customer'"; // MODIFIÉ ICI: 'client' -> 'customer'
        $params = [];

        if (!empty($search)) {
            // La recherche inclura les champs de 'users' et 'customers' (même si NULL pour certains)
            $sql .= " AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.email LIKE :search OR c.phone LIKE :search OR c.address LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY u.last_name, u.first_name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_clients_display = count($clients);

    } catch (PDOException $e) {
        error_log("Erreur de récupération des clients (clients.php): " . $e->getMessage());
        // Laissez le message d'erreur PDO détaillé pour le moment pour le débogage
        $_SESSION['error_message'] = "Erreur lors du chargement des clients: " . $e->getMessage();
        $clients = [];
        $total_clients_display = 0;
    }
}

// Générer un token CSRF (pour les actions comme la suppression)
$csrfToken = '';
if (function_exists('generateCsrfToken')) {
    $csrfToken = generateCsrfToken();
} else {
    error_log("Fonction generateCsrfToken non définie dans clients.php.");
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des clients</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="badge bg-secondary me-3">Total clients : <?= $total_clients_display ?></span>
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
            <input type="hidden" name="page" value="clients">
            <div class="col-md-8">
                <label for="search" class="form-label">Recherche par Nom, Email, Téléphone, Adresse</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search"></i> Rechercher
                </button>
                <a href="dashboard.php?page=clients" class="btn btn-outline-secondary">
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
                        <th>ID (Utilisateur)</th>
                        <th>Nom Complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Adresse</th>
                        <th>Date d'inscription (Utilisateur)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Aucun client trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['user_id']) ?></td>
                            <td><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></td>
                            <td><?= htmlspecialchars($client['email']) ?></td>
                            <td><?= htmlspecialchars($client['phone'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($client['address'] ?: 'N/A') ?></td>
                            <td><?= formatDate($client['created_at'], 'd/m/Y H:i') ?></td>
                            <td>
                                <a href="dashboard.php?page=clients&action=delete&id=<?= htmlspecialchars($client['user_id']) ?>&csrf_token=<?= $csrfToken ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible et supprimera l\'utilisateur et ses détails clients associés.')"
                                    title="Supprimer">
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