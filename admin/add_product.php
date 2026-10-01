<?php
// admin/add_product.php

// Toujours commencer par ob_start() pour capturer les éventuelles erreurs et éviter l'affichage prématuré
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php'; // Votre fichier de configuration avec les fonctions et la connexion DB
require_once '../includes/functions.php'; // Assurez-vous que setFlashMessage, sanitize, verifyCsrfToken, generateCsrfToken et redirect sont définies ici.


// Vérification du rôle admin (essentiel pour la sécurité)
checkAdminRole(); // Assurez-vous que cette fonction gère la redirection ou l'arrêt si non-admin

// DEBUG: Initialisation de la variable de réponse (peut être utile si on renvoie du JSON, mais ici on gère via flash messages)
$response = [
    'success_message' => null,
    'error_message' => null,
    'products' => [] // Pour les requêtes de filtrage (pas utilisé ici)
];

try {
    // Traitement du formulaire de soumission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // --- DEBUG TEMPORAIRE: Afficher les données POST et FILES ---
        // Garder ces lignes pour le débogage seulement, à retirer en production.
        // echo "<pre><code>";
        // echo "<h4>Données POST soumises :</h4>";
        // var_dump($_POST);
        // echo "<h4>Données FILES soumises :</h4>";
        // var_dump($_FILES);
        // echo "</code></pre>";
        // --- FIN DEBUG TEMPORAIRE ---

        // 1. Vérification CSRF
        if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            setFlashMessage('danger', 'Erreur de sécurité : token CSRF invalide.');
            // On continue pour afficher l'erreur et pré-remplir le formulaire, plutôt que de rediriger immédiatement
        } else { // Si le token CSRF est valide, on procède
            // 2. Récupération et nettoyage des données du formulaire principal du produit
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT); // Le prix sera le prix de base du produit
            $stock = filter_var($_POST['stock'] ?? '', FILTER_VALIDATE_INT); // Stock pour la variante par défaut (si une seule)
            $category_id = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);

            // NOUVEAU: Récupération des données de promotion
            $promo_price = filter_var($_POST['promo_price'] ?? '', FILTER_VALIDATE_FLOAT);
            $promo_start_date = !empty($_POST['promo_start_date']) ? sanitize($_POST['promo_start_date']) : null;
            $promo_end_date = !empty($_POST['promo_end_date']) ? sanitize($_POST['promo_end_date']) : null;

            // NOUVEAU: Récupération des données de VARIANTE (peut y en avoir plusieurs)
            // Assurez-vous que les données 'variants' sont un tableau
            $variants_data = $_POST['variants'] ?? [];
            if (!is_array($variants_data)) {
                $variants_data = []; // S'assurer que c'est un tableau
            }

            $errors = [];

            // 3. Validation des données du produit principal
            if (empty($name)) {
                $errors[] = 'Le nom du produit est requis.';
            }
            if (empty($description)) {
                $errors[] = 'La description du produit est requise.';
            }
            if ($price === false || $price <= 0) {
                $errors[] = 'Le prix doit être un nombre positif valide.';
            }
            // Le stock est validé ici car nous allons créer une variante par défaut avec ce stock
            if ($stock === false || $stock < 0) {
                $errors[] = 'Le stock principal doit être un nombre entier positif ou nul.';
            }
            // Vérifiez si category_id est valide si les catégories sont obligatoires et existent
            if ($category_id === false || $category_id <= 0) {
                $errors[] = 'Veuillez sélectionner une catégorie valide.';
            } else {
                // Vérifier si la catégorie existe réellement dans la DB
                try {
                    $stmt_cat_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE id = ?");
                    $stmt_cat_check->execute([$category_id]);
                    if ($stmt_cat_check->fetchColumn() == 0) {
                        $errors[] = 'La catégorie sélectionnée n\'existe pas.';
                    }
                } catch (PDOException $e) {
                    error_log("Erreur de vérification catégorie: " . $e->getMessage());
                    $errors[] = 'Erreur interne lors de la vérification de la catégorie.';
                }
            }

            // NOUVEAU: Validation des données de promotion (si un prix promo est renseigné)
            if ($promo_price !== false && $promo_price > 0) { // Un prix promo a été renseigné et est positif
                if ($promo_price >= $price) {
                    $errors[] = 'Le prix promotionnel doit être inférieur au prix normal du produit.';
                }
                if (empty($promo_start_date)) {
                    $errors[] = 'La date de début de promotion est requise si un prix promotionnel est défini.';
                }
                if (empty($promo_end_date)) {
                    $errors[] = 'La date de fin de promotion est requise si un prix promotionnel est défini.';
                }
                if ($promo_start_date && $promo_end_date) {
                    try {
                        $start_dt = new DateTime($promo_start_date);
                        $end_dt = new DateTime($promo_end_date);
                        if ($start_dt >= $end_dt) {
                            $errors[] = 'La date de fin de promotion doit être postérieure à la date de début.';
                        }
                    } catch (Exception $e) {
                        $errors[] = 'Format de date de promotion invalide.';
                    }
                }
            } else {
                // Si le prix promo n'est pas valide ou n'est pas renseigné, on s'assure que les dates sont NULL
                $promo_price = null;
                $promo_start_date = null;
                $promo_end_date = null;
            }

            // NOUVEAU: Validation des variantes
            $has_valid_variant = false;
            foreach ($variants_data as $index => $variant) {
                $variant_name_i = sanitize($variant['name'] ?? '');
                $variant_color_name_i = sanitize($variant['color_name'] ?? ''); // Nouvelle colonne pour la couleur de la variante
                $variant_hex_code_i = sanitize($variant['hex_code'] ?? ''); // Nouvelle colonne pour le code hex de la couleur
                $variant_size_i = isset($variant['sizes']) ? implode(',', (array)$variant['sizes']) : ''; // Modification pour gérer les tailles multiples
                $variant_stock_i = filter_var($variant['stock'] ?? '', FILTER_VALIDATE_INT);
                $variant_price_adjustment_i = filter_var($variant['price_adjustment'] ?? '', FILTER_VALIDATE_FLOAT);

                // Une variante est considérée comme "définie" si au moins un de ces champs est rempli
                if (!empty($variant_name_i) || !empty($variant_color_name_i) || !empty($variant_size_i) || ($variant_stock_i !== false && $variant_stock_i >= 0) || ($variant_price_adjustment_i !== false)) {
                    $has_valid_variant = true;
                    if (empty($variant_name_i) && empty($variant_size_i) && empty($variant_color_name_i)) {
                         $errors[] = "Pour la variante #".($index+1).", veuillez spécifier au moins un nom de variante, une couleur ou une taille.";
                    }
                    if ($variant_stock_i === false || $variant_stock_i < 0) {
                        $errors[] = "Pour la variante #".($index+1).", le stock doit être un nombre entier positif ou nul.";
                    }
                    if ($variant_price_adjustment_i === false && !empty($variant['price_adjustment'])) { // Vérifie si le champ était rempli mais invalide
                        $errors[] = "Pour la variante #".($index+1).", l'ajustement de prix doit être un nombre valide.";
                    }
                    // Vous pouvez ajouter d'autres validations ici, par exemple, pour le format du code hex.
                }
            }

            // Si aucune variante n'est fournie, nous devrons créer une variante par défaut basée sur le stock principal du produit
            if (!$has_valid_variant && empty($errors)) {
                // Si aucune variante n'est entrée via le formulaire de variantes,
                // créez une seule variante "Standard" avec le stock et les détails du produit principal
                $variants_data = [
                    [
                        'name' => 'Standard',
                        'color_name' => 'N/A', // Ou une valeur par défaut
                        'hex_code' => '#FFFFFF', // Couleur par défaut
                        'sizes' => ['N/A'], // Taille par défaut
                        'stock' => $stock, // Utilise le stock principal du produit
                        'price_adjustment' => 0.00
                    ]
                ];
            }


            // 4. Gestion de l'upload d'image
            $image_url = null; // Initialiser l'URL de l'image à NULL (plus explicite pour la DB si non uploadé)

            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                if (!defined('UPLOAD_PRODUCTS_DIR')) {
                    error_log("UPLOAD_PRODUCTS_DIR n'est pas défini dans config.php.");
                    $errors[] = 'Erreur de configuration du serveur pour l\'upload d\'image (UPLOAD_PRODUCTS_DIR non défini).';
                } else {
                    $upload_dir = UPLOAD_PRODUCTS_DIR; // Utilise la constante définie dans config.php

                    // Créer le répertoire s'il n'existe pas
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0775, true)) { // Tente de créer le dossier récursivement
                            $errors[] = 'Impossible de créer le dossier d\'upload : ' . $upload_dir;
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
                            $errors[] = 'Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés.';
                        }

                        // Vérifier la taille du fichier (ex: 5MB maximum)
                        if ($_FILES['product_image']['size'] > 5 * 1024 * 1024) {
                            $errors[] = 'La taille de l\'image ne doit pas dépasser 5MB.';
                        }

                        if (empty($errors)) {
                            if (move_uploaded_file($file_tmp_name, $target_file)) {
                                $image_url = $file_name; // Stocker juste le nom du fichier
                            } else {
                                $errors[] = 'Erreur lors de l\'upload de l\'image.';
                                error_log("Erreur move_uploaded_file: " . $_FILES['product_image']['error'] . " pour " . $_FILES['product_image']['name'] . ". Erreur PHP: " . (error_get_last()['message'] ?? 'N/A'));
                            }
                        }
                    }
                }
            } else if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                error_log("Erreur d'upload de fichier inattendue (Code: " . $_FILES['product_image']['error'] . ") pour " . ($_FILES['product_image']['name'] ?? 'N/A'));
                $errors[] = 'Une erreur d\'upload inconnue s\'est produite (Code: ' . $_FILES['product_image']['error'] . ').';
            }


            // 5. Si aucune erreur de validation, insérer dans la base de données
            if (empty($errors)) {
                try {
                    $pdo->beginTransaction(); // Commencer une transaction

                    // Insérer le produit dans la table `products`
                    $stmt_product = $pdo->prepare("INSERT INTO products (name, description, price, category_id, image_url, stock_quantity, promo_price, promo_start_date, promo_end_date, created_at, updated_at) VALUES (:name, :description, :price, :category_id, :image_url, :stock_quantity, :promo_price, :promo_start_date, :promo_end_date, NOW(), NOW())");
                    $stmt_product->execute([
                        ':name' => $name,
                        ':description' => $description,
                        ':price' => $price,
                        ':category_id' => $category_id,
                        ':image_url' => $image_url, // L'image principale du produit
                        ':stock_quantity' => $stock, // Le stock principal du produit
                        ':promo_price' => $promo_price,
                        ':promo_start_date' => $promo_start_date,
                        ':promo_end_date' => $promo_end_date
                    ]);

                    $product_id = $pdo->lastInsertId(); // Récupérer l'ID du produit nouvellement inséré

                    // NOUVEAU: Insérer toutes les variantes soumises
                    foreach ($variants_data as $index => $variant) {
                        $variant_name = sanitize($variant['name'] ?? '');
                        $variant_color_name = sanitize($variant['color_name'] ?? '');
                        $variant_hex_code = sanitize($variant['hex_code'] ?? '');
                        $variant_size = isset($variant['sizes']) ? implode(',', (array)$variant['sizes']) : ''; // Modification pour gérer les tailles multiples
                        $variant_stock = filter_var($variant['stock'] ?? '', FILTER_VALIDATE_INT);
                        $variant_price_adjustment = filter_var($variant['price_adjustment'] ?? '', FILTER_VALIDATE_FLOAT);

                        // Si stock vide, utiliser le stock principal du produit ou 0 si le produit n'a pas de stock principal défini
                        if ($variant_stock === false || $variant_stock < 0 || $variant_stock === null) {
                            $variant_stock = $stock !== false && $stock >= 0 ? $stock : 0; // Utilise le stock principal du produit, sinon 0
                        }
                        // Si ajustement de prix vide ou invalide, pas d'ajustement
                        if ($variant_price_adjustment === false || $variant_price_adjustment === null) {
                            $variant_price_adjustment = 0.00;
                        }

                        // Générer un SKU unique pour CHAQUE variante
                        $sku = 'SKU-' . $product_id . '-' . uniqid();

                        // L'image de la variante sera NULL par défaut ou gérée séparément si besoin
                        $variant_image_url = null;

                        $stmt_variant = $pdo->prepare("INSERT INTO product_variants (product_id, name, color_name, hex_code, size_name, stock_quantity, price_adjustment, sku, image_url, created_at, updated_at) VALUES (:product_id, :name, :color_name, :hex_code, :size_name, :stock_quantity, :price_adjustment, :sku, :image_url, NOW(), NOW())");
                        $stmt_variant->execute([
                            ':product_id' => $product_id,
                            ':name' => empty($variant_name) ? 'Standard' : $variant_name, // 'Standard' si vide
                            ':color_name' => empty($variant_color_name) ? 'N/A' : $variant_color_name, // 'N/A' si vide
                            ':hex_code' => empty($variant_hex_code) ? '#FFFFFF' : $variant_hex_code, // '#FFFFFF' si vide
                            ':size_name' => empty($variant_size) ? 'N/A' : $variant_size, // 'N/A' si vide
                            ':stock_quantity' => $variant_stock,
                            ':price_adjustment' => $variant_price_adjustment,
                            ':sku' => $sku,
                            ':image_url' => $variant_image_url
                        ]);
                    }

                    $pdo->commit(); // Confirmer la transaction si tout s'est bien passé

                    // Message de succès
                    setFlashMessage('success', 'Produit \'' . htmlspecialchars($name) . '\' et ses variantes ajoutés avec succès !');
                    redirect(BASE_URL . 'admin/dashboard.php?page=products'); // Rediriger vers la liste des produits
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack(); // Annuler la transaction en cas d'erreur
                    error_log("Erreur PDO lors de l'ajout de produit: " . $e->getMessage());
                    $user_message = 'Erreur lors de l\'ajout du produit.';
                    if (strpos($e->getMessage(), '1062 Duplicate entry') !== false) {
                        $user_message .= ' Un problème est survenu avec un identifiant interne (SKU) déjà existant. Veuillez réessayer ou contacter l\'administrateur si le problème persiste.';
                    } else {
                        $user_message .= ' Détails techniques : ' . $e->getMessage();
                    }
                    setFlashMessage('danger', $user_message);
                } catch (Exception $e) {
                    $pdo->rollBack(); // Annuler la transaction en cas d'erreur générale
                    error_log("Erreur générale lors de l'ajout de produit: " . $e->getMessage());
                    setFlashMessage('danger', 'Une erreur inattendue est survenue: ' . $e->getMessage());
                }
            } else {
                setFlashMessage('danger', implode('<br>', $errors));
                if ($promo_start_date && $promo_end_date) {
                    $_POST['promo_start_date'] = (new DateTime($promo_start_date))->format('Y-m-d\TH:i');
                    $_POST['promo_end_date'] = (new DateTime($promo_end_date))->format('Y-m-d\TH:i');
                }
                $_SESSION['form_data'] = $_POST;
            }
        }
    }
} catch (Exception $e) {
    error_log("General error in add_product.php: " . $e->getMessage());
    setFlashMessage('danger', 'Une erreur inattendue est survenue: ' . $e->getMessage());
}

