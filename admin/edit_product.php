<?php
// edit_product.php - Page d'édition de produit dans le tableau de bord admin

// IMPORTANT : Démarrer la session au tout début, en s'assurant qu'elle n'est pas déjà active.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php';
require_once '../includes/functions.php'; // Assurez-vous que setFlashMessage, sanitize, verifyCsrfToken et generateCsrfToken sont définies ici.

// Vérifier si la variable $pdo (connexion à la base de données) est disponible
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur: La connexion PDO n'est pas disponible dans edit_product.php");
    setFlashMessage('danger', "Erreur interne: Impossible d'accéder à la base de données.");
    header("Location: dashboard.php?page=products"); // Rediriger en cas de problème grave de DB
    exit();
}

// --- Récupération de l'ID du produit depuis l'URL ---
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    setFlashMessage('danger', "ID de produit invalide pour la modification.");
    header("Location: dashboard.php?page=products");
    exit();
}

$product = null;
$variants = [];
$categories = []; // Pour le sélecteur de catégorie

// --- Génération du token CSRF ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Récupérer les catégories pour le sélecteur ---
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des catégories dans edit_product.php: " . $e->getMessage());
    setFlashMessage('danger', "Erreur lors du chargement des catégories.");
}

// --- Chargement des données existantes du produit et de ses variantes ---
try {
    // AJOUT DES COLONNES DE PROMOTION DANS LE SELECT
    $stmt_product = $pdo->prepare("SELECT id, name, description, image_url, price, promo_price, promo_start_date, promo_end_date, category_id, stock_quantity FROM products WHERE id = ?");
    $stmt_product->execute([$product_id]);
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        setFlashMessage('danger', "Produit non trouvé.");
        header("Location: dashboard.php?page=products");
        exit();
    }

    // AJOUT DE 'size_name' DANS LA SÉLECTION DES VARIANTES
    $stmt_variants = $pdo->prepare("SELECT id, product_id, name, color_name, hex_code, stock_quantity, price_adjustment, sku, image_url, size_name FROM product_variants WHERE product_id = ? ORDER BY id");
    $stmt_variants->execute([$product_id]);
    $variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur PDO lors du chargement du produit ou de ses variantes pour modification: " . $e->getMessage());
    setFlashMessage('danger', "Erreur lors du chargement des informations du produit.");
    header("Location: dashboard.php?page=products");
    exit();
}

