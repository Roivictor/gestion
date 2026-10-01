<?php
// products.php - Version Améliorée et optimisée

require_once 'config.php'; // Inclut le fichier de configuration (connexion DB, fonctions utilitaires, PHPMailer)
require_once 'includes/functions.php'; // Assurez-vous que ce fichier existe et contient `getProductCurrentPrice()` et `sanitize()`

// Vérifier que la connexion PDO est bien établie
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Si $pdo n'est pas disponible, rediriger ou afficher une erreur critique.
    // Pour une application en production, un message d'erreur générique est préférable.
    error_log("Erreur critique: La connexion à la base de données (PDO) n'est pas disponible dans products.php.");
    // setFlashMessage('danger', 'Une erreur est survenue lors du chargement des produits. Veuillez réessayer plus tard.');
    // redirect(BASE_URL . 'error.php'); // Ou vers une page d'erreur générique
    die("Site temporairement indisponible. Veuillez réessayer plus tard."); // Pour débogage, mais à remplacer en production
}

// 3. Récupération et nettoyage des paramètres de filtrage (GET)
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// 4. Construction de la requête SQL de base
// L'objectif est d'afficher chaque produit une seule fois, avec une image et un prix "par défaut" (de sa variante la plus simple/première)
// tout en permettant de le filtrer et de le trier.
$query = "SELECT 
            p.id,
            p.name,
            p.description,
            p.price, -- Prix de base du produit
            p.promo_price, -- NOUVEAU : Récupère le prix promotionnel du produit
            p.promo_start_date, -- NOUVEAU : Récupère la date de début de promotion
            p.promo_end_date, -- NOUVEAU : Récupère la date de fin de promotion
            c.name AS category_name,
            -- Sous-requête pour récupérer l'image de la variante par défaut (celle avec le plus petit ID et en stock)
            COALESCE(
                p.image_url, -- On préfère l'image principale du produit si elle existe
                (SELECT pv.image_url FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0 AND pv.image_url IS NOT NULL AND pv.image_url != '' ORDER BY pv.id ASC LIMIT 1)
            ) AS display_image_url,
            -- Sous-requête pour récupérer le price_adjustment de la variante par défaut
            (SELECT pv.price_adjustment FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0 ORDER BY pv.id ASC LIMIT 1) AS variant_price_adjustment,
            -- Sous-requête pour récupérer l'ID de la variante par défaut pour l'ajout au panier rapide
            (SELECT pv.id FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0 ORDER BY pv.id ASC LIMIT 1) AS default_variant_id,
            -- Agrégations pour les avis (inchangées)
            (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') AS review_count
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE 1=1 "; // Clause WHERE de base, toujours vraie pour ajouter des conditions facilement
$params = []; // Tableau pour stocker les paramètres des requêtes préparées

// Filtrer uniquement les produits qui ont au moins une variante en stock (ou le produit lui-même si pas de variantes et stock > 0)
// Ajustement de la logique de stock pour les produits sans variantes
$query .= " AND (EXISTS (SELECT 1 FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0) OR (NOT EXISTS (SELECT 1 FROM product_variants WHERE product_id = p.id) AND p.stock_quantity > 0)) ";


// 5. Ajout des conditions de filtrage dynamiquement
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    // Filtrer par catégorie, incluant les produits de sous-catégories
    // Assurez-vous que votre table categories a une colonne 'parent_id' pour les sous-catégories
    $query .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_id;
    $params[] = $category_id;
}

// Pour les filtres de prix, nous devons évaluer le prix actuel du produit
// C'est plus complexe car le prix actuel dépend des dates de promotion.
// Nous allons donc filtrer après la récupération des données ou ajuster la requête
// pour inclure cette logique côté SQL si le volume de données le justifie.
// Pour l'instant, on laisse le filtre sur le price_adjustment de la variante par défaut
// comme c'était, mais il faut être conscient que cela ne tient pas compte des promos.
// Une solution plus robuste serait d'incorporer la logique de getProductCurrentPrice dans la requête SQL elle-même
// ou de filtrer en PHP après la récupération. Pour la simplicité et éviter des requêtes SQL très complexes,
// on maintiendra le filtre sur `variant_price_adjustment` mais on ajustera son affichage.

