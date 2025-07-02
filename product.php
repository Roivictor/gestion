<?php
// product.php - Version optimisée et fonctionnelle

// 1. Démarrage de la session et inclusion du fichier de configuration
// session_start() est déjà géré par config.php
require_once 'config.php';

// Activez l'affichage des erreurs pour le débogage (à désactiver en production)
// Votre config.php le gère déjà, mais vous pouvez le décommenter ici si besoin pour un débogage localisé
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// 2. Vérification et nettoyage de l'ID du produit
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirige vers la page des produits avec un message flash si l'ID est manquant
    setFlashMessage('danger', __('product_id_missing')); // Utilisez la traduction
    redirect(BASE_URL . 'products.php'); // Redirige vers la liste des produits
    exit;
}

$product_id = (int)$_GET['id']; // Convertit en entier pour sécurité

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
    $product = $stmt->fetch(PDO::FETCH_ASSOC); // Récupère un seul résultat
} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur PDO dans product.php lors de la récupération du produit: " . $e->getMessage());
    setFlashMessage('danger', __('db_error_product_fetch')); // Message traduit
    redirect(BASE_URL . 'products.php'); // Redirige vers la liste des produits
    exit;
}

// 4. Vérifier si le produit a été trouvé
if (!$product) {
    // Si aucun produit n'est trouvé, rediriger vers la page des produits avec un message
    setFlashMessage('warning', __('product_not_found')); // Message traduit
    redirect(BASE_URL . 'products.php'); // Redirige vers la liste des produits
    exit;
}

// 5. Récupération des avis approuvés pour ce produit
$reviews = [];
try {
    // CORRECTION ICI : Sélectionne first_name et last_name de la table users (alias u)
    $reviews_query = "
        SELECT
            r.*,
            u.username,
            u.first_name,  -- AJOUTÉ
            u.last_name    -- AJOUTÉ
        FROM
            reviews r
        LEFT JOIN
            customers c ON r.customer_id = c.id
        LEFT JOIN
            users u ON c.user_id = u.id
        WHERE
            r.product_id = :product_id AND r.status = 'approved'
        ORDER BY
            r.review_date DESC"; // Utilisation de 'review_date' comme confirmé

    $stmt_reviews = $pdo->prepare($reviews_query);
    $stmt_reviews->execute([':product_id' => $product_id]);
    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO dans product.php lors de la récupération des avis: " . $e->getMessage());
    // Laissez le message de débogage temporairement pour confirmer la résolution
    echo "<div class='alert alert-danger'>ERREUR DE DÉBOGAGE (À RETIRER EN PRODUCTION) : " . htmlspecialchars($e->getMessage()) . "</div>";
    // setFlashMessage('warning', __('error_fetching_reviews')); // Message traduit, à décommenter en production
}


