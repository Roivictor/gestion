<?php
// orders.php - Gère l'affichage et les actions de base sur les commandes

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


// --- Logique de mise à jour du statut d'une commande (optionnel, si vous voulez le faire directement ici) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Erreur de sécurité : CSRF invalide.";
        redirect(BASE_URL . 'admin/dashboard.php?page=orders');
        exit(); // Toujours exit() après une redirection
    }

    $order_id = filter_var($_POST['order_id'], FILTER_VALIDATE_INT);
    $new_status = sanitize($_POST['new_status']);

    // Utiliser la variable $valid_statuses GLOBALE que nous venons de définir
    if ($order_id && array_key_exists($new_status, $valid_statuses)) { // Utiliser array_key_exists pour vérifier si la clé existe
        try {
            $stmt = $pdo->prepare("UPDATE orders SET status = :status WHERE id = :id");
            if ($stmt->execute([':status' => $new_status, ':id' => $order_id])) {
                $message = "Statut de la commande #{$order_id} mis à jour à '" . htmlspecialchars($valid_statuses[$new_status]) . "' avec succès !"; // Utilisation du libellé pour le message
                $message_type = "success";
            } else {
                $message = "Erreur lors de la mise à jour du statut de la commande.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour du statut de commande : " . $e->getMessage());
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
        -- Correction de la jointure pour récupérer le nom du client
        u_cust.first_name AS customer_first_name,
        u_cust.last_name AS customer_last_name,
        -- Les jointures pour l'employé restent inchangées
        u_emp.first_name AS employee_first_name,
        u_emp.last_name AS employee_last_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.id   -- <<--- CORRECTION CLÉ : D'abord joindre 'orders' à 'customers'
    JOIN users u_cust ON c.user_id = u_cust.id -- <<--- CORRECTION CLÉ : Puis joindre 'customers' à 'users'
    LEFT JOIN users u_emp ON o.employee_id = u_emp.id -- LEFT JOIN car employee_id peut être NULL
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
    error_log("Erreur PDO lors de la récupération des commandes : " . $e->getMessage());
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
                                    <?php if (!empty($order['employee_first_name'])): ?>
                                        <?= htmlspecialchars($order['employee_first_name'] . ' ' . $order['employee_last_name']) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
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