// Note: Pour filtrer sur le prix _actuel_ (promo incluse), il faudrait une sous-requête plus complexe
// ou filtrer les résultats après les avoir récupérés. Laisser tel quel filtre sur le prix de base de la variante.
// Pour une logique de filtrage incluant les promotions SQL, ce serait plus complexe et potentiellement lourd.
// La solution actuelle filtre sur le prix de base du produit + ajustement de la variante par défaut.

// Fonction pour déterminer le prix actuel (promo ou normal) en SQL si nécessaire
// C'est une simplification pour SQL, pour une logique complète comme getProductCurrentPrice,
// il faudrait une fonction SQL personnalisée ou filtrer en PHP.
$current_date_sql = date('Y-m-d H:i:s');
$price_expression = "COALESCE(
    CASE
        WHEN p.promo_price IS NOT NULL AND p.promo_price > 0 AND p.promo_start_date <= '$current_date_sql' AND p.promo_end_date >= '$current_date_sql'
        THEN p.promo_price
        ELSE p.price
    END, p.price
) + COALESCE((SELECT pv.price_adjustment FROM product_variants pv WHERE pv.product_id = p.id AND pv.stock_quantity > 0 ORDER BY pv.id ASC LIMIT 1), 0)";


if ($min_price > 0) {
    $query .= " AND " . $price_expression . " >= ?";
    $params[] = $min_price;
}

if ($max_price > 0 && $max_price >= $min_price) { // max_price doit être supérieur ou égal à min_price
    $query .= " AND " . $price_expression . " <= ?";
    $params[] = $max_price;
}


// 6. Ajout du tri dynamique
switch ($sort) {
    case 'price_asc':
        // Tri par prix, en tenant compte du prix de base du produit, de l'ajustement de la variante ET de la promo
        $query .= " ORDER BY " . $price_expression . " ASC";
        break;
    case 'price_desc':
        // Tri par prix, en tenant compte du prix de base du produit, de l'ajustement de la variante ET de la promo
        $query .= " ORDER BY " . $price_expression . " DESC";
        break;
    case 'rating':
        $query .= " ORDER BY avg_rating DESC, p.created_at DESC";
        break;
    case 'popular':
        $query .= " ORDER BY (SELECT COUNT(*) FROM order_details WHERE product_id = p.id) DESC, p.created_at DESC";
        break;
    default: // 'newest'
        $query .= " ORDER BY p.created_at DESC";
}

// 7. Exécution de la requête préparée
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC); // Utiliser FETCH_ASSOC pour un accès facile par nom de colonne

// 8. Récupération des catégories pour le filtre
// Récupérer toutes les catégories, les organiser par parent_id en PHP pour un affichage hiérarchique si désiré
$categories_stmt = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY name");
$all_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Optionnel: Organiser les catégories en arborescence pour la liste déroulante
$nested_categories = [];
foreach ($all_categories as $cat) {
    if ($cat['parent_id'] === null) {
        $nested_categories[$cat['id']] = $cat;
        $nested_categories[$cat['id']]['children'] = [];
    } else {
        // Pour les sous-catégories, nous pourrions les lister avec un préfixe dans la dropdown
        // ou les attacher à leur parent si nous construisons un arbre plus complexe.
        // Pour l'instant, pour une simple liste déroulante, cela est suffisant.
        // Si vous voulez afficher les sous-catégories indentées:
        // if (isset($nested_categories[$cat['parent_id']])) {
        //     $nested_categories[$cat['parent_id']]['children'][] = $cat;
        // }
    }
}
// Pour la simplicité de la dropdown, on peut aussi aplatir ou afficher juste les parents directs.
// Ici, on utilisera $all_categories directement si une hiérarchie n'est pas affichée spécifiquement.
// Si vous voulez seulement les catégories de niveau supérieur pour le filtre principal:
$top_level_categories = array_filter($all_categories, fn($cat) => $cat['parent_id'] === null);


