<?php
// admin_ajax.php

// Toujours commencer par ob_start() pour capturer les éventuelles erreurs et éviter l'affichage prématuré
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../config.php'; // Votre fichier de configuration avec les fonctions et la connexion DB

// IMPORTANT : Définir l'en-tête pour indiquer que la réponse est du JSON
header('Content-Type: application/json');

// Vérification du rôle admin (essentiel pour la sécurité)
// Cette fonction devrait s'assurer de sortir avec un JSON d'erreur si non-admin
// au lieu de rediriger vers une page HTML.
checkAdminRole(); // Assurez-vous que cette fonction gère la redirection ou l'arrêt si non-admin

// Vérifier le token CSRF pour toutes les requêtes qui modifient des données (POST, ou GET pour delete ici)
// La vérification du token doit être après checkAdminRole pour ne pas exposer d'informations inutiles.
$csrf_token = $_POST['_csrf_token'] ?? $_GET['_csrf_token'] ?? '';
if (!verifyCsrfToken($csrf_token)) { // Cette fonction doit être dans config.php
    http_response_code(403); // Forbidden
    echo json_encode(['success_message' => null, 'error_message' => '[csrf_token_mismatch]']);
    ob_end_clean(); // Nettoyer le tampon avant d'envoyer la réponse
    exit();
}


$response = [
    'success_message' => null,
    'error_message' => null,
    'products' => [] // Pour les requêtes de filtrage
];

// Récupérer l'action demandée par le JavaScript
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'filter_products':
            $search = $_GET['search'] ?? '';
            $category_id = $_GET['category_id'] ?? '';

            $sql = "SELECT p.*, c.name AS category_name, 
                           COALESCE(SUM(pv.stock_quantity), 0) AS total_stock_quantity
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN product_variants pv ON p.id = pv.product_id
                    WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            if (!empty($category_id) && $category_id !== 'all') {
                $sql .= " AND p.category_id = ?";
                $params[] = $category_id;
            }

            $sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($products) {
                foreach ($products as &$product) {
                    $product['image_url'] = $product['image_url'] ?? ''; 
                    $product['stock_quantity'] = (int)$product['total_stock_quantity']; // Cast en entier pour JS
                }
                $response['products'] = $products;
                $response['success_message'] = '[products_fetched_success]';
            } else {
                $response['error_message'] = '[no_products_found]';
            }
            break;

        case 'delete_product':
            $product_id = $_GET['id'] ?? null; 
            if (!$product_id || !is_numeric($product_id)) {
                http_response_code(400); // Bad Request
                $response['error_message'] = '[invalid_product_id]';
                break;
            }

            // Commencer une transaction pour assurer l'intégrité
            $pdo->beginTransaction();
            try {
                // Supprimer d'abord les variantes du produit
                $stmt_delete_variants = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
                $stmt_delete_variants->execute([$product_id]);

                // Ensuite, supprimer le produit lui-même
                $stmt_delete_product = $pdo->prepare("DELETE FROM products WHERE id = ?");
                if ($stmt_delete_product->execute([$product_id])) {
                    if ($stmt_delete_product->rowCount() > 0) {
                        $response['success_message'] = '[product_deleted_success]';
                        $pdo->commit(); // Confirmer la transaction
                    } else {
                        http_response_code(404); // Not Found
                        $response['error_message'] = '[invalid_product_id]'; 
                        $pdo->rollBack(); // Annuler la transaction
                    }
                } else {
                    http_response_code(500); // Internal Server Error
                    $response['error_message'] = '[product_delete_error]';
                    $pdo->rollBack(); // Annuler la transaction
                }
            } catch (PDOException $e) {
                $pdo->rollBack(); // Assurez-vous de rollBack en cas d'erreur
                throw $e; // Relaisser l'exception au bloc catch principal
            }
            break;

        default:
            http_response_code(400); // Bad Request
            $response['error_message'] = '[invalid_request_method]'; 
            break;
    }
} catch (PDOException $e) {
    error_log("Database error in admin_ajax.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['error_message'] = '[db_error_generic]'; 
} catch (Exception $e) {
    error_log("General error in admin_ajax.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    $response['error_message'] = '[unexpected_server_response]'; 
}

// Nettoyer le tampon de sortie avant d'envoyer la réponse JSON
ob_end_clean(); // Remplace ob_end_flush() ici pour s'assurer qu'aucune autre sortie n'est envoyée
echo json_encode($response);
exit(); // Assure qu'aucun autre code n'est exécuté après l'envoi du JSON
?>