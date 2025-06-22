<?php
// C'est le mot de passe CLAIR que vous voulez pour votre admin.
// REMPLACEZ 'SuperAdminPass123' par le vrai mot de passe que vous avez choisi.
$mot_de_passe_clair_admin = '123'; 

// Cette fonction PHP va hacher le mot de passe.
$mot_de_passe_hache_pour_bd = password_hash($mot_de_passe_clair_admin, PASSWORD_DEFAULT);

echo "Mot de passe CLAIR de l'admin que vous avez choisi : <strong>" . htmlspecialchars($mot_de_passe_clair_admin) . "</strong><br><br>";
echo "Voici le mot de passe HACHÉ à copier et coller dans la colonne 'password' de votre table 'users' pour l'utilisateur 'admin' :<br>";
echo "<code><strong>" . htmlspecialchars($mot_de_passe_hache_pour_bd) . "</strong></code><br><br>";
echo "IMPORTANT : Supprimez ce fichier (generer_hash_admin.php) après utilisation pour la sécurité.";
?>