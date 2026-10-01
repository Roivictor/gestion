<?php
// mes_commandes.php - Affiche la liste des commandes pour le client connecté

// Assurez-vous que config.php est à la racine du projet, au même niveau que ce fichier
require_once 'config.php';
require_once 'includes/functions.php'; // Inclut les fonctions utilitaires comme formatDate, isLoggedIn, redirect

// Vérifier si l'utilisateur est connecté.
if (!isLoggedIn()) {
    redirect('login.php'); // Redirige vers la page de connexion si l'utilisateur n'est pas connecté
    exit(); // Termine l'exécution après la redirection
}

// L'ID de l'utilisateur connecté depuis la session.
// CORRECTION ICI : Utilise $_SESSION['user']['id'] pour la cohérence avec le login.
$user_id = $_SESSION['user']['id'] ?? null;

// Si, après la connexion, l'ID utilisateur est toujours null, c'est une anomalie.
// Cela gère les cas où la session est incomplète ou corrompue.
if ($user_id === null) {
    error_log("User ID (SESSION['user']['id']) not found for logged in user on mes_commandes.php. Session content: " . print_r($_SESSION, true));
    $_SESSION['error_message'] = "Votre session utilisateur est invalide ou incomplète. Veuillez vous reconnecter.";
    redirect('login.php');
    exit();
}

$orders = []; // Initialiser le tableau des commandes
$message = ''; // Pour les messages d'erreur ou de succès
$message_type = ''; // Type du message (e.g., 'danger', 'info')

try {
    // Vérifier que la connexion PDO est bien établie (définie dans config.php)
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Erreur de configuration : la connexion à la base de données n\'est pas disponible.');
    }

    // SCÉNARIO CORRECT ET RECOMMANDÉ: Si vous avez bien une table 'customers' séparée où 'customers.user_id'
    // référence 'users.id' ET 'orders.customer_id' référence 'customers.id'.
    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.order_date,
            o.total_amount,
            o.status
        FROM
            orders o
        JOIN
            customers c ON o.customer_id = c.id -- Joindre la table des commandes avec la table des clients
        WHERE
            c.user_id = :user_id                 -- Filtrer par l'ID de l'utilisateur connecté via la table 'customers'
        ORDER BY
            o.order_date DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC); // Récupérer toutes les commandes sous forme de tableau associatif

} catch (PDOException $e) {
    // Gérer les erreurs de base de données
    error_log("Erreur PDO lors de la récupération des commandes du client sur mes_commandes.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $message = "Une erreur est survenue lors du chargement de vos commandes. Veuillez réessayer plus tard.";
    $message_type = "danger";
} catch (Exception $e) {
    // Gérer d'autres exceptions générales
    error_log("Erreur générale sur mes_commandes.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $message = "Une erreur inattendue est survenue. Veuillez réessayer plus tard.";
    $message_type = "danger";
}

// Optionnel: Récupérer des messages de session si une redirection a eu lieu avant (e.g., succès d'une commande passée)
if (isset($_SESSION['success_message'])) { // Utilisation de 'success_message' pour être cohérent avec 'error_message'
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message']) && empty($message)) { // Si un message d'erreur PDO est déjà là, ne pas écraser
    $message = $_SESSION['error_message'];
    $message_type = "danger";
    unset($_SESSION['error_message']);
}
// Assurez-vous que les messages de session proviennent d'une seule clé standardisée (ex: 'flash_message')
// Ou gérez chaque type de message (success_message, error_message, info_message) séparément.

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes commandes - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> </head>
<body>
    <?php
    // Inclure l'en-tête de la page (navbar, etc.)
    include 'includes/header.php';
    ?>

    <div class="container my-5">
        <h2 class="mb-4">Mes commandes</h2>

        <?php if ($message): // Afficher les messages (erreurs/succès) ?>
            <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info" role="alert">
                Vous n'avez pas encore passé de commande.
                <a href="products.php" class="alert-link">Parcourir nos produits</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Date</th>
                            <th>Montant</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['id']) ?></td>
                            <td><?= formatDate($order['order_date'], 'd/m/Y H:i') ?></td>
                            <td><?= number_format($order['total_amount'], 2, ',', ' ') ?> €</td>
                            <td>
                                <?php
                                // Définition des classes de badge Bootstrap selon le statut
                                $status_badge_class = '';
                                switch ($order['status']) {
                                    case 'pending': $status_badge_class = 'warning text-dark'; break;
                                    case 'processing': $status_badge_class = 'info text-white'; break;
                                    case 'shipped': $status_badge_class = 'primary'; break;
                                    case 'delivered': $status_badge_class = 'success'; break;
                                    case 'cancelled': $status_badge_class = 'danger'; break;
                                    default: $status_badge_class = 'secondary'; break; // Statut inconnu
                                }
                                ?>
                                <span class="badge bg-<?= $status_badge_class ?>">
                                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="order_details.php?id=<?= htmlspecialchars($order['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    Détails <i class="bi bi-arrow-right-circle"></i>
                                </a>
                                <?php
                                // Si vous avez des actions spécifiques pour les commandes en attente ou en traitement, ajoutez-les ici.
                                // Exemple : un bouton "Annuler la commande"
                                /*
                                if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                                    <form action="cancel_order.php" method="POST" class="d-inline-block ms-2">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['id']) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?');">
                                            Annuler <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                <?php endif;
                                */
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // Inclure le pied de page
    include 'includes/footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>