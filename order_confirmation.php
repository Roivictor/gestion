<?php
require_once 'config.php';

// Optionnel: Récupérer l'ID de commande pour afficher les détails
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Commande - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container my-5">
        <h1 class="mb-4">Commande Confirmée !</h1>

        <?php include 'includes/flash_messages.php'; ?>

        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading">Merci pour votre commande !</h4>
            <p>Votre commande a été passée avec succès. Nous vous remercions de votre confiance.</p>
            <?php if ($order_id): ?>
                <p>Numéro de commande : <strong>#<?= htmlspecialchars($order_id) ?></strong></p>
                <hr>
                <p class="mb-0">Vous pouvez consulter le statut de votre commande dans votre <a href="<?= BASE_URL ?>account.php?section=orders" class="alert-link">espace personnel</a>.</p>
            <?php else: ?>
                <p class="mb-0">Vous pouvez consulter le statut de votre commande dans votre <a href="<?= BASE_URL ?>account.php?section=orders" class="alert-link">espace personnel</a>.</p>
            <?php endif; ?>
        </div>
        
        <a href="<?= BASE_URL ?>products.php" class="btn btn-primary">Continuer mes achats</a>
        <a href="<?= BASE_URL ?>account.php?section=orders" class="btn btn-outline-secondary">Voir mes commandes</a>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>