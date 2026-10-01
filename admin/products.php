<?php
// products.php - Gestion des produits dans le tableau de bord admin

// IMPORTANT : Démarrer la session au tout début.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php'; // Inclut la configuration et les fonctions utilitaires (PDO, sanitize, etc.)
require_once '../includes/functions.php'; // Assurez-vous que setFlashMessage, displayFlashMessages, sanitize et verifyCsrfToken sont définies ici.

// Vérifier si la variable $pdo (connexion à la base de données) est disponible
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur: La connexion PDO n'est pas disponible dans products.php");
    $error_message_pdo = "Erreur interne: Impossible d'accéder à la base de données.";
    // Si la connexion PDO n'est pas disponible, il est inutile de continuer.
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error_message' => $error_message_pdo]);
    } else {
        setFlashMessage('danger', $error_message_pdo);
        header("Location: dashboard.php"); // Ou une page d'erreur générique
    }
    exit(); // Arrêter l'exécution
}

// --- Génération du token CSRF ---
// S'assure qu'un token CSRF existe en session pour les formulaires.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token']; // Variable pour l'utiliser dans le HTML

// --- Détection des requêtes AJAX ---
// Cette variable sera vraie si la requête vient de JavaScript (fetch)
$is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// --- Gestion des actions (suppression) ---
// La suppression est gérée pour les requêtes POST, conformément aux bonnes pratiques avec CSRF.
// L'action est toujours passée via GET dans l'URL du formulaire d'action pour identifier l'opération.
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // L'ID du produit et le token CSRF doivent venir de $_POST
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $submitted_csrf_token = $_POST['csrf_token'] ?? '';

    // DÉCOMMENTER CE BLOC POUR ACTIVER LA VÉRIFICATION CSRF APRÈS LA MISE À JOUR DE `functions.php`
    if (!function_exists('verifyCsrfToken') || !verifyCsrfToken($submitted_csrf_token)) {
        error_log("CSRF Error (products.php): Token invalide ou manquant. Soumis: '" . $submitted_csrf_token . "', Stocké: '" . ($_SESSION['csrf_token'] ?? 'N/A') . "'");
        $message_error_csrf = "Erreur de sécurité : token CSRF invalide.";
        if ($is_ajax_request) {
            header('Content-Type: application/json');
            echo json_encode(['error_message' => $message_error_csrf]);
        } else {
            setFlashMessage('danger', $message_error_csrf);
            header("Location: dashboard.php?page=products");
        }
        exit();
    }
    // FIN DU BLOC CSRF

    if ($id > 0) {
        try {
            $pdo->beginTransaction(); // Démarre une transaction

            // 1. Supprimer d'abord les variantes du produit
            $stmt_delete_variants = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
            $stmt_delete_variants->execute([$id]);

            // 2. Ensuite, supprimer le produit lui-même
            $stmt_delete_product = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt_delete_product->execute([$id]);

            if ($stmt_delete_product->rowCount() > 0) {
                $pdo->commit(); // Valide la transaction
                $_SESSION['success_message'] = 'Produit et ses variantes supprimés avec succès.';
            } else {
                $pdo->rollBack(); // Annule la transaction si le produit n'a pas été trouvé
                $_SESSION['error_message'] = 'Produit non trouvé ou déjà supprimé.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack(); // Annule la transaction en cas d'erreur
            $_SESSION['error_message'] = 'Erreur lors de la suppression du produit : ' . $e->getMessage();
            error_log("Erreur PDO lors de la suppression d'un produit (products.php): " . $e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = 'ID produit invalide.';
    }

    // Si c'est une requête AJAX de suppression, on renvoie du JSON et on arrête l'exécution.
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        echo json_encode([
            'success_message' => $_SESSION['success_message'] ?? null,
            'error_message' => $_SESSION['error_message'] ?? null,
        ]);
        // Nettoyer les messages de session après les avoir envoyés en JSON
        unset($_SESSION['success_message']);
        unset($_SESSION['error_message']);
        exit(); // TRÈS IMPORTANT : Arrêter l'exécution après l'envoi du JSON
    } else {
        // Pour les requêtes non-AJAX (ex: accès direct), on redirige.
        header("Location: dashboard.php?page=products");
        exit();
    }
}

// --- Récupération des paramètres de filtre ---
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// --- Construction de la requête SQL pour récupérer les produits ---
// Le commentaire original indiquait 'p.price` est maintenant `p.base_price`, mais la table a `price`.
$query = "SELECT p.*, c.name AS category_name, COALESCE(SUM(pv.stock_quantity), 0) AS total_stock_quantity
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.id
          LEFT JOIN product_variants pv ON p.id = pv.product_id
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

// Ajout du GROUP BY car nous utilisons une fonction d'agrégation (SUM)
$query .= " GROUP BY p.id ORDER BY p.created_at DESC";

$products = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des produits (products.php): " . $e->getMessage());
    $_SESSION['error_message'] = 'Une erreur est survenue lors de la récupération des produits.';
}

