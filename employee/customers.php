<?php
// employee/customers.php
// Ce fichier est inclus par employee/dashboard.php.
// $pdo et les fonctions sont disponibles.

$customers = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, username, email, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération des clients (employee/customers.php): " . $e->getMessage());
    $_SESSION['error_message'] = __('error_loading_customers');
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('customer_list_title') ?></h1>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $_SESSION['error_message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th><?= __('customer_id') ?></th>
                <th><?= __('name') ?></th>
                <th><?= __('username') ?></th>
                <th><?= __('email_address') ?></th>
                <th><?= __('registration_date') ?></th>
                <th><?= __('actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="6" class="text-center"><?= __('no_customers_found') ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= htmlspecialchars($customer['id']) ?></td>
                    <td><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                    <td><?= htmlspecialchars($customer['username']) ?></td>
                    <td><?= htmlspecialchars($customer['email']) ?></td>
                    <td><?= formatDate($customer['created_at'], 'd/m/Y H:i') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>employee/dashboard.php?page=customer_details&id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-info" title="<?= __('view_details') ?>">
                            <i class="bi bi-eye"></i>
                        </a>
                        </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>