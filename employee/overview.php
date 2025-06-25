<?php
// employee/overview.php
// Ce fichier est inclus par employee/dashboard.php

// Vous pouvez inclure ici des données spécifiques à l'aperçu de l'employé
// Par exemple, des statistiques sur les commandes qu'il gère, etc.

// Assurez-vous que $pdo est disponible via config.php

$pending_orders_count = 0;
$today_sales_amount = 0;

try {
    // Exemple de requête : nombre de commandes en attente
    $stmt_pending_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pending_orders_count = $stmt_pending_orders->fetchColumn();

    // Exemple de requête : ventes du jour (simplifié)
    $stmt_today_sales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(date_commande) = CURDATE()");
    $today_sales_amount = $stmt_today_sales->fetchColumn() ?? 0; // Gère le cas où SUM retourne NULL
} catch (PDOException $e) {
    error_log("Error fetching employee overview data: " . $e->getMessage());
    // Optionnel: Définir un message d'erreur utilisateur
    $_SESSION['error_message'] = "Impossible de charger les données d'aperçu.";
}

?>

<p>Bienvenue sur votre tableau de bord, **<?= htmlspecialchars($currentUser['first_name']) ?>** !</p>
<p>C'est ici que vous pouvez gérer vos tâches et suivre les informations importantes.</p>

<div class="row mt-4">
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title">Commandes en attente</h5>
                    <p class="card-text fs-3"><?= $pending_orders_count ?></p>
                </div>
                <i class="bi bi-hourglass-split display-4"></i>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title">Ventes du jour</h5>
                    <p class="card-text fs-3"><?= number_format($today_sales_amount, 2, ',', ' ') ?> &euro;</p>
                </div>
                <i class="bi bi-currency-euro display-4"></i>
            </div>
        </div>
    </div>
    </div>

<div class="mt-4">
    <h3>Activités récentes</h3>
    <p>Listez ici les tâches récentes, les notifications ou les alertes spécifiques à l'employé.</p>
    <div class="card shadow-sm">
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <li class="list-group-item">Commande #12345 mise à jour par un administrateur.</li>
                <li class="list-group-item">Nouvelle demande de support reçue.</li>
                <li class="list-group-item">Votre performance mensuelle est disponible.</li>
            </ul>
        </div>
    </div>
</div>