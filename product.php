<?php
// product.php - Version complète et optimisée avec gestion des tailles et largeurs

// 1. Démarrage de la session et inclusion des fichiers essentiels
session_start();

// Inclure 'config.php' et 'includes/functions.php' au tout début
require_once 'config.php';
require_once 'includes/functions.php';

// Générer le jeton CSRF pour cette page.
$csrf_token = generateCsrfToken();

// Initialisation de $current_language_code
if (!isset($_SESSION['current_language_code'])) {
    $_SESSION['current_language_code'] = 'fr';
}
$current_language_code = $_SESSION['current_language_code'];

// 2. Vérification et nettoyage de l'ID du produit
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('danger', 'ID de produit manquant.');
    redirect(BASE_URL . 'products.php');
    exit;
}

$product_id = (int)$_GET['id'];

// 3. Requête SQL pour récupérer les détails du produit
$query = "SELECT p.*, c.name AS category_name,
                  (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
                  (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') AS review_count
           FROM products p
           LEFT JOIN categories c ON p.category_id = c.id
           WHERE p.id = ?";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO dans product.php lors de la récupération du produit: " . $e->getMessage());
    setFlashMessage('danger', 'Une erreur est survenue lors de la récupération des données du produit. Veuillez réessayer plus tard.');
    redirect(BASE_URL . 'products.php');
    exit;
}

// 4. Vérifier si le produit a été trouvé
if (!$product) {
    setFlashMessage('warning', 'Le produit demandé n\'a pas été trouvé.');
    redirect(BASE_URL . 'products.php');
    exit;
}

// Récupération des variantes pour ce produit
$raw_product_variants = []; // Renommé pour stocker les variantes brutes de la BDD
try {
    // AJOUT DE size_name et width_name DANS LA SÉLECTION
    $variants_query = "SELECT id, product_id, name, color_name, hex_code, stock_quantity, price_adjustment, sku, image_url, size_name FROM product_variants WHERE product_id = :product_id ORDER BY id ASC";
    $stmt_variants = $pdo->prepare($variants_query);
    $stmt_variants->execute([':product_id' => $product_id]);
    $raw_product_variants = $stmt_variants->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO dans product.php lors de la récupération des variantes: " . $e->getMessage());
    setFlashMessage('warning', 'Une erreur est survenue lors de la récupération des options de produit.');
}

// --- NOUVELLE LOGIQUE POUR LE CHOIX DU PRODUIT PRINCIPAL OU VARIANTE ET LE REGROUPEMENT ---

// Ajout d'une "variante virtuelle" pour le produit principal au début du tableau
$main_product_as_variant = [
    'id' => null, // ID null pour le produit principal
    'product_id' => $product['id'],
    'name' => $product['name'],
    'color_name' => 'Original', // Nom affiché pour la couleur par défaut
    'hex_code' => '#FFFFFF', // Couleur neutre si vous affichez un swatch
    'stock_quantity' => $product['stock_quantity'] ?? 0,
    'price_adjustment' => 0.00,
    'sku' => $product['sku'] ?? null,
    'image_url' => $product['image_url'],
    'size_name' => '' // Pas de taille spécifique par défaut, sera déterminé par le JS
];
array_unshift($raw_product_variants, $main_product_as_variant);

// Initialiser les valeurs d'affichage avec le produit principal comme variante par défaut
$initial_selected_variant = $raw_product_variants[0];
$product['current_image_url'] = $initial_selected_variant['image_url'];
$product['current_price'] = $product['price'] + ($initial_selected_variant['price_adjustment'] ?? 0);
$product['current_stock_quantity'] = $initial_selected_variant['stock_quantity'] ?? 0;
$product['current_variant_id'] = $initial_selected_variant['id'];


// PRÉPARATION DES DONNÉES POUR L'AFFICHAGE ET LE JAVASCRIPT
$variants_by_color = [];
$all_available_sizes = [];
$all_available_widths = [];

