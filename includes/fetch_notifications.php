<?php
// includes/fetch_notifications.php

require_once __DIR__ . '/../config.php'; // Ajustez le chemin vers votre config.php
// Assurez-vous que la session est démarrée et que l'utilisateur est connecté
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

try {
    // Récupérer les notifications non lues pour l'utilisateur actuel
    $stmt = $pdo->prepare("SELECT id, message, link, is_read, created_at FROM notifications WHERE user_id = :user_id AND is_read = FALSE ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([':user_id' => $userId]);
    $unreadNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer le nombre total de notifications non lues
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = FALSE");
    $stmtCount->execute([':user_id' => $userId]);
    $unreadCount = $stmtCount->fetchColumn();

    $response['success'] = true;
    $response['notifications'] = $unreadNotifications;
    $response['unread_count'] = $unreadCount;

} catch (PDOException $e) {
    error_log("Erreur de base de données lors de la récupération des notifications : " . $e->getMessage());
    $response['message'] = 'Erreur de base de données.';
}

echo json_encode($response);
?>