// --- GESTION DE LA SOUMISSION DU FORMULAIRE (Mise à jour) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du CSRF Token
    if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF Error (edit_product.php): Token invalide ou manquant. Soumis: '" . ($_POST['csrf_token'] ?? 'N/A') . "', Stocké: '" . ($_SESSION['csrf_token'] ?? 'N/A') . "'");
        setFlashMessage('danger', "Erreur de sécurité : token CSRF invalide.");
        // Pas de redirection ici pour permettre à l'utilisateur de corriger sans perdre les saisies
        // header("Location: dashboard.php?page=edit_product&id=" . $product_id);
        // exit(); // Ne pas exit pour afficher le formulaire avec les erreurs
    } else {
        // Si le CSRF est bon, on procède au traitement
        $product_name = sanitize(trim($_POST['name'] ?? ''));
        $product_description = sanitize(trim($_POST['description'] ?? ''));
        $base_price = (float)($_POST['base_price'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $product_stock_quantity = (int)($_POST['product_stock_quantity'] ?? 0); 
        
        // NOUVEAU : Récupération des champs de promotion
        $promo_price = (float)($_POST['promo_price'] ?? 0.0);
        $promo_start_date = !empty($_POST['promo_start_date']) ? $_POST['promo_start_date'] : null;
        $promo_end_date = !empty($_POST['promo_end_date']) ? $_POST['promo_end_date'] : null;

        // Validation simple des dates de promotion si elles sont renseignées
        if ($promo_price > 0 && ($promo_start_date === null || $promo_end_date === null)) {
            setFlashMessage('warning', "Si un prix promotionnel est défini, les dates de début et de fin de promotion sont obligatoires.");
            // Ne pas rediriger, laisser le formulaire s'afficher avec l'erreur.
        } else if ($promo_start_date && $promo_end_date && (new DateTime($promo_start_date)) > (new DateTime($promo_end_date))) {
            setFlashMessage('warning', "La date de fin de promotion ne peut pas être antérieure à la date de début.");
            // Ne pas rediriger
        } else {
            // Si le prix promotionnel est mis à 0, s'assurer que les dates sont nulles
            if ($promo_price <= 0) {
                $promo_price = null;
                $promo_start_date = null;
                $promo_end_date = null;
            }
        }
        
        // Initialiser avec l'URL de l'image existante du produit
        $product_image_url = $product['image_url'];

        $upload_dir = UPLOAD_PRODUCTS_DIR;
        $upload_url_base = UPLOAD_PRODUCTS_URL; // Cette variable n'est pas utilisée ici, mais conservée pour cohérence

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                error_log("Erreur critique: Impossible de créer le dossier d'upload des produits: " . $upload_dir);
                setFlashMessage('danger', "Erreur serveur: Impossible de préparer le dossier d'upload.");
                // Pas de redirection pour afficher l'erreur sur place
            }
        }

        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        // Gérer l'upload de l'image principale du produit
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_info = pathinfo($_FILES['product_image']['name']);
            $file_extension = strtolower($file_info['extension']);
            $new_file_name = uniqid('prod_main_', true) . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (!in_array($file_extension, $allowed_types)) {
                setFlashMessage('warning', "Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés pour l'image principale.");
            } elseif ($_FILES['product_image']['size'] > $max_file_size) {
                setFlashMessage('warning', "La taille de l'image principale ne doit pas dépasser 5MB.");
            } else {
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                    // Supprimer l'ancienne image si elle existe et n'est pas la nouvelle
                    if (!empty($product['image_url']) && file_exists($upload_dir . $product['image_url']) && $product['image_url'] !== $new_file_name) {
                        unlink($upload_dir . $product['image_url']);
                    }
                    $product_image_url = $new_file_name;
                } else {
                    setFlashMessage('warning', "Erreur lors de l'upload de l'image principale du produit (code: " . $_FILES['product_image']['error'] . ").");
                    error_log("Erreur move_uploaded_file pour image principale: " . $_FILES['product_image']['error']);
                }
            }
        } else if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            setFlashMessage('warning', "Erreur lors de l'upload de l'image principale : " . getFileUploadErrorMessage($_FILES['product_image']['error']));
        }

        try {
            $pdo->beginTransaction();

            // AJOUT DES CHAMPS DE PROMOTION DANS LA REQUÊTE UPDATE
            $stmt_update_product = $pdo->prepare("UPDATE products SET name = ?, description = ?, image_url = ?, price = ?, promo_price = ?, promo_start_date = ?, promo_end_date = ?, category_id = ?, stock_quantity = ?, updated_at = NOW() WHERE id = ?");
            $stmt_update_product->execute([
                $product_name, $product_description, $product_image_url, $base_price, 
                $promo_price, $promo_start_date, $promo_end_date, 
                $category_id, $product_stock_quantity, $product_id
            ]);

            $existing_variants_map = [];
            foreach ($variants as $v) {
                $existing_variants_map[$v['id']] = $v['image_url'];
            }
            $submitted_variant_ids = $_POST['variant_ids'] ?? [];

            $variants_to_keep = [];
            if (isset($_POST['variant_names']) && is_array($_POST['variant_names'])) {
                // MISE À JOUR DE LA REQUÊTE INSERT POUR INCLURE 'size_name'
                $stmt_insert_variant = $pdo->prepare("INSERT INTO product_variants (product_id, name, size_name, color_name, hex_code, stock_quantity, price_adjustment, sku, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                // MISE À JOUR DE LA REQUÊTE UPDATE POUR INCLURE 'size_name'
                $stmt_update_variant = $pdo->prepare("UPDATE product_variants SET name = ?, size_name = ?, color_name = ?, hex_code = ?, stock_quantity = ?, price_adjustment = ?, sku = ?, image_url = ?, updated_at = NOW() WHERE id = ? AND product_id = ?");

                foreach ($_POST['variant_names'] as $index => $variant_name) {
                    $v_id = $submitted_variant_ids[$index] ?? 'new';
                    $v_color_name = sanitize(trim($_POST['variant_color_names'][$index] ?? ''));
                    $v_hex_code = sanitize(trim($_POST['variant_hex_codes'][$index] ?? '#000000'));
                    $v_stock_quantity = (int)($_POST['variant_stock_quantities'][$index] ?? 0);
                    $v_price_adjustment = (float)($_POST['variant_price_adjustments'][$index] ?? 0.00);
                    $v_sku = sanitize(trim($_POST['variant_skus'][$index] ?? ''));
                    // NOUVEAU : Récupération du champ taille
                    $v_size_name = sanitize(trim($_POST['variant_size_names'][$index] ?? ''));
                    
                    $v_image_url = ($v_id !== 'new' && isset($existing_variants_map[$v_id])) ? $existing_variants_map[$v_id] : null;

                    $variant_image_file_key = 'variant_image_' . $index;
                    if (isset($_FILES[$variant_image_file_key]) && $_FILES[$variant_image_file_key]['error'] === UPLOAD_ERR_OK) {
                        $v_file_info = pathinfo($_FILES[$variant_image_file_key]['name']);
                        $v_file_extension = strtolower($v_file_info['extension']);
                        $v_new_file_name = uniqid('prod_variant_', true) . '.' . $v_file_extension;
                        $v_target_file = $upload_dir . $v_new_file_name;

                        if (!in_array($v_file_extension, $allowed_types)) {
                            setFlashMessage('warning', "Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés pour la variante " . ($variant_name ?: ($index + 1)) . ".");
                        } elseif ($_FILES[$variant_image_file_key]['size'] > $max_file_size) {
                            setFlashMessage('warning', "La taille de l'image de la variante " . ($variant_name ?: ($index + 1)) . " ne doit pas dépasser 5MB.");
                        } else {
                            if (move_uploaded_file($_FILES[$variant_image_file_key]['tmp_name'], $v_target_file)) {
                                if (!empty($v_image_url) && file_exists($upload_dir . $v_image_url) && $v_image_url !== $v_new_file_name) {
                                    unlink($upload_dir . $v_image_url);
                                }
                                $v_image_url = $v_new_file_name;
                            } else {
                                setFlashMessage('warning', "Erreur lors de l'upload de l'image pour la variante " . ($variant_name ?: ($index + 1)) . " (code: " . $_FILES[$variant_image_file_key]['error'] . ").");
                                error_log("Erreur move_uploaded_file pour image variante " . ($variant_name ?: ($index + 1)) . ": " . $_FILES[$variant_image_file_key]['error']);
                            }
                        }
                    } else if (isset($_FILES[$variant_image_file_key]) && $_FILES[$variant_image_file_key]['error'] !== UPLOAD_ERR_NO_FILE) {
                        setFlashMessage('warning', "Erreur lors de l'upload de l'image pour la variante " . ($variant_name ?: ($index + 1)) . " : " . getFileUploadErrorMessage($_FILES[$variant_image_file_key]['error']));
                    }

                    if ($v_id === 'new') {
                        $stmt_insert_variant->execute([
                            $product_id, $variant_name, $v_size_name, $v_color_name, $v_hex_code,
                            $v_stock_quantity, $v_price_adjustment, $v_sku, $v_image_url
                        ]);
                    } else {
                        $stmt_update_variant->execute([
                            $variant_name, $v_size_name, $v_color_name, $v_hex_code, $v_stock_quantity,
                            $v_price_adjustment, $v_sku, $v_image_url, $v_id, $product_id
                        ]);
                        $variants_to_keep[] = (int)$v_id;
                    }
                }
            }

            $variants_to_delete_ids = array_diff(array_keys($existing_variants_map), $variants_to_keep);
            if (!empty($variants_to_delete_ids)) {
                $images_to_delete_from_disk = [];
                foreach ($variants_to_delete_ids as $deleted_id) {
                    if (isset($existing_variants_map[$deleted_id]) && !empty($existing_variants_map[$deleted_id])) {
                        $images_to_delete_from_disk[] = $upload_dir . $existing_variants_map[$deleted_id];
                    }
                }

                $placeholders = implode(',', array_fill(0, count($variants_to_delete_ids), '?'));
                $stmt_delete_variants = $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders) AND product_id = ?");
                $stmt_delete_variants->execute(array_merge($variants_to_delete_ids, [$product_id]));
                
                foreach ($images_to_delete_from_disk as $file_path) {
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        error_log("Image de variante supprimée du disque: " . $file_path);
                    }
                }
            }

            $pdo->commit();
            setFlashMessage('success', "Produit et variantes mis à jour avec succès.");
            header("Location: dashboard.php?page=products"); // Redirection après succès pour éviter la soumission multiple
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Erreur PDO lors de la mise à jour du produit (edit_product.php): " . $e->getMessage());
            setFlashMessage('danger', "Erreur lors de la mise à jour du produit : " . $e->getMessage());
            // Important : Ne pas rediriger ici, les variables POST sont conservées pour le formulaire
            // et on passera à la section HTML du formulaire pour l'affichage des valeurs et messages.
        }
    }
    // Si le CSRF est invalide ou une erreur PDO se produit, le script continue
    // et affichera le formulaire avec les valeurs $_POST et les messages flash.
}


