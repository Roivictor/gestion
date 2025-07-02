<?php
// employee/upload_profile_picture.php
session_start(); // Démarre la session PHP
require_once __DIR__ . '/../config.php'; // Chemin vers config.php (adapter si nécessaire)
require_once __DIR__ . '/../includes/functions.php'; // Fonctions utilitaires

header('Content-Type: application/json'); // Indique que la réponse sera en JSON

$response = ['success' => false, 'message' => ''];

// 1. Vérification de l'authentification et du rôle
// Vérifier si l'utilisateur est connecté et est un EMPLOYE
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'employee') {
    $response['message'] = __('access_denied'); // Utilise la fonction de traduction si disponible
    echo json_encode($response);
    exit();
}

// Récupérer les informations de l'utilisateur connecté (incluant l'ancienne photo de profil si elle existe)
$currentUser = $_SESSION['user']; // Normalement la session devrait déjà contenir les infos à jour
$user_id = $currentUser['id'];

// --- Optionnel mais fortement recommandé : Vérification du token CSRF ---
// Si vous implémentez un token CSRF, décommentez et adaptez cette partie.
/*
if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
    $response['message'] = __('invalid_csrf_token');
    echo json_encode($response);
    exit();
}
*/

// 2. Traitement de l'upload du fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];

    // Vérifier les erreurs d'upload PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $phpFileUploadErrors = array(
            UPLOAD_ERR_INI_SIZE   => 'Le fichier téléchargé dépasse la directive upload_max_filesize dans php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier téléchargé dépasse la directive MAX_FILE_SIZE spécifiée dans le formulaire HTML.',
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Un dossier temporaire est manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
            UPLOAD_ERR_EXTENSION  => 'Une extension PHP a arrêté l\'upload du fichier.'
        );
        $response['message'] = $phpFileUploadErrors[$file['error']] ?? 'Erreur inconnue lors de l\'upload du fichier.';
        echo json_encode($response);
        exit();
    }

    // Valider le type de fichier (extensions et types MIME autorisés)
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedMimeTypes)) {
        $response['message'] = "Type de fichier non autorisé. Seules les images JPG, PNG, GIF sont acceptées.";
        echo json_encode($response);
        exit();
    }

    // Valider la taille du fichier (ex: max 2MB)
    $maxFileSize = 2 * 1024 * 1024; // 2 Mo (ajustez si nécessaire)
    if ($file['size'] > $maxFileSize) {
        $response['message'] = "Le fichier est trop grand. Taille maximale : " . ($maxFileSize / (1024 * 1024)) . " Mo.";
        echo json_encode($response);
        exit();
    }

    // 3. Déplacement du fichier et mise à jour en base de données
    $uploadDir = __DIR__ . '/../uploads/profile_pictures/'; // Chemin absolu vers le dossier d'upload
    $relativePath = 'uploads/profile_pictures/'; // Chemin relatif à stocker en base de données (pour l'accès via URL)

    // Créer le dossier si inexistant
    if (!is_dir($uploadDir)) {
        // Le 0777 est permis pour le développement, mais utilisez 0755 ou 0775 en production
        if (!mkdir($uploadDir, 0777, true)) {
            $response['message'] = "Impossible de créer le dossier d'upload.";
            echo json_encode($response);
            exit();
        }
    }

    // Générer un nom de fichier unique pour éviter les collisions
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = uniqid('profile_', true) . '.' . strtolower($fileExtension); // Assurer une extension en minuscules
    $destinationPath = $uploadDir . $newFileName;
    $relativePathToDb = $relativePath . $newFileName; // Chemin complet relatif à stocker

    // Déplacer le fichier téléchargé du dossier temporaire vers le dossier permanent
    if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
        try {
            // Suppression de l'ancienne image si elle existe et n'est pas l'image par défaut
            // Assurez-vous que $currentUser['profile_picture'] est bien le chemin relatif stocké en DB
            if (!empty($currentUser['profile_picture']) && $currentUser['profile_picture'] !== 'assets/img/default_profile.png') {
                $oldImagePath = __DIR__ . '/../' . $currentUser['profile_picture']; // Chemin absolu de l'ancienne image
                if (file_exists($oldImagePath) && is_file($oldImagePath)) {
                    unlink($oldImagePath); // Supprime l'ancien fichier
                }
            }

            // Mettre à jour le chemin de l'image de profil dans la base de données
            global $pdo; // Assurez-vous que $pdo est accessible (déclaré dans config.php)
            $stmt = $pdo->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :id");
            if ($stmt->execute([':profile_picture' => $relativePathToDb, ':id' => $user_id])) {
                $response['success'] = true;
                $response['message'] = "Photo de profil mise à jour avec succès.";
                $response['new_image_path'] = BASE_URL . $relativePathToDb; // Pour l'utiliser côté client si besoin

                // Mettre à jour la variable de session de l'utilisateur avec le nouveau chemin
                // Cela est important pour que la nouvelle image s'affiche sans recharger la page
                $_SESSION['user']['profile_picture'] = $relativePathToDb;

            } else {
                $response['message'] = "Erreur lors de l'enregistrement du chemin de l'image en base de données.";
                // Si l'insertion échoue, supprimer le fichier téléchargé pour ne pas laisser de fichiers orphelins
                if (file_exists($destinationPath)) {
                    unlink($destinationPath);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de la mise à jour du chemin de l'image de profil : " . $e->getMessage());
            $response['message'] = "Erreur système : Échec de la base de données. (" . $e->getMessage() . ")";
             // Supprimer le fichier téléchargé si la DB échoue
             if (file_exists($destinationPath)) {
                unlink($destinationPath);
            }
        }
    } else {
        $response['message'] = "Erreur lors du déplacement du fichier téléchargé. Vérifiez les permissions du dossier " . $uploadDir;
    }
} else {
    $response['message'] = "Aucun fichier n'a été envoyé ou requête invalide.";
}

echo json_encode($response); // Renvoyer la réponse JSON au client
exit(); // Terminer l'exécution du script
?>