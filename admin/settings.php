<?php
// Empêcher l'accès direct si nécessaire (déjà géré par dashboard.php qui inclut ce fichier)
// if (!defined('SITE_NAME')) { die('Accès direct interdit'); }

// Incluez le fichier de configuration si cette page est accessible directement (non incluse dans dashboard.php)
// require_once '../config.php';

// Vous êtes déjà dans le contexte de dashboard.php, donc $_SESSION est accessible.
// Le rôle admin a déjà été vérifié par checkAdminRole() dans dashboard.php.

// --- Logique pour gérer le changement de langue ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (isset($_POST['language'])) {
        $selected_language = sanitize($_POST['language']); // Assurez-vous d'avoir une fonction sanitize
        $_SESSION['user_language'] = $selected_language; // Stocke dans la session
        
        // Optionnel: Mettre à jour la langue préférée dans la base de données pour l'utilisateur
        // Cela nécessiterait une colonne 'language_preference' dans votre table 'users'
        if (isset($_SESSION['user']['id'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET language_preference = :lang WHERE id = :user_id");
                $stmt->execute([':lang' => $selected_language, ':user_id' => $_SESSION['user']['id']]);
                // Ajoutez un message de succès
                $_SESSION['success_message'] = "La langue a été mise à jour avec succès.";
            } catch (PDOException $e) {
                error_log("Erreur lors de la mise à jour de la langue: " . $e->getMessage());
                $_SESSION['error_message'] = "Erreur lors de la mise à jour de la langue.";
            }
        }
        
        // Rediriger pour éviter la soumission multiple du formulaire
        header("Location: dashboard.php?page=settings");
        exit();
    }
}

// Récupérer la langue actuelle de l'utilisateur pour pré-sélectionner l'option
$current_language = isset($_SESSION['user_language']) ? $_SESSION['user_language'] : 'fr'; // 'fr' par défaut
// Si la langue est stockée en DB, récupérez-la de là en priorité
if (isset($_SESSION['user']['language_preference'])) {
    $current_language = $_SESSION['user']['language_preference'];
}

?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Paramètres du tableau de bord</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Paramètres généraux</h6>
        </div>
        <div class="card-body">
            <form action="dashboard.php?page=settings" method="POST">
                <input type="hidden" name="action" value="update_settings">

                <div class="mb-3">
                    <label for="language_select" class="form-label">Langue d'affichage :</label>
                    <select class="form-select" id="language_select" name="language">
                        <option value="fr" <?= ($current_language === 'fr' ? 'selected' : '') ?>>Français</option>
                        <option value="en" <?= ($current_language === 'en' ? 'selected' : '') ?>>English</option>
                        <option value="zh" <?= ($current_language === 'zh' ? 'selected' : '') ?>>中文 (Chinois)</option>
                        <option value="de" <?= ($current_language === 'de' ? 'selected' : '') ?>>Deutsch (Allemand)</option>
                        <option value="es" <?= ($current_language === 'es' ? 'selected' : '') ?>>Español (Espagnol)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4 mt-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Autres paramètres (Exemple)</h6>
        </div>
        <div class="card-body">
            <p>Ici vous pouvez ajouter d'autres options de paramètres pour l'admin, comme :</p>
            <ul>
                <li>Gestion des devises</li>
                <li>Paramètres de notification</li>
                <li>Fuseau horaire</li>
                <li>Etc.</li>
            </ul>
        </div>
    </div>

</div>