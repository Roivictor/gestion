<?php
// C:\xampp\htdocs\u\test_db.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Mettez votre mot de passe si vous en avez un
define('DB_NAME', 'store_crm');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "Connexion à la base de données réussie ! La variable \$pdo est disponible.<br>";
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
        echo "Requête simple exécutée avec succès.<br>";
    }
} catch (PDOException $e) {
    echo "Erreur de connexion PDO : " . $e->getMessage() . "<br>";
    echo "Veuillez vérifier vos paramètres DB_HOST, DB_NAME, DB_USER, DB_PASS dans config.php.<br>";
    echo "Assurez-vous que MySQL est démarré dans XAMPP.";
}
?>