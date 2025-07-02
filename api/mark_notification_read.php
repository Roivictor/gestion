<?php
// api/mark_notification_read.php

session_start();

require_once __DIR__ . '/../config.php'; // Adjust path to your config.php
require_once __DIR__ . '/../includes/functions.php'; // Adjust path to your functions.php for CSRF validation

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Check for authenticated user
if (!isset($_SESSION['user']['id'])) {
    $response['message'] = 'Utilisateur non connecté.';
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user']['id'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate CSRF token for POST requests
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrf_token)) { // Assuming validateCsrfToken is in functions.php
    $response['message'] = 'Erreur de sécurité : Token CSRF invalide.';
    echo json_encode($response);
    exit();
}


$action = $data['action'] ?? ''; // 'read_one' or 'read_all'

try {
    if ($action === 'read_one') {
        $notificationId = $data['notification_id'] ?? null;

        if (!$notificationId) {
            $response['message'] = 'ID de notification manquant.';
            echo json_encode($response);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Notification marquée comme lue.';
        } else {
            // This could mean it was already read or not found for this user
            $response['success'] = true; // Still report success if already read for idempotency
            $response['message'] = 'Notification déjà lue ou non trouvée.';
        }
    } elseif ($action === 'read_all') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE");
        $stmt->execute([':user_id' => $userId]);

        $response['success'] = true;
        $response['message'] = 'Toutes les notifications ont été marquées comme lues.';
    } else {
        $response['message'] = 'Action non valide.';
    }

} catch (PDOException $e) {
    error_log("Erreur de base de données lors du marquage des notifications : " . $e->getMessage());
    $response['message'] = 'Erreur de base de données.';
}

echo json_encode($response);
?>