// --- Préparer les valeurs à afficher dans le formulaire ---
// Cette section DOIT venir APRÈS la partie de traitement POST.
// Elle s'assure que les valeurs du formulaire sont celles soumises via POST
// si le formulaire a été soumis et qu'il n'y a pas eu de redirection.
// Sinon, elle utilise les valeurs chargées depuis la DB ($product, $variants).

$display_name = $_POST['name'] ?? ($product['name'] ?? '');
$display_description = $_POST['description'] ?? ($product['description'] ?? '');
$display_base_price = $_POST['base_price'] ?? ($product['price'] ?? 0.00);
$display_category_id = $_POST['category_id'] ?? ($product['category_id'] ?? 0);
$display_product_stock_quantity = $_POST['product_stock_quantity'] ?? ($product['stock_quantity'] ?? 0); 

// NOUVEAU : Valeurs à afficher pour les champs de promotion
$display_promo_price = $_POST['promo_price'] ?? ($product['promo_price'] ?? 0.0);
$display_promo_start_date = $_POST['promo_start_date'] ?? ($product['promo_start_date'] ? date('Y-m-d\TH:i', strtotime($product['promo_start_date'])) : '');
$display_promo_end_date = $_POST['promo_end_date'] ?? ($product['promo_end_date'] ? date('Y-m-d\TH:i', strtotime($product['promo_end_date'])) : '');


