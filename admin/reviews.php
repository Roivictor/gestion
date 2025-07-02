<?php
// reviews.php - Gère l'affichage, l'approbation et le rejet des avis sur les produits

// Le fichier config.php est déjà inclus par dashboard.php, donc $pdo est disponible ici.
// checkAdminRole(); // Si vous voulez une vérification de rôle spécifique pour cette page, mais dashboard.php le fait déjà.

$message = '';
$message_type = '';

// --- Logique de mise à jour du statut d'un avis ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['review_id'])) {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Erreur de sécurité : CSRF invalide.";
        redirect(BASE_URL . 'admin/dashboard.php?page=reviews');
    }

    $review_id = filter_var($_POST['review_id'], FILTER_VALIDATE_INT);
    $action = sanitize($_POST['action']); // 'approve' ou 'reject'
    $new_status = '';

    if ($action === 'approve') {
        $new_status = 'approved';
    } elseif ($action === 'reject') {
        $new_status = 'rejected';
    }

    if ($review_id && !empty($new_status)) {
        try {
            $stmt = $pdo->prepare("UPDATE reviews SET status = :status WHERE id = :id");
            if ($stmt->execute([':status' => $new_status, ':id' => $review_id])) {
                $message = "Avis mis à jour avec succès !";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la mise à jour de l'avis.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour de l'avis : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la mise à jour de l'avis.";
            $message_type = "danger";
        }
    } else {
        $message = "Paramètres de mise à jour invalides.";
        $message_type = "danger";
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=reviews');
}

// --- Logique de suppression d'un avis ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_review') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error_message'] = "Erreur de sécurité : CSRF invalide.";
        redirect(BASE_URL . 'admin/dashboard.php?page=reviews');
    }

    $review_id = filter_var($_POST['review_id'], FILTER_VALIDATE_INT);

    if ($review_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = :id");
            if ($stmt->execute([':id' => $review_id])) {
                $message = "Avis supprimé avec succès !";
                $message_type = "success";
            } else {
                $message = "Erreur lors de la suppression de l'avis.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la suppression de l'avis : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la suppression de l'avis.";
            $message_type = "danger";
        }
    } else {
        $message = "ID d'avis invalide pour la suppression.";
        $message_type = "danger";
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=reviews');
}

// --- Récupération des avis existants pour l'affichage ---
$reviews = [];
$filter_status = sanitize($_GET['status'] ?? 'all'); // 'all', 'pending', 'approved', 'rejected'

$sql = "
    SELECT
        r.id,
        r.rating,
        r.comment,
        r.review_date,
        r.status,
        p.name AS product_name,
        u.first_name,
        u.last_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    JOIN customers c ON r.customer_id = c.id
    JOIN users u ON c.user_id = u.id
";

$params = [];
if ($filter_status !== 'all') {
    $sql .= " WHERE r.status = :status";
    $params[':status'] = $filter_status;
}

$sql .= " ORDER BY r.review_date DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des avis : " . $e->getMessage());
    $message = "Impossible de charger les avis.";
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
    <h1 class="h2">Gestion des avis</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        Liste des avis
        <div class="btn-group">
            <a href="dashboard.php?page=reviews&status=all" class="btn btn-sm btn-outline-light <?= ($filter_status === 'all' ? 'active' : '') ?>">Tous</a>
            <a href="dashboard.php?page=reviews&status=pending" class="btn btn-sm btn-outline-warning <?= ($filter_status === 'pending' ? 'active' : '') ?>">En attente</a>
            <a href="dashboard.php?page=reviews&status=approved" class="btn btn-sm btn-outline-success <?= ($filter_status === 'approved' ? 'active' : '') ?>">Approuvés</a>
            <a href="dashboard.php?page=reviews&status=rejected" class="btn btn-sm btn-outline-danger <?= ($filter_status === 'rejected' ? 'active' : '') ?>">Rejetés</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($reviews)): ?>
            <div class="alert alert-info" role="alert">
                Aucun avis trouvé pour le statut sélectionné.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Client</th>
                            <th>Note</th>
                            <th>Commentaire</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?= htmlspecialchars($review['id']) ?></td>
                                <td><?= htmlspecialchars($review['product_name']) ?></td>
                                <td>
                                    <?php
                                    // Afficher le prénom et le nom de la table users s'ils existent, sinon "Utilisateur Inconnu"
                                    if (!empty($review['first_name']) && !empty($review['last_name'])) {
                                        echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']);
                                    } else {
                                        echo 'Utilisateur Inconnu';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <i class="bi bi-star-fill <?= ($i < $review['rating'] ? 'text-warning' : 'text-muted') ?>"></i>
                                    <?php endfor; ?>
                                    (<?= htmlspecialchars($review['rating']) ?>)
                                </td>
                                <td><?= nl2br(htmlspecialchars(html_entity_decode($review['comment'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?></td>
                                <td><?= formatDate($review['review_date'], 'd/m/Y H:i') ?></td>
                                <td>
                                    <?php
                                    $status_badge_class = '';
                                    switch ($review['status']) {
                                        case 'pending': $status_badge_class = 'bg-warning text-dark'; break;
                                        case 'approved': $status_badge_class = 'bg-success'; break;
                                        case 'rejected': $status_badge_class = 'bg-danger'; break;
                                        default: $status_badge_class = 'bg-secondary'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $status_badge_class ?>"><?= htmlspecialchars(ucfirst($review['status'])) ?></span>
                                </td>
                                <td>
                                    <?php if ($review['status'] === 'pending'): ?>
                                        <form action="" method="POST" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['id']) ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-sm btn-success" title="Approuver"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                        <form action="" method="POST" class="d-inline-block ms-1">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['id']) ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Rejeter"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary disabled" title="Déjà traité" disabled><i class="bi bi-info-circle"></i></button>
                                    <?php endif; ?>
                                    <form action="" method="POST" class="d-inline-block ms-1" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ? Cette action est irréversible.');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['id']) ?>">
                                        <input type="hidden" name="action" value="delete_review">
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