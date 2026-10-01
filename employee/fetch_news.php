<?php
// employee/fetch_news.php

require_once __DIR__ . '/../config.php'; // Inclure le fichier de configuration pour la clé API

header('Content-Type: application/json'); // Indiquer que la réponse est du JSON

// Vérifier si la clé API est définie
// Cette vérification est utile si vous n'utilisez pas directement les constantes
// Mais étant donné que nous allons les utiliser, assurez-vous qu'elles sont bien définies dans config.php
if (!defined('NEWS_API_KEY') || !defined('NEWS_API_URL')) {
    echo json_encode(['error' => 'News API key or URL not defined in config.php. Please ensure NEWS_API_KEY and NEWS_API_URL are defined.']);
    exit();
}

// Utilisation des constantes définies dans config.php
$apiKey = NEWS_API_KEY; // Correctement récupéré de config.php
$apiUrl = NEWS_API_URL; // Correctement récupéré de config.php

// Paramètres de la requête API (vous pouvez les ajuster)
$params = [
    'country' => 'fr', // Pays des actualités (ex: 'fr' pour France, 'us' pour États-Unis)
    'category' => 'general', // Catégorie (ex: 'business', 'technology', 'health', 'sports', 'general')
    'pageSize' => 6, // Nombre d'articles à récupérer
    'apiKey' => $apiKey // Utilisation de la variable $apiKey
];

$query = http_build_query($params);
$fullUrl = "$apiUrl?$query";

// Initialisation de cURL
$ch = curl_init();

// Configuration de cURL
curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retourner le transfert sous forme de chaîne de caractères
curl_setopt($ch, CURLOPT_USERAGENT, 'HR-Management-App/1.0'); // Un agent utilisateur est souvent requis

// Exécution de la requête et récupération de la réponse
$response = curl_exec($ch);

// Vérification des erreurs cURL
if (curl_errno($ch)) {
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit();
}

// Fermeture de la session cURL
curl_close($ch);

// Décoder la réponse JSON
$data = json_decode($response, true);

// Vérifier si la réponse est valide
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Failed to decode API response: ' . json_last_error_msg() . ' Raw response: ' . $response]); // Added raw response for debugging
    exit();
}

// Vérifier si l'API a retourné un statut d'erreur
if (isset($data['status']) && $data['status'] === 'error') {
    echo json_encode(['error' => 'NewsAPI Error: ' . ($data['code'] ?? 'Unknown Code') . ' - ' . ($data['message'] ?? 'Unknown Message')]);
    exit();
}

// Retourner les données d'actualités
echo json_encode($data);
?>