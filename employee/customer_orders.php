<?php
error_log("Session User ID: " . ($_SESSION['user_id'] ?? 'Non défini'));
// OU : var_dump($_SESSION['user_id']); exit(); // Pour l'afficher directement dans le navigateur
// customer_orders.php - Gère l'affichage et les actions sur les commandes clients pour les employés

// Ces fichiers sont déjà inclus par dashboard.php, donc $pdo et les fonctions sont disponibles.
// require_once __DIR__ . '/../config.php';
// require_once __DIR__ . '/../includes/functions.php';

// Le dashboard.php a déjà vérifié l'authentification et le rôle 'employee'.

$message = '';
$message_type = '';

// --- DÉFINITION GLOBALE DES STATUTS VALIDES ---
// Il est important de définir cela ici aussi, comme dans orders.php de l'admin.
$valid_statuses = [
    'pending' => 'En attente',
    'processing' => 'En traitement',
    'shipped' => 'Expédiée',
    'delivered' => 'Livrée',
    'cancelled' => 'Annulée'
];


// --- Logique de mise à jour du statut d'une commande (si les employés peuvent le faire) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('error', "Erreur de sécurité : CSRF invalide.");
        redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
        exit();
    }

    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize($_POST['new_status']);

    // --- CORRECTION MAJEURE ICI : Récupérer l'ID de l'employé à partir de l'ID de l'utilisateur connecté ---
// L'ID de l'utilisateur est stocké dans $_SESSION['user']['id'] par dashboard.php
$logged_in_user_id = $_SESSION['user']['id'] ?? null;
    $employee_record_id = null; // Cet ID sera l'ID de la table 'employees'

    if ($logged_in_user_id) {
        try {
            // Requête pour récupérer l'ID de l'employé dans la table 'employees'
            // en utilisant le user_id de la session (qui vient de la table 'users').
            $stmt_emp = $pdo->prepare("SELECT id FROM employees WHERE user_id = :user_id LIMIT 1");
            $stmt_emp->execute([':user_id' => $logged_in_user_id]);
            $employee_data = $stmt_emp->fetch(PDO::FETCH_ASSOC);
            
            if ($employee_data) {
                $employee_record_id = $employee_data['id']; // C'est l'ID de la table 'employees' que nous voulons
            } else {
                // Ceci signifie que l'utilisateur connecté n'a pas de fiche correspondante dans la table 'employees'.
                // Cela DOIT être corrigé en ajoutant l'utilisateur (admin ou employé) à la table 'employees'.
                error_log("Utilisateur connecté (ID: {$logged_in_user_id}) n'a PAS de fiche correspondante dans la table 'employees'. Impossible de mettre à jour la commande.");
                setFlashMessage('danger', "Votre profil employé n'a pas été trouvé. Impossible de mettre à jour la commande.");
                redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
                exit(); 
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la récupération de l'ID employé : " . $e->getMessage());
            setFlashMessage('danger', "Une erreur interne est survenue lors de l'identification de l'employé.");
            redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
            exit();
        }
    } else {
        // Aucun utilisateur n'est connecté, ce qui ne devrait pas arriver si le dashboard.php est sécurisé.
        setFlashMessage('danger', "Aucun utilisateur connecté pour effectuer cette action.");
        redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
        exit();
    }


    // Utiliser la variable $valid_statuses GLOBALE
    if ($order_id && array_key_exists($new_status, $valid_statuses)) {
        try {
            // --- UTILISATION DE $employee_record_id DANS LA REQUÊTE UPDATE ---
            // C'est ici que nous utilisons l'ID provenant de la table 'employees'
            $stmt = $pdo->prepare("UPDATE orders SET status = :status, employee_id = :employee_id, updated_at = NOW() WHERE id = :id");
            if ($stmt->execute([
                ':status' => $new_status,
                ':employee_id' => $employee_record_id, // Utilise l'ID de l'EMPLOYÉ, pas l'ID de l'utilisateur
                ':id' => $order_id
            ])) {
                setFlashMessage('success', "Statut de la commande #{$order_id} mis à jour à '" . htmlspecialchars($valid_statuses[$new_status]) . "' avec succès !");
            } else {
                setFlashMessage('danger', "Erreur lors de la mise à jour du statut de la commande.");
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour du statut de commande (employé) : " . $e->getMessage());
            setFlashMessage('danger', "Une erreur interne est survenue lors de la mise à jour du statut de la commande.");
        }
    } else {
        setFlashMessage('danger', "Paramètres de mise à jour du statut invalides.");
    }
    redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
    exit();
}


// --- Récupération des commandes existantes pour l'affichage ---
$orders = [];
$filter_status = sanitize($_GET['status'] ?? 'all'); 

$sql = "
    SELECT
        o.id,
        o.order_date,
        o.total_amount,
        o.status,
        o.payment_method,
        o.payment_status,
        o.updated_at, -- AJOUTÉ : updated_at pour l'affichage dans le tableau
        u_cust.first_name AS customer_first_name,
        u_cust.last_name AS customer_last_name,
        -- Correction de la jointure pour l'employé :
        -- D'abord joindre la table employees, puis la table users via employees.user_id
        CASE 
            WHEN u_emp.role = 'admin' THEN 'Admin' 
            ELSE CONCAT(u_emp.first_name, ' ', u_emp.last_name) 
        END AS employee_full_name,
        -- Nous n'avons pas besoin de employee_role ici si nous utilisons CASE
        -- Mais vous l'aviez, donc je la laisse si elle est utilisée ailleurs.
        u_emp.role AS employee_role 
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN users u_cust ON c.user_id = u_cust.id
    LEFT JOIN employees emp ON o.employee_id = emp.id -- JOINTURE VERS EMPLOYEES
    LEFT JOIN users u_emp ON emp.user_id = u_emp.id    -- JOINTURE VERS USERS VIA EMPLOYEES
";

$params = [];
// Vérifier si le statut filtré est un statut valide existant dans notre liste
if ($filter_status !== 'all' && array_key_exists($filter_status, $valid_statuses)) {
    $sql .= " WHERE o.status = :status";
    $params[':status'] = $filter_status;
} else {
    $filter_status = 'all'; // Réinitialiser à "all" si le statut n'est pas valide
}

$sql .= " ORDER BY o.order_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des commandes (employé) : " . $e->getMessage());
    setFlashMessage('danger', "Impossible de charger les commandes clients.");
}

