<?php
// categories.php - Gère l'affichage, l'ajout, la modification et la suppression de catégories

// Le fichier config.php est déjà inclus par dashboard.php, donc $pdo est disponible ici.
// checkAdminRole(); // Si vous voulez une vérification de rôle spécifique pour cette page, mais dashboard.php le fait déjà.

$message = '';
$message_type = '';
$edit_category_data = null; // Pour stocker les données de la catégorie à modifier

// --- Récupération des données de la catégorie à modifier (si un ID est passé en GET) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $category_id_to_edit = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($category_id_to_edit !== false && $category_id_to_edit > 0) {
        try {
            $stmt_edit = $pdo->prepare("SELECT id, name, description, parent_id FROM categories WHERE id = :id");
            $stmt_edit->execute([':id' => $category_id_to_edit]);
            $edit_category_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
            if (!$edit_category_data) {
                $_SESSION['form_message'] = ['text' => "Catégorie à modifier introuvable.", 'type' => "danger"];
                redirect(BASE_URL . 'admin/dashboard.php?page=categories');
                exit();
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la récupération de la catégorie pour modification : " . $e->getMessage());
            $_SESSION['form_message'] = ['text' => "Erreur interne lors du chargement de la catégorie.", 'type' => "danger"];
            redirect(BASE_URL . 'admin/dashboard.php?page=categories');
            exit();
        }
    } else {
        $_SESSION['form_message'] = ['text' => "ID de catégorie invalide pour la modification.", 'type' => "danger"];
        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
        exit();
    }
}


// --- Logique d'ajout de catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("CSRF Error: Invalid token on category add attempt. IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error_message'] = "Erreur de sécurité : Token CSRF invalide. Veuillez réessayer.";
        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
        exit();
    }

    // 2. Nettoyage des entrées
    $category_name = sanitize($_POST['name']);
    $category_description = sanitize($_POST['description'] ?? '');
    $parent_id = filter_var($_POST['parent_id'] ?? '', FILTER_VALIDATE_INT);

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

