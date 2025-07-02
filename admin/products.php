<?php
// products.php - Gestion des produits dans le tableau de bord admin

// IMPORTANT : Démarrer la session au tout début.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php'; // Inclut la configuration et les fonctions utilitaires (PDO, isLoggedIn, verifyCsrfToken, sanitize)

// Supprime la fonction de traduction car elle n'est plus utilisée.
// if (!function_exists('__')) {
//     function __($key, $default = '') {
//         global $lang;
//         return isset($lang[$key]) ? $lang[$key] : $default;
//     }
// }

// checkAdminRole() est normalement appelé dans dashboard.php avant d'inclure ce fichier.
// Si ce fichier pouvait être accédé directement, une vérification ici serait nécessaire.
// Pour l'instant, je pars du principe que l'accès est via dashboard.php.

// --- Gestion des actions (suppression) ---
// Note: La suppression devrait idéalement utiliser une requête POST avec un token CSRF
// pour une meilleure sécurité, surtout en production. Pour l'instant, on garde le GET pour la simplicité.
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = 'Produit supprimé avec succès.'; // Texte en dur
        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Erreur lors de la suppression du produit : ' . $e->getMessage(); // Texte en dur
            error_log("Erreur PDO lors de la suppression d'un produit (products.php): " . $e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = 'ID produit invalide.'; // Texte en dur
    }
    // Rediriger pour éviter la re-soumission de la suppression si la page est rafraîchie
    header("Location: dashboard.php?page=products");
    exit();
}

// --- Récupération des paramètres de filtre ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// --- Construction de la requête SQL pour récupérer les produits ---
$query = "SELECT p.*, c.name AS category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

$query .= " ORDER BY p.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC); // Toujours récupérer en tableau associatif
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des produits (products.php): " . $e->getMessage());
    $products = []; // En cas d'erreur, assurez-vous que $products est un tableau vide
    $_SESSION['error_message'] = 'Une erreur est survenue lors de la récupération des produits.'; // Texte en dur
}


// --- Récupérer les catégories pour le filtre (toujours nécessaires) ---
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des catégories (products.php): " . $e->getMessage());
    $categories = []; // En cas d'erreur, assurez-vous que $categories est un tableau vide
    // Pas besoin de message d'erreur utilisateur ici, car l'absence de catégories n'est pas bloquante.
}

// --- DÉTECTION AJAX ET RENVOI DE JSON ---
// Vérifie si la requête a été envoyée via AJAX (ex: par fetch API ou XMLHttpRequest)
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode([
        'products' => $products,
        'success_message' => isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null,
        'error_message' => isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null,
    ]);
    // Nettoyer les messages de session après les avoir envoyés en JSON
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
    exit(); // Très important d'arrêter l'exécution après l'envoi du JSON
}

// --- DÉBUT DU RENDU HTML (si ce n'est pas une requête AJAX) ---
// Note: Le HTML de <head>, <body>, sidebar etc. est géré par dashboard.php
// Ce fichier est inclus DANS le <body> de dashboard.php
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des produits</h1> <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php?page=add_product" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ajouter un produit </a>
    </div>
</div>

<?php 
// Affichage des messages de session (pour les rechargements complets de page)
// Les messages AJAX sont gérés par le JSON ci-dessus
if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form id="filterProductsForm" method="GET" class="row g-3">
            <input type="hidden" name="page" value="products"> 

            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label> <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <label for="category_id" class="form-label">Catégorie</label> <select class="form-select" id="category_id" name="category_id">
                    <option value="0">Toutes les catégories</option> <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel"></i> Filtrer </button>
                <a href="dashboard.php?page=products" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th> <th>Image</th> <th>Nom</th> <th>Catégorie</th> <th>Prix</th> <th>Stock</th> <th>Date création</th> <th>Actions</th> </tr>
                </thead>
                <tbody id="productsTableBody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Aucun produit trouvé</td> </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id']) ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="../uploads/products/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="50">
                                    <?php else: ?>
                                        <span class="text-muted">Aucune image</span> <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= $product['category_name'] ? htmlspecialchars($product['category_name']) : 'Aucune' ?></td> <td><?= number_format($product['price'], 2) ?> €</td>
                                <td>
                                    <span class="badge <?= $product['stock_quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= htmlspecialchars($product['stock_quantity']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
                                <td>
                                    <a href="dashboard.php?page=edit_product&id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifier"> <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="dashboard.php?page=products&action=delete&id=<?= $product['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')" title="Supprimer"> <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>