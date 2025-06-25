<?php
require_once 'config.php';

// 2. Récupération des produits populaires
$popular_products = $pdo->query("
    SELECT p.*, c.name AS category_name,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();
// EXPLICATION :
// - '$pdo->query(...)': Exécute une requête SQL directement. Pour des requêtes simples sans paramètres utilisateurs, c'est acceptable.
// - 'SELECT p.*, c.name AS category_name': Sélectionne toutes les colonnes de la table 'products' (alias 'p') et le nom de la catégorie (alias 'c.name') sous le nom 'category_name'.
// - '(SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating': Une sous-requête corrélée qui calcule la moyenne des notes (ratings) pour chaque produit, mais seulement pour les avis dont le statut est 'approved'. C'est une excellente façon d'afficher des notes pertinentes.
// - 'FROM products p LEFT JOIN categories c ON p.category_id = c.id': Joint la table 'products' avec la table 'categories' pour récupérer le nom de la catégorie associée à chaque produit. Le LEFT JOIN assure que même les produits sans catégorie (si c'est possible) sont inclus.
// - 'ORDER BY p.created_at DESC': Trie les produits par date de création, du plus récent au plus ancien.
// - 'LIMIT 8': Ne récupère que les 8 premiers produits, ce qui est typique pour une section "produits populaires" ou "nouveautés" sur une page d'accueil.
// - '->fetchAll()': Récupère tous les résultats de la requête sous forme de tableau d'objets ou de tableaux associatifs (dépend de PDO::ATTR_DEFAULT_FETCH_MODE dans config.php, ici c'est FETCH_ASSOC).

// 3. Récupération des catégories principales
$main_categories = $pdo->query("
    SELECT * FROM categories WHERE parent_id IS NULL LIMIT 6
")->fetchAll();
// EXPLICATION :
// - 'SELECT * FROM categories': Sélectionne toutes les colonnes de la table 'categories'.
// - 'WHERE parent_id IS NULL': Filtre pour ne récupérer que les catégories de "niveau supérieur" (celles qui n'ont pas de catégorie parente). C'est idéal pour afficher les catégories principales sur la page d'accueil.
// - 'LIMIT 6': Limite à 6 catégories pour l'affichage.
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accueil - <?= SITE_NAME ?></title> <!-- 4. Titre dynamique de la page -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/> <!-- 5. Liens CSS externes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> <!-- Votre CSS personnalisé -->
</head>
<body>
    <?php include 'includes/header.php'; ?> <!-- 6. Inclusion de l'en-tête (menu, logo, etc.) -->

    <!-- Section Hero (Bannière principale) -->
    <section class="hero-section bg-light py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-4 fw-bold mb-3">Bienvenue dans notre boutique</h1>
                    <p class="lead mb-4">Découvrez nos produits de qualité à des prix imbattables.</p>
                    <a href="products.php" class="btn btn-primary btn-lg">Voir nos produits</a>
                </div>
                <div class="col-md-6">
                    <img src="images/histoire.jpg" alt="Boutique en ligne" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Section Catégories -->
    <section class="categories-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Nos catégories</h2>
            <div class="row g-4">
                <?php foreach ($main_categories as $category): ?> <!-- 7. Boucle PHP pour afficher les catégories -->
                <div class="col-md-4 col-lg-2">
                    <a href="products.php?category_id=<?= $category['id'] ?>" class="category-card text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm text-center">
                            <div class="card-body">
                                <i class="bi bi-tag fs-1 text-primary"></i> <!-- Icône Bootstrap Icons -->
                                <h5 class="mt-3"><?= htmlspecialchars($category['name']) ?></h5> <!-- Affichage du nom de la catégorie -->
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Section Produits populaires -->
    <section class="products-section py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h2>Nos produits populaires</h2>
                <a href="products.php" class="btn btn-outline-primary">Voir tous les produits</a>
            </div>

            <div class="row g-4">
                <?php foreach ($popular_products as $product): ?> <!-- 8. Boucle PHP pour afficher les produits populaires -->
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 product-card">
                        <?php if ($product['image_url']): ?> <!-- Vérifie si une URL d'image existe -->
                            <img src="uploads/products/<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?> <!-- Image de remplacement si aucune image n'est disponible -->
                            <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                <i class="bi bi-image text-white fs-1"></i>
                            </div>
                        <?php endif; ?>

                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                <span class="badge bg-info"><?= htmlspecialchars($product['category_name'] ?? 'Non catégorisé') ?></span>
                            </div>

                            <?php if ($product['avg_rating']): ?> <!-- Affichage des étoiles de notation si une moyenne existe -->
                                <div class="mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <!-- Affichage d'une étoile pleine ou vide en fonction de la note moyenne -->
                                        <i class="bi bi-star<?= $i <= round($product['avg_rating']) ? '-fill text-warning' : '' ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted">(<?= number_format($product['avg_rating'], 1) ?>)</small>
                                </div>
                            <?php endif; ?>

                            <p class="card-text text-truncate"><?= htmlspecialchars($product['description']) ?></p>
                        </div>

                        <div class="card-footer bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary"><?= number_format($product['price'], 2) ?> €</span>
                                <div>
                                    <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> <!-- Icône pour voir le produit -->
                                    </a>
                                    <button class="btn btn-sm btn-primary add-to-cart" data-id="<?= $product['id'] ?>">
                                        <i class="bi bi-cart-plus"></i> <!-- Bouton pour ajouter au panier -->
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?> <!-- 9. Inclusion du pied de page -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- 10. Scripts JS externes (Bootstrap) -->
    <script src="js/script.js"></script> <!-- Votre script JS personnalisé -->
</body>
</html>