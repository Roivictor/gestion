<?php
// includes/mark_notification_read.php

require_once __DIR__ . '/../config.php'; // Ajustez le chemin vers votre config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user']['id'])) {
    $response['message'] = 'Utilisateur non connecté.';
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user']['id'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$notificationId = $data['notification_id'] ?? null;

if (!$notificationId) {
    $response['message'] = 'ID de notification manquant.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Notification marquée comme lue.';
    } else {
        $response['message'] = 'Notification non trouvée ou déjà lue.';
    }

} catch (PDOException $e) {
    error_log("Erreur de base de données lors du marquage comme lu : " . $e->getMessage());
    $response['message'] = 'Erreur de base de données.';
}

echo json_encode($response);
?>