foreach ($raw_product_variants as $variant) {
    // Si la variante a un nom de couleur (et n'est pas juste une variante taille/largeur "sans couleur")
    if (!empty($variant['color_name'])) {
        $color_key = $variant['color_name'];
        if (!isset($variants_by_color[$color_key])) {
            $variants_by_color[$color_key] = [
                'color_name' => $variant['color_name'],
                'hex_code' => $variant['hex_code'],
                'image_url' => $variant['image_url'] ?: $product['image_url'], // Utilise l'image de la variante, ou celle du produit principal si vide
                'variants' => [] // Stocke toutes les combinaisons taille/largeur pour cette couleur
            ];
        }
        $variants_by_color[$color_key]['variants'][] = $variant;
    }

    // Collecter toutes les tailles et largeurs uniques disponibles sur toutes les variantes
    if (!empty($variant['size_name']) && !in_array($variant['size_name'], $all_available_sizes)) {
        $all_available_sizes[] = $variant['size_name'];
    }
    if (!empty($variant['width_name']) && !in_array($variant['width_name'], $all_available_widths)) {
        $all_available_widths[] = $variant['width_name'];
    }
}
// Trier les tailles numériquement si possible, sinon alphabétiquement
usort($all_available_sizes, 'version_compare'); // Pour gérer "5.5" avant "6"


// On encode TOUTES les variantes brutes pour le JavaScript
$product_variants_json = json_encode($raw_product_variants);

