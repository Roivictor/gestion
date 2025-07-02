<?php
// products.php - Version Finale et optimisée

// RETIREZ OU METTEZ EN COMMENTAIRE CETTE LIGNE : session_start();
// session_start(); // <= Cette ligne est redondante car config.php la gère.

require_once 'config.php'; // 2. Inclut le fichier de configuration (connexion DB, fonctions utilitaires, PHPMailer)

// 3. Récupération et nettoyage des paramètres de filtrage (GET)
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0; // Convertit en entier pour sécurité
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0; // Convertit en float pour sécurité
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 0; // Convertit en float pour sécurité
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest'; // 'newest' par défaut

// 4. Construction de la requête SQL de base
$query = "SELECT p.*, c.name AS category_name,
                 (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
                 (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') AS review_count
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.stock_quantity > 0"; // Ne montre que les produits en stock
$params = []; // Tableau pour stocker les paramètres des requêtes préparées

// 5. Ajout des conditions de filtrage dynamiquement
if (!empty($search)) {
    // Recherche par nom, description ou nom de catégorie
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    // Filtrer par catégorie, incluant les produits de sous-catégories (si c.parent_id est la catégorie actuelle)
    // NOTE: Si vos catégories n'ont pas de parent_id ou si la logique de sous-catégories est différente,
    // ajustez cette partie. Pour l'instant, cela suppose que les produits sont directement liés
    // à une catégorie ou à sa catégorie parente via c.parent_id.
    $query .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_id;
    $params[] = $category_id;
}

if ($min_price > 0) {
    // Prix minimum
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0 && $max_price > $min_price) {
    // Prix maximum (vérifie aussi qu'il est supérieur au min_price)
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

// 6. Ajout du tri dynamique
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'rating':
        // Tri par meilleure note (avg_rating est calculé par une sous-requête)
        // Les produits sans avis seront traités différemment. Vous pouvez ajouter IS NOT NULL pour les exclure.
        $query .= " ORDER BY avg_rating DESC, p.created_at DESC"; // Ajout de created_at pour départager
        break;
    case 'popular':
        // Tri par popularité (basé sur le nombre de fois qu'un produit a été commandé)
        $query .= " ORDER BY (SELECT COUNT(*) FROM order_details WHERE product_id = p.id) DESC, p.created_at DESC";
        break;
    default:
        // Tri par défaut : les plus récents
        $query .= " ORDER BY p.created_at DESC";
}

// 7. Exécution de la requête préparée
$stmt = $pdo->prepare($query); // Prépare la requête SQL pour la sécurité (prévention des injections SQL)
$stmt->execute($params); // Exécute la requête en liant les paramètres
$products = $stmt->fetchAll(); // Récupère tous les résultats

// 8. Récupération des catégories pour le filtre (menu déroulant)
// Affiche seulement les catégories de premier niveau. Si vous voulez toutes les catégories, ajustez la requête.
$categories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// 9. Récupération du prix max pour le slider de filtre de prix
$price_range = $pdo->query("SELECT MIN(price) AS min_price, MAX(price) AS max_price FROM products")->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="js/css/style.css" rel="stylesheet">

    <?php include 'includes/header.php'; // Inclut la balise meta CSRF si elle est dans header.php ?>
    </head>
<body>
    <?php // Si header.php ne contient que la balise meta CSRF, déplacez l'inclusion du reste de l'en-tête ici si nécessaire.
          // Si header.php contient déjà le <body> d'ouverture et le nav, déplacez cette ligne au-dessus de <body>. ?>

    <main class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Filtrer les produits</h5>
                        </div>
                        <div class="card-body">
                            <form method="GET" id="filter-form"> <div class="mb-3">
                                    <label for="search" class="form-label">Recherche</label>
                                    <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Catégorie</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="0">Toutes les catégories</option>
                                        <?php foreach ($categories as $category): ?>
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
                                            <input type="number" class="form-control" placeholder="Min" name="min_price" value="<?= $min_price ?>">
                                        </div>
                                        <div class="col">
                                            <input type="number" class="form-control" placeholder="Max" name="max_price" value="<?= $max_price ?>">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <input type="range" class="form-range" id="price-range" min="0" max="<?= ceil($price_range['max_price']) ?>" step="1"
                                               value="<?= $max_price ? $max_price : ceil($price_range['max_price']) ?>">
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
                                    <?php if ($product['image_url']): ?>
                                        <img src="uploads/products/<?= htmlspecialchars($product['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="bi bi-image text-white fs-1"></i>
                                        </div>
                                    <?php endif; ?>

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

                                        <p class="card-text"><?= htmlspecialchars($product['description'] ?? '') ?></p> </div>

                                    <div class="card-footer bg-white border-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-primary"><?= number_format($product['price'], 2) ?> €</span>
                                            <div>
                                               <a href="<?= BASE_URL ?>product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-primary add-to-cart" data-id="<?= $product['id'] ?>">
                                                    <i class="bi bi-cart-plus"></i>
                                                </button>
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
            document.getElementById('products-container').classList.remove('list-view'); // Supprime la classe 'list-view'
            this.classList.add('active'); // Active le bouton 'grille'
            document.getElementById('list-view').classList.remove('active'); // Désactive le bouton 'liste'
        });

        document.getElementById('list-view').addEventListener('click', function() {
            document.getElementById('products-container').classList.add('list-view'); // Ajoute la classe 'list-view'
            this.classList.add('active'); // Active le bouton 'liste'
            document.getElementById('grid-view').classList.remove('active'); // Désactive le bouton 'grille'
        });

        // 12. Gestion du filtre par prix (interaction entre le slider et l'input numérique)
        const priceRange = document.getElementById('price-range');
        const maxPriceInput = document.querySelector('input[name="max_price"]');

        priceRange.addEventListener('input', function() {
            // Quand le slider est déplacé, met à jour la valeur de l'input max_price
            maxPriceInput.value = this.value;
        });

        maxPriceInput.addEventListener('change', function() {
            // Quand l'input max_price est changé, met à jour la valeur du slider
            // Utilise priceRange.max si l'input est vide, pour éviter une valeur nulle
            priceRange.value = this.value || priceRange.max;
        });
    </script>
</body>
</html>