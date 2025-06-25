<?php
// categories.php - Gère l'affichage et l'ajout de catégories

// Le fichier config.php est déjà inclus par dashboard.php, donc $pdo est disponible ici.
// checkAdminRole(); // Si vous voulez une vérification de rôle spécifique pour cette page, mais dashboard.php le fait déjà.

$message = '';
$message_type = '';

// --- Logique d'ajout de catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    // 1. Vérification du token CSRF
    // Assurez-vous que verifyCsrfToken est bien défini et fonctionnel dans functions.php ou config.php
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        // Log l'erreur pour le débogage (si non déjà fait dans verifyCsrfToken)
        error_log("CSRF Error: Invalid token on category add attempt. IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error_message'] = "Erreur de sécurité : Token CSRF invalide. Veuillez réessayer.";
        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
        exit(); // Important d'arrêter l'exécution après une redirection
    }

    // 2. Nettoyage des entrées
    $category_name = sanitize($_POST['name']);
    $category_description = sanitize($_POST['description'] ?? ''); // Nouvelle colonne, optionnelle
    $parent_id = filter_var($_POST['parent_id'] ?? '', FILTER_VALIDATE_INT); // Nouvelle colonne, optionnelle

    // 3. Validation des entrées
    if (empty($category_name)) {
        $message = "Le nom de la catégorie ne peut pas être vide.";
        $message_type = "danger";
    } else {
        try {
            // 4. Vérifier si la catégorie existe déjà (par nom)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name");
            $stmt_check->execute([':name' => $category_name]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = "Une catégorie avec ce nom existe déjà.";
                $message_type = "warning";
            } else {
                // Si parent_id est fourni, vérifier s'il existe dans la table categories
                if ($parent_id === false || $parent_id <= 0) {
                    $parent_id = null; // Si invalide ou non fourni, définir à NULL pour la DB
                } else {
                    $stmt_parent_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = :parent_id");
                    $stmt_parent_check->execute([':parent_id' => $parent_id]);
                    if ($stmt_parent_check->fetchColumn() === 0) {
                        $message = "Le Parent ID spécifié n'existe pas.";
                        $message_type = "danger";
                        $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
                        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
                        exit();
                    }
                }

                // 5. Insertion dans la base de données
                $stmt_insert = $pdo->prepare("INSERT INTO categories (name, description, parent_id) VALUES (:name, :description, :parent_id)");
                if ($stmt_insert->execute([
                    ':name' => $category_name,
                    ':description' => $category_description,
                    ':parent_id' => $parent_id
                ])) {
                    $message = "Catégorie '" . htmlspecialchars($category_name) . "' ajoutée avec succès !";
                    $message_type = "success";
                } else {
                    $message = "Erreur lors de l'ajout de la catégorie.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'ajout de catégorie : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de l'ajout de la catégorie.";
            $message_type = "danger";
        }
    }
    // Stocker le message en session pour l'afficher après redirection
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=categories');
    exit(); // Important d'arrêter l'exécution après une redirection
}

// --- Récupération des catégories existantes pour l'affichage ---
$categories = [];
try {
    $stmt_fetch = $pdo->query("SELECT id, name, description, parent_id FROM categories ORDER BY name ASC");
    $categories = $stmt_fetch->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des catégories : " . $e->getMessage());
    // Gérer l'erreur, par exemple en affichant un message générique à l'utilisateur
    $message = "Impossible de charger les catégories.";
    $message_type = "danger";
}

// Récupérer le message de la session s'il existe (après une redirection)
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message']['text'];
    $message_type = $_SESSION['form_message']['type'];
    unset($_SESSION['form_message']); // Supprimer le message après l'avoir affiché
}

// Générer un nouveau token CSRF pour le formulaire
// IMPORTANT: Si dashboard.php a déjà une balise <meta name="csrf-token">
// qui appelle generateCsrfToken(), cet appel ici ne devrait PAS générer un nouveau token
// mais récupérer celui déjà en session, ce qui est le comportement attendu de generateCsrfToken().
$csrf_token = generateCsrfToken();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des catégories</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                Ajouter une nouvelle catégorie
            </div>
            <div class="card-body">
                <form action="" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>"> <div class="mb-3">
                        <label for="categoryName" class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                        <div class="invalid-feedback">
                            Veuillez entrer le nom de la catégorie.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="parentCategory" class="form-label">Catégorie parente (ID)</label>
                        <select class="form-select" id="parentCategory" name="parent_id">
                            <option value="">Aucune (Catégorie principale)</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Sélectionnez une catégorie existante si celle-ci est une sous-catégorie.</small>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter la catégorie</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                Catégories existantes
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="alert alert-info" role="alert">
                        Aucune catégorie n'a été ajoutée pour le moment.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Parent ID</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['id']) ?></td>
                                        <td><?= htmlspecialchars($category['name']) ?></td>
                                        <td><?= htmlspecialchars($category['description'] ?? 'N/A') ?></td> <td><?= htmlspecialchars($category['parent_id'] ?? 'Aucun') ?></td> <td>
                                            <a href="dashboard.php?page=edit_category&id=<?= $category['id'] ?>" class="btn btn-sm btn-warning me-1" title="Modifier"><i class="bi bi-pencil"></i></a>
                                            <a href="dashboard.php?page=delete_category&id=<?= $category['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?');"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Script de validation Bootstrap
(function () {
    'use strict'
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>