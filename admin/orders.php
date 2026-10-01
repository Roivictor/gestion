<?php
// orders.php - Gère l'affichage et les actions de base sur les commandes

// Assurez-vous que ces fonctions (checkAdminRole, redirect, sanitize, verifyCsrfToken, generateCsrfToken, formatDate)
// ainsi que la connexion $pdo et BASE_URL sont définies et incluses via dashboard.php ou un fichier config.php.
// Exemple d'inclusion typique si ce n'est pas déjà fait par dashboard.php:
// require_once __DIR__ . '/../config/config.php'; // Chemin vers votre fichier de configuration
// require_once __DIR__ . '/../includes/functions.php'; // Chemin vers votre fichier de fonctions utilitaires

// Le fichier config.php est déjà inclus par dashboard.php, donc $pdo est disponible ici.
// checkAdminRole(); // Si vous voulez une vérification de rôle spécifique pour cette page, mais dashboard.php le fait déjà.

$message = '';
$message_type = '';

// --- DÉFINITION GLOBALE DES STATUTS VALIDES ---
// Cette variable doit être définie au début pour être accessible partout.
// Les clés doivent correspondre aux valeurs stockées dans la base de données.
// Les valeurs peuvent être des libellés plus lisibles pour l'interface utilisateur.
$valid_statuses = [
    'pending' => 'En attente',
    'processing' => 'En traitement',
    'shipped' => 'Expédiée',
    'delivered' => 'Livrée',
    'cancelled' => 'Annulée'
];


// --- Logique de mise à jour du statut d'une commande ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Erreur de sécurité : CSRF invalide.";
        redirect(BASE_URL . 'admin/dashboard.php?page=orders');
        exit(); // Toujours exit() après une redirection
    }

    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize($_POST['new_status']);

    // --- CORRECTION MAJEURE ICI : Récupérer l'ID de l'employé à partir de l'ID de l'utilisateur connecté ---
    // Ancienne ligne : $logged_in_user_id = $_SESSION['user_id'] ?? null; 
    // Correction : Utiliser $_SESSION['user']['id'] qui est défini par login.php
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
                $_SESSION['form_message'] = ['text' => "Votre profil administrateur/employé n'a pas été trouvé. Impossible de mettre à jour la commande.", 'type' => 'danger'];
                redirect(BASE_URL . 'admin/dashboard.php?page=orders');
                exit(); 
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la récupération de l'ID employé (admin) : " . $e->getMessage());
            $_SESSION['form_message'] = ['text' => "Une erreur interne est survenue lors de l'identification de l'employé.", 'type' => 'danger'];
            redirect(BASE_URL . 'admin/dashboard.php?page=orders');
            exit();
        }
    } else {
        // Aucun utilisateur n'est connecté, ce qui ne devrait pas arriver si le dashboard.php est sécurisé.
        $_SESSION['form_message'] = ['text' => "Aucun utilisateur connecté pour effectuer cette action.", 'type' => 'danger'];
        redirect(BASE_URL . 'admin/dashboard.php?page=orders');
        exit();
    }

    // Utiliser la variable $valid_statuses GLOBALE que nous venons de définir
    if ($order_id && array_key_exists($new_status, $valid_statuses)) { // Utiliser array_key_exists pour vérifier si la clé existe
        try {
            // --- MODIFICATION DE LA REQUÊTE : Mettre à jour 'employee_id' et 'updated_at' ---
            // On met à jour le statut, et on enregistre qui a fait la modification (employee_id)
            // et quand (updated_at).
            $stmt = $pdo->prepare("UPDATE orders SET status = :status, employee_id = :employee_id, updated_at = NOW() WHERE id = :id");
            if ($stmt->execute([
                ':status' => $new_status,
                ':employee_id' => $employee_record_id, // Utilise l'ID de l'EMPLOYÉ, pas l'ID de l'utilisateur
                ':id' => $order_id
            ])) {
                $message = "Statut de la commande #{$order_id} mis à jour à '" . htmlspecialchars($valid_statuses[$new_status]) . "' avec succès !"; // Utilisation du libellé pour le message
                $message_type = "success";
            } else {
                $message = "Erreur lors de la mise à jour du statut de la commande.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour du statut de commande (admin) : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la mise à jour du statut de la commande.";
            $message_type = "danger";
        }
    } else {
        $message = "Paramètres de mise à jour du statut invalides.";
        $message_type = "danger";
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=orders');
    exit(); // Toujours exit() après une redirection
}


// --- Récupération des commandes existantes pour l'affichage ---
$orders = [];
$filter_status = sanitize($_GET['status'] ?? 'all'); // 'all', 'pending', 'processing', 'shipped', 'delivered', 'cancelled'

$sql = "
    SELECT
        o.id,
        o.order_date,
        o.total_amount,
        o.status,
        o.payment_method,
        o.payment_status,
        o.updated_at, -- AJOUTÉ : updated_at pour l'affichage dans le tableau
        -- Jointure pour récupérer les informations du client
        u_cust.first_name AS customer_first_name,
        u_cust.last_name AS customer_last_name,
        -- Correction de la jointure pour l'employé/admin :
        -- D'abord joindre la table employees, puis la table users via employees.user_id
        CASE 
            WHEN u_emp.role = 'admin' THEN 'Admin' 
            ELSE CONCAT(u_emp.first_name, ' ', u_emp.last_name) 
        END AS employee_full_name,
        -- Nous n'avons pas besoin de employee_role ici si nous utilisons CASE, mais je le laisse si utilisé ailleurs.
        u_emp.role AS employee_role 
    FROM orders o
    JOIN customers c ON o.customer_id = c.id      -- Jointure orders -> customers
    JOIN users u_cust ON c.user_id = u_cust.id    -- Jointure customers -> users (pour le client)
    LEFT JOIN employees emp ON o.employee_id = emp.id -- JOINTURE VERS EMPLOYEES
    LEFT JOIN users u_emp ON emp.user_id = u_emp.id    -- JOINTURE VERS USERS VIA EMPLOYEES
";

$params = [];
// Vérifier si le statut filtré est un statut valide existant dans notre liste
if ($filter_status !== 'all' && array_key_exists($filter_status, $valid_statuses)) {
    $sql .= " WHERE o.status = :status";
    $params[':status'] = $filter_status;
} else {
    // Si le statut filtré n'est pas "all" et n'est pas valide, réinitialiser à "all" pour éviter une erreur SQL
    $filter_status = 'all';
}

$sql .= " ORDER BY o.order_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des commandes (admin) : " . $e->getMessage());
    $message = "Impossible de charger les commandes.";
    $message_type = "danger";
}