// 9. Récupération du prix max/min pour le slider de filtre de prix
// La plage doit tenir compte des prix promotionnels si actifs, mais une requête complexe ici peut être lourde.
// Pour une plage représentative, on peut prendre min/max des prix de base + ajustements des variantes.
// Le filtrage réel sera fait par la clause WHERE avec $price_expression.
$price_range_query = "SELECT 
                          MIN(" . $price_expression . ") AS min_overall_price, 
                          MAX(" . $price_expression . ") AS max_overall_price
                      FROM products p
                      LEFT JOIN product_variants pv ON p.id = pv.product_id"; // Utilisation de LEFT JOIN pour inclure les produits sans variantes
$price_range_stmt = $pdo->prepare($price_range_query);
$price_range_stmt->execute();
$price_range = $price_range_stmt->fetch(PDO::FETCH_ASSOC);

// Assurez-vous que les valeurs min et max sont numériques
$min_overall_price = floor($price_range['min_overall_price'] ?? 0);
$max_overall_price = ceil($price_range['max_overall_price'] ?? 100);

// Ajuster les valeurs des inputs si elles sont hors de la nouvelle plage
if ($min_price < $min_overall_price) $min_price = $min_overall_price;
if ($max_price > $max_overall_price || $max_price == 0) $max_price = $max_overall_price; // Si max_price est 0, ou trop élevé, le fixer au max réel

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/favicon.png"> 
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Filtrer les produits</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filter-form">
                                <div class="mb-3">
                                    <label for="search" class="form-label">Recherche</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Catégorie</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="0">Toutes les catégories</option>
                                        <?php foreach ($top_level_categories as $category): // Utiliser $all_categories si vous gérez les sous-catégories dans la boucle ?>
                                            <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Prix</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="number" class="form-control" placeholder="Min" name="min_price" value="<?= $min_price ?>" min="<?= $min_overall_price ?>" max="<?= $max_overall_price ?>">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control" placeholder="Max" name="max_price" value="<?= $max_price ?>" min="<?= $min_overall_price ?>" max="<?= $max_overall_price ?>">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <input type="range" class="form-range" id="price-range" 
                                               min="<?= $min_overall_price ?>" 
                                               max="<?= $max_overall_price ?>" step="1"
                                               value="<?= $max_price ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="sort" class="form-label">Trier par</label>
                                    <select class="form-select" id="sort" name="sort">
                                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Nouveautés</option>
                                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Meilleures notes</option>
                                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Plus populaires</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
                                <a href="products.php" class="btn btn-outline-secondary w-100 mt-2">Réinitialiser</a>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Nos produits</h2>
                        <div class="d-flex">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary active" id="grid-view">
                                    <i class="bi bi-grid"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="list-view">
                                    <i class="bi bi-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($products)): ?>
                        <div class="alert alert-info">
                            Aucun produit trouvé avec ces critères de recherche.
                        </div>
                    <?php else: ?>
                        <div class="row" id="products-container">
                            <?php foreach ($products as $product): ?>
                                <div class="col-md-6 col-xl-4 mb-4 product-item">
                                    <div class="card h-100">
                                        <?php 
                                        // Affiche l'image du produit principal, ou de la variante par défaut, ou une image par défaut
                                        $display_image = !empty($product['display_image_url']) ? UPLOAD_URL . htmlspecialchars($product['display_image_url']) : 'assets/img/placeholder.jpg'; // Assurez-vous d'avoir une image placeholder et UPLOAD_URL configuré
                                        ?>
                                        <img src="<?= $display_image ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">

                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                                                <span class="badge bg-info"><?= htmlspecialchars($product['category_name'] ?? 'Non catégorisé') ?></span>
                                            </div>

                                            <?php if ($product['avg_rating']): ?>
                                                <div class="mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star<?= $i <= round($product['avg_rating']) ? '-fill text-warning' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="text-muted">(<?= number_format($product['avg_rating'], 1) ?>)</small>
                                                </div>
                                            <?php endif; ?>

                                            <p class="card-text">
                                                <?= htmlspecialchars(mb_strimwidth($product['description'] ?? '', 0, 100, '...')) ?>
                                            </p>
                                        </div>

                                        <div class="card-footer bg-white border-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <?php 
                                                // Pour le calcul du prix du produit dans la liste, on combine le prix de base du produit
                                                // avec l'ajustement de la variante par défaut.
                                                // Ensuite, on applique la logique de promotion du produit principal.
                                                $base_price_for_display = $product['price'] + ($product['variant_price_adjustment'] ?? 0);
                                                $product_with_adjusted_price = array_merge($product, ['price' => $base_price_for_display]);
                                                $priceInfo = getProductCurrentPrice($product_with_adjusted_price);
                                                ?>
                                                <div class="product-price">
                                                    <?php if ($priceInfo['is_promo']): ?>
                                                        <span class="text-muted text-decoration-line-through me-2">
                                                            <?= number_format($priceInfo['original_price'], 2, ',', ' ') ?> €
                                                        </span>
                                                        <span class="text-danger fw-bold">
                                                            <?= number_format($priceInfo['display_price'], 2, ',', ' ') ?> €
                                                        </span>
                                                        <span class="badge bg-success ms-2">PROMO!</span>
                                                    <?php else: ?>
                                                        <span class="fw-bold text-primary">
                                                            <?= number_format($priceInfo['display_price'], 2, ',', ' ') ?> €
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <a href="<?= BASE_URL ?>product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir les détails du produit">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($product['default_variant_id']): // Affiche le bouton si une variante par défaut existe ?>
                                                        <button class="btn btn-sm btn-primary add-to-cart" 
                                                                data-product-id="<?= $product['id'] ?>" 
                                                                data-variant-id="<?= $product['default_variant_id'] ?>" 
                                                                title="Ajouter au panier cette version">
                                                            <i class="bi bi-cart-plus"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="Produit indisponible pour l'achat rapide">
                                                            <i class="bi bi-cart-x"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script> 
    <script>
        // 11. Gestion des vues (grille/liste) avec JavaScript
        document.getElementById('grid-view').addEventListener('click', function() {
            document.getElementById('products-container').classList.remove('list-view');
            this.classList.add('active');
            document.getElementById('list-view').classList.remove('active');
        });

        document.getElementById('list-view').addEventListener('click', function() {
            document.getElementById('products-container').classList.add('list-view');
            this.classList.add('active');
            document.getElementById('grid-view').classList.remove('active');
        });

        // 12. Gestion du filtre par prix (interaction entre le slider et l'input numérique)
        const priceRange = document.getElementById('price-range');
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');

        // Mettre à jour l'input max_price quand le slider bouge
        priceRange.addEventListener('input', function() {
            maxPriceInput.value = this.value;
        });

        // Mettre à jour le slider quand l'input max_price change
        maxPriceInput.addEventListener('change', function() {
            // Assurez-vous que max est toujours supérieur ou égal à min
            if (parseFloat(this.value) < parseFloat(minPriceInput.value)) {
                this.value = minPriceInput.value; 
            }
            priceRange.value = this.value || priceRange.max; // Si vide, se rabattre sur le max du range
        });

        // Mettre à jour le slider quand l'input min_price change
        minPriceInput.addEventListener('change', function() {
            // Assurez-vous que min est toujours inférieur ou égal à max
            if (parseFloat(this.value) > parseFloat(maxPriceInput.value)) {
                this.value = maxPriceInput.value; 
            }
            // Le slider représente le max, donc on ne le change pas directement par minPriceInput
            // mais on s'assure que maxPriceInput est cohérent avec minPriceInput si nécessaire
        });
    </script>
</body>
</html>