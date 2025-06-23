<?php
require_once 'config.php';
$page_title = "À propos - " . SITE_NAME;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="py-5">
        <!-- Hero Section -->
        <section class="bg-light py-5 mb-5">
            <div class="container text-center">
                <h1 class="display-4 fw-bold">À propos de <?= SITE_NAME ?></h1>
                <p class="lead">Découvrez notre histoire, notre mission et notre équipe</p>
            </div>
        </section>
        
        <div class="container">
            <!-- Notre histoire -->
            <section class="mb-5">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h2 class="mb-4"><i class="bi bi-book"></i> Notre histoire</h2>
                        <p>Fondée en 2010, <?= SITE_NAME ?> est née de la passion pour offrir des produits de qualité à des prix accessibles. Ce qui a commencé comme une petite boutique locale est rapidement devenu une référence dans notre secteur.</p>
                        <p>Au fil des années, nous avons élargi notre gamme de produits tout en maintenant notre engagement envers la qualité et le service client exceptionnel.</p>
                    </div>
                    <div class="col-lg-6">
                        <img src="images/histoire.jpg" alt="Notre histoire" class="img-fluid rounded shadow">
                    </div>
                </div>
            </section>
            
            <!-- Notre mission -->
            <section class="mb-5 py-4 bg-light rounded">
                <div class="container">
                    <h2 class="text-center mb-5"><i class="bi bi-bullseye"></i> Notre mission</h2>
                    <div class="row text-center">
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="bi bi-star-fill text-warning fs-1 mb-3"></i>
                                    <h3>Qualité</h3>
                                    <p>Nous sélectionnons rigoureusement chaque produit pour garantir une qualité optimale.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="bi bi-currency-euro text-success fs-1 mb-3"></i>
                                    <h3>Prix justes</h3>
                                    <p>Des prix compétitifs sans compromis sur la qualité des produits.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <i class="bi bi-people-fill text-primary fs-1 mb-3"></i>
                                    <h3>Service client</h3>
                                    <p>Une équipe dévouée pour répondre à tous vos besoins et questions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Notre équipe -->
            <section class="mb-5">
                <h2 class="text-center mb-5"><i class="bi bi-people"></i> Notre équipe</h2>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center">
                            <img src="images/direc.jpg" class="card-img-top" alt="Directeur">
                            <div class="card-body">
                                <h4 class="card-title">Jean Dupont</h4>
                                <p class="text-muted">Fondateur & Directeur</p>
                                <p class="card-text">"Notre priorité est votre satisfaction. N'hésitez pas à nous contacter pour toute question."</p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-linkedin"></i></a>
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-twitter"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center">
                            <img src="images/bj.jpg" class="card-img-top" alt="Responsable commercial">
                            <div class="card-body">
                                <h4 class="card-title">Marie Martin</h4>
                                <p class="text-muted">Responsable Commercial</p>
                                <p class="card-text">"Je m'assure que chaque produit répond à nos standards de qualité avant d'arriver chez vous."</p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-linkedin"></i></a>
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-twitter"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 text-center">
                            <img src="images/team3.jpg" class="card-img-top" alt="Service client">
                            <div class="card-body">
                                <h4 class="card-title">Thomas Leroy</h4>
                                <p class="text-muted">Service Client</p>
                                <p class="card-text">"Je suis là pour répondre à toutes vos questions et résoudre vos problèmes rapidement."</p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-linkedin"></i></a>
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="bi bi-twitter"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Chiffres clés -->
            <section class="py-4 bg-primary text-white rounded mb-5">
                <div class="container">
                    <h2 class="text-center mb-5"><?= SITE_NAME ?> en chiffres</h2>
                    <div class="row text-center">
                        <div class="col-md-3 mb-4">
                            <h3 class="display-4 fw-bold">10+</h3>
                            <p>Années d'expérience</p>
                        </div>
                        <div class="col-md-3 mb-4">
                            <h3 class="display-4 fw-bold">5000+</h3>
                            <p>Clients satisfaits</p>
                        </div>
                        <div class="col-md-3 mb-4">
                            <h3 class="display-4 fw-bold">100+</h3>
                            <p>Produits disponibles</p>
                        </div>
                        <div class="col-md-3 mb-4">
                            <h3 class="display-4 fw-bold">24/7</h3>
                            <p>Service client</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>