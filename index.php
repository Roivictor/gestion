<?php
// index.php
require_once 'config.php';
require_once 'includes/functions.php'; // Assurez-vous que ce fichier existe et contient `generateCsrfToken()` et `getProductCurrentPrice()` ainsi que `htmlspecialchars()`

// 2. Récupération des produits populaires
$popular_products_stmt = $pdo->query("
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.promo_price,
        p.promo_start_date,
        p.promo_end_date,
        c.name AS category_name,
        COALESCE(
            p.image_url,
            (SELECT pv_sub.image_url FROM product_variants pv_sub WHERE pv_sub.product_id = p.id AND pv_sub.image_url IS NOT NULL AND pv_sub.image_url != '' ORDER BY pv_sub.id ASC LIMIT 1)
        ) AS display_image_url,
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating
    FROM
        products p
    LEFT JOIN
        categories c ON p.category_id = c.id
    ORDER BY
        p.created_at DESC
    LIMIT 8
");

if ($popular_products_stmt === false) {
    error_log("Erreur SQL pour les produits populaires : " . print_r($pdo->errorInfo(), true));
    $popular_products = [];
} else {
    $popular_products = $popular_products_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Récupération des catégories principales
$main_categories_stmt = $pdo->query("
    SELECT * FROM categories WHERE parent_id IS NULL LIMIT 6
");
if ($main_categories_stmt === false) {
    error_log("Erreur SQL pour les catégories principales : " . print_r($pdo->errorInfo(), true));
    $main_categories = [];
} else {
    $main_categories = $main_categories_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <section class="hero-section bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold mb-3">Bienvenue dans notre boutique</h1>
                    <p class="lead mb-4">Découvrez nos produits de qualité à des prix imbattables.</p>
                    <a href="products.php" class="btn btn-primary btn-lg">Voir nos produits</a>
                </div>
                <div class="col-md-6">
                    <img src="images/vi.jpg" alt="Boutique en ligne" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <section class="categories-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Nos catégories</h2>
            <div class="row g-4">
                <?php if (!empty($main_categories)): ?>
                    <?php foreach ($main_categories as $category): ?>
                        <div class="col-md-4 col-lg-2">
                            <a href="products.php?category_id=<?= htmlspecialchars($category['id'], ENT_QUOTES, 'UTF-8') ?>" class="category-card text-decoration-none">
                                <div class="card h-100 border-0 shadow-sm text-center">
                                    <div class="card-body">
                                        <i class="bi bi-tag fs-1 text-primary"></i>
                                        <h5 class="mt-3"><?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">Aucune catégorie principale n'est disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="products-section py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h2>Nos produits populaires</h2>
                <a href="products.php" class="btn btn-outline-primary">Voir tous les produits</a>
            </div>

            <div class="row g-4">
                <?php if (!empty($popular_products)): ?>
                    <?php foreach ($popular_products as $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100 product-card">
                                <?php
                                $image_url = $product['display_image_url'];
                                $display_image_src = UPLOAD_URL . htmlspecialchars($image_url, ENT_QUOTES, 'UTF-8');
                                $image_exists_or_is_valid = !empty($image_url);
                                ?>

                                <?php if ($image_exists_or_is_valid): ?>
                                    <img src="<?= $display_image_src ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <i class="bi bi-image text-white fs-1"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title"><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h5>
                                        <span class="badge bg-info"><?= htmlspecialchars($product['category_name'] ?? 'Non catégorisé', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                    <?php if (!is_null($product['avg_rating'])): ?>
                                        <div class="mb-2">
                                            <?php
                                            $rounded_rating = round($product['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++):
                                            ?>
                                                <i class="bi bi-star<?= $i <= $rounded_rating ? '-fill text-warning' : '' ?>"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted">(<?= number_format($product['avg_rating'], 1) ?>)</small>
                                        </div>
                                    <?php endif; ?>

                                    <p class="card-text text-truncate"><?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>

                                <div class="card-footer bg-white border-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <?php $priceInfo = getProductCurrentPrice($product); ?>
                                        <div class="product-price">
                                            <?php if ($priceInfo['is_promo']): ?>
                                                <span class="text-muted text-decoration-line-through me-2">
                                                    <?= number_format($priceInfo['original_price'], 2, ',', ' ') ?> €
                                                </span>
                                                <span class="text-danger fw-bold">
                                                    <?= number_format($priceInfo['display_price'], 2, ',', ' ') ?> € </span>
                                                <span class="badge bg-success ms-2">PROMO!</span>
                                            <?php else: ?>
                                                <span class="fw-bold text-primary">
                                                    <?= number_format($priceInfo['display_price'], 2, ',', ' ') ?> € </span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="product.php?id=<?= htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary add-to-cart"
                                                data-product-id="<?= htmlspecialchars($product['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-base-price="<?= htmlspecialchars($priceInfo['display_price'], ENT_QUOTES, 'UTF-8') ?>" title="Ajouter au panier">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">Aucun produit populaire n'est disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>