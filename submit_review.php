<?php
// submit_review.php - Traite la soumission des avis via AJAX
session_start();
require_once 'config.php'; // Inclut la configuration et les fonctions utilitaires (PDO, isLoggedIn, verifyCsrfToken, sanitize, __)

header('Content-Type: application/json'); // Indique que la réponse est du JSON

$response = ['success' => false, 'message' => ''];

// Vérifiez que la méthode de requête est bien POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("CSRF token mismatch on submit_review.php. Submitted: " . ($_POST['csrf_token'] ?? 'NULL') . ", Stored: " . ($_SESSION['csrf_token'] ?? 'NULL'));
        $response['message'] = __('csrf_token_mismatch', 'Erreur de sécurité : Jeton CSRF invalide.');
        echo json_encode($response);
        exit();
    }

    // 2. Vérification de l'authentification de l'utilisateur
    if (!isLoggedIn()) {
        $response['message'] = __('not_logged_in_review', 'Vous devez être connecté pour soumettre un avis.');
        echo json_encode($response);
        exit();
    }

    // 3. Récupération de l'ID utilisateur de la session (qui est l'ID de la table 'users')
    $userSessionId = $_SESSION['user']['id'] ?? null;
    if ($userSessionId === null) {
        $response['message'] = __('session_user_id_missing', 'Erreur interne: ID utilisateur non trouvé en session. Veuillez vous reconnecter.');
        echo json_encode($response);
        exit();
    }

    // 4. Récupération du customer_id à partir de l'userSessionId
    // Ceci est crucial si votre table 'reviews' utilise 'customer_id' qui est une FK vers 'customers.id'
    // et que votre 'customers' table a 'user_id' qui est une FK vers 'users.id'.
    $customerId = null;
    try {
        $stmt_customer = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
        $stmt_customer->execute([$userSessionId]);
        $customerData = $stmt_customer->fetch(PDO::FETCH_ASSOC);

        if ($customerData) {
            $customerId = $customerData['id'];
        } else {
            $response['message'] = __('customer_profile_missing', 'Votre profil client est introuvable. Veuillez contacter le support.');
            echo json_encode($response);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du customer_id dans submit_review.php: " . $e->getMessage());
        $response['message'] = __('db_error_customer_id', 'Une erreur est survenue lors de la vérification de votre profil client. Veuillez réessayer.');
        echo json_encode($response);
        exit();
    }

    // 5. Récupération et validation des données de l'avis depuis $_POST
    $productId = (int)($_POST['product_id'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = sanitize($_POST['comment'] ?? ''); // Assurez-vous que sanitize() nettoie correctement

    if ($productId <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
        $response['message'] = __('invalid_review_data', 'Veuillez fournir un ID produit valide, une note (1-5 étoiles) et un commentaire.');
        echo json_encode($response);
        exit();
    }

    // 6. Traitement de la soumission de l'avis
    try {
        // Vérifier si l'utilisateur a déjà soumis un avis pour ce produit (basé sur customer_id)
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE customer_id = ? AND product_id = ?");
        $stmt_check->execute([$customerId, $productId]);
        if ($stmt_check->fetchColumn() > 0) {
            $response['message'] = __('already_reviewed', 'Vous avez déjà soumis un avis pour ce produit.');
            echo json_encode($response);
            exit();
        }

        $review_status = 'pending'; // Définissez le statut par défaut (ex: 'pending' ou 'approved')
                                    // Changez à 'approved' si vous voulez qu'il s'affiche immédiatement sans modération.

        // Insertion de l'avis dans la base de données
        $stmt = $pdo->prepare("INSERT INTO reviews (customer_id, product_id, rating, comment, status, review_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$customerId, $productId, $rating, $comment, $review_status]);

        $response['success'] = true;
        // C'est ici que le message est défini pour la réponse JSON
        if ($review_status === 'pending') {
            // Vous pouvez laisser le message de traduction ou le mettre en dur ici si vous voulez
            $response['message'] = 'Avis soumis avec succès. En attente d\'approbation.'; // Message plus direct
            $response['status'] = 'pending';
        } else {
            $response['message'] = 'Votre avis a été soumis et approuvé !'; // Message plus direct
            $response['status'] = 'approved';
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la soumission de l'avis (submit_review.php): " . $e->getMessage());
        $response['message'] = __('review_submit_error', 'Une erreur est survenue lors de la soumission de votre avis. Veuillez réessayer.');
    }

} else {
    // Si la méthode n'est pas POST
    $response['message'] = __('invalid_request_method', 'Requête invalide ou méthode non autorisée.');
}

echo json_encode($response);
exit();
?>