<?php
// Empêcher l'accès direct
if (!defined('SITE_NAME')) {
    die('Accès direct interdit');
}
?>

<footer class="bg-dark text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5><?= SITE_NAME ?></h5>
                <p class="text-white">Votre boutique en ligne préférée pour des produits de qualité.</p>
            </div>
            
            <div class="col-md-2 mb-4 mb-md-0">
                <h5>Liens rapides</h5>
                <ul class="list-unstyled">
                    <li><a href="index.php" class="text-white">Accueil</a></li>
                    <li><a href="products.php" class="text-white">Produits</a></li>
                    <li><a href="about.php" class="text-white">À propos</a></li>
                    <li><a href="contact.php" class="text-white">Contact</a></li>
                </ul>
            </div>
            
            <div class="col-md-3 mb-4 mb-md-0">
                <h5>Contact</h5>
                <ul class="list-unstyled text-white">
                    <li><i class="bi bi-geo-alt"></i> 123 Rue Sly,Lomé</li>
                    <li><i class="bi bi-telephone"></i> 0022870512027</li>
                    <li><i class="bi bi-envelope"></i> contact@votreboutique.com</li>
                </ul>
            </div>
            
            <div class="col-md-3">
                <h5>Newsletter</h5>
                <p class="text-white">Abonnez-vous pour recevoir nos offres spéciales.</p>
                <form class="mb-3">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Votre email">
                        <button class="btn btn-primary" type="submit">OK</button>
                    </div>
                </form>
                <div class="social-icons">
                    <a href="#" class="text-white me-2"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white me-2"><i class="bi bi-twitter"></i></a>
                    <a href="#" class="text-white me-2"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-linkedin"></i></a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-star-fill"></i> Avis Clients (Admin Only)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produit</th>
                                        <th>Client</th>
                                        <th>Note</th>
                                        <th>Commentaire</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Connexion à la base de données et récupération des avis
                                    require_once '../config.php'; // Assurez-vous que le chemin est correct depuis l'emplacement du footer.php
                                    $stmt = $pdo->query("
                                        SELECT r.id, p.name AS product_name, 
                                               CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                                               r.rating, r.comment, r.review_date, r.status
                                        FROM reviews r
                                        JOIN products p ON r.product_id = p.id
                                        JOIN customers c ON r.customer_id = c.id
                                        JOIN users u ON c.user_id = u.id
                                        ORDER BY r.review_date DESC
                                        LIMIT 3
                                    ");
                                    
                                    while ($review = $stmt->fetch()):
                                    ?>
                                    <tr>
                                        <td><?= $review['id'] ?></td>
                                        <td><?= htmlspecialchars($review['product_name']) ?></td>
                                        <td><?= htmlspecialchars($review['customer_name']) ?></td>
                                        <td>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </td>
                                        <td><?= htmlspecialchars(substr($review['comment'], 0, 50)) . (strlen($review['comment']) > 50 ? '...' : '') ?></td>
                                        <td><?= date('d/m/Y', strtotime($review['review_date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $review['status'] === 'approved' ? 'success' : ($review['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($review['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <a href="../admin/reviews.php" class="btn btn-sm btn-warning">
                                <i class="bi bi-box-arrow-in-right"></i> Gérer les avis
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <hr class="my-4 bg-secondary">
        
        <div class="text-center text-white"> <p class="mb-0">&copy; <?= date('Y') ?> <?= SITE_NAME ?>. Tous droits réservés.</p>
        </div>
    </div>
</footer>