// Récupération des avis pour le produit
$reviews = [];
try {
    $reviews_query = "SELECT r.*, u.username, u.first_name, u.last_name
                      FROM reviews r
                      LEFT JOIN users u ON r.customer_id = u.id
                      WHERE r.product_id = :product_id AND r.status = 'approved'
                      ORDER BY r.review_date DESC";
    $stmt_reviews = $pdo->prepare($reviews_query);
    $stmt_reviews->execute([':product_id' => $product_id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO dans product.php lors de la récupération des avis: " . $e->getMessage());
    setFlashMessage('warning', 'Une erreur est survenue lors de la récupération des avis.');
}

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_language_code) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="icon" type="image/png" href="images/favicon.png">
    <style>
        /* Styles pour les options de couleur */
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: inline-block;
            margin: 5px;
            border: 2px solid transparent;
            transition: border-color 0.2s ease-in-out;
            background-size: cover;
            background-position: center;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .color-option.selected {
            border-color: #007bff;
            box-shadow: 0 0 8px rgba(0,123,255,0.5);
        }
        .color-option:hover {
            border-color: #6c757d;
        }

        /* Styles pour les options de taille et largeur (nouveaux) */
        .size-option,
        .width-option {
            min-width: 50px; /* Taille minimale pour les boutons */
            text-align: center;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s, border-color 0.2s, color 0.2s;
            font-size: 0.9rem;
            color: #495057;
            margin-right: 8px; /* Espacement entre les options */
            margin-bottom: 8px; /* Espacement pour les lignes multiples */
        }
        .size-option:hover,
        .width-option:hover {
            border-color: #007bff;
            color: #007bff;
        }
        .size-option.active,
        .width-option.active {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }
        .size-option.disabled,
        .width-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #e9ecef;
            border-color: #dee2e6;
            color: #6c757d;
            text-decoration: line-through; /* Barrer l'option désactivée */
        }
        .size-option.disabled:hover,
        .width-option.disabled:hover {
            border-color: #dee2e6; /* Pas de changement de bordure au survol si désactivé */
            color: #6c757d;
        }

        /* Style pour le fallback d'image */
        #product-image-fallback {
            height: 400px;
            background-color: #f8f9fa;
            border: 1px dashed #ced4da;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <?php
                    $initial_display_image_filename = !empty($product['image_url']) ? htmlspecialchars($product['image_url']) : '';
                    $initial_display_image_src = UPLOAD_PRODUCTS_URL . $initial_display_image_filename;

                    $show_fallback_initially = empty($initial_display_image_filename) || !file_exists(str_replace(BASE_URL, '', $initial_display_image_src));
                    ?>
                    <div id="product-image-wrapper">
                        <img id="product-image"
                             src="<?= $initial_display_image_src ?>"
                             class="img-fluid rounded shadow-sm"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             data-main-product-image="<?= htmlspecialchars($product['image_url']) ?>"
                             style="display: <?= $show_fallback_initially ? 'none' : 'block' ?>;">
                        <div id="product-image-fallback" class="rounded shadow-sm"
                             style="display: <?= $show_fallback_initially ? 'flex' : 'none' ?>;">
                            <i class="bi bi-image text-muted fs-1"></i>
                            <span class="ms-2 text-muted">Image non disponible</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <h1 class="mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                    <span class="badge bg-info mb-3"><?= htmlspecialchars($product['category_name'] ?? 'Non catégorisé') ?></span>

                    <?php if ($product['avg_rating']): ?>
                        <div class="mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= round($product['avg_rating']) ? '-fill text-warning' : '' ?>"></i>
                            <?php endfor; ?>
                            <small class="text-muted ms-2">(<?= number_format($product['avg_rating'], 1) ?> / <?= (int)$product['review_count'] ?> avis)</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-3"><small class="text-muted">Aucun avis pour le moment.</small></div>
                    <?php endif; ?>

                    <p class="lead text-secondary"><?= htmlspecialchars($product['description'] ?? '') ?></p>

                    <?php if (!empty($variants_by_color)): ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Couleur:</label>
                            <div id="color-options-container" class="d-flex flex-wrap">
                                <?php foreach ($variants_by_color as $color_key => $color_group):
                                    $is_initial_selected_color = ($color_key === $initial_selected_variant['color_name']);
                                    $variant_bg_style = '';
                                    if (!empty($color_group['image_url'])) {
                                        $variant_bg_style = "background-image: url('" . UPLOAD_PRODUCTS_URL . htmlspecialchars($color_group['image_url']) . "'); background-color: transparent;";
                                    } else {
                                        $variant_bg_style = "background-color: " . htmlspecialchars($color_group['hex_code'] ?? '#CCCCCC') . ";";
                                    }
                                ?>
                                    <div class="color-option <?= $is_initial_selected_color ? 'selected' : '' ?>"
                                        style="<?= $variant_bg_style ?>"
                                        data-color-name="<?= htmlspecialchars($color_group['color_name']) ?>"
                                        title="<?= htmlspecialchars($color_group['color_name']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small id="selected-color-name" class="text-muted mt-2">Couleur sélectionnée : <?= htmlspecialchars($initial_selected_variant['color_name'] ?? 'Original') ?></small>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($all_available_sizes)): ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Taille:</label>
                            <div id="size-options-container" class="d-flex flex-wrap">
                                <?php foreach ($all_available_sizes as $size):
                                    $is_initial_selected_size = ($size === $initial_selected_variant['size_name']);
                                ?>
                                    <div class="size-option btn btn-outline-secondary <?= $is_initial_selected_size ? 'active' : '' ?>"
                                        data-size-name="<?= htmlspecialchars($size) ?>">
                                        <?= htmlspecialchars($size) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($all_available_widths)): ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Largeur:</label>
                            <div id="width-options-container" class="d-flex flex-wrap">
                                <?php foreach ($all_available_widths as $width):
                                    $is_initial_selected_width = ($width === $initial_selected_variant['width_name']);
                                ?>
                                    <div class="width-option btn btn-outline-secondary <?= $is_initial_selected_width ? 'active' : '' ?>"
                                        data-width-name="<?= htmlspecialchars($width) ?>">
                                        <?= htmlspecialchars($width) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h2 class="text-primary mb-4"><span id="product-price"><?= number_format($product['current_price'], 2, ',', ' ') ?></span> €</h2>

                    <?php if ($product['current_stock_quantity'] > 0): ?>
                        <p class="text-success fw-bold" id="stock-status"><i class="bi bi-check-circle-fill"></i> En stock (<span id="stock-quantity"><?= (int)$product['current_stock_quantity'] ?></span> disponibles)</p>
                        <div class="d-flex align-items-center mb-4">
                            <label for="quantity" class="form-label me-2 mb-0">Quantité :</label>
                            <input type="number" id="quantity" class="form-control me-3" value="1" min="1" max="<?= (int)$product['current_stock_quantity'] ?>" style="width: 90px;">
                            <button class="btn btn-primary add-to-cart"
                                    data-product-id="<?= $product['id'] ?>"
                                    data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-base-price="<?= $product['price'] ?>"
                                    data-selected-variant-id="<?= $initial_selected_variant['id'] === null ? 'null' : htmlspecialchars($initial_selected_variant['id']) ?>">
                                <i class="bi bi-cart-plus"></i> Ajouter au panier
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-danger fw-bold" id="stock-status"><i class="bi bi-x-circle-fill"></i> Rupture de stock</p>
                        <div class="d-flex align-items-center mb-4">
                            <label for="quantity" class="form-label me-2 mb-0">Quantité :</label>
                            <input type="number" id="quantity" class="form-control me-3" value="1" min="1" max="0" style="width: 90px;" disabled>
                            <button class="btn btn-primary add-to-cart" disabled
                                    data-product-id="<?= $product['id'] ?>"
                                    data-product-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-base-price="<?= $product['price'] ?>"
                                    data-selected-variant-id="<?= $initial_selected_variant['id'] === null ? 'null' : htmlspecialchars($initial_selected_variant['id']) ?>">
                                <i class="bi bi-cart-plus"></i> Ajouter au panier
                            </button>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <h3>Avis des clients</h3>
                    <div class="reviews-section">
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted">Aucun avis pour le moment.</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php
                                            if (!empty($review['first_name']) && !empty($review['last_name'])) {
                                                echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']);
                                            } elseif (!empty($review['username'])) {
                                                echo htmlspecialchars($review['username']);
                                            } else {
                                                echo 'Utilisateur Inconnu';
                                            }
                                            ?>
                                            <small class="text-muted">- <?= formatDate($review['review_date'], 'd/m/Y') ?></small>
                                        </h6>
                                        <div>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= (int)$review['rating'] ? '-fill text-warning' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="card-text mt-2"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#reviewModal">Laisser un avis</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Laisser un avis pour <?= htmlspecialchars($product['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isLoggedIn()): ?>
                        <form id="reviewForm" action="<?= BASE_URL ?>submit_review.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="mb-3">
                                <label for="rating" class="form-label">Note</label>
                                <select class="form-select" id="rating" name="rating" required>
                                    <option value="">Sélectionnez une note</option>
                                    <option value="5">5 étoiles - Excellent</option>
                                    <option value="4">4 étoiles - Très bien</option>
                                    <option value="3">3 étoiles - Moyen</option>
                                    <option value="2">2 étoiles - Passable</option>
                                    <option value="1">1 étoile - Mauvais</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="comment" class="form-label">Votre commentaire</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Soumettre l'avis</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Vous devez être connecté pour laisser un avis. <a href="<?= BASE_URL ?>login.php">Se connecter</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productImageElement = document.getElementById('product-image');
            const productImageFallback = document.getElementById('product-image-fallback');
            const productPrice = document.getElementById('product-price');
            const stockStatus = document.getElementById('stock-status');
            const stockQuantitySpan = document.getElementById('stock-quantity');
            const quantityInput = document.getElementById('quantity');
            const addToCartButton = document.querySelector('.add-to-cart');
            const colorOptionsContainer = document.getElementById('color-options-container');
            const sizeOptionsContainer = document.getElementById('size-options-container'); // Nouveau
            const widthOptionsContainer = document.getElementById('width-options-container'); // Nouveau
            const selectedColorNameSpan = document.getElementById('selected-color-name');

            const currentProductId = <?= (int)$product['id'] ?>;
            const currentBasePrice = <?= $product['price'] ?>;

            // Données de toutes les variantes (y compris la "variante" principale)
            const allProductVariants = <?= $product_variants_json ?>;

            const UPLOAD_PRODUCTS_BASE_URL = '<?= UPLOAD_PRODUCTS_URL ?>';
            const DEFAULT_PRODUCT_IMAGE_URL = '<?= BASE_URL . 'assets/img/default_product_image.png' ?>';

            // État actuel des sélections
            let currentSelectedColor = '<?= htmlspecialchars($initial_selected_variant['color_name'] ?? 'Original') ?>';
            // Initialiser les tailles et largeurs sélectionnées à vide ou à leur valeur par défaut si présente sur la variante initiale
            let currentSelectedSize = '<?= htmlspecialchars($initial_selected_variant['size_name'] ?? '') ?>';
            let currentSelectedWidth = '<?= htmlspecialchars($initial_selected_variant['width_name'] ?? '') ?>';

            // Fonction pour trouver la variante correspondante basée sur les sélections actuelles
            function findMatchingVariant() {
                // Chercher la variante qui correspond EXACTEMENT à la combinaison couleur, taille, largeur sélectionnée
                let exactMatch = allProductVariants.find(v => {
                    const matchesColor = (v.color_name === currentSelectedColor);
                    const matchesSize = (v.size_name === currentSelectedSize || (currentSelectedSize === '' && v.size_name === ''));
                    const matchesWidth = (v.width_name === currentSelectedWidth || (currentSelectedWidth === '' && v.width_name === ''));
                    return matchesColor && matchesSize && matchesWidth;
                });

                if (exactMatch) {
                    return exactMatch;
                }

                // Logique de repli si aucune correspondance exacte n'est trouvée pour une sélection incomplète ou invalide:
                // Si la taille ou la largeur n'est pas sélectionnée, ou n'est plus valide pour la couleur,
                // on cherche la première variante disponible pour la couleur sélectionnée.
                let fallbackVariant = allProductVariants.find(v => v.color_name === currentSelectedColor);

                // Si même la couleur n'a pas de variante correspondante (cas peu probable avec la variante principale)
                if (!fallbackVariant) {
                    return allProductVariants.find(v => v.id === null); // Revenir au produit principal
                }
                return fallbackVariant;
            }


            // Fonction pour mettre à jour l'affichage du produit en fonction de la variante sélectionnée
            function updateProductDisplay(variant) {
                if (!variant) {
                    variant = allProductVariants.find(v => v.id === null); // S'assurer qu'on a toujours la "variante virtuelle" du produit principal
                }

                // Mise à jour de l'image
                const mainProductImageFilename = productImageElement ? productImageElement.dataset.mainProductImage : '';
                let imageToDisplayUrl = DEFAULT_PRODUCT_IMAGE_URL;

                if (variant && variant.image_url && variant.image_url.trim() !== '') {
                    imageToDisplayUrl = UPLOAD_PRODUCTS_BASE_URL + variant.image_url;
                } else if (mainProductImageFilename && mainProductImageFilename.trim() !== '') {
                    imageToDisplayUrl = UPLOAD_PRODUCTS_BASE_URL + mainProductImageFilename;
                }

                if (productImageElement) {
                    const isDefaultOrEmptyImage = imageToDisplayUrl === DEFAULT_PRODUCT_IMAGE_URL || imageToDisplayUrl.trim() === UPLOAD_PRODUCTS_BASE_URL || (!imageToDisplayUrl.endsWith('.png') && !imageToDisplayUrl.endsWith('.jpg') && !imageToDisplayUrl.endsWith('.jpeg') && !imageToDisplayUrl.endsWith('.gif') && !imageToDisplayUrl.endsWith('.webp'));
                    
                    if (isDefaultOrEmptyImage) {
                        productImageElement.style.display = 'none';
                        if (productImageFallback) productImageFallback.style.display = 'flex';
                    } else {
                        productImageElement.src = imageToDisplayUrl;
                        productImageElement.alt = `<?= htmlspecialchars($product['name']) ?> - ${variant ? variant.color_name : 'Produit Principal'}`;
                        productImageElement.style.display = 'block';
                        if (productImageFallback) productImageFallback.style.display = 'none';
                    }
                }

                // Mise à jour du prix
                const finalPrice = currentBasePrice + parseFloat(variant.price_adjustment);
                if (productPrice) {
                    productPrice.textContent = finalPrice.toFixed(2).replace('.', ',');
                }

                // Mise à jour du stock
                const stockQuantity = parseInt(variant.stock_quantity);
                if (stockStatus) {
                    if (stockQuantity > 0) {
                        stockStatus.className = 'text-success fw-bold';
                        stockStatus.innerHTML = '<i class="bi bi-check-circle-fill"></i> En stock (<span id="stock-quantity">' + stockQuantity + '</span> disponibles)';
                        if (quantityInput) {
                            quantityInput.max = stockQuantity;
                            quantityInput.value = Math.min(parseInt(quantityInput.value), stockQuantity);
                            quantityInput.disabled = false;
                        }
                        if (addToCartButton) {
                            addToCartButton.disabled = false;
                        }
                    } else {
                        stockStatus.className = 'text-danger fw-bold';
                        stockStatus.innerHTML = '<i class="bi bi-x-circle-fill"></i> Rupture de stock';
                        if (quantityInput) {
                            quantityInput.value = 0;
                            quantityInput.max = 0;
                            quantityInput.disabled = true;
                        }
                        if (addToCartButton) {
                            addToCartButton.disabled = true;
                        }
                    }
                }

                // Mise à jour du texte de la couleur sélectionnée
                if (selectedColorNameSpan) {
                    selectedColorNameSpan.textContent = `Couleur sélectionnée : ${variant.color_name || 'Original'}`;
                }

                // Mise à jour de l'ID de variante sur le bouton "Ajouter au panier"
                if (addToCartButton) {
                    addToCartButton.dataset.selectedVariantId = variant.id === null ? 'null' : variant.id;
                }

                // Mettre à jour la sélection visuelle des options de couleur
                document.querySelectorAll('.color-option').forEach(option => {
                    if (option.dataset.colorName === currentSelectedColor) {
                        option.classList.add('selected');
                    } else {
                        option.classList.remove('selected');
                    }
                });

                // Mettre à jour la sélection visuelle des options de taille
                document.querySelectorAll('.size-option').forEach(option => {
                    if (option.dataset.sizeName === currentSelectedSize) {
                        option.classList.add('active');
                    } else {
                        option.classList.remove('active');
                    }
                });

                // Mettre à jour la sélection visuelle des options de largeur
                document.querySelectorAll('.width-option').forEach(option => {
                    if (option.dataset.widthName === currentSelectedWidth) {
                        option.classList.add('active');
                    } else {
                        option.classList.remove('active');
                    }
                });
            }

            // Fonction pour mettre à jour l'état activé/désactivé des options de taille et largeur
            // en fonction de la couleur actuellement sélectionnée.
            function updateAvailableOptions() {
                const availableSizesForSelectedColor = new Set();
                const availableWidthsForSelectedColor = new Set();
                
                // Collecter les tailles et largeurs disponibles pour la couleur sélectionnée
                allProductVariants.forEach(variant => {
                    if (variant.color_name === currentSelectedColor) {
                        if (variant.size_name !== null && variant.size_name !== undefined && variant.size_name !== '') {
                            availableSizesForSelectedColor.add(variant.size_name);
                        }
                        if (variant.width_name !== null && variant.width_name !== undefined && variant.width_name !== '') {
                            availableWidthsForSelectedColor.add(variant.width_name);
                        }
                    }
                });

                // Mise à jour des boutons de taille
                if (sizeOptionsContainer) {
                    document.querySelectorAll('.size-option').forEach(option => {
                        const sizeName = option.dataset.sizeName;
                        if (availableSizesForSelectedColor.has(sizeName)) {
                            option.classList.remove('disabled');
                        } else {
                            option.classList.add('disabled');
                            // Si la taille sélectionnée n'est plus disponible pour cette couleur, la désélectionner
                            if (sizeName === currentSelectedSize) {
                                currentSelectedSize = ''; // Réinitialiser la sélection de taille
                            }
                        }
                    });
                }

                // Mise à jour des boutons de largeur
                if (widthOptionsContainer) {
                    document.querySelectorAll('.width-option').forEach(option => {
                        const widthName = option.dataset.widthName;
                        if (availableWidthsForSelectedColor.has(widthName)) {
                            option.classList.remove('disabled');
                        } else {
                            option.classList.add('disabled');
                            // Si la largeur sélectionnée n'est plus disponible, la désélectionner
                            if (widthName === currentSelectedWidth) {
                                currentSelectedWidth = ''; // Réinitialiser la sélection de largeur
                            }
                        }
                    });
                }
            }


            // Écouteurs d'événements
            if (colorOptionsContainer) {
                colorOptionsContainer.addEventListener('click', function(event) {
                    let target = event.target.closest('.color-option');
                    if (target) {
                        currentSelectedColor = target.dataset.colorName;
                        // Après avoir changé la couleur, mettez à jour les options de taille et largeur
                        updateAvailableOptions();
                        // Trouver et afficher la variante correspondant à la nouvelle combinaison
                        updateProductDisplay(findMatchingVariant());
                    }
                });
            }

            if (sizeOptionsContainer) {
                sizeOptionsContainer.addEventListener('click', function(event) {
                    let target = event.target.closest('.size-option');
                    if (target && !target.classList.contains('disabled')) { // Ne réagit que si l'option n'est pas désactivée
                        currentSelectedSize = target.dataset.sizeName;
                        updateProductDisplay(findMatchingVariant());
                    }
                });
            }

            if (widthOptionsContainer) {
                widthOptionsContainer.addEventListener('click', function(event) {
                    let target = event.target.closest('.width-option');
                    if (target && !target.classList.contains('disabled')) { // Ne réagit que si l'option n'est pas désactivée
                        currentSelectedWidth = target.dataset.widthName;
                        updateProductDisplay(findMatchingVariant());
                    }
                });
            }

            // Initialisation au chargement de la page
            updateAvailableOptions(); // Met à jour l'état des options de taille/largeur en fonction de la couleur initiale
            updateProductDisplay(findMatchingVariant()); // Affiche la variante initiale

            // Gestion du bouton "Ajouter au panier"
            if (addToCartButton) {
                addToCartButton.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const rawVariantId = this.dataset.selectedVariantId;
                    
                    let variantId = null;
                    if (rawVariantId !== 'null' && rawVariantId !== undefined && rawVariantId !== '') {
                        const parsed = parseInt(rawVariantId);
                        if (!isNaN(parsed) && parsed > 0) {
                            variantId = parsed;
                        }
                    }

                    const quantity = parseInt(quantityInput ? quantityInput.value : 1);

                    if (isNaN(quantity) || quantity < 1) {
                        showToast(translateMessage('[invalid_quantity]', "Veuillez entrer une quantité valide."), 'warning');
                        return;
                    }
                    
                    // Vérifier le stock de la variante actuellement affichée
                    const currentDisplayedVariant = findMatchingVariant();
                    if (!currentDisplayedVariant || currentDisplayedVariant.stock_quantity <= 0 || quantity > currentDisplayedVariant.stock_quantity) {
                        showToast(translateMessage('[out_of_stock_or_invalid_quantity]', "La quantité demandée n'est pas disponible en stock ou l'option n'est pas valide."), 'warning');
                        return;
                    }

                    // Envoyer la requête AJAX au serveur
                    fetch('<?= BASE_URL ?>add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest' // Indique que c'est une requête AJAX
                            // Le jeton CSRF est maintenant inclus dans le corps de la requête (voir ci-dessous)
                        },
                        body: new URLSearchParams({
                            product_id: productId,
                            variant_id: variantId, // Sera null si la variante principale est sélectionnée
                            quantity: quantity,
                            _csrf_token: document.querySelector('meta[name="csrf-token"]').getAttribute('content') // AJOUTÉ ICI
                        })
                    })
                    .then(response => {
                        // Vérifiez le statut de la réponse HTTP
                        if (!response.ok) {
                            // Si la réponse n'est pas OK (e.g., 400, 500), lisez l'erreur du corps de la réponse
                            return response.json().then(errorData => {
                                throw new Error(errorData.message || 'Erreur réseau ou serveur.');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            showToast(translateMessage('[add_to_cart_success]', data.message || "Produit ajouté au panier avec succès !"), 'success');
                            // Mettre à jour le compteur du panier dans le header
                            updateCartCount(data.cart_count);
                        } else {
                            showToast(translateMessage('[add_to_cart_error]', data.message || "Erreur lors de l'ajout au panier."), 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur AJAX:', error);
                        showToast(translateMessage('[ajax_error]', "Une erreur est survenue lors de l'opération."), 'danger');
                    });
                });
            }

            // Fonction pour mettre à jour le compteur du panier (si elle existe dans script.js)
            // Assurez-vous que cette fonction est globale ou accessible ici
            function updateCartCount(count) {
                const cartCountElement = document.getElementById('cart-count');
                if (cartCountElement) {
                    cartCountElement.textContent = count > 0 ? count : '';
                    cartCountElement.classList.toggle('d-none', count <= 0);
                }
            }
        });
    </script>
</body>
</html>