ob_end_flush();

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$categories = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt_categories = $pdo->query("SELECT id, name FROM categories ORDER BY name");
        $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des catégories dans add_product.php: " . $e->getMessage());
        setFlashMessage('danger', (getFlashMessage()['message'] ?? '') . "<br>" . 'Impossible de charger les catégories.');
    }
}

$csrfToken = '';
if (function_exists('generateCsrfToken')) {
    $csrfToken = generateCsrfToken();
} else {
    error_log("Fonction generateCsrfToken non définie dans add_product.php.");
}

$flashMessage = getFlashMessage();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .card-header.bg-info {
            background-color: #17a2b8 !important;
        }
        .card-header.bg-primary {
            background-color: #0d6efd !important;
        }
        .variant-item {
            border: 1px solid #e9ecef;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .variant-item .btn-close {
            float: right;
            font-size: 0.8rem;
        }
        /* Styles pour les tailles */
        .size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .size-option {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .size-option:hover {
            background-color: #f8f9fa;
        }
        .form-check-input:checked + .size-option {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .form-check-input {
            position: absolute;
            opacity: 0;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Ajouter un nouveau produit</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="dashboard.php?page=products" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Retour à la liste des produits
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
                Informations sur le produit
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nom du produit <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                        <div class="invalid-feedback">
                            Veuillez fournir un nom de produit.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                        <div class="invalid-feedback">
                            Veuillez fournir une description.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Prix (€) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?= htmlspecialchars($formData['price'] ?? '') ?>" required min="0.01">
                            <div class="invalid-feedback">
                                Veuillez fournir un prix valide (nombre positif).
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Stock principal du produit (si aucune variante spécifiée) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($formData['stock'] ?? '') ?>" required min="0">
                            <div class="invalid-feedback">
                                Veuillez fournir une quantité en stock valide (nombre entier positif ou nul).
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Catégorie <span class="text-danger">*</span></label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Sélectionnez une catégorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['id']) ?>"
                                    <?= ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Veuillez sélectionner une catégorie.
                        </div>
                        <?php if (empty($categories)): ?>
                            <small class="form-text text-muted">Aucune catégorie disponible. Ajoutez-en une <a href="dashboard.php?page=categories">ici</a>.</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="product_image" class="form-label">Image du produit</label>
                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                        <small class="form-text text-muted">Seuls les fichiers JPG, JPEG, PNG & GIF sont autorisés, taille maximale 5MB.</small>
                    </div>

                    <hr class="my-4">

                    <div class="card-header bg-info text-white">
                        Variantes du produit (Tailles, Couleurs, etc.)
                    </div>
                    <div class="card-body">
                        <div id="variants-container">
                            <?php if (!empty($formData['variants']) && is_array($formData['variants'])): ?>
                                <?php foreach ($formData['variants'] as $index => $variant): ?>
                                    <div class="variant-item" data-index="<?= $index ?>">
                                        <button type="button" class="btn-close remove-variant" aria-label="Close"></button>
                                        <h5>Variante #<span class="variant-number"><?= $index + 1 ?></span></h5>
                                        <div class="mb-3">
                                            <label for="variant_name_<?= $index ?>" class="form-label">Nom de la variante (Ex: T-shirt, Chaussure)</label>
                                            <input type="text" class="form-control" id="variant_name_<?= $index ?>" name="variants[<?= $index ?>][name]" value="<?= htmlspecialchars($variant['name'] ?? '') ?>">
                                            <small class="form-text text-muted">Nom descriptif de cette variante (ex: "Débardeur", "Manches longues").</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="variant_color_name_<?= $index ?>" class="form-label">Nom de la Couleur (Ex: Rouge)</label>
                                                <input type="text" class="form-control" id="variant_color_name_<?= $index ?>" name="variants[<?= $index ?>][color_name]" value="<?= htmlspecialchars($variant['color_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="variant_hex_code_<?= $index ?>" class="form-label">Code Hex Couleur (Ex: #FF0000)</label>
                                                <input type="text" class="form-control" id="variant_hex_code_<?= $index ?>" name="variants[<?= $index ?>][hex_code]" value="<?= htmlspecialchars($variant['hex_code'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tailles disponibles</label>
                                            <div class="size-options">
                                                <!-- Tailles pour vêtements -->
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_xs_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="XS" <?= isset($variant['sizes']) && in_array('XS', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_xs_<?= $index ?>">XS</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_s_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="S" <?= isset($variant['sizes']) && in_array('S', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_s_<?= $index ?>">S</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_m_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="M" <?= isset($variant['sizes']) && in_array('M', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_m_<?= $index ?>">M</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_l_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="L" <?= isset($variant['sizes']) && in_array('L', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_l_<?= $index ?>">L</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_xl_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="XL" <?= isset($variant['sizes']) && in_array('XL', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_xl_<?= $index ?>">XL</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="variant_size_2xl_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="2XL" <?= isset($variant['sizes']) && in_array('2XL', (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                    <label class="form-check-label size-option" for="variant_size_2xl_<?= $index ?>">2XL</label>
                                                </div>
                                                <!-- Pointures pour chaussures -->
                                                <?php for ($i = 35; $i <= 46; $i++): ?>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" id="variant_size_<?= $i ?>_<?= $index ?>" name="variants[<?= $index ?>][sizes][]" value="<?= $i ?>" <?= isset($variant['sizes']) && in_array($i, (array)$variant['sizes']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label size-option" for="variant_size_<?= $i ?>_<?= $index ?>"><?= $i ?></label>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="form-text text-muted">Sélectionnez les tailles disponibles pour cette variante.</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="variant_stock_<?= $index ?>" class="form-label">Stock pour cette variante</label>
                                                <input type="number" class="form-control" id="variant_stock_<?= $index ?>" name="variants[<?= $index ?>][stock]" value="<?= htmlspecialchars($variant['stock'] ?? '') ?>" min="0">
                                                <small class="form-text text-muted">Si vide, le stock principal du produit sera utilisé.</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="variant_price_adjustment_<?= $index ?>" class="form-label">Ajustement de prix (€)</label>
                                                <input type="number" step="0.01" class="form-control" id="variant_price_adjustment_<?= $index ?>" name="variants[<?= $index ?>][price_adjustment]" value="<?= htmlspecialchars($variant['price_adjustment'] ?? '') ?>">
                                                <small class="form-text text-muted">Ex: 5.00 pour ajouter 5€, -2.50 pour retirer 2.50€. Laissez vide pour aucun ajustement.</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-outline-secondary mt-3" id="add-variant-btn">
                            <i class="bi bi-plus-circle"></i> Ajouter une variante
                        </button>
                    </div>

                    <hr class="my-4">

                    <div class="card-header bg-info text-white mt-4">
                        Informations sur la promotion (optionnel)
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="promo_price" class="form-label">Prix promotionnel (€)</label>
                                <input type="number" step="0.01" class="form-control" id="promo_price" name="promo_price" value="<?= htmlspecialchars($formData['promo_price'] ?? '') ?>" min="0.01">
                                <div class="form-text text-muted">Laissez vide si pas de promotion.</div>
                                <div class="invalid-feedback">
                                    Le prix promotionnel doit être un nombre positif inférieur au prix normal.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="promo_start_date" class="form-label">Date de début promotion</label>
                                <input type="datetime-local" class="form-control" id="promo_start_date" name="promo_start_date" value="<?= htmlspecialchars($formData['promo_start_date'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Veuillez spécifier la date de début de la promotion.
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="promo_end_date" class="form-label">Date de fin promotion</label>
                                <input type="datetime-local" class="form-control" id="promo_end_date" name="promo_end_date" value="<?= htmlspecialchars($formData['promo_end_date'] ?? '') ?>">
                                <div class="invalid-feedback">
                                    Veuillez spécifier la date de fin de la promotion.
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-plus-circle"></i> Ajouter le produit</button>
                    <a href="dashboard.php?page=products" class="btn btn-secondary mt-3 ms-2">Annuler</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        (function () {
            'use strict';
            var form = document.querySelector('.needs-validation');
            var variantsContainer = document.getElementById('variants-container');
            var addVariantBtn = document.getElementById('add-variant-btn');
            let variantIndex = variantsContainer.children.length; // Start index from existing variants or 0

            // Function to add a new variant row
            function addVariantRow(variantData = {}) {
                const newVariantItem = document.createElement('div');
                newVariantItem.classList.add('variant-item');
                newVariantItem.dataset.index = variantIndex;
                
                // Generate size options
                let sizeOptions = '';
                
                // Clothing sizes
                const sizes = ['XS', 'S', 'M', 'L', 'XL', '2XL'];
                sizes.forEach(size => {
                    const checked = variantData.sizes && variantData.sizes.includes(size) ? 'checked' : '';
                    sizeOptions += `
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" 
                                   id="variants_size_${size}_${variantIndex}" 
                                   name="variants[${variantIndex}][sizes][]" 
                                   value="${size}" ${checked}>
                            <label class="form-check-label size-option" for="variants_size_${size}_${variantIndex}">
                                ${size}
                            </label>
                        </div>
                    `;
                });
                
                // Shoe sizes
                for (let i = 35; i <= 46; i++) {
                    const checked = variantData.sizes && variantData.sizes.includes(i.toString()) ? 'checked' : '';
                    sizeOptions += `
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" 
                                   id="variants_size_${i}_${variantIndex}" 
                                   name="variants[${variantIndex}][sizes][]" 
                                   value="${i}" ${checked}>
                            <label class="form-check-label size-option" for="variants_size_${i}_${variantIndex}">
                                ${i}
                            </label>
                        </div>
                    `;
                }
                
                newVariantItem.innerHTML = `
                    <button type="button" class="btn-close remove-variant" aria-label="Close"></button>
                    <h5>Variante #<span class="variant-number">${variantIndex + 1}</span></h5>
                    <div class="mb-3">
                        <label for="variants_name_${variantIndex}" class="form-label">Nom de la variante (Ex: T-shirt, Chaussure)</label>
                        <input type="text" class="form-control" id="variants_name_${variantIndex}" name="variants[${variantIndex}][name]" value="${variantData.name ? htmlspecialchars(variantData.name) : ''}">
                        <small class="form-text text-muted">Nom descriptif de cette variante (ex: "Débardeur", "Manches longues").</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="variants_color_name_${variantIndex}" class="form-label">Nom de la Couleur (Ex: Rouge)</label>
                            <input type="text" class="form-control" id="variants_color_name_${variantIndex}" name="variants[${variantIndex}][color_name]" value="${variantData.color_name ? htmlspecialchars(variantData.color_name) : ''}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="variants_hex_code_${variantIndex}" class="form-label">Code Hex Couleur (Ex: #FF0000)</label>
                            <input type="text" class="form-control" id="variants_hex_code_${variantIndex}" name="variants[${variantIndex}][hex_code]" value="${variantData.hex_code ? htmlspecialchars(variantData.hex_code) : ''}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tailles disponibles</label>
                        <div class="size-options">
                            ${sizeOptions}
                        </div>
                        <small class="form-text text-muted">Sélectionnez les tailles disponibles pour cette variante.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="variants_stock_${variantIndex}" class="form-label">Stock pour cette variante</label>
                            <input type="number" class="form-control" id="variants_stock_${variantIndex}" name="variants[${variantIndex}][stock]" value="${variantData.stock ? htmlspecialchars(variantData.stock) : ''}" min="0">
                            <div class="invalid-feedback">Veuillez fournir un stock valide (nombre entier positif ou nul).</div>
                            <small class="form-text text-muted">Si vide, le stock principal du produit sera utilisé.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="variants_price_adjustment_${variantIndex}" class="form-label">Ajustement de prix (€)</label>
                            <input type="number" step="0.01" class="form-control" id="variants_price_adjustment_${variantIndex}" name="variants[${variantIndex}][price_adjustment]" value="${variantData.price_adjustment ? htmlspecialchars(variantData.price_adjustment) : ''}">
                            <div class="invalid-feedback">Veuillez fournir un ajustement de prix valide (nombre).</div>
                            <small class="form-text text-muted">Ex: 5.00 pour ajouter 5€, -2.50 pour retirer 2.50€. Laissez vide pour aucun ajustement.</small>
                        </div>
                    </div>
                `;
                variantsContainer.appendChild(newVariantItem);
                variantIndex++;
                updateVariantNumbers();
            }

            // Function to update variant numbers and attach remove listeners
            function updateVariantNumbers() {
                const variantItems = variantsContainer.querySelectorAll('.variant-item');
                variantItems.forEach((item, index) => {
                    item.querySelector('.variant-number').textContent = index + 1;
                    // Re-attach listener for remove button
                    const removeBtn = item.querySelector('.remove-variant');
                    removeBtn.onclick = function() {
                        item.remove();
                        updateVariantNumbers(); // Re-number after removal
                    };
                });
            }

            // Helper function to escape HTML for input values
            function htmlspecialchars(str) {
                return str
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Add initial empty variant row if none exist (or if form data is empty after submission errors)
            if (variantsContainer.children.length === 0 && !('variants' in <?= json_encode($formData) ?>)) {
                 addVariantRow();
            } else if ('variants' in <?= json_encode($formData) ?> && <?= json_encode($formData) ?>.variants.length > 0) {
                // If there's form data with variants (e.g., after an error), re-populate them
                <?= json_encode($formData) ?>.variants.forEach(variant => {
                    addVariantRow(variant);
                });
            }

            // Event listener for adding new variant rows
            addVariantBtn.addEventListener('click', () => addVariantRow());

            // Initial numbering and remove listener attachment
            updateVariantNumbers();

            // Handle form validation
            form.addEventListener('submit', function (event) {
                // Custom validation for promo dates if promo price is set
                const promoPriceInput = form.querySelector('#promo_price');
                const promoStartDateInput = form.querySelector('#promo_start_date');
                const promoEndDateInput = form.querySelector('#promo_end_date');
                const priceInput = form.querySelector('#price');

                let promoPrice = parseFloat(promoPriceInput.value);
                let normalPrice = parseFloat(priceInput.value);

                // Reset custom validity messages first
                promoPriceInput.setCustomValidity('');
                promoStartDateInput.setCustomValidity('');
                promoEndDateInput.setCustomValidity('');

                if (promoPriceInput.value !== '' && !isNaN(promoPrice) && promoPrice > 0) {
                    if (isNaN(normalPrice) || promoPrice >= normalPrice) {
                        promoPriceInput.setCustomValidity('Le prix promotionnel doit être inférieur au prix normal.');
                    } else if (promoStartDateInput.value === '') {
                        promoStartDateInput.setCustomValidity('La date de début de promotion est requise.');
                    } else if (promoEndDateInput.value === '') {
                        promoEndDateInput.setCustomValidity('La date de fin de promotion est requise.');
                    } else {
                        const startDate = new Date(promoStartDateInput.value);
                        const endDate = new Date(promoEndDateInput.value);
                        if (startDate >= endDate) {
                            promoEndDateInput.setCustomValidity('La date de fin doit être postérieure à la date de début.');
                        }
                    }
                }

                // NOUVEAU: Validation pour chaque variante ajoutée dynamiquement
                let allVariantsValid = true;
                const variantItems = variantsContainer.querySelectorAll('.variant-item');
                if (variantItems.length === 0) {
                    // Si aucune variante n'est ajoutée, cela sera géré par la logique PHP pour créer une variante "Standard"
                    // Donc pas de validation spécifique ici si le conteneur est vide.
                } else {
                    variantItems.forEach(item => {
                        const variantNameInput = item.querySelector('[name$="[name]"]'); // Sélectionne par la fin du nom
                        const variantColorNameInput = item.querySelector('[name$="[color_name]"]');
                        const variantSizeInputs = item.querySelectorAll('[name$="[sizes][]"]');
                        const variantStockInput = item.querySelector('[name$="[stock]"]');
                        const variantPriceAdjustmentInput = item.querySelector('[name$="[price_adjustment]"]');

                        // Réinitialiser la validité pour cette variante
                        variantNameInput.setCustomValidity('');
                        variantColorNameInput.setCustomValidity('');
                        variantStockInput.setCustomValidity('');
                        variantPriceAdjustmentInput.setCustomValidity('');

                        let variantDefined = false;
                        if (variantNameInput.value.trim() !== '' || variantColorNameInput.value.trim() !== '') {
                            variantDefined = true;
                        }

                        // Vérifier si au moins une taille est sélectionnée
                        let atLeastOneSizeSelected = false;
                        variantSizeInputs.forEach(input => {
                            if (input.checked) {
                                atLeastOneSizeSelected = true;
                            }
                        });
                        
                        if (!atLeastOneSizeSelected) {
                            variantDefined = false;
                        }

                        if (variantDefined) {
                            if (variantStockInput.value.trim() === '' || isNaN(parseFloat(variantStockInput.value)) || parseFloat(variantStockInput.value) < 0) {
                                variantStockInput.setCustomValidity('Veuillez fournir un stock valide pour cette variante (nombre entier positif ou nul).');
                                allVariantsValid = false;
                            }
                            if (variantPriceAdjustmentInput.value.trim() !== '' && isNaN(parseFloat(variantPriceAdjustmentInput.value))) {
                                variantPriceAdjustmentInput.setCustomValidity('Veuillez fournir un ajustement de prix valide pour cette variante (nombre).');
                                allVariantsValid = false;
                            }
                        } else {
                            // Si aucun champ principal de la variante n'est rempli, on vide les messages d'erreur de stock/prix
                            // car cette variante pourrait être ignorée ou traitée comme vide par le backend.
                            variantStockInput.setCustomValidity('');
                            variantPriceAdjustmentInput.setCustomValidity('');
                        }
                        // Marquer comme invalide si la validité personnalisée n'est pas passée
                        if (!variantNameInput.checkValidity() || !variantColorNameInput.checkValidity() || !variantStockInput.checkValidity() || !variantPriceAdjustmentInput.checkValidity()) {
                            allVariantsValid = false;
                        }
                    });
                }

                if (!form.checkValidity() || !allVariantsValid) { // Inclure la validation des variantes
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        })();
    </script>
</body>
</html>