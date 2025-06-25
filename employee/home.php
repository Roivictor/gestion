<?php
// employee/home.php
// Ce fichier est inclus par employee/dashboard.php.
// $pdo et $currentUser sont disponibles.

// Récupérer le nombre de commandes en attente
$pendingOrdersCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pendingOrdersCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur de récupération des commandes en attente (employee/home.php): " . $e->getMessage());
    // Vous pouvez définir un message d'erreur de session ici si vous le souhaitez
}

// Récupérer le nombre total de clients
$totalCustomers = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $totalCustomers = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur de récupération des clients (employee/home.php): " . $e->getMessage());
}

// Récupérer le nombre total de produits
$totalProducts = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur de récupération des produits (employee/home.php): " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('employee_dashboard_welcome', ['name' => htmlspecialchars($currentUser['first_name'] ?? '')]) ?></h1>
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

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-warning mb-3">
            <div class="card-header"><?= __('pending_orders') ?></div>
            <div class="card-body">
                <h5 class="card-title"><?= $pendingOrdersCount ?></h5>
                <p class="card-text"><?= __('new_orders_awaiting_processing') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-info mb-3">
            <div class="card-header"><?= __('total_customers') ?></div>
            <div class="card-body">
                <h5 class="card-title"><?= $totalCustomers ?></h5>
                <p class="card-text"><?= __('registered_customers_count') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header"><?= __('total_products') ?></div>
            <div class="card-body">
                <h5 class="card-title"><?= $totalProducts ?></h5>
                <p class="card-text"><?= __('available_products_in_stock') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><?= __('notifications') ?></div>
    <div class="card-body">
        <p class="card-text"><?= __('no_new_notifications') ?></p>
        </div>
</div>