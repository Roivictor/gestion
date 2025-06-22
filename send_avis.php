<?php
session_start();
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars($_POST['nom']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);

    if (!empty($nom) && !empty($email) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO avis_clients (nom, email, message) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $email, $message]);
        $_SESSION['avis_envoye'] = true;
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
