<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'includes/functions.php'; // Assurez-vous que isLoggedIn() et redirect() sont ici

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Vous devez être connecté pour accéder à votre profil.";
    redirect('login.php');
    exit();
}

// CORRECTION ICI: Utilisation de $_SESSION['user']['id'] pour la robustesse
$user_id = $_SESSION['user']['id'] ?? null; 

// Vérifiez que user_id est valide
if (is_null($user_id)) {
    $_SESSION['error_message'] = "Erreur de session. Veuillez vous reconnecter.";
    redirect('login.php');
    exit();
}

$customer_info = [];

// Récupérer les informations du client depuis la base de données
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $customer_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si le client n'existe pas encore, insérez une entrée basique
    if (!$customer_info) {
        $stmt_insert = $pdo->prepare("INSERT INTO customers (user_id, created_at, updated_at) VALUES (:user_id, NOW(), NOW())");
        $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_insert->execute();
        // Réinitialisation de customer_info après l'insertion pour s'assurer qu'il contient au moins les clés par défaut
        $customer_info = ['user_id' => $user_id, 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address' => '', 'city' => '', 'zip_code' => '', 'country' => ''];
        $_SESSION['success_message'] = "Votre profil client a été créé. Veuillez compléter vos informations.";
    }

    // Gérer la soumission du formulaire de mise à jour
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Vérification du token CSRF
        if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide.";
            redirect(BASE_URL . 'profile.php');
            exit();
        }

        // Récupération et nettoyage des nouvelles données, y compris les nouveaux champs
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $zip_code = sanitize($_POST['zip_code'] ?? '');
        $country = sanitize($_POST['country'] ?? '');

        // Validation simple des champs obligatoires (ajustée pour inclure les nouveaux champs)
        if (empty($first_name) || empty($last_name) || empty($email) || empty($address) || empty($city) || empty($country)) {
            $_SESSION['error_message'] = "Veuillez remplir tous les champs obligatoires (Prénom, Nom, Email, Adresse, Ville, Pays).";
            redirect(BASE_URL . 'profile.php');
            exit();
        }
        
        // Validation simple de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = "Veuillez saisir une adresse email valide.";
            redirect(BASE_URL . 'profile.php');
            exit();
        }

        // Requête de mise à jour incluant tous les champs
        $stmt_update = $pdo->prepare("
            UPDATE customers
            SET 
                first_name = :first_name, 
                last_name = :last_name, 
                email = :email, 
                phone = :phone, 
                address = :address, 
                city = :city, 
                zip_code = :zip_code, 
                country = :country, 
                updated_at = NOW()
            WHERE user_id = :user_id
        ");
        $stmt_update->bindParam(':first_name', $first_name);
        $stmt_update->bindParam(':last_name', $last_name);
        $stmt_update->bindParam(':email', $email);
        $stmt_update->bindParam(':phone', $phone);
        $stmt_update->bindParam(':address', $address);
        $stmt_update->bindParam(':city', $city);
        $stmt_update->bindParam(':zip_code', $zip_code);
        $stmt_update->bindParam(':country', $country);
        $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($stmt_update->execute()) {
            $_SESSION['success_message'] = "Votre profil a été mis à jour avec succès !";
            // Re-récupérer les infos après la mise à jour pour les afficher
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $customer_info = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['error_message'] = "Erreur lors de la mise à jour de votre profil.";
        }
        redirect(BASE_URL . 'profile.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Erreur PDO dans profile.php: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors du chargement ou de la mise à jour de votre profil. Veuillez réessayer.";
    // Rediriger vers une page sûre en cas d'erreur grave
    redirect(BASE_URL . 'index.php'); // Changé de dashboard.php à index.php pour plus de sécurité si dashboard n'existe pas
    exit();
}

// Générer le jeton CSRF pour le formulaire
$csrf_token = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="py-5">
        <div class="container">
            <h2 class="mb-4">Mon Profil Client</h2>

            <?php include 'includes/flash_messages.php'; ?>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Mes informations personnelles et de livraison</h5>
                </div>
                <div class="card-body">
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="mb-3">
                            <label for="first_name" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($customer_info['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Nom de famille</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($customer_info['last_name'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($customer_info['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($customer_info['phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Adresse Complète</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= htmlspecialchars($customer_info['address'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="city" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= htmlspecialchars($customer_info['city'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="zip_code" class="form-label">Code Postal</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" value="<?= htmlspecialchars($customer_info['zip_code'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label">Pays</label>
                            <input type="text" class="form-control" id="country" name="country" value="<?= htmlspecialchars($customer_info['country'] ?? '') ?>" required>
                        </div>

                        <button type="submit" class="btn btn-success">Mettre à jour le profil</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>