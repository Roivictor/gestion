<?php
// admin/dashboard_overview.php
// Ce fichier est inclus DANS dashboard.php, donc il n'a PAS besoin de:
// - require_once '../config.php';
// - checkAdminRole();
// - balises <html>, <head>, <body>
// Les variables comme $total_orders, $recent_orders, etc., sont déjà définies dans dashboard.php

// Le code pour les statistiques et commandes récentes est maintenant directement dans dashboard.php
// car il s'agit du contenu par défaut. Si vous aviez des requêtes spécifiques ici, déplacez-les en haut de ce fichier.
?>

<h1 class="mb-4">Tableau de bord</h1>

<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-6 opacity-75">Commandes</div>
                    <div class="display-4 fw-bold"><?= $total_orders ?></div>
                </div>
                <i class="bi bi-cart fs-1 opacity-50"></i>
            </div>
            <hr class="opacity-50">
            <small class="opacity-75">Nombre total de commandes</small>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card clients">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-6 opacity-75">Clients</div>
                    <div class="display-4 fw-bold"><?= $total_clients ?></div>
                </div>
                <i class="bi bi-people fs-1 opacity-50"></i>
            </div>
            <hr class="opacity-50">
            <small class="opacity-75">Nombre total de clients</small>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card products">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-6 opacity-75">Produits</div>
                    <div class="display-4 fw-bold"><?= $total_products ?></div>
                </div>
                <i class="bi bi-box-seam fs-1 opacity-50"></i>
            </div>
            <hr class="opacity-50">
            <small class="opacity-75">Nombre total de produits</small>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="stat-card employees">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fs-6 opacity-75">Employés</div>
                    <div class="display-4 fw-bold"><?= $total_employees ?></div>
                </div>
                <i class="bi bi-person-badge fs-1 opacity-50"></i>
            </div>
            <hr class="opacity-50">
            <small class="opacity-75">Nombre total d'employés</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Commandes récentes</h5>
                <a href="dashboard.php?page=orders" class="btn btn-sm btn-outline-primary">Voir toutes</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_orders)): ?>
                    <p class="text-muted text-center">Aucune commande récente.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td> <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                    <td><?= number_format($order['total_amount'], 2) ?> €</td>
                                    <td><span class="badge bg-<?= ($order['status'] == 'delivered' ? 'success' : ($order['status'] == 'processing' ? 'info' : 'warning')) ?>"><?= htmlspecialchars(ucfirst($order['status'])) ?></span></td>
                                    <td>
                                        <a href="dashboard.php?page=order_details&id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">Voir</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Avis en attente</h5>
                <a href="dashboard.php?page=reviews" class="btn btn-sm btn-outline-primary">Voir tous</a>
            </div>
            <div class="card-body">
                <?php if ($pending_reviews_count > 0): ?>
                    <div class="alert alert-warning text-center mb-0">
                        Vous avez <strong><?= $pending_reviews_count ?></strong> avis en attente de modération.
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">Aucun avis en attente.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Feedback service</h5>
                <a href="dashboard.php?page=feedback" class="btn btn-sm btn-outline-primary">Voir tous</a>
            </div>
            <div class="card-body">
                <?php if ($recent_feedback_count > 0): ?>
                    <div class="alert alert-info text-center mb-0">
                        Vous avez <strong><?= $recent_feedback_count ?></strong> nouveaux feedbacks.
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center mb-0">Aucun feedback récent.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>