// --- Logique de modification de catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_category') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("CSRF Error: Invalid token on category update attempt. IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error_message'] = "Erreur de sécurité : Token CSRF invalide. Veuillez réessayer.";
        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
        exit();
    }

    // 2. Nettoyage et validation des entrées
    $category_id = filter_var($_POST['category_id'], FILTER_VALIDATE_INT);
    $category_name = sanitize($_POST['name']);
    $category_description = sanitize($_POST['description'] ?? '');
    $parent_id = filter_var($_POST['parent_id'] ?? '', FILTER_VALIDATE_INT);

    if ($category_id === false || $category_id <= 0 || empty($category_name)) {
        $message = "Paramètres de modification invalides (ID ou nom de catégorie).";
        $message_type = "danger";
    } else {
        try {
            // Vérifier si le nouveau nom existe déjà pour une autre catégorie
            $stmt_check_name = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = :name AND id != :id");
            $stmt_check_name->execute([':name' => $category_name, ':id' => $category_id]);
            if ($stmt_check_name->fetchColumn() > 0) {
                $message = "Une autre catégorie avec ce nom existe déjà.";
                $message_type = "warning";
            } else {
                // Vérifier et valider parent_id
                if ($parent_id === false || $parent_id <= 0) {
                    $parent_id = null;
                } else {
                    // Empêcher une catégorie d'être sa propre parente
                    if ($parent_id == $category_id) {
                        $message = "Une catégorie ne peut pas être sa propre parente.";
                        $message_type = "danger";
                        $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
                        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
                        exit();
                    }

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

                // Exécuter la mise à jour
                $stmt_update = $pdo->prepare("UPDATE categories SET name = :name, description = :description, parent_id = :parent_id WHERE id = :id");
                if ($stmt_update->execute([
                    ':name' => $category_name,
                    ':description' => $category_description,
                    ':parent_id' => $parent_id,
                    ':id' => $category_id
                ])) {
                    $message = "Catégorie '" . htmlspecialchars($category_name) . "' mise à jour avec succès !";
                    $message_type = "success";
                } else {
                    $message = "Erreur lors de la mise à jour de la catégorie.";
                    $message_type = "danger";
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour de catégorie : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la mise à jour de la catégorie.";
            $message_type = "danger";
        }
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=categories');
    exit();
}

// --- Logique de suppression de catégorie ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("CSRF Error: Invalid token on category delete attempt. IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error_message'] = "Erreur de sécurité : Token CSRF invalide. Veuillez réessayer.";
        redirect(BASE_URL . 'admin/dashboard.php?page=categories');
        exit();
    }

    // 2. Validation de l'ID
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    if ($category_id !== false && $category_id > 0) {
        try {
            // Vérifier s'il existe des sous-catégories
            $stmt_check_children = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = :id");
            $stmt_check_children->execute([':id' => $category_id]);
            if ($stmt_check_children->fetchColumn() > 0) {
                $message = "Impossible de supprimer la catégorie car elle contient des sous-catégories. Veuillez d'abord supprimer ou déplacer les sous-catégories.";
                $message_type = "danger";
            } else {
                // Vérifier s'il existe des produits associés à cette catégorie
                // Assurez-vous que votre table 'products' a bien une colonne 'category_id'
                $stmt_check_products = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
                $stmt_check_products->execute([':id' => $category_id]);
                if ($stmt_check_products->fetchColumn() > 0) {
                    $message = "Impossible de supprimer la catégorie car elle est associée à des produits. Veuillez d'abord réaffecter ou supprimer les produits de cette catégorie.";
                    $message_type = "danger";
                } else {
                    // Exécuter la suppression
                    $stmt_delete = $pdo->prepare("DELETE FROM categories WHERE id = :id");
                    if ($stmt_delete->execute([':id' => $category_id])) {
                        if ($stmt_delete->rowCount() > 0) {
                            $message = "Catégorie supprimée avec succès !";
                            $message_type = "success";
                        } else {
                            $message = "Aucune catégorie trouvée avec cet ID ou la catégorie n'a pas été supprimée.";
                            $message_type = "warning";
                        }
                    } else {
                        $message = "Erreur lors de la suppression de la catégorie.";
                        $message_type = "danger";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la suppression de catégorie : " . $e->getMessage());
            $message = "Une erreur interne est survenue lors de la suppression de la catégorie.";
            $message_type = "danger";
        }
    } else {
        $message = "ID de catégorie invalide pour la suppression.";
        $message_type = "danger";
    }
    $_SESSION['form_message'] = ['text' => $message, 'type' => $message_type];
    redirect(BASE_URL . 'admin/dashboard.php?page=categories');
    exit();
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
                <?= $edit_category_data ? 'Modifier la catégorie #' . htmlspecialchars($edit_category_data['id']) : 'Ajouter une nouvelle catégorie' ?>
            </div>
            <div class="card-body">
                <form action="" method="POST" class="needs-validation" novalidate>
                    <?php if ($edit_category_data): ?>
                        <input type="hidden" name="action" value="update_category">
                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($edit_category_data['id']) ?>">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add_category">
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Nom de la catégorie <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" value="<?= htmlspecialchars($edit_category_data['name'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            Veuillez entrer le nom de la catégorie.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"><?= htmlspecialchars($edit_category_data['description'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="parentCategory" class="form-label">Catégorie parente</label>
                        <select class="form-select" id="parentCategory" name="parent_id">
                            <option value="">Aucune (Catégorie principale)</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php
                                // Empêcher une catégorie d'être sa propre parente et d'être une parente si elle est la catégorie en cours de modification
                                $is_selected = ($edit_category_data && $edit_category_data['parent_id'] == $cat['id']) ? 'selected' : '';
                                $is_disabled = ($edit_category_data && $edit_category_data['id'] == $cat['id']) ? 'disabled' : '';
                                ?>
                                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $is_selected ?> <?= $is_disabled ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <?= $is_disabled ? ' (Catégorie actuelle)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Sélectionnez une catégorie existante si celle-ci est une sous-catégorie.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i>
                        <?= $edit_category_data ? 'Mettre à jour la catégorie' : 'Ajouter la catégorie' ?>
                    </button>
                    <?php if ($edit_category_data): ?>
                        <a href="dashboard.php?page=categories" class="btn btn-secondary ms-2">Annuler</a>
                    <?php endif; ?>
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
                                    <th>Parent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['id']) ?></td>
                                        <td><?= htmlspecialchars($category['name']) ?></td>
                                        <td><?= htmlspecialchars($category['description'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php
                                            if ($category['parent_id']) {
                                                // Trouver le nom du parent
                                                $parent_name = 'Inconnu';
                                                foreach ($categories as $parent_cat) {
                                                    if ($parent_cat['id'] == $category['parent_id']) {
                                                        $parent_name = $parent_cat['name'];
                                                        break;
                                                    }
                                                }
                                                echo htmlspecialchars($parent_name);
                                            } else {
                                                echo 'Aucun';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="dashboard.php?page=categories&action=edit&id=<?= htmlspecialchars($category['id']) ?>" class="btn btn-sm btn-warning me-1" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <form action="" method="POST" class="d-inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ? Cela supprimera également ses sous-catégories et les produits associés !');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?= htmlspecialchars($category['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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