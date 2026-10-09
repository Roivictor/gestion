<?php
// process_checkout.php — Traitement sécurisé du passage de commande

require_once __DIR__ . '/config.php';

// ============================================================
// 1. VÉRIFICATIONS PRÉALABLES
// ============================================================

if (!isLoggedIn()) {
    setFlashMessage('danger', "Vous devez être connecté pour finaliser votre commande.");
    redirect(BASE_URL . 'login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('danger', "Méthode de requête non autorisée.");
    redirect(BASE_URL . 'checkout.php');
    exit();
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlashMessage('danger', 'Jeton CSRF invalide. Veuillez réessayer.');
    redirect(BASE_URL . 'checkout.php');
    exit();
}

$user_id = (int)($_SESSION['user']['id'] ?? 0);

if ($user_id <= 0) {
    error_log("process_checkout.php: user_id invalide dans la session.");
    setFlashMessage('danger', "Votre session utilisateur est invalide. Veuillez vous reconnecter.");
    redirect(BASE_URL . 'login.php');
    exit();
}

// ============================================================
// 2. RÉCUPÉRATION ET VALIDATION DES DONNÉES
// ============================================================

$total_amount        = filter_var($_POST['total_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$payment_method      = sanitize($_POST['payment_method'] ?? '');
$delivery_first_name = sanitize($_POST['delivery_first_name'] ?? '');
$delivery_last_name  = sanitize($_POST['delivery_last_name'] ?? '');
$delivery_email      = filter_var($_POST['delivery_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
$delivery_phone      = sanitize($_POST['delivery_phone'] ?? '');
$delivery_address    = sanitize($_POST['delivery_address'] ?? '');
$delivery_city       = sanitize($_POST['delivery_city'] ?? '');
$delivery_zip_code   = sanitize($_POST['delivery_zip_code'] ?? '');
$delivery_country    = sanitize($_POST['delivery_country'] ?? '');

$errors = [];

if ($total_amount === false || $total_amount <= 0) {
    $errors[] = "Total de commande invalide.";
}
if (empty($payment_method)) {
    $errors[] = "Méthode de paiement requise.";
}
if (empty($delivery_address)) {
    $errors[] = "Adresse de livraison requise.";
}

$allowed_payment_methods = ['cash_on_delivery', 'card', 'paypal', 'bank_transfer'];
if (!in_array($payment_method, $allowed_payment_methods, true)) {
    $errors[] = "Méthode de paiement invalide.";
}

if (!empty($errors)) {
    setFlashMessage('danger', implode('<br>', $errors));
    redirect(BASE_URL . 'checkout.php');
    exit();
}

// ============================================================
// 3. TRAITEMENT DE LA COMMANDE
// ============================================================

try {
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Connexion PDO indisponible.');
    }

    $pdo->beginTransaction();

    // ---- Récupérer le customer_id ----
    $stmt_customer = $pdo->prepare("SELECT id FROM customers WHERE user_id = :user_id LIMIT 1");
    $stmt_customer->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_customer->execute();
    $customer_data = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    if (!$customer_data) {
        $pdo->rollBack();
        setFlashMessage('danger', "Profil client introuvable. Contactez le support.");
        redirect(BASE_URL . 'checkout.php');
        exit();
    }
    $customer_id_for_order = (int)$customer_data['id'];

    // ---- Récupérer les articles du panier (user_id OU session_id) ----
    $session_id = session_id();
    $stmt_cart = $pdo->prepare("
        SELECT
            ci.quantity,
            p.id AS product_id,
            p.name AS product_name,
            p.price AS product_price,
            p.promo_price AS product_promo_price,
            p.stock_quantity AS product_stock_quantity,
            pv.id AS variant_id,
            pv.name AS variant_name,
            pv.price_adjustment AS variant_price_adjustment,
            pv.promo_price AS variant_promo_price,
            pv.stock_quantity AS variant_stock_quantity
        FROM cart_items ci
        INNER JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.user_id = :user_id 
           OR ci.session_id = :session_id
        FOR UPDATE
    ");
    $stmt_cart->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_cart->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt_cart->execute();
    $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $pdo->rollBack();
        setFlashMessage('warning', "Votre panier est vide.");
        redirect(BASE_URL . 'cart.php');
        exit();
    }

    // ---- Vérification stock + recalcul du total avec les prix ACTUELS ----
    $recalculated_total = 0;
    $order_items_prepared = [];

    foreach ($cart_items as $item) {
        $current_stock = ($item['variant_id'] !== null) 
            ? (int)$item['variant_stock_quantity'] 
            : (int)$item['product_stock_quantity'];

        if ((int)$item['quantity'] > $current_stock) {
            $pdo->rollBack();
            $name = htmlspecialchars($item['product_name']);
            if (!empty($item['variant_name'])) {
                $name .= " (" . htmlspecialchars($item['variant_name']) . ")";
            }
            setFlashMessage('danger', "Stock insuffisant pour {$name}. Disponible : {$current_stock}.");
            redirect(BASE_URL . 'cart.php');
            exit();
        }

        // Prix actuel (promo prioritaire)
        $base_price = (float)($item['product_promo_price'] ?? $item['product_price']);

        if ($item['variant_id'] !== null) {
            $base_price += (float)($item['variant_price_adjustment'] ?? 0);
            if (!empty($item['variant_promo_price'])) {
                $base_price = (float)$item['variant_promo_price'];
            }
        }

        $recalculated_total += $base_price * (int)$item['quantity'];

        $order_items_prepared[] = [
            'product_id' => (int)$item['product_id'],
            'variant_id' => $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
            'quantity'   => (int)$item['quantity'],
            'unit_price' => $base_price,
        ];
    }

    // Vérifier que le total client correspond (tolérance 0.01)
    if (abs($recalculated_total - $total_amount) > 0.01) {
        $pdo->rollBack();
        setFlashMessage('danger', "Le total de votre commande a changé. Veuillez vérifier votre panier.");
        redirect(BASE_URL . 'cart.php');
        exit();
    }

    // ---- Insérer la commande ----
    // ⚠️ IMPORTANT : user_id doit être fourni (NOT NULL dans le schéma)
    $stmt_order = $pdo->prepare("
        INSERT INTO orders 
        (customer_id, user_id, order_date, total_amount, status, payment_method, payment_status,
         delivery_first_name, delivery_last_name, delivery_email, delivery_phone,
         delivery_address, delivery_city, delivery_zip_code, delivery_country)
        VALUES 
        (:customer_id, :user_id, NOW(), :total_amount, 'pending', :payment_method, 'pending',
         :delivery_first_name, :delivery_last_name, :delivery_email, :delivery_phone,
         :delivery_address, :delivery_city, :delivery_zip_code, :delivery_country)
    ");
    $stmt_order->execute([
        ':customer_id'         => $customer_id_for_order,
        ':user_id'             => $user_id,
        ':total_amount'        => $recalculated_total,
        ':payment_method'      => $payment_method,
        ':delivery_first_name' => $delivery_first_name,
        ':delivery_last_name'  => $delivery_last_name,
        ':delivery_email'      => $delivery_email,
        ':delivery_phone'      => $delivery_phone,
        ':delivery_address'    => $delivery_address,
        ':delivery_city'       => $delivery_city,
        ':delivery_zip_code'   => $delivery_zip_code,
        ':delivery_country'    => $delivery_country,
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // ---- Insérer les détails + mettre à jour les stocks ----
    // ⚠️ IMPORTANT : price_at_purchase est NOT NULL, on le remplit
    $stmt_detail = $pdo->prepare("
        INSERT INTO order_details 
        (order_id, product_id, variant_id, quantity, unit_price, price_at_purchase) 
        VALUES 
        (:order_id, :product_id, :variant_id, :quantity, :unit_price, :price_at_purchase)
    ");
    $stmt_variant_stock = $pdo->prepare("
        UPDATE product_variants 
        SET stock_quantity = stock_quantity - :quantity 
        WHERE id = :variant_id
    ");
    $stmt_product_stock = $pdo->prepare("
        UPDATE products 
        SET stock_quantity = stock_quantity - :quantity 
        WHERE id = :product_id
    ");

    foreach ($order_items_prepared as $item) {
        $stmt_detail->execute([
            ':order_id'          => $order_id,
            ':product_id'        => $item['product_id'],
            ':variant_id'        => $item['variant_id'],
            ':quantity'          => $item['quantity'],
            ':unit_price'        => $item['unit_price'],
            ':price_at_purchase' => $item['unit_price'],
        ]);

        if ($item['variant_id'] !== null) {
            $stmt_variant_stock->execute([
                ':quantity'   => $item['quantity'],
                ':variant_id' => $item['variant_id'],
            ]);
        } else {
            $stmt_product_stock->execute([
                ':quantity'   => $item['quantity'],
                ':product_id' => $item['product_id'],
            ]);
        }
    }

    // ---- Historique de commande ----
    $stmt_history = $pdo->prepare("
        INSERT INTO order_history (order_id, status, comment, changed_by, created_at)
        VALUES (:order_id, 'pending', 'Commande créée par le client', :user_id, NOW())
    ");
    $stmt_history->execute([
        ':order_id' => $order_id,
        ':user_id'  => $user_id,
    ]);

    // ---- Vider le panier (user_id + session_id) ----
    $stmt_clear = $pdo->prepare("
        DELETE FROM cart_items 
        WHERE user_id = :user_id 
           OR session_id = :session_id
    ");
    $stmt_clear->execute([
        ':user_id'    => $user_id,
        ':session_id' => $session_id,
    ]);

    $pdo->commit();

    setFlashMessage('success', "Votre commande #{$order_id} a été enregistrée avec succès !");
    redirect(BASE_URL . 'order_confirmation.php?order_id=' . $order_id);
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PDO error in process_checkout.php: " . $e->getMessage());
    setFlashMessage('danger', 'Erreur de base de données. Veuillez réessayer.');
    redirect(BASE_URL . 'checkout.php');
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error in process_checkout.php: " . $e->getMessage());
    setFlashMessage('danger', 'Erreur inattendue. Veuillez réessayer.');
    redirect(BASE_URL . 'checkout.php');
    exit();
}