// Pour les variantes, nous devons reconstruire le tableau $variants
// pour inclure les nouvelles variantes saisies par l'utilisateur
// et conserver les valeurs saisies même si l'enregistrement a échoué.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['variant_names'])) {
    $display_variants = [];
    foreach ($_POST['variant_names'] as $index => $name) {
        $v_id = $_POST['variant_ids'][$index] ?? 'new';
        // NOUVEAU : Récupération de la taille pour l'affichage
        $v_size_name = $_POST['variant_size_names'][$index] ?? '';
        $v_color_name = $_POST['variant_color_names'][$index] ?? '';
        $v_hex_code = $_POST['variant_hex_codes'][$index] ?? '#000000';
        $v_stock_quantity = $_POST['variant_stock_quantities'][$index] ?? 0;
        $v_price_adjustment = $_POST['variant_price_adjustments'][$index] ?? 0.00;
        $v_sku = $_POST['variant_skus'][$index] ?? '';

        // Tenter de récupérer l'URL de l'image existante si la variante n'est pas "nouvelle"
        $current_image_url_for_display = null;
        if ($v_id !== 'new') {
            foreach ($variants as $existing_v) {
                if ($existing_v['id'] == $v_id) {
                    $current_image_url_for_display = $existing_v['image_url'];
                    break;
                }
            }
        }
        
        // Note: Les champs type="file" ne conservent pas leur valeur via POST.
        // On ne peut pas les pré-remplir avec $_FILES. Seule l'URL existante peut être affichée.
        $display_variants[] = [
            'id' => $v_id,
            'name' => $name,
            'size_name' => $v_size_name, // Ajoutez le nouveau champ ici
            'color_name' => $v_color_name,
            'hex_code' => $v_hex_code,
            'stock_quantity' => $v_stock_quantity,
            'price_adjustment' => $v_price_adjustment,
            'sku' => $v_sku,
            'image_url' => $current_image_url_for_display // Afficher l'image existante, pas celle temporairement uploadée
        ];
    }
    // Si la soumission a eu lieu et qu'on est resté sur la page, on utilise ces variantes pour l'affichage
    $variants = $display_variants; 
}


