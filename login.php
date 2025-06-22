<?php
// login.php - Version Finale et Sécurisée

// Inclut le fichier de configuration qui contient la connexion DB, les fonctions utilitaires (sanitize, redirect),
// la gestion des sessions (session_start est au début de config.php), et les fonctions CSRF.
require_once 'config.php';

// Debug: Afficher les erreurs (IMPORTANT : à retirer en production en mettant display_errors à 0 !)
ini_set('display_errors', 1);       // 1 pour afficher les erreurs, 0 pour les cacher
ini_set('display_startup_errors', 1); // Affiche les erreurs de démarrage
error_reporting(E_ALL);             // Rapporte tous les types d'erreurs

$error = ''; // Variable pour stocker et afficher les messages d'erreur de connexion

// Traitement du formulaire de connexion si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Protection CSRF : Vérification du token avant toute autre opération sensible
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité : Tentative de connexion non autorisée. Veuillez réessayer.";
        error_log("CSRF token mismatch or missing on login attempt.");
        // Pour des raisons de sécurité, on peut aussi exit() ici, mais afficher une erreur est plus user-friendly
    } else {
        // Nettoyage des entrées utilisateur
        $username = sanitize($_POST['username']);
        $password = $_POST['password']; // Ne pas sanitize le mot de passe avant password_verify car il doit être brut

        try {
            // Prépare et exécute la requête SQL pour récupérer l'utilisateur par son nom d'utilisateur.
            // Requête préparée pour prévenir les injections SQL.
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(); // Récupère la ligne utilisateur

            if ($user) { // Si un utilisateur est trouvé avec ce nom d'utilisateur
                // Vérification du mot de passe haché.
                // 'password_verify()' compare le mot de passe en texte clair avec le hachage stocké.
                if (password_verify($password, $user['password'])) {
                    // Stockage des données utilisateur dans la session après authentification réussie.
                    // Le rôle est stocké en minuscules pour une comparaison uniforme.
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => strtolower($user['role']), // Normalise le rôle en minuscules (ex: 'Admin' devient 'admin')
                        'email' => $user['email']
                    ];

                    // Log pour débogage (peut être retiré ou adapté en production)
                    error_log("User logged in: " . $_SESSION['user']['username'] . " with role: " . $_SESSION['user']['role']);

                    // Redirection basée sur le rôle de l'utilisateur ou le besoin de changer le mot de passe.
                    if (isset($user['must_change_password']) && $user['must_change_password']) { // Vérifier si la colonne existe
                        redirect(BASE_URL . 'change_password.php'); // Redirige vers la page de changement de mot de passe
                    } else {
                        switch ($_SESSION['user']['role']) { // Utilise le rôle normalisé de la session
                            case 'admin':
                                // Redirige les administrateurs vers le tableau de bord admin.
                                redirect(BASE_URL . 'admin/dashboard.php');
                                break;
                            case 'employee':
                                // Redirige les employés vers leur tableau de bord.
                                redirect(BASE_URL . 'employee/dashboard.php');
                                break;
                            default: // Pour les autres rôles (ex: 'client'), redirige vers la page d'accueil du site client.
                                redirect(BASE_URL . 'index.php');
                        }
                    }
                } else {
                    $error = "Mot de passe incorrect."; // Message d'erreur si le mot de passe ne correspond pas.
                }
            } else {
                $error = "Nom d'utilisateur introuvable."; // Message d'erreur si l'utilisateur n'existe pas.
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données. Veuillez réessayer."; // Erreur générique DB
            error_log("Login PDO error: " . $e->getMessage()); // Log l'erreur détaillée pour l'administrateur
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <!-- Liens CSS Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Lien pour les icônes Bootstrap (doit être un LINK, pas un SCRIPT) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Styles CSS personnalisés pour la page de connexion */
        body {
            background-color: #f8f9fa; /* Couleur de fond légère */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* La carte prendra au moins toute la hauteur de la vue */
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); /* Ombre plus prononcée */
            width: 100%; /* S'assure que la carte prend toute la largeur de sa colonne */
            max-width: 400px; /* Limite la largeur maximale pour les grands écrans */
        }
        .card-header {
            background-color: #007bff !important; /* Couleur primaire de Bootstrap */
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 1.25rem;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center mb-0">Connexion</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($_SESSION['error_message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error_message']); // Supprime le message après l'affichage ?>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <!-- Champ caché pour le token CSRF -->
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-box-arrow-in-right"></i> Se connecter
                            </button>
                        </form>

                        <div class="mt-3 text-center">
                            <a href="<?= BASE_URL ?>register.php" class="text-decoration-none">Créer un compte</a> |
                            <a href="<?= BASE_URL ?>forgot_password.php" class="text-decoration-none">Mot de passe oublié?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script JavaScript de Bootstrap (pour les fonctionnalités comme les alertes dismissibles) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
