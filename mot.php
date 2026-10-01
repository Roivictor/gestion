<?php
/**
 * mot.php - Script de démonstration UNIQUEMENT pour comprendre le hachage de mot de passe.
 *
 * ATTENTION : Ce script NE DOIT PAS être utilisé sur un serveur de production ou sur une page
 * accessible au public. Il est conçu uniquement à des fins éducatives et de test local.
 * Il affiche un mot de passe en clair, ce qui est une pratique non sécurisée en production.
 *
 * Le hachage de mot de passe est un processus irréversible. Vous ne pouvez PAS récupérer le mot
 * de passe original à partir de son hachage.
 *
 * PHP utilise la fonction 'password_hash()' pour hacher les mots de passe de manière sécurisée
 * (en utilisant l'algorithme bcrypt par défaut, qui inclut un "salt" aléatoire).
 * La fonction 'password_verify()' est utilisée pour vérifier si un mot de passe en clair correspond
 * à un hachage stocké, sans jamais "déhacher" le mot de passe.
 */

// 1. Définition du mot de passe en clair pour la démonstration
$motDePasseClair = "1234"; // Ce mot de passe est en clair uniquement à des fins de test ici.

// 2. Hachage du mot de passe clair
// PASSWORD_DEFAULT utilise l'algorithme de hachage le plus fort et le plus récent disponible (actuellement bcrypt).
// Il inclut automatiquement un "salt" aléatoire pour chaque hachage.
$motDePasseHache = password_hash($motDePasseClair, PASSWORD_DEFAULT);

echo "<h2>Démonstration de Hachage de Mot de Passe</h2>";
echo "<p><strong>Mot de passe clair :</strong> " . htmlspecialchars($motDePasseClair) . "</p>";
echo "<p><strong>Mot de passe haché (stocké en DB) :</strong> <code style='word-break: break-all;'>" . htmlspecialchars($motDePasseHache) . "</code></p>";

echo "<hr>";

// 3. Simulation de la vérification du mot de passe lors de la connexion
// Supposons que l'utilisateur entre "MonSuperMotDePasseSecret123!" lors de la connexion.
$motDePasseTente = "MonSuperMotDePasseSecret123!";
echo "<h3>Vérification du mot de passe :</h3>";
echo "<p>Mot de passe que l'utilisateur tente d'entrer : <strong>" . htmlspecialchars($motDePasseTente) . "</strong></p>";

// 'password_verify()' prend le mot de passe en clair et le hachage.
// Il hache le mot de passe en clair avec le même salt que le hachage fourni, puis compare les deux hachages.
if (password_verify($motDePasseTente, $motDePasseHache)) {
    echo "<p style='color: green; font-weight: bold;'>Le mot de passe tenté correspond au hachage ! Connexion réussie.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Le mot de passe tenté NE correspond PAS au hachage. Connexion échouée.</p>";
}

echo "<br>";

// Test avec un mot de passe incorrect pour la démonstration
$motDePasseIncorrect = "MotDePasseIncorrect";
echo "<p>Mot de passe incorrect tenté : <strong>" . htmlspecialchars($motDePasseIncorrect) . "</strong></p>";
if (password_verify($motDePasseIncorrect, $motDePasseHache)) {
    echo "<p style='color: green; font-weight: bold;'>Le mot de passe incorrect correspond (erreur !).</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Le mot de passe incorrect NE correspond PAS au hachage. (Correct).</p>";
}

?>