// Fonction utilitaire pour obtenir un message d'erreur d'upload
if (!function_exists('getFileUploadErrorMessage')) {
    function getFileUploadErrorMessage($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return "La taille du fichier téléchargé excède la directive upload_max_filesize dans php.ini.";
            case UPLOAD_ERR_FORM_SIZE:
                return "La taille du fichier téléchargé excède la directive MAX_FILE_SIZE spécifiée dans le formulaire HTML.";
            case UPLOAD_ERR_PARTIAL:
                return "Le fichier n'a été que partiellement téléchargé.";
            case UPLOAD_ERR_NO_FILE:
                return "Aucun fichier n'a été téléchargé.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Un dossier temporaire est manquant.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Échec de l'écriture du fichier sur le disque.";
            case UPLOAD_ERR_EXTENSION:
                return "Une extension PHP a arrêté l'upload du fichier.";
            default:
                return "Erreur d'upload inconnue.";
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Modifier le produit: <?= htmlspecialchars($display_name) ?></h1>
</div>

<?php displayFlashMessages(); ?>

<form method="POST" action="dashboard.php?page=edit_product&id=<?= htmlspecialchars($product_id) ?>" enctype="multipart/form-data">
    <?php if (function_exists('generateCsrfToken')) { echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token) . '">'; } ?>

    <div class="card mb-4">
        <div class="card-header">Informations principales du produit</div>
        <div class="card-body">
            <div class="mb-3">
                <label for="name" class="form-label">Nom du produit</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($display_name) ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($display_description) ?></textarea>
            </div>
            <div class="mb-3">
                <label for="base_price" class="form-label">Prix de base (€)</label>
                <input type="number" step="0.01" class="form-control" id="base_price" name="base_price" value="<?= htmlspecialchars($display_base_price) ?>" required>
            </div>
            <div class="mb-3">
                <label for="product_stock_quantity" class="form-label">Quantité en stock (Produit de base)</label>
                <input type="number" class="form-control" id="product_stock_quantity" name="product_stock_quantity" value="<?= htmlspecialchars($display_product_stock_quantity) ?>" required min="0">
            </div>
            <div class="mb-3">
                <label for="category_id" class="form-label">Catégorie</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">Sélectionnez une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['id']) ?>" <?= ($display_category_id == $category['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="product_image" class="form-label">Image principale du produit</label>
                <input class="form-control" type="file" id="product_image" name="product_image" accept="image/*">
                <?php if (!empty($product['image_url'])): // Utilise $product['image_url'] pour l'affichage de l'image existante ?>
                    <div class="mt-2">
                        Image actuelle: <img src="<?= UPLOAD_PRODUCTS_URL . htmlspecialchars($product['image_url']) ?>" alt="Image produit actuelle" width="100">
                    </div>
                <?php else: ?>
                    <div class="mt-2 text-muted">Aucune image principale actuelle.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Gestion des promotions</div>
        <div class="card-body">
            <div class="mb-3">
                <label for="promo_price" class="form-label">Prix promotionnel (€) <small class="text-muted">(Laissez vide ou 0 pour désactiver la promotion)</small></label>
                <input type="number" step="0.01" class="form-control" id="promo_price" name="promo_price" value="<?= htmlspecialchars($display_promo_price) ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="promo_start_date" class="form-label">Date de début de promotion</label>
                    <input type="datetime-local" class="form-control" id="promo_start_date" name="promo_start_date" value="<?= htmlspecialchars($display_promo_start_date) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="promo_end_date" class="form-label">Date de fin de promotion</label>
                    <input type="datetime-local" class="form-control" id="promo_end_date" name="promo_end_date" value="<?= htmlspecialchars($display_promo_end_date) ?>">
                </div>
            </div>
            <p class="text-muted"><small>Les dates de promotion doivent être renseignées si un prix promotionnel est appliqué. Le format est YYYY-MM-DD HH:MM.</small></p>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            Variantes du produit
            <button type="button" id="addVariantBtn" class="btn btn-sm btn-success"><i class="bi bi-plus-circle"></i> Ajouter une variante</button>
        </div>
        <div class="card-body">
            <div id="productVariantsContainer">
                <?php if (empty($variants)): ?>
                    <p class="text-muted text-center" id="noVariantsMessage">Aucune variante pour ce produit.</p>
                <?php else: ?>
                    <?php foreach ($variants as $index => $variant): ?>
                        <div class="row g-3 mb-3 border p-3 rounded variant-item">
                            <input type="hidden" name="variant_ids[]" value="<?= htmlspecialchars($variant['id']) ?>">
                            <div class="col-md-3">
                                <label for="variant_name_<?= $index ?>" class="form-label">Nom de la variante</label>
                                <input type="text" class="form-control" id="variant_name_<?= $index ?>" name="variant_names[]" value="<?= htmlspecialchars($variant['name'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="variant_size_name_<?= $index ?>" class="form-label">Taille</label>
                                <input type="text" class="form-control" id="variant_size_name_<?= $index ?>" name="variant_size_names[]" value="<?= htmlspecialchars($variant['size_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="variant_color_name_<?= $index ?>" class="form-label">Couleur</label>
                                <input type="text" class="form-control" id="variant_color_name_<?= $index ?>" name="variant_color_names[]" value="<?= htmlspecialchars($variant['color_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-1">
                                <label for="variant_hex_code_<?= $index ?>" class="form-label">Hex</label>
                                <input type="color" class="form-control form-control-color" id="variant_hex_code_<?= $index ?>" name="variant_hex_codes[]" value="<?= htmlspecialchars($variant['hex_code'] ?? '#000000') ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="variant_stock_quantity_<?= $index ?>" class="form-label">Stock</label>
                                <input type="number" class="form-control" id="variant_stock_quantity_<?= $index ?>" name="variant_stock_quantities[]" value="<?= htmlspecialchars($variant['stock_quantity'] ?? 0) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="variant_price_adjustment_<?= $index ?>" class="form-label">Ajust. Prix (€)</label>
                                <input type="number" step="0.01" class="form-control" id="variant_price_adjustment_<?= $index ?>" name="variant_price_adjustments[]" value="<?= htmlspecialchars($variant['price_adjustment'] ?? 0.00) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="variant_sku_<?= $index ?>" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="variant_sku_<?= $index ?>" name="variant_skus[]" value="<?= htmlspecialchars($variant['sku'] ?? '') ?>">
                            </div>
                            <div class="col-md-12">
                                <label for="variant_image_<?= $index ?>" class="form-label">Image de la variante</label>
                                <input class="form-control form-control-sm" type="file" id="variant_image_<?= $index ?>" name="variant_image_<?= $index ?>" accept="image/*">
                                <?php if (!empty($variant['image_url'])): ?>
                                    <div class="mt-1">
                                        Image actuelle: <img src="<?= UPLOAD_PRODUCTS_URL . htmlspecialchars($variant['image_url']) ?>" alt="Image variante actuelle" width="50">
                                    </div>
                                <?php else: ?>
                                    <div class="mt-1 text-muted">Aucune image pour cette variante.</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-variant-btn">Supprimer cette variante</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="text-muted mt-2">Cliquez sur "Ajouter une variante" pour ajouter de nouvelles options de produit (taille, couleur, etc.).</p>
        </div>
    </div>

    <button type="submit" class="btn btn-success mt-4"><i class="bi bi-save"></i> Enregistrer les modifications</button>
    <a href="dashboard.php?page=products" class="btn btn-secondary mt-4"><i class="bi bi-x-circle"></i> Annuler</a>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addVariantBtn = document.getElementById('addVariantBtn');
    const variantsContainer = document.getElementById('productVariantsContainer');
    const noVariantsMessage = document.getElementById('noVariantsMessage');
    let variantCounter = document.querySelectorAll('.variant-item').length;

    function addVariantField() {
        if (noVariantsMessage && noVariantsMessage.parentNode) { 
            noVariantsMessage.remove(); 
        }
        const newVariantHtml = `
            <div class="row g-3 mb-3 border p-3 rounded variant-item">
                <input type="hidden" name="variant_ids[]" value="new">
                <div class="col-md-3">
                    <label for="variant_name_${variantCounter}" class="form-label">Nom de la variante</label>
                    <input type="text" class="form-control" id="variant_name_${variantCounter}" name="variant_names[]">
                </div>
                <div class="col-md-2">
                    <label for="variant_size_name_${variantCounter}" class="form-label">Taille</label>
                    <input type="text" class="form-control" id="variant_size_name_${variantCounter}" name="variant_size_names[]">
                </div>
                <div class="col-md-2">
                    <label for="variant_color_name_${variantCounter}" class="form-label">Couleur</label>
                    <input type="text" class="form-control" id="variant_color_name_${variantCounter}" name="variant_color_names[]">
                </div>
                <div class="col-md-1">
                    <label for="variant_hex_code_${variantCounter}" class="form-label">Hex</label>
                    <input type="color" class="form-control form-control-color" id="variant_hex_code_${variantCounter}" name="variant_hex_codes[]" value="#000000">
                </div>
                <div class="col-md-2">
                    <label for="variant_stock_quantity_${variantCounter}" class="form-label">Stock</label>
                    <input type="number" class="form-control" id="variant_stock_quantity_${variantCounter}" name="variant_stock_quantities[]" value="0">
                </div>
                <div class="col-md-2">
                    <label for="variant_price_adjustment_${variantCounter}" class="form-label">Ajust. Prix (€)</label>
                    <input type="number" step="0.01" class="form-control" id="variant_price_adjustment_${variantCounter}" name="variant_price_adjustments[]" value="0.00">
                </div>
                <div class="col-md-2">
                    <label for="variant_sku_${variantCounter}" class="form-label">SKU</label>
                    <input type="text" class="form-control" id="variant_sku_${variantCounter}" name="variant_skus[]">
                </div>
                <div class="col-md-12">
                    <label for="variant_image_${variantCounter}" class="form-label">Image de la variante</label>
                    <input class="form-control form-control-sm" type="file" id="variant_image_${variantCounter}" name="variant_image_${variantCounter}" accept="image/*">
                </div>
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-variant-btn">Supprimer cette variante</button>
                </div>
            </div>
        `;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = newVariantHtml.trim();
        variantsContainer.appendChild(tempDiv.firstChild);
        variantCounter++;
        updateRemoveButtons();
    }

    addVariantBtn.addEventListener('click', addVariantField);

    function updateRemoveButtons() {
        document.querySelectorAll('.remove-variant-btn').forEach(button => {
            button.onclick = function() {
                this.closest('.variant-item').remove();
                if (variantsContainer.children.length === 0) {
                    const p = document.createElement('p');
                    p.className = 'text-muted text-center';
                    p.id = 'noVariantsMessage';
                    p.textContent = 'Aucune variante pour ce produit.';
                    variantsContainer.appendChild(p);
                }
            };
        });
    }

    // Appeler une fois au chargement pour les variantes existantes
    updateRemoveButtons();

    // Initialiser les champs date/heure si des valeurs existent
    const promoStartDateInput = document.getElementById('promo_start_date');
    const promoEndDateInput = document.getElementById('promo_end_date');

    // Fonction pour formater les dates pour les champs datetime-local
    function formatDateTimeLocal(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const day = date.getDate().toString().padStart(2, '0');
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Appliquer le formatage au chargement si les valeurs existent
    if (promoStartDateInput && promoStartDateInput.value) {
        promoStartDateInput.value = formatDateTimeLocal(promoStartDateInput.value);
    }
    if (promoEndDateInput && promoEndDateInput.value) {
        promoEndDateInput.value = formatDateTimeLocal(promoEndDateInput.value);
    }
});
</script>