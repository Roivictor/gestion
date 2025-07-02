<?php
// admin/add_product.php (Ce fichier est inclus par dashboard.php)

// S'assurer que $pdo est disponible (il devrait l'être via dashboard.php qui inclut config.php)
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur: La connexion PDO n'est pas disponible dans add_product.php");
    // Utiliser setFlashMessage au lieu de $_SESSION directement
    setFlashMessage('danger', __('internal_error_db_access')); // Traduction: "Erreur interne: Impossible d'accéder à la base de données."
} else {
    // Traitement du formulaire de soumission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Vérification CSRF
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlashMessage('danger', __('csrf_token_invalid')); // Traduction: "Erreur de sécurité : token CSRF invalide."
            redirect(BASE_URL . 'admin/dashboard.php?page=add_product');
            exit();
        }

        // 2. Récupération et nettoyage des données du formulaire
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT);
        $category_id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);

        $errors = [];

        // 3. Validation des données
        if (empty($name)) {
            $errors[] = __('product_name_required'); // Traduction: "Le nom du produit est requis."
        }
        if (empty($description)) {
            $errors[] = __('product_description_required'); // Traduction: "La description du produit est requise."
        }
        if ($price === false || $price <= 0) {
            $errors[] = __('product_price_invalid'); // Traduction: "Le prix doit être un nombre positif valide."
        }
        if ($stock === false || $stock < 0) {
            $errors[] = __('product_stock_invalid'); // Traduction: "Le stock doit être un nombre entier positif ou nul."
        }
        // Vérifiez si category_id est valide si les catégories sont obligatoires et existent
        if ($category_id === false || $category_id <= 0) {
            $errors[] = __('category_select_valid'); // Traduction: "Veuillez sélectionner une catégorie valide."
        } else {
            // Vérifier si la catégorie existe réellement dans la DB
            try {
                $stmt_cat_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
                $stmt_cat_check->execute([$category_id]);
                if ($stmt_cat_check->fetchColumn() == 0) {
                    $errors[] = __('category_not_exist'); // Traduction: "La catégorie sélectionnée n'existe pas."
                }
            } catch (PDOException $e) {
                error_log("Erreur de vérification catégorie: " . $e->getMessage());
                $errors[] = __('internal_error_category_check'); // Traduction: "Erreur interne lors de la vérification de la catégorie."
            }
        }


        // 4. Gestion de l'upload d'image
        $image_url = '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            // Assurez-vous que UPLOAD_DIR est défini dans config.php, sinon cela posera problème
            if (!defined('UPLOAD_DIR')) {
                error_log("UPLOAD_DIR n'est pas défini dans config.php.");
                $errors[] = __('upload_config_error'); // Traduction: "Erreur de configuration du serveur pour l'upload d'image."
            } else {
                $upload_dir = UPLOAD_DIR; // Utilise la constante définie dans config.php

                // Créer le répertoire s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) { // Tente de créer le dossier récursivement
                        $errors[] = __('upload_directory_create_error') . " : " . $upload_dir; // Traduction: "Impossible de créer le dossier d'upload"
                        error_log("Impossible de créer le dossier d'upload: " . $upload_dir);
                    }
                }

                if (empty($errors)) { // Seulement si aucun problème de répertoire
                    $file_tmp_name = $_FILES['product_image']['tmp_name'];
                    $file_extension = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                    $file_name = uniqid('prod_') . '.' . $file_extension; // Nom de fichier unique
                    $target_file = $upload_dir . $file_name;

                    // Vérifier le type de fichier
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($file_extension, $allowed_types)) {
                        $errors[] = __('upload_invalid_type'); // Traduction: "Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés..."
                    }

                    // Vérifier la taille du fichier (ex: 5MB maximum)
                    if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) {
                        $errors[] = __('upload_size_limit'); // Traduction: "La taille de l'image ne doit pas dépasser 5MB."
                    }

                    if (empty($errors)) {
                        if (move_uploaded_file($file_tmp_name, $target_file)) {
                            $image_url = $file_name; // Stocker juste le nom du fichier
                        } else {
                            $errors[] = __('upload_failed'); // Traduction: "Erreur lors de l'upload de l'image."
                            error_log("Erreur move_uploaded_file: " . $_FILES['product_image']['error'] . " pour " . $_FILES['product_image']['name']);
                        }
                    }
                }
            }
        } else if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $errors[] = __('upload_unknown_error') . " (Code: " . $_FILES['product_image']['error'] . ")."; // Traduction: "Une erreur d'upload inconnue s'est produite..."
            error_log("Erreur d'upload de fichier inattendue: " . $_FILES['product_image']['error']);
        }


        // 5. Si aucune erreur de validation, insérer dans la base de données
        if (empty($errors)) {
            try {
                // MODIFICATION ICI : 'stock' remplacé par 'stock_quantity' dans la liste des colonnes
                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category_id, image_url) VALUES (:name, :description, :price, :stock_quantity, :category_id, :image_url)");
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description,
                    ':price' => $price,
                    ':stock_quantity' => $stock, // MODIFICATION ICI : ':stock' remplacé par ':stock_quantity' pour le placeholder
                    ':category_id' => $category_id,
                    ':image_url' => $image_url
                ]);

                setFlashMessage('success', sprintf(__('product_add_success'), htmlspecialchars($name))); // Traduction: "Produit '%s' ajouté avec succès!"
                redirect(BASE_URL . 'admin/dashboard.php?page=products'); // Rediriger vers la liste des produits
                exit();
            } catch (PDOException $e) {
                error_log("Erreur PDO lors de l'ajout de produit: " . $e->getMessage());
                setFlashMessage('danger', __('product_add_error') . ": " . $e->getMessage()); // Traduction: "Erreur lors de l'ajout du produit:"
            }
        } else {
            // S'il y a des erreurs, les stocker pour les afficher
            setFlashMessage('danger', implode('<br>', $errors));
            // Stocker les données POST pour pré-remplir le formulaire
            $_SESSION['form_data'] = $_POST;
            redirect(BASE_URL . 'admin/dashboard.php?page=add_product');
            exit();
        }
    }
}

