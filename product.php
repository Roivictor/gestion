<?php
session_start(); // Démarrage de la session
require_once 'config.php'; // Inclusion du fichier de configuration (connexion DB, fonctions utilitaires, etc.)

// Activez l'affichage des erreurs pour le débogage (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier si un ID de produit est passé dans l'URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Si aucun ID n'est fourni, rediriger l'utilisateur vers la page des produits
    header('Location: products.php?error=no_product_id');
    exit;
}

// Récupérer et nettoyer l'ID du produit de manière sécurisée
$product_id = (int)$_GET['id']; // Convertir en entier pour prévenir les injections SQL

// Requête SQL pour récupérer les détails du produit unique
$query = "SELECT p.*, c.name AS category_name,
                 (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') AS avg_rating,
                 (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') AS review_count
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          WHERE p.id = ?"; // Critère de recherche spécifique par ID

try {
    // Préparer et exécuter la requête
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC); // Récupérer un seul résultat
} catch (PDOException $e) {
    // Gérer les erreurs de base de données (pour le débogage)
    error_log("Erreur PDO dans product.php: " . $e->getMessage()); // Écrit l'erreur dans les logs du serveur
    echo "<div class='alert alert-danger'>Une erreur est survenue lors de la récupération des données du produit. Veuillez réessayer plus tard.</div>";
    // En production, il est préférable de ne pas afficher l'erreur directement à l'utilisateur
    // header('Location: error_page.php'); // Rediriger vers une page d'erreur générique
    exit;
}

// Vérifier si le produit a été trouvé
if (!$product) {
    // Si aucun produit n'est trouvé avec cet ID, rediriger ou afficher un message d'erreur 404
    header('Location: products.php?error=product_not_found');
    exit;
}

// Le produit a été trouvé, on peut maintenant afficher ses détails
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css"></head>
<body>
    <?php include 'includes/header.php'; ?> <main class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mb-4 mb-md-0">
                    <?php if ($product['image_url']): ?>
                        <img src="uploads/products/<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow-sm" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center rounded shadow-sm" style="height: 400px;">
                            <i class="bi bi-image text-muted fs-1"></i>
                            <span class="ms-2 text-muted">Image non disponible</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h1 class="mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                    <span class="badge bg-info mb-3"><?= htmlspecialchars($product['category_name'] ?? 'Non catégorisé') ?></span>

                    <?php if ($product['avg_rating']): ?>
                        <div class="mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= round($product['avg_rating']) ? '-fill text-warning' : '' ?>"></i>
                            <?php endfor; ?>
                            <small class="text-muted ms-2">(<?= number_format($product['avg_rating'], 1) ?> / <?= $product['review_count'] ?> avis)</small>
                        </div>
                    <?php else: ?>
                         <div class="mb-3"><small class="text-muted">Aucun avis pour le moment.</small></div>
                    <?php endif; ?>

         <p class="lead text-secondary"><?= htmlspecialchars($product['description'] ?? '') ?></p>

                    <h2 class="text-primary mb-4"><?= number_format($product['price'], 2) ?> €</h2>

                    <?php if ($product['stock_quantity'] > 0): ?>
                        <p class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> En stock (<?= (int)$product['stock_quantity'] ?> disponibles)</p>
                        <div class="d-flex align-items-center mb-4">
                            <label for="quantity" class="form-label me-2 mb-0">Quantité:</label>
                            <input type="number" id="quantity" class="form-control me-3" value="1" min="1" max="<?= (int)$product['stock_quantity'] ?>" style="width: 90px;">
                            <button class="btn btn-primary add-to-cart" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>">
                                <i class="bi bi-cart-plus"></i> Ajouter au panier
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-danger fw-bold"><i class="bi bi-x-circle-fill"></i> Rupture de stock</p>
                    <?php endif; ?>

                    <hr class="my-4">

                    <h3>Avis des clients</h3>
                    <div class="reviews-section">
                        <p class="text-muted">Aucun avis affiché pour le moment. Soyez le premier à laisser un avis !</p>
                        <button class="btn btn-outline-secondary mt-3">Laisser un avis</button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script> </body>
</html>