// --- Récupérer les catégories pour le filtre (toujours nécessaires) ---
$categories = [];
try {
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des catégories (products.php): " . $e->getMessage());
}

// --- RENVOI DE JSON si c'est une requête AJAX de filtrage/recherche ---
if ($is_ajax_request && !isset($_GET['action'])) { // S'assure que ce n'est pas une requête de suppression
    header('Content-Type: application/json');

    $response_products = [];
    foreach ($products as $product) {
        $response_products[] = [
            'id' => $product['id'] ?? null,
            'image_url' => $product['image_url'] ?? null, // Maintenant disponible si la colonne est ajoutée
            'name' => htmlspecialchars($product['name'] ?? ''),
            'category_name' => htmlspecialchars($product['category_name'] ?? 'Aucune'),
            // MODIFICATION ICI : Utilise 'price' au lieu de 'base_price'
            'price' => number_format((float)($product['price'] ?? 0.00), 2, '.', ''),
            'stock_quantity' => (int)($product['total_stock_quantity'] ?? 0), // Utilisez la colonne agrégée
            'created_at' => isset($product['created_at']) ? (new DateTime($product['created_at']))->format('Y-m-d H:i:s') : null,
        ];
    }

    echo json_encode([
        'products' => $response_products,
        'success_message' => $_SESSION['success_message'] ?? null,
        'error_message' => $_SESSION['error_message'] ?? null,
    ]);

    // Nettoyer les messages de session après les avoir envoyés en JSON
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
    exit();
}

// --- DÉBUT DU RENDU HTML (si ce n'est PAS une requête AJAX de données/suppression) ---
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestion des produits</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php?page=add_product" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Ajouter un produit
        </a>
    </div>
</div>

<?php
// Utilisation de la fonction displayFlashMessages() de functions.php
displayFlashMessages();
?>

<div class="card mb-4">
    <div class="card-body">
        <form id="filterProductsForm" method="GET" class="row g-3">
            <input type="hidden" name="page" value="products">

            <div class="col-md-4">
                <label for="search" class="form-label">Recherche</label>
                <input type="text" class="form-control" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4">
                <label for="category_id" class="form-label">Catégorie</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="0">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category['id'] ?? '') ?>" <?= $category_id == ($category['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name'] ?? 'Inconnue') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel"></i> Filtrer
                </button>
                <a href="dashboard.php?page=products" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                </a>
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
                <tbody id="productsTableBody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" class="text-center">Aucun produit trouvé</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['id'] ?? '') ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="../uploads/products/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name'] ?? 'Image produit') ?>" width="50" height="50" style="object-fit: cover;">
                                    <?php else: ?>
                                        <span class="text-muted">Aucune image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Aucune') ?></td>
                                <td><?= number_format((float)($product['price'] ?? 0.00), 2, ',', '.') ?> €</td>
                                <td>
                                    <?php
                                    // Utilisez 'total_stock_quantity' pour le rendu HTML
                                    $stock_quantity = $product['total_stock_quantity'] ?? 0;
                                    $badge_class = ($stock_quantity > 0) ? 'bg-success' : 'bg-danger';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= htmlspecialchars((string)$stock_quantity) ?>
                                    </span>
                                </td>
                                <td><?= isset($product['created_at']) ? (new DateTime($product['created_at']))->format('d/m/Y') : '' ?></td>
                                <td>
                                    <a href="dashboard.php?page=edit_product&id=<?= htmlspecialchars($product['id'] ?? '') ?>" class="btn btn-sm btn-outline-primary" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="dashboard.php?page=products&action=delete" method="POST" class="d-inline delete-product-form" data-product-id="<?= htmlspecialchars($product['id'] ?? '') ?>">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($product['id'] ?? '') ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Ce script gère les interactions AJAX pour le filtrage et la suppression.