// Récupérer le message de la session s'il existe (après une redirection)
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message']['text'];
    $message_type = $_SESSION['form_message']['type'];
    unset($_SESSION['form_message']); // Supprimer le message après l'avoir affiché
}

// Générer un nouveau token CSRF pour les formulaires d'action
$csrf_token = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des commandes</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        Liste des commandes
        <div class="btn-group">
            <a href="dashboard.php?page=orders&status=all" class="btn btn-sm btn-outline-light <?= ($filter_status === 'all' ? 'active' : '') ?>">Toutes</a>
            <?php foreach ($valid_statuses as $status_key => $status_label): ?>
                <a href="dashboard.php?page=orders&status=<?= htmlspecialchars($status_key) ?>" class="btn btn-sm btn-outline-<?= ($status_key === 'pending' ? 'warning' : ($status_key === 'processing' ? 'info' : ($status_key === 'shipped' ? 'primary' : ($status_key === 'delivered' ? 'success' : 'danger')))) ?> <?= ($filter_status === $status_key ? 'active' : '') ?>">
                    <?= htmlspecialchars($status_label) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="alert alert-info" role="alert">
                Aucune commande trouvée pour le statut sélectionné.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>ID Commande</th>
                            <th>Client</th>
                            <th>Employé</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Méthode Paiement</th>
                            <th>Statut Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['id']) ?></td>
                                <td><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></td>
                                <td>
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
                                <td><?= htmlspecialchars(ucfirst($order['payment_method'])) ?></td>
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
                                    <a href="dashboard.php?page=order_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-info me-1" title="Voir détails"><i class="bi bi-eye"></i></a>
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $order['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Statut
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $order['id'] ?>">
                                            <?php foreach ($valid_statuses as $status_key => $status_label): // Utilise la variable $valid_statuses globale ?>
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