<?php
// C:\xampp\htdocs\u\employee\view_order.php
// Ce fichier est inclus par employee/dashboard.php.
// Il ne doit PAS contenir les balises <html>, <head>, <body>, etc.

// S'assurer que $pdo est disponible.
if (!isset($pdo)) {
    error_log("PDO object not available in employee/view_order.php.");
    echo '<div class="alert alert-danger" role="alert">Une erreur interne est survenue.</div>';
    return;
}

// Récupérer l'ID de la commande depuis l'URL
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id) {
    setFlashMessage('danger', __('invalid_order_id'));
    redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
    exit();
}

$order_details = null;
$order_items = [];
$error_message = '';

try {
    // 1. Récupérer les détails de la commande principale
    $stmt_order = $pdo->prepare("
        SELECT
            o.id AS order_id,
            o.order_date,
            o.total_amount,
            o.status,
            o.payment_method,
            o.payment_status,
            o.shipping_address,
            o.billing_address,
            u_cust.first_name AS customer_first_name,
            u_cust.last_name AS customer_last_name,
            u_cust.email AS customer_email,
            u_emp.first_name AS employee_first_name,
            u_emp.last_name AS employee_last_name
        FROM orders o
        JOIN users u_cust ON o.customer_id = u_cust.id
        LEFT JOIN users u_emp ON o.employee_id = u_emp.id
        WHERE o.id = :order_id
    ");
    $stmt_order->execute([':order_id' => $order_id]);
    $order_details = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order_details) {
        setFlashMessage('danger', __('order_not_found'));
        redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
        exit();
    }

    // 2. Récupérer les articles de la commande (products dans order_items)
    // Assurez-vous que votre table 'order_items' a des colonnes comme 'product_id', 'quantity', 'unit_price'.
    // Et que 'products' a des colonnes comme 'name'.
    $stmt_items = $pdo->prepare("
        SELECT
            oi.product_id,
            p.name AS product_name,
            oi.quantity,
            oi.unit_price,
            (oi.quantity * oi.unit_price) AS item_total
            -- Ajoutez d'autres champs si nécessaire, par ex: p.image, pv.name (pour les variantes)
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
    ");
    $stmt_items->execute([':order_id' => $order_id]);
    $order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des détails de commande : " . $e->getMessage());
    setFlashMessage('danger', __('database_error') . ': ' . $e->getMessage());
    redirect(BASE_URL . 'employee/dashboard.php?page=customer_orders');
    exit();
}

// Récupérer et afficher les messages flash (si redirection depuis une autre page)
$flash_message = getFlashMessage();
if ($flash_message) {
    echo '<div class="alert alert-' . htmlspecialchars($flash_message['type']) . ' alert-dismissible fade show mt-3" role="alert">' . htmlspecialchars($flash_message['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('order_details') ?> #<?= htmlspecialchars($order_details['order_id']) ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?= BASE_URL ?>employee/dashboard.php?page=customer_orders" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= __('back_to_orders') ?>
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white">
        <?= __('order_information') ?>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong><?= __('customer') ?>:</strong> <?= htmlspecialchars($order_details['customer_first_name'] . ' ' . $order_details['customer_last_name']) ?></p>
                <p><strong><?= __('customer_email') ?>:</strong> <?= htmlspecialchars($order_details['customer_email']) ?></p>
                <p><strong><?= __('order_date') ?>:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($order_details['order_date']))) ?></p>
                <p><strong><?= __('total_amount') ?>:</strong> <?= number_format($order_details['total_amount'], 2, ',', ' ') ?> &euro;</p>
            </div>
            <div class="col-md-6">
                <p><strong><?= __('status') ?>:</strong> <span class="badge bg-primary"><?= htmlspecialchars(__(ucfirst($order_details['status']))) ?></span></p>
                <p><strong><?= __('payment_method') ?>:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order_details['payment_method']))) ?></p>
                <p><strong><?= __('payment_status') ?>:</strong> <span class="badge bg-success"><?= htmlspecialchars(ucfirst($order_details['payment_status'])) ?></span></p>
                <p><strong><?= __('handled_by') ?>:</strong>
                    <?php if (!empty($order_details['employee_first_name'])): ?>
                        <?= htmlspecialchars($order_details['employee_first_name'] . ' ' . $order_details['employee_last_name']) ?>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <hr>
        <h5><?= __('shipping_address') ?></h5>
        <p><?= nl2br(htmlspecialchars($order_details['shipping_address'])) ?></p>
        <h5><?= __('billing_address') ?></h5>
        <p><?= nl2br(htmlspecialchars($order_details['billing_address'])) ?></p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white">
        <?= __('order_items') ?>
    </div>
    <div class="card-body">
        <?php if (empty($order_items)): ?>
            <div class="alert alert-info" role="alert">
                <?= __('no_items_found_for_order') ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><?= __('product') ?></th>
                            <th scope="col"><?= __('quantity') ?></th>
                            <th scope="col"><?= __('unit_price') ?></th>
                            <th scope="col"><?= __('item_total') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                <td><?= number_format($item['unit_price'], 2, ',', ' ') ?> &euro;</td>
                                <td><?= number_format($item['item_total'], 2, ',', ' ') ?> &euro;</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>