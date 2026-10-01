<?php
// login.php - Version Corrigée et Sécurisée

// Inclut le fichier de configuration qui contient la connexion DB, les fonctions utilitaires (sanitize, redirect),
// la gestion des sessions (session_start est au début de config.php), et les fonctions CSRF.
require_once 'config.php'; // Assurez-vous que ce chemin est correct selon votre arborescence

// Debug: Afficher les erreurs (IMPORTANT : à retirer en production en mettant display_errors à 0 !)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error = ''; // Variable pour stocker et afficher les messages d'erreur de connexion

// --- GESTION DU JETON CSRF ---
// Générer le jeton CSRF pour le formulaire. Il doit être appelé ici pour s'assurer
// qu'un jeton est toujours présent en session avant toute tentative de POST ou d'affichage du formulaire.
// Assurez-vous que la fonction generateCsrfToken() est définie dans config.php ou includes/functions.php
$csrf_token = generateCsrfToken();


// Redirection si déjà connecté
if (isset($_SESSION['user']['id'])) {
    if (isset($_SESSION['user']['role'])) {
        $user_role = strtolower($_SESSION['user']['role']);

        if ($user_role === 'admin') {
            redirect(BASE_URL . 'admin/dashboard.php');
        } elseif ($user_role === 'employee') {
            if (isset($_SESSION['user']['is_first_login']) && $_SESSION['user']['is_first_login']) {
                redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
            } else {
                redirect(BASE_URL . 'employee/dashboard.php');
            }
        } elseif ($user_role === 'client' || $user_role === 'customer') {
            // Vérifier que le customer_id est bien défini pour un client avant de rediriger
            // Si customer_id est manquant ici, c'est une anomalie de session, on pourrait rediriger vers le profil.
            if (!empty($_SESSION['user']['customer_id'])) {
                redirect(BASE_URL . 'index.php'); // Redirection par défaut pour les clients
            } else {
                // Si le client est connecté mais n'a plus de customer_id en session,
                // il faut le rediriger vers le profil pour qu'il le mette à jour ou qu'il soit créé.
                $_SESSION['error_message'] = "Votre profil client est incomplet. Veuillez vérifier vos informations.";
                error_log("DEBUG: Utilisateur " . $_SESSION['user']['username'] . " (ID:" . $_SESSION['user']['id'] . ") client sans customer_id en session. Redirection vers profile.php");
                redirect(BASE_URL . 'profile.php'); // Rediriger vers la page de profil
            }
        } else {
            error_log("Rôle utilisateur inconnu en session pour user_id: " . $_SESSION['user']['id'] . ". Rôle: " . $_SESSION['user']['role']); // Modifié ici
            // Optionnel : redirect(BASE_URL . 'logout.php'); pour vider la session
            redirect(BASE_URL . 'index.php');
        }
    } else {
        error_log("Session user_id existante sans rôle défini. Déconnexion forcée.");
        redirect(BASE_URL . 'logout.php');
    }
    exit();
}


