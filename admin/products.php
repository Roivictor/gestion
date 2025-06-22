<?php
require_once '../config.php';
checkRole(['admin']);

// Gestion des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    try {
        if ($action === 'delete' && $id > 0) {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Produit supprimé avec succès.";
            redirect('products.php');
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de la suppression: " . $e->getMessage();
        redirect('products.php');
    }
}

// Récupérer la liste des produits
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

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

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Récupérer les catégories pour le filtre
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des produits - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/admin.css" rel="stylesheet">
</head>
<body>
    
    <div class="container-fluid">
        <div class="row">
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestion des produits</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="add_product.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Ajouter un produit
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['success_message'] ?>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error_message'] ?>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Recherche</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-funnel"></i> Filtrer
                                </button>
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Liste des produits -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Nom</th>
                                        <th>Catégorie</th>
                                        <th>Prix</th>
                                        <th>Stock</th>
                                        <th>Date création</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucun produit trouvé</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?= $product['id'] ?></td>
                                            <td>
                                                <?php if ($product['image_url']): ?>
                                                    <img src="../uploads/products/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" width="50">
                                                <?php else: ?>
                                                    <span class="text-muted">Aucune image</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= $product['category_name'] ? htmlspecialchars($product['category_name']) : 'Aucune' ?></td>
                                            <td><?= number_format($product['price'], 2) ?> €</td>
                                            <td>
                                                <span class="badge <?= $product['stock_quantity'] > 0 ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $product['stock_quantity'] ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($product['created_at'])) ?></td>
                                            <td>
                                                <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="products.php?action=delete&id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit?')">
                                                    <i class="bi bi-trash"></i>
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
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/admin.js"></script>
</body>
</html>