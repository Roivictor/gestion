<?php
// feedback.php - Gère l'affichage des feedbacks de service

// Le fichier config.php est déjà inclus par dashboard.php, donc $pdo est disponible ici.
// checkAdminRole(); // Si vous voulez une vérification de rôle spécifique pour cette page, mais dashboard.php le fait déjà.

$message = '';
$message_type = '';

// --- Logique de suppression d'un feedback ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_feedback') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Erreur de sécurité : CSRF invalide.";
        redirect(BASE_URL . 'admin/dashboard.php?page=feedback');
    }

    $feedback_id = filter_var($_POST['feedback_id'], FILTER_VALIDATE_INT);

    if ($feedback_id) {
        try {
            // CORRECTION: Changement de 'feedback' à 'service_feedbacks'
            $stmt = $pdo->prepare("DELETE FROM service_feedbacks WHERE id = :id");
            if ($stmt->execute([':id' => $feedback_id])) {
                $message = "Feedback supprimé avec succès !";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la suppression du feedback.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la suppression du feedback : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la suppression du feedback.";
            $message_type = "danger";
        }
    } else {
        $message = "ID de feedback invalide pour la suppression.";
        $message_type = "danger";
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=feedback');
}

// --- Récupération des feedbacks existants pour l'affichage ---
$feedbacks = [];
try {
    // Récupère les feedbacks avec les noms du client et de l'employé mentionné (si applicable)
    $sql = "
        SELECT
            f.id,
            f.rating,
            f.comment,
            f.feedback_date,
            f.order_id,
            u_cust.first_name AS customer_first_name,
            u_cust.last_name AS customer_last_name,
            u_emp.first_name AS employee_first_name,
            u_emp.last_name AS employee_last_name
        FROM service_feedbacks f -- CORRECTION: Changement de 'feedback' à 'service_feedbacks'
        JOIN users u_cust ON f.customer_id = u_cust.id
        LEFT JOIN users u_emp ON f.employee_mentioned = u_emp.id
        ORDER BY f.feedback_date DESC
    ";
    $stmt = $pdo->query($sql);
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des feedbacks : " . $e->getMessage());
    $message = "Impossible de charger les feedbacks.";
    $message_type = "danger";
}

// Récupérer le message de la session s'il existe (après une redirection)
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message']['text'];
    $message_type = $_SESSION['form_message']['type'];
    unset($_SESSION['form_message']); // Supprimer le message après l'avoir affiché
}

// Générer un nouveau token CSRF pour le formulaire de suppression
$csrf_token = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Feedbacks Service</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white">
        Liste des feedbacks
    </div>
    <div class="card-body">
        <?php if (empty($feedbacks)): ?>
            <div class="alert alert-info" role="alert">
                Aucun feedback de service n'a été trouvé.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Commande ID</th>
                            <th>Note</th>
                            <th>Commentaire</th>
                            <th>Employé mentionné</th>
                            <th>Date du feedback</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <tr>
                                <td><?= htmlspecialchars($feedback['id']) ?></td>
                                <td><?= htmlspecialchars($feedback['customer_first_name'] . ' ' . $feedback['customer_last_name']) ?></td>
                                <td>
                                    <?php if (!empty($feedback['order_id'])): ?>
                                        <a href="dashboard.php?page=order_details&id=<?= htmlspecialchars($feedback['order_id']) ?>">#<?= htmlspecialchars($feedback['order_id']) ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="bi bi-star-fill <?= ($i < $feedback['rating'] ? 'text-warning' : 'text-muted') ?>"></i>
                                    <?php endfor; ?>
                                    (<?= htmlspecialchars($feedback['rating']) ?>)
                                </td>
                                <td><?= nl2br(htmlspecialchars(mb_strimwidth($feedback['comment'], 0, 100, '...'))) ?></td>
                                <td>
                                    <?php if (!empty($feedback['employee_first_name'])): ?>
                                        <?= htmlspecialchars($feedback['employee_first_name'] . ' ' . $feedback['employee_last_name']) ?>
                                    <?php else: ?>
                                        Non spécifié
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($feedback['feedback_date'], 'd/m/Y H:i') ?></td>
                                <td>
                                    <form action="" method="POST" class="d-inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce feedback ? Cette action est irréversible.');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="feedback_id" value="<?= htmlspecialchars($feedback['id']) ?>">
                                        <input type="hidden" name="action" value="delete_feedback">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>