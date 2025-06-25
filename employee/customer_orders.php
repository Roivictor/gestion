<?php
// employee/customer_orders.php
// Ce fichier est inclus par employee/dashboard.php
// Il ne doit PAS contenir les balises <html>, <head>, <body>, etc.,
// car elles sont déjà dans dashboard.php.

// S'assurer que $pdo est disponible. Il doit l'être via config.php,
// qui est inclus par dashboard.php avant d'inclure ce fichier.
if (!isset($pdo)) {
    // Si $pdo n'est pas défini, cela indique un problème critique.
    error_log("PDO object not available in employee/customer_orders.php. Check config.php inclusion.");
    echo '<div class="alert alert-danger" role="alert">Une erreur interne est survenue. Veuillez contacter l\'administrateur.</div>';
    return; // Arrête l'exécution de ce script partiel pour éviter d'autres erreurs.
}

$orders = [];
$error_message = '';

try {
    // Requête SQL pour récupérer toutes les commandes avec les informations des clients.
    // Nous joignons la table 'orders' avec la table 'users' sur 'customer_id' pour obtenir
    // les noms, prénoms et e-mails des clients associés à chaque commande.
    //
    // IMPORTANT : Nous utilisons maintenant 'o.customer_id' pour la jointure et la sélection,
    // car c'est la colonne qui lie la commande au client.

    $stmt = $pdo->prepare("
        SELECT
            o.id AS order_id,             -- ID de la commande
            o.customer_id,                -- ID du client qui a passé la commande (CORRIGÉ ICI !)
            u.first_name,                 -- Prénom du client
            u.last_name,                  -- Nom de famille du client
            u.email AS customer_email,    -- E-mail du client
            o.total_amount,               -- Montant total de la commande
            o.status,                     -- Statut de la commande (ex: pending, processing, completed, cancelled)
            o.order_date,                 -- Date et heure de la commande (CORRIGÉ: Utilisation de 'order_date' comme dans votre schéma)
            o.payment_method              -- Méthode de paiement (ex: credit_card, bank_transfer, cash)
        FROM orders o
        JOIN users u ON o.customer_id = u.id -- <-- CORRIGÉ ICI DE 'employee_id' À 'customer_id'
        ORDER BY o.order_date DESC       -- Tri par date de commande décroissante (les plus récentes d'abord)
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // En cas d'erreur de base de données, enregistrer l'erreur et afficher un message à l'utilisateur.
    $error_message = "Erreur lors de la récupération des commandes clients : " . $e->getMessage();
    error_log("PDO Error in employee/customer_orders.php: " . $e->getMessage());
}

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('customer_orders_title') ?></h1>
    </div>

<?php
// Affichage des messages de succès ou d'erreur (provenant des sessions ou de ce script)
// Ces messages sont déjà gérés dans dashboard.php, mais peuvent être réaffichés ici si besoin
// pour des messages spécifiques à cette page.
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['error_message']);
}
?>

<?php if ($error_message): // Affichage de l'erreur spécifique à la requête des commandes ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($orders)): // Message si aucune commande n'est trouvée ?>
    <div class="alert alert-info" role="alert">
        <?= __('no_customer_orders_found') ?>
    </div>
<?php else: // Affichage du tableau des commandes ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th scope="col">ID Commande</th>
                    <th scope="col">Client</th>
                    <th scope="col">Email Client</th>
                    <th scope="col">Montant Total</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Date Commande</th>
                    <th scope="col">Méthode Paiement</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                        <td><?= htmlspecialchars($order['customer_email']) ?></td>
                        <td><?= number_format($order['total_amount'], 2, ',', ' ') ?> &euro;</td>
                        <td>
                            <?php
                            // Attribution de classes Bootstrap pour un badge de statut visuel
                            $status_badge_class = '';
                            switch ($order['status']) {
                                case 'pending':    $status_badge_class = 'bg-warning text-dark'; break; // En attente
                                case 'processing': $status_badge_class = 'bg-info text-white'; break;  // En cours de traitement
                                case 'completed':  $status_badge_class = 'bg-success'; break;          // Terminée
                                case 'cancelled':  $status_badge_class = 'bg-danger'; break;           // Annulée
                                case 'shipped':    $status_badge_class = 'bg-primary text-white'; break; // Ajouté pour le statut 'shipped'
                                default:           $status_badge_class = 'bg-secondary'; break;         // Autre statut
                            }
                            ?>
                            <span class="badge <?= $status_badge_class ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span>
                        </td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['order_date']))) ?></td> <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>employee/dashboard.php?page=view_order&id=<?= $order['order_id'] ?>" class="btn btn-sm btn-info text-white" title="<?= __('view_order_details') ?>">
                                <i class="bi bi-eye"></i> </a>
                            <a href="<?= BASE_URL ?>employee/dashboard.php?page=edit_order&id=<?= $order['order_id'] ?>" class="btn btn-sm btn-primary ms-1" title="<?= __('edit_order') ?>">
                                <i class="bi bi-pencil"></i> </a>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
    function confirmDelete(orderId) {
        if (confirm("Êtes-vous sûr de vouloir supprimer cette commande (ID: " + orderId + ") ? Cette action est irréversible.")) {
            // Rediriger vers un script de suppression ou soumettre un formulaire
            // Exemple: window.location.href = '<?= BASE_URL ?>employee/dashboard.php?page=delete_order&id=' + orderId;
            alert("Fonctionnalité de suppression non implémentée.");
        }
    }
</script>