// Pré-remplir le formulaire en cas d'erreur ou de redirection
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // Nettoyer après utilisation

// Récupérer les catégories pour le sélecteur déroulant
$categories = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name");
        $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des catégories dans add_product.php: " . $e->getMessage());
        // Utiliser setFlashMessage pour les erreurs de chargement de catégories
        setFlashMessage('danger', (getFlashMessage()['message'] ?? '') . "<br>" . __('category_load_error')); // Traduction: "Impossible de charger les catégories."
    }
}

// Générer un token CSRF
$csrfToken = '';
if (function_exists('generateCsrfToken')) {
    $csrfToken = generateCsrfToken();
} else {
    error_log("Fonction generateCsrfToken non définie dans add_product.php.");
}

// Récupérer et afficher les messages flash
$flashMessage = getFlashMessage();

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><?= __('add_new_product_title') ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php?page=products" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> <?= __('back_to_product_list') ?>
        </a>
    </div>
</div>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= $flashMessage['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        <?= __('product_info_title') ?>
    </div>
    <div class="card-body">
        <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="mb-3">
                <label for="name" class="form-label"><?= __('product_name') ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                <div class="invalid-feedback">
                    <?= __('product_name_validation') ?>
                </div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label"><?= __('description') ?> <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                <div class="invalid-feedback">
                    <?= __('product_description_validation') ?>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="price" class="form-label"><?= __('price') ?> (€) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($formData['price'] ?? '') ?>" required min="0.01">
                    <div class="invalid-feedback">
                        <?= __('product_price_validation') ?>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="stock" class="form-label"><?= __('stock') ?> <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($formData['stock'] ?? '') ?>" required min="0">
                    <div class="invalid-feedback">
                        <?= __('product_stock_validation') ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="category_id" class="form-label"><?= __('category') ?> <span class="text-danger">*</span></label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value=""><?= __('select_category_placeholder') ?></option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['id']) ?>"
                            <?= ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">
                    <?= __('category_validation') ?>
                </div>
                <?php if (empty($categories)): ?>
                    <small class="form-text text-muted"><?= __('no_categories_available') ?> <a href="dashboard.php?page=categories"><?= __('here') ?></a>.</small>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="product_image" class="form-label"><?= __('product_image') ?></label>
                <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                <small class="form-text text-muted"><?= __('image_format_size_info') ?></small>
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-circle"></i> <?= __('add_product_button') ?></button>
            <a href="dashboard.php?page=products" class="btn btn-secondary mt-3 ms-2"><?= __('cancel_button') ?></a>
        </form>
    </div>
</div>

<script>
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