// Traitement du formulaire de connexion si la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Protection CSRF : Vérification du token avant toute autre opération sensible
    // Ajout d'une vérification explicite de l'existence du jeton en session
    if (!isset($_SESSION['csrf_token'])) {
        $error = "Erreur de sécurité : Jeton CSRF manquant en session. Veuillez réessayer.";
        error_log("CSRF Error: Aucune jeton CSRF en session.");
    } elseif (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité : Tentative de connexion non autorisée. Veuillez réessayer.";
        error_log("CSRF token mismatch or missing on login attempt.");
    } else {
        // Nettoyage des entrées utilisateur
        $username_input = sanitize($_POST['username']); // Renommé pour éviter la confusion avec $username de la DB
        $password_input = $_POST['password']; // Ne pas sanitize le mot de passe avant password_verify car il doit être brut

        // Vérification de l'existence des champs
        if (empty($username_input) || empty($password_input)) {
            $error = "Veuillez remplir tous les champs.";
        } else {
            try {
                // Prépare et exécute la requête SQL pour récupérer l'utilisateur par son nom d'utilisateur.
                // Nous joignons également la table 'customers' pour obtenir le 'customer_id' si l'utilisateur est un client.
                $stmt = $pdo->prepare("
                    SELECT
                        u.id AS user_id_from_users_table,
                        u.username,
                        u.first_name,
                        u.last_name,
                        u.role,
                        u.email,
                        u.password AS password_hash,
                        u.is_first_login,
                        c.id AS customer_id_from_customers_table
                    FROM
                        users u
                    LEFT JOIN
                        customers c ON u.id = c.user_id
                    WHERE
                        u.username = :username
                ");
                $stmt->bindParam(':username', $username_input, PDO::PARAM_STR);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Vérifier le mot de passe haché
                    if (password_verify($password_input, $user['password_hash'])) {
                        // Authentification réussie.

                        $role = strtolower($user['role']);

                        // --- GESTION AMÉLIORÉE DE customer_id ---
                        // Pour les clients, si customer_id est NULL, nous devons le créer MAINTENANT.
                        // Cela résout le problème "L'utilisateur X est un client mais n'a pas de customer_id associé."
                        $customer_id_to_use = null;
                        if ($role === 'client' || $role === 'customer') {
                            if (!empty($user['customer_id_from_customers_table'])) {
                                $customer_id_to_use = $user['customer_id_from_customers_table'];
                                error_log("DEBUG: Utilisateur " . $user['username'] . " (ID:" . $user['user_id_from_users_table'] . ") est un client existant avec customer_id: " . $customer_id_to_use);
                            } else {
                                // L'utilisateur est un client mais n'a pas d'entrée dans la table customers.
                                // Insérons-le maintenant pour éviter l'erreur de clé étrangère plus tard.
                                try {
                                    $stmt_insert_customer = $pdo->prepare("INSERT INTO customers (user_id, created_at, updated_at) VALUES (:user_id, NOW(), NOW())");
                                    $stmt_insert_customer->bindParam(':user_id', $user['user_id_from_users_table'], PDO::PARAM_INT);
                                    $stmt_insert_customer->execute();
                                    $customer_id_to_use = $pdo->lastInsertId(); // Récupère l'ID nouvellement créé

                                    error_log("DEBUG: Utilisateur " . $user['username'] . " (ID:" . $user['user_id_from_users_table'] . ") nouveau client. customer_id créé: " . $customer_id_to_use);
                                    $_SESSION['success_message'] = "Votre compte client a été initialisé. Veuillez compléter vos informations de profil.";

                                    // Mettez à jour $user['customer_id_from_customers_table'] pour la session ci-dessous
                                    $user['customer_id_from_customers_table'] = $customer_id_to_use;

                                } catch (PDOException $e) {
                                    error_log("Erreur PDO lors de la création d'un client dans login.php: " . $e->getMessage());
                                    $error = "Une erreur est survenue lors de l'initialisation de votre compte client. Veuillez réessayer.";
                                    // Ne pas continuer la connexion si la création du client échoue
                                    // Pas besoin de unset($_SESSION['user_id']); ou session_destroy() car aucune session n'est encore pleinement établie
                                    exit();
                                }
                            }
                        }
                        // La variable $_SESSION['user_id'] est obsolète et sera supprimée. Toutes les informations sont dans $_SESSION['user'].

                        // Stocker les détails complets de l'utilisateur dans la session
                        $_SESSION['user'] = [
                            'id' => $user['user_id_from_users_table'], // L'ID de l'utilisateur dans la table users (toujours l'original)
                            'username' => $user['username'],
                            'first_name' => $user['first_name'],
                            'last_name' => $user['last_name'],
                            'role' => $role,
                            'email' => $user['email'],
                            'is_first_login' => (bool)($user['is_first_login'] ?? false),
                            'customer_id' => $user['customer_id_from_customers_table'] // Assurez-vous que c'est le customer_id mis à jour
                        ];

                        error_log("User logged in: " . $_SESSION['user']['username'] . " with role: " . $_SESSION['user']['role'] . " and is_first_login: " . ($_SESSION['user']['is_first_login'] ? 'true' : 'false') . ". Session user ID: " . $_SESSION['user']['id'] . ". Customer ID: " . ($_SESSION['user']['customer_id'] ?? 'N/A')); // Modifié ici

                        // Redirection basée sur le rôle de l'utilisateur
                        switch ($_SESSION['user']['role']) {
                            case 'admin':
                                redirect(BASE_URL . 'admin/dashboard.php');
                                break;
                            case 'employee':
                                if ($_SESSION['user']['is_first_login']) {
                                    redirect(BASE_URL . 'employee/dashboard.php?page=change_password_first_login');
                                } else {
                                    redirect(BASE_URL . 'employee/dashboard.php');
                                }
                                break;
                            case 'client':
                            case 'customer':
                                // Si un nouveau customer_id vient d'être créé, rediriger vers le profil pour compléter
                                if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) { // Nous avons défini un message de succès si client créé
                                    redirect(BASE_URL . 'profile.php');
                                } else {
                                    redirect(BASE_URL . 'index.php'); // Redirection par défaut pour les clients
                                }
                                break;
                            default:
                                redirect(BASE_URL . 'index.php');
                        }
                        exit();

                    } else {
                        // Mot de passe incorrect
                        $error = "Nom d'utilisateur ou mot de passe incorrect.";
                        error_log("Tentative de connexion échouée pour l'utilisateur " . $username_input . ": mot de passe incorrect.");
                    }
                } else {
                    // Utilisateur non trouvé
                    $error = "Nom d'utilisateur ou mot de passe incorrect.";
                    error_log("Tentative de connexion échouée: utilisateur " . $username_input . " non trouvé.");
                }
            } catch (PDOException $e) {
                $error = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
                error_log("Login PDO error: " . $e->getMessage());
            }
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
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }
        .card-header {
            background-color: #007bff !important;
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
                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($_SESSION['success_message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['success_message']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($_SESSION['error_message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error_message']); ?>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

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