// Il est conçu pour être exécuté après que le contenu de products.php a été chargé.
// La fonction window.initProductsPage est appelée par le script global de dashboard.php.
window.initProductsPage = function() {
    console.log("initProductsPage called"); // Ajouté pour le débogage
    const filterForm = document.getElementById('filterProductsForm');
    const productsTableBody = document.getElementById('productsTableBody');

    // Fonction pour afficher les messages flash (alertes Bootstrap)
    // Cette fonction est cruciale pour afficher les messages venant des réponses AJAX.
    // Vérifie si la fonction existe déjà pour éviter de la redéfinir si elle est globale
    // (cela permet de la définir une seule fois dans un fichier global comme dashboard.js si nécessaire)
    if (typeof window.displayFlashMessage !== 'function') {
        window.displayFlashMessage = function(message, type) {
            const alertPlaceholder = document.querySelector('.d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center.pt-3.pb-2.mb-3.border-bottom');
            if (alertPlaceholder) {
                // Supprime toutes les alertes existantes pour éviter l'accumulation
                document.querySelectorAll('.alert').forEach(alert => alert.remove());

                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                alertPlaceholder.insertAdjacentHTML('afterend', alertHtml);
            }
        };
    }

    // --- Fonction pour charger les produits via AJAX (pour les filtres/recherche) ---
    function loadProducts() {
        console.log("loadProducts called"); // Ajouté pour le débogage
        const formData = new FormData(filterForm);
        const searchParams = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            searchParams.append(key, value);
        }
        searchParams.set('page', 'products');

        const url = `dashboard.php?${searchParams.toString()}`;

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Indique à PHP que c'est une requête AJAX
            }
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json(); // Traite la réponse comme du JSON
            } else {
                // Si la réponse n'est pas JSON, c'est une erreur inattendue du serveur
                return response.text().then(text => {
                    console.error("Réponse non JSON reçue lors du filtrage/recherche:", text);
                    window.displayFlashMessage("Erreur: Réponse inattendue du serveur lors du filtrage. Vérifiez la console du navigateur pour plus de détails.", 'danger');
                    throw new Error("Réponse inattendue du serveur (non-JSON).");
                });
            }
        })
        .then(data => {
            console.log("Data received from AJAX:", data); // Ajouté pour le débogage
            // Afficher les messages de succès ou d'erreur provenant du PHP (via JSON)
            if (data.success_message) {
                window.displayFlashMessage(data.success_message, 'success');
            }
            if (data.error_message) {
                window.displayFlashMessage(data.error_message, 'danger');
            }
            updateProductsTable(data.products); // Met à jour le tableau avec les nouvelles données
        })
        .catch(error => {
            console.error('Erreur lors du chargement des produits (filtre):', error);
            // Éviter de réafficher le message si le texte d'erreur a déjà été affiché
            if (!error.message.includes("Réponse inattendue du serveur")) {
                window.displayFlashMessage("Erreur de communication lors du filtrage des produits.", 'danger');
            }
        });
    }

    // --- Fonction pour mettre à jour le tableau HTML avec les données AJAX ---
    function updateProductsTable(products) {
        console.log("updateProductsTable called with products:", products); // Ajouté pour le débogage
        productsTableBody.innerHTML = ''; // Vide le tableau actuel

        if (products && products.length > 0) {
            products.forEach(product => {
                const row = document.createElement('tr');
                let imageUrl = product.image_url ? `../uploads/products/${product.image_url}` : '';
                let imageHtml = imageUrl ? `<img src="${imageUrl}" alt="${product.name || 'Image produit'}" width="50" height="50" style="object-fit: cover;">` : `<span class="text-muted">Aucune image</span>`;

                let stockQuantity = product.stock_quantity !== undefined && product.stock_quantity !== null ? product.stock_quantity : 0;
                let badgeClass = stockQuantity > 0 ? 'bg-success' : 'bg-danger';

                row.innerHTML = `
                    <td>${product.id || ''}</td>
                    <td>${imageHtml}</td>
                    <td>${product.name || ''}</td>
                    <td>${product.category_name || 'Aucune'}</td>
                    <td>${parseFloat(product.price).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €</td>
                    <td>
                        <span class="badge ${badgeClass}">
                            ${stockQuantity}
                        </span>
                    </td>
                    <td>${product.created_at ? new Date(product.created_at).toLocaleDateString('fr-FR') : ''}</td>
                    <td>
                        <a href="dashboard.php?page=edit_product&id=${product.id || ''}" class="btn btn-sm btn-outline-primary" title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="dashboard.php?page=products&action=delete" method="POST" class="d-inline delete-product-form" data-product-id="${product.id || ''}">
                            <input type="hidden" name="id" value="${product.id || ''}">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                `;
                productsTableBody.appendChild(row);
            });
        } else {
            productsTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">Aucun produit trouvé</td>
                </tr>
            `;
        }
        attachDeleteEventListeners(); // Important: Réattacher les écouteurs pour les NOUVEAUX formulaires
    }

    // --- Gérer la soumission du formulaire de filtre (AJAX) ---
    if (filterForm) {
        filterForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche le rechargement de la page
            loadProducts(); // Charge les produits via AJAX
        });

        // Charger les produits quand la catégorie change
        document.getElementById('category_id').addEventListener('change', function() {
            loadProducts();
        });
        // Charger les produits après un délai quand on tape dans la recherche
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadProducts, 500); // Délai de 500ms
        });
    }

    // --- Gérer la suppression des produits (AJAX) ---
    function attachDeleteEventListeners() {
        // Sélectionne TOUS les FORMULAIRES de suppression actuels dans le tableau
        document.querySelectorAll('.delete-product-form').forEach(form => {
            // Supprime tout écouteur d'événements existant pour éviter les duplicatas
            form.removeEventListener('submit', handleDeleteSubmit);
            // Attache le nouvel écouteur d'événements
            form.addEventListener('submit', handleDeleteSubmit);
        });
    }

    function handleDeleteSubmit(event) {
        event.preventDefault(); // Empêche la soumission normale du formulaire
        const form = event.currentTarget; // Le formulaire qui a été soumis
        const productId = form.dataset.productId; // L'ID du produit depuis le data-attribut

        if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')) {
            return;
        }

        fetch(form.action, { // L'URL d'action du formulaire est déjà correcte (dashboard.php?page=products&action=delete)
            method: 'POST', // On envoie en POST
            body: new FormData(form), // Envoie toutes les données du formulaire (id et csrf_token)
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Pour que PHP détecte la requête comme AJAX
            }
        })
        .then(response => {
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                return response.text().then(text => {
                    console.error("Réponse non JSON reçue lors de la suppression:", text);
                    window.displayFlashMessage("Erreur: Réponse inattendue du serveur lors de la suppression. Vérifiez la console.", 'danger');
                    throw new Error("Réponse inattendue du serveur (non-JSON).");
                });
            }
        })
        .then(data => {
            if (data.success_message) {
                window.displayFlashMessage(data.success_message, 'success');
                loadProducts(); // Recharger la liste après succès pour voir la suppression
            } else if (data.error_message) {
                window.displayFlashMessage(data.error_message, 'danger');
            } else {
                window.displayFlashMessage("Action de suppression terminée, mais sans message spécifique.", 'info');
                loadProducts(); // Recharger quand même
            }
        })
        .catch(error => {
            console.error('Erreur lors de la suppression du produit:', error);
            if (!error.message.includes("Réponse inattendue du serveur")) {
                window.displayFlashMessage("Erreur de communication lors de la suppression.", 'danger');
            }
        });
    }

    // Attache les écouteurs d'événements au chargement initial de la page
    attachDeleteEventListeners();
    // Appelle loadProducts au premier chargement pour être sûr que tout fonctionne bien
    // Si la page est déjà chargée avec des produits, vous pouvez commenter cette ligne
    // loadProducts();
};
</script>