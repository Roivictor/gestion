<?php
// employee/sales_report.php
// Ce fichier est inclus par employee/dashboard.php.

// S'assurer que $pdo est disponible (via config.php).
if (!isset($pdo)) {
    error_log("PDO object not available in employee/sales_report.php. Check config.php inclusion.");
    echo '<div class="alert alert-danger" role="alert">Une erreur interne est survenue. Veuillez contacter l\'administrateur.</div>';
    return;
}

$salesData = [
    'today' => ['total_amount' => 0, 'order_count' => 0],
    'this_week' => ['total_amount' => 0, 'order_count' => 0],
    'this_month' => ['total_amount' => 0, 'order_count' => 0],
    'this_year' => ['total_amount' => 0, 'order_count' => 0],
    'all_time' => ['total_amount' => 0, 'order_count' => 0],
    'status_counts' => []
];
$error_message = '';

try {
    // Sales for Today
    // CHANGEMENT : de date_commande à order_date
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(id) AS count FROM orders WHERE DATE(order_date) = CURDATE() AND status IN ('completed', 'processing', 'shipped')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesData['today']['total_amount'] = $result['total'] ?? 0;
    $salesData['today']['order_count'] = $result['count'] ?? 0;

    // Sales for This Week (Sunday to Saturday)
    // CHANGEMENT : de date_commande à order_date
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(id) AS count FROM orders WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('completed', 'processing', 'shipped')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesData['this_week']['total_amount'] = $result['total'] ?? 0;
    $salesData['this_week']['order_count'] = $result['count'] ?? 0;

    // Sales for This Month
    // CHANGEMENT : de date_commande à order_date
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(id) AS count FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND status IN ('completed', 'processing', 'shipped')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesData['this_month']['total_amount'] = $result['total'] ?? 0;
    $salesData['this_month']['order_count'] = $result['count'] ?? 0;

    // Sales for This Year
    // CHANGEMENT : de date_commande à order_date
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(id) AS count FROM orders WHERE YEAR(order_date) = YEAR(CURDATE()) AND status IN ('completed', 'processing', 'shipped')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesData['this_year']['total_amount'] = $result['total'] ?? 0;
    $salesData['this_year']['order_count'] = $result['count'] ?? 0;

    // All-time Sales
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(id) AS count FROM orders WHERE status IN ('completed', 'processing', 'shipped')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesData['all_time']['total_amount'] = $result['total'] ?? 0;
    $salesData['all_time']['order_count'] = $result['count'] ?? 0;

    // Order Status Counts
    $stmt = $pdo->query("SELECT status, COUNT(id) AS count FROM orders GROUP BY status");
    $statusResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusResults as $statusRow) {
        $salesData['status_counts'][$statusRow['status']] = $statusRow['count'];
    }

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données de vente : " . $e->getMessage();
    error_log("PDO Error in employee/sales_report.php: " . $e->getMessage());
}

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('sales_report_title') ?></h1>
</div>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title"><?= __('sales_today') ?></h5>
                <p class="card-text display-6"><?= number_format($salesData['today']['total_amount'], 2, ',', ' ') ?> &euro;</p>
                <p class="card-text"><?= $salesData['today']['order_count'] ?> <?= __('orders') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title"><?= __('sales_this_week') ?></h5>
                <p class="card-text display-6"><?= number_format($salesData['this_week']['total_amount'], 2, ',', ' ') ?> &euro;</p>
                <p class="card-text"><?= $salesData['this_week']['order_count'] ?> <?= __('orders') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-info text-white shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title"><?= __('sales_this_month') ?></h5>
                <p class="card-text display-6"><?= number_format($salesData['this_month']['total_amount'], 2, ',', ' ') ?> &euro;</p>
                <p class="card-text"><?= $salesData['this_month']['order_count'] ?> <?= __('orders') ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-dark shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title"><?= __('sales_this_year') ?></h5>
                <p class="card-text display-6"><?= number_format($salesData['this_year']['total_amount'], 2, ',', ' ') ?> &euro;</p>
                <p class="card-text"><?= $salesData['this_year']['order_count'] ?> <?= __('orders') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <?= __('all_time_sales') ?>
            </div>
            <div class="card-body">
                <p class="fs-4 mb-1"><strong><?= number_format($salesData['all_time']['total_amount'], 2, ',', ' ') ?> &euro;</strong></p>
                <p class="text-muted"><?= __('total_orders_processed') ?>: <?= $salesData['all_time']['order_count'] ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">
                <?= __('order_status_distribution') ?>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php
                    $status_colors = [
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'shipped' => 'primary', // Ajout du statut 'shipped' ici aussi
                        'refunded' => 'secondary', // Example if you have refunded status
                    ];
                    foreach ($salesData['status_counts'] as $status => $count): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= htmlspecialchars(ucfirst($status)) ?>
                            <span class="badge bg-<?= $status_colors[$status] ?? 'dark' ?> rounded-pill"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($salesData['status_counts'])): ?>
                    <p class="text-muted mt-3"><?= __('no_status_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <h3><?= __('advanced_reports_and_charts') ?></h3>
    <p class="text-muted"><?= __('chart_placeholder_text') ?></p>
</div>