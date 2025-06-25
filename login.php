<?php
// login.php - Version Finale et Sécurisée (Corrigée)

// Inclut le fichier de configuration qui contient la connexion DB, les fonctions utilitaires (sanitize, redirect),
// la gestion des sessions (session_start est au début de config.php), et les fonctions CSRF.
require_once 'config.php'; // Assurez-vous que ce chemin est correct selon votre arborescence

// Debug: Afficher les erreurs (IMPORTANT : à retirer en production en mettant display_errors à 0 !)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = ''; // Variable pour stocker et afficher les messages d'erreur de connexion

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user']['role'])) { // S'assurer que le rôle est défini
        if ($_SESSION['user']['role'] === 'admin') {
            redirect(BASE_URL . 'admin/dashboard.php');
        } elseif ($_SESSION['user']['role'] === 'employee') {
            // Check for first login *before* redirecting to general dashboard
            if (isset($_SESSION['user']['is_first_login']) && $_SESSION['user']['is_first_login']) {
                redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
            } else {
                redirect(BASE_URL . 'employee/dashboard.php');
            }
        } else {
            redirect(BASE_URL . 'index.php'); // Rôle client ou non géré
        }
    } else {
        // Rôle non défini en session mais user_id l'est, déconnexion pour sécurité
        redirect(BASE_URL . 'logout.php');
    }
    exit();
}


// Traitement du formulaire de connexion si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Protection CSRF : Vérification du token avant toute autre opération sensible
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité : Tentative de connexion non autorisée. Veuillez réessayer.";
        error_log("CSRF token mismatch or missing on login attempt.");
    } else {
        // Nettoyage des entrées utilisateur
        $username = sanitize($_POST['username']);
        $password = $_POST['password']; // Ne pas sanitize le mot de passe avant password_verify car il doit être brut

        try {
            // Prépare et exécute la requête SQL pour récupérer l'utilisateur par son nom d'utilisateur.
            $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role, email, password, is_first_login FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Authentification réussie. Stockage des données utilisateur dans la session.
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => strtolower($user['role']), // Normalise le rôle en minuscules (ex: 'Admin' devient 'admin')
                        'email' => $user['email'],
                        'is_first_login' => (bool)($user['is_first_login'] ?? false)
                    ];

                    error_log("User logged in: " . $_SESSION['user']['username'] . " with role: " . $_SESSION['user']['role'] . " and is_first_login: " . ($_SESSION['user']['is_first_login'] ? 'true' : 'false'));

                    // --- CORRECTION APPORTÉE ICI ---
                    // Redirection basée sur le rôle de l'utilisateur, et ensuite la logique is_first_login
                    switch ($_SESSION['user']['role']) {
                        case 'admin':
                            // Si les admins doivent aussi changer leur mot de passe au premier login,
                            // ajoutez une logique similaire ici, redirigeant vers une page admin-spécifique.
                            // Exemple:
                            // if ($_SESSION['user']['is_first_login']) {
                            //     redirect(BASE_URL . 'admin/dashboard.php?page=change_password_first_login');
                            // } else {
                            //     redirect(BASE_URL . 'admin/dashboard.php');
                            // }
                            redirect(BASE_URL . 'admin/dashboard.php'); // Redirection par défaut pour l'admin
                            break;
                        case 'employee':
                            // SEULS les employés sont concernés par la redirection forcée ici
                            if ($_SESSION['user']['is_first_login']) {
                                redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
                            } else {
                                redirect(BASE_URL . 'employee/dashboard.php');
                            }
                            break;
                        default: // Pour les autres rôles (ex: 'client'), redirige vers la page d'accueil du site client.
                            redirect(BASE_URL . 'index.php');
                    }
                    exit(); // Très important d'appeler exit() après une redirection

                } else {
                    $error = "Nom d'utilisateur ou mot de passe incorrect.";
                }
            } else {
                $error = "Nom d'utilisateur ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données. Veuillez réessayer.";
            error_log("Login PDO error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= defined('SITE_NAME') ? SITE_NAME : 'Mon Application' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>