// Vous pouvez définir ici des traductions temporaires pour les messages flash
// si vous n'avez pas encore configuré un fichier de langue complet.
// Sinon, assurez-vous que votre fichier /lang/fr.php contient ces clés :
/*
Dans /lang/fr.php :
return [
    'product_id_missing' => 'ID de produit manquant.',
    'db_error_product_fetch' => 'Une erreur est survenue lors de la récupération des données du produit. Veuillez réessayer plus tard.',
    'product_not_found' => 'Le produit demandé n\'a pas été trouvé.',
    'error_fetching_reviews' => 'Une erreur est survenue lors de la récupération des avis.',
    'no_reviews_yet' => 'Aucun avis pour le moment.',
    'write_a_review' => 'Laisser un avis',
    'in_stock' => 'En stock',
    'available' => 'disponibles',
    'out_of_stock' => 'Rupture de stock',
    'quantity' => 'Quantité',
    'add_to_cart' => 'Ajouter au panier',
    'customer_reviews' => 'Avis des clients',
    'unauthorized_access_admin' => 'Accès non autorisé. Veuillez vous connecter en tant qu\'administrateur.', // (déjà dans checkAdminRole)
    // ... d'autres traductions
];
*/

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_language_code) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCsrfToken()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <?php
            // Affichage des messages flash
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
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image_url']) ?>" class="img-fluid rounded shadow-sm" alt="<?= htmlspecialchars($product['name']) ?>">
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
                            <small class="text-muted ms-2">(<?= number_format($product['avg_rating'], 1) ?> / <?= (int)$product['review_count'] ?> avis)</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-3"><small class="text-muted"><?= __('no_reviews_yet') ?></small></div>
                    <?php endif; ?>

                    <p class="lead text-secondary"><?= htmlspecialchars($product['description'] ?? '') ?></p>

                    <h2 class="text-primary mb-4"><?= number_format($product['price'], 2) ?> €</h2>

                    <?php if ($product['stock_quantity'] > 0): ?>
                        <p class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> <?= __('in_stock') ?> (<?= (int)$product['stock_quantity'] ?> <?= __('available') ?>)</p>
                        <div class="d-flex align-items-center mb-4">
                            <label for="quantity" class="form-label me-2 mb-0"><?= __('quantity') ?>:</label>
                            <input type="number" id="quantity" class="form-control me-3" value="1" min="1" max="<?= (int)$product['stock_quantity'] ?>" style="width: 90px;">
                            <button class="btn btn-primary add-to-cart" data-id="<?= $product['id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>">
                                <i class="bi bi-cart-plus"></i> <?= __('add_to_cart') ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-danger fw-bold"><i class="bi bi-x-circle-fill"></i> <?= __('out_of_stock') ?></p>
                    <?php endif; ?>

                    <hr class="my-4">

                    <h3><?= __('customer_reviews') ?></h3>
                    <div class="reviews-section">
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted"><?= __('no_reviews_yet') ?></p>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <?php
                                            // Affiche le prénom et le nom de la table users s'ils existent, sinon l'username, sinon "Utilisateur Inconnu"
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
                        <button class="btn btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#reviewModal"><?= __('write_a_review') ?></button>
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
                    <h5 class="modal-title" id="reviewModalLabel"><?= __('write_a_review') ?> pour <?= htmlspecialchars($product['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (isLoggedIn()): ?>
                        <form id="reviewForm" action="<?= BASE_URL ?>submit_review.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken()) ?>">
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
            // Logique pour le bouton "Ajouter au panier" si elle est gérée par AJAX
            const addToCartButtons = document.querySelectorAll('.add-to-cart');
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.id;
                    const productName = this.dataset.name;
                    const productPrice = this.dataset.price;
                    const quantityInput = document.getElementById('quantity');
                    const quantity = quantityInput ? parseInt(quantityInput.value) : 1; // Prend la quantité de l'input s'il existe

                    // Récupérez le token CSRF de la méta-balise
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    if (productId && quantity > 0) {
                        fetch('<?= BASE_URL ?>cart_ajax.php', { // Assurez-vous que cart_ajax.php gère l'ajout au panier
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest', // Indique que c'est une requête AJAX
                                'X-CSRF-TOKEN': csrfToken // Envoyez le token CSRF
                            },
                            body: JSON.stringify({
                                action: 'add',
                                product_id: productId,
                                quantity: quantity,
                                _csrf_token: csrfToken // Envoyer aussi dans le body pour les scripts PHP
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Mettre à jour l'interface utilisateur (ex: nombre d'articles dans le panier)
                                updateCartCount(data.cart_count); // Fonction à définir dans script.js ou globalement
                                showToast('success', data.message); // Afficher un toast de succès
                            } else {
                                // Correction de la concaténation de chaîne pour le message d'erreur
                                showToast('danger', data.message || 'Erreur lors de l\'ajout au panier.'); // Afficher un toast d'erreur
                            }
                        })
                        .catch(error => {
                            console.error('Erreur AJAX:', error);
                            showToast('danger', 'Une erreur de communication est survenue.');
                        });
                    }
                });
            });

            // GESTION DU FORMULAIRE D'AVIS
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Empêche la soumission normale du formulaire

                    fetch('<?= BASE_URL ?>submit_review.php', {
                        method: 'POST',
                        body: new FormData(this) // 'this' fait référence au formulaire
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // MODIFICATION ICI : Afficher un message fixe "Avis envoyé !"
                            showToast('success', 'Avis envoyé !'); 

                            const reviewModal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
                            if (reviewModal) {
                                reviewModal.hide(); // Ferme le modal
                            }
                            // Optionnel : Réinitialiser le formulaire après la soumission
                            reviewForm.reset();

                            // Si l'avis est directement approuvé, recharger la page pour le voir apparaître
                            // Sinon, l'utilisateur devra attendre l'approbation de l'administrateur
                            if (data.status === 'approved') {
                                location.reload(); // Recharge la page pour afficher le nouvel avis
                            }
                        } else {
                            showToast('danger', data.message); // Affiche le message d'erreur du serveur
                        }
                    })
                    .catch(error => {
                        console.error('Erreur AJAX:', error);
                        showToast('danger', 'Une erreur de communication est survenue.');
                    });
                });
            }


            // Fonctions utilitaires pour le JS (à mettre dans script.js pour la production)
            function showToast(type, message) {
                // Implémentez votre propre logique pour afficher un toast (ex: avec Bootstrap Toasts)
                console.log(`Toast (${type}): ${message}`);
                // Exemple simple avec un div temporaire
                let toastDiv = document.createElement('div');
                toastDiv.className = `alert alert-${type} mt-3 fixed-top mx-auto w-50 text-center`;
                toastDiv.innerHTML = message;
                document.body.appendChild(toastDiv);
                setTimeout(() => toastDiv.remove(), 3000);
            }

            function updateCartCount(count) {
                // Mettre à jour un élément HTML qui affiche le nombre d'articles dans le panier
                const cartCountElement = document.getElementById('cart-count'); // Assurez-vous d'avoir cet ID dans votre header.php
                if (cartCountElement) {
                    cartCountElement.textContent = count;
                }
            }
        });
    </script>
</body>
</html>