// Récupérer le message de la session s'il existe (après une redirection)
$flash_message = getFlashMessage();
if ($flash_message) {
    $message = $flash_message['message'];
    $message_type = $flash_message['type'];
}

// Générer un nouveau token CSRF pour les formulaires d'action
$csrf_token = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Commandes Clients</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center flex-wrap">
        <span class="mb-2 mb-md-0">Liste des Commandes Clients</span>
        <div class="btn-group flex-wrap flex-md-nowrap">
            <a href="dashboard.php?page=customer_orders&status=all" class="btn btn-sm btn-outline-light <?= ($filter_status === 'all' ? 'active' : '') ?>">Toutes les commandes</a>
            <?php foreach ($valid_statuses as $status_key => $status_label): ?>
                <a href="dashboard.php?page=customer_orders&status=<?= htmlspecialchars($status_key) ?>" class="btn btn-sm btn-outline-<?= ($status_key === 'pending' ? 'warning' : ($status_key === 'processing' ? 'info' : ($status_key === 'shipped' ? 'primary' : ($status_key === 'delivered' ? 'success' : 'danger')))) ?> <?= ($filter_status === $status_key ? 'active' : '') ?>">
                    <?= htmlspecialchars($status_label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="alert alert-info" role="alert">
                Aucune commande trouvée pour ce statut.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped table-sm">
                    <thead>
                        <tr>
                            <th class="text-nowrap">ID Commande</th>
                            <th>Client</th>
                            <th class="d-none d-md-table-cell">Employé</th>
                            <th class="text-nowrap">Date</th>
                            <th class="text-nowrap">Total</th>
                            <th class="text-nowrap">Statut</th>
                            <th class="d-none d-lg-table-cell text-nowrap">Méthode de Paiement</th>
                            <th class="text-nowrap">Statut Paiement</th>
                            <th class="text-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['id']) ?></td>
                                <td><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></td>
                                <td class="d-none d-md-table-cell">
                                    <?php
                                    // Utilise la nouvelle colonne 'employee_full_name' de la requête SQL
                                    if (!empty($order['employee_full_name'])) {
                                        echo htmlspecialchars($order['employee_full_name']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><?= formatDate($order['order_date'], 'd/m/Y H:i') ?></td>
                                <td><?= htmlspecialchars(number_format($order['total_amount'], 2)) ?> €</td>
                                <td>
                                    <?php
                                    $status_badge_class = '';
                                    switch ($order['status']) {
                                        case 'pending': $status_badge_class = 'bg-warning text-dark'; break;
                                        case 'processing': $status_badge_class = 'bg-info text-white'; break;
                                        case 'shipped': $status_badge_class = 'bg-primary'; break;
                                        case 'delivered': $status_badge_class = 'bg-success'; break;
                                        case 'cancelled': $status_badge_class = 'bg-danger'; break;
                                        default: $status_badge_class = 'bg-secondary'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $status_badge_class ?>"><?= htmlspecialchars($valid_statuses[$order['status']] ?? ucfirst($order['status'])) ?></span>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars(ucfirst($order['payment_method'])) ?></td>
                                <td>
                                    <?php
                                    $payment_status_badge_class = '';
                                    switch ($order['payment_status']) {
                                        case 'pending': $payment_status_badge_class = 'bg-warning text-dark'; break;
                                        case 'paid': $payment_status_badge_class = 'bg-success'; break;
                                        case 'refunded': $payment_status_badge_class = 'bg-secondary'; break;
                                        case 'failed': $payment_status_badge_class = 'bg-danger'; break;
                                        default: $payment_status_badge_class = 'bg-light text-dark'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $payment_status_badge_class ?>"><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></span>
                                </td>
                                <td>
                                    <a href="dashboard.php?page=view_order&id=<?= $order['id'] ?>" class="btn btn-sm btn-info me-1" title="Voir les détails"><i class="bi bi-eye"></i></a>
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $order['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Statut
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?= $order['id'] ?>">
                                            <?php foreach ($valid_statuses as $status_key => $status_label): ?>
                                                <li>
                                                    <form action="" method="POST" class="dropdown-item p-0">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="new_status" value="<?= htmlspecialchars($status_key) ?>">
                                                        <button type="submit" class="btn btn-sm w-100 text-start">
                                                            <?= htmlspecialchars($status_label) ?>
                                                            <?php if ($order['status'] === $status_key): ?> <i class="bi bi-check"></i><?php endif; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>