<?php
// includes/reviews_display.php

// Ce fichier est inclus dans product.php pour afficher les avis.
// La variable $product_id et $csrf_token doivent être disponibles depuis product.php.
if (!isset($product_id) || !isset($csrf_token)) {
    echo '<p class="alert alert-warning">Impossible d\'afficher les avis car l\'ID du produit ou le jeton CSRF est manquant.</p>';
    return; // Arrête l'exécution du script inclus
}

// Récupérer les avis approuvés pour ce produit
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.review_date, u.username, u.first_name, u.last_name
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        WHERE r.product_id = :product_id AND r.status = 'approved'
        ORDER BY r.review_date DESC
    ");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si une erreur PDO survient mais n'est pas capturée par l'exception (dépend de l'erreur mode)
    if ($stmt->errorCode() !== '00000') {
        $errorInfo = $stmt->errorInfo();
        error_log("PDO Statement Error (reviews_display.php): Code " . $errorInfo[0] . ", Driver Code " . $errorInfo[1] . ", Message " . $errorInfo[2]);
        echo '<p class="alert alert-danger">Une erreur est survenue lors de la récupération des avis.</p>';
    }

} catch (PDOException $e) {
    // Capture l'erreur PDO si le mode d'erreur est configuré en EXCEPTION
    error_log("Erreur PDO dans reviews_display.php: " . $e->getMessage());
    echo '<p class="alert alert-danger">Une erreur est survenue lors de la récupération des avis.</p>';
    $reviews = []; // Assurer que $reviews est un tableau vide en cas d'erreur
}

?>

<div class="reviews-section">
    <?php if (!empty($reviews)): ?>
        <?php foreach ($reviews as $review): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">
                        <?php
                        // Affiche le prénom et l'initiale du nom de famille de l'utilisateur qui a laissé l'avis
                        echo htmlspecialchars($review['first_name'] . ' ' . (isset($review['last_name'][0]) ? $review['last_name'][0] . '.' : ''));
                        ?>
                        <small class="text-muted ms-2"><?= htmlspecialchars(formatDate($review['review_date'])) ?></small>
                    </h5>
                    <div class="mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $review['rating']): ?>
                                <i class="bi bi-star-fill text-warning"></i>
                            <?php else: ?>
                                <i class="bi bi-star text-warning"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <p class="card-text"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Aucun avis approuvé pour ce produit pour le moment.</p>
    <?php endif; ?>

    <?php if (isLoggedIn()): // Afficher le formulaire si l'utilisateur est connecté ?>
        <hr>
        <h5>Laisser un avis</h5>
        <form id="review_form">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="mb-3">
                <label for="rating" class="form-label">Note :</label>
                <select class="form-select" id="rating" name="rating" required>
                    <option value="">Choisir une note</option>
                    <option value="5">5 étoiles (Excellent)</option>
                    <option value="4">4 étoiles (Très bien)</option>
                    <option value="3">3 étoiles (Moyen)</option>
                    <option value="2">2 étoiles (Faible)</option>
                    <option value="1">1 étoile (Mauvais)</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="comment" class="form-label">Commentaire :</label>
                <textarea class="form-control" id="comment" name="comment" rows="4" maxlength="500" required></textarea>
                <small class="form-text text-muted">Maximum 500 caractères.</small>
            </div>
            <button type="submit" class="btn btn-success"><i class="bi bi-chat-dots"></i> Soumettre l'avis</button>
        </form>
    <?php else: ?>
        <p class="alert alert-info">Vous devez être <a href="login.php" class="alert-link">connecté</a> pour laisser un avis.</p>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const reviewForm = document.getElementById('review_form');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this); // Collecte toutes les données du formulaire

                fetch('submit_review.php', {
                    method: 'POST',
                    body: formData // FormData est automatiquement encodé en multipart/form-data
                })
                .then(response => {
                    if (!response.ok) {
                        // Si la réponse n'est pas OK (statut HTTP 2xx), lisez le texte de l'erreur
                        return response.text().then(text => { throw new Error(text) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Assurez-vous que showToast est défini dans votre script principal (e.g., script.js ou layout.js)
                        if (typeof showToast === 'function') {
                            showToast(data.message, 'success');
                        } else {
                            console.log('Toast success:', data.message);
                            alert(data.message); // Fallback si showToast n'est pas dispo
                        }
                        
                        reviewForm.reset();
                        // Recharge la page ou met à jour la section des avis pour voir le nouvel avis
                        // Vous pouvez ajuster cela selon que vous souhaitez que l'avis soit visible immédiatement ou après modération
                        window.location.reload(); 
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message, 'danger');
                        } else {
                            console.error('Toast error:', data.message);
                            alert('Erreur: ' + data.message); // Fallback
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur AJAX lors de la soumission de l\'avis:', error);
                    if (typeof showToast === 'function') {
                        showToast("Une erreur est survenue lors de la soumission de votre avis. Détails: " + error.message, 'danger');
                    } else {
                         alert("Une erreur est survenue lors de la soumission de votre avis. Détails: " + error.message);
                    }
                });
            });
        }
    });
</script>