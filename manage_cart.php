<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = (int)($_POST['product_id'] ?? 0);
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Key for cart storage: productID_variantID (variantID is 0 if not applicable)
    $cart_key = $variant_id > 0 ? $product_id . '_' . $variant_id : (string)$product_id;
    $expiredMessage = getExpiredPurchaseMessage();

    if ($action === 'add') {
        if ($variant_id > 0) {
            $stmt = $pdo->prepare("SELECT pv.stock_quantity, pv.expiry_date, p.expiry_date AS product_expiry_date FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.id = ? AND pv.product_id = ? AND p.status = 'Active'");
            $stmt->execute([$variant_id, $product_id]);
        } else {
            $stmt = $pdo->prepare("SELECT stock_quantity, expiry_date FROM products WHERE id = ? AND status = 'Active'");
            $stmt->execute([$product_id]);
        }
        $item = $stmt->fetch();

        if ($item && (isExpiredDateValue($item['expiry_date'] ?? null) || isExpiredDateValue($item['product_expiry_date'] ?? null))) {
            echo json_encode([
                'success' => false,
                'status' => 'error',
                'message' => $expiredMessage,
                'error' => $expiredMessage
            ]);
            exit;
        }
        
        $current_in_cart = $_SESSION['cart'][$cart_key] ?? 0;
        if (!$item || $item['stock_quantity'] < ($current_in_cart + $quantity)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Out of stock or insufficient quantity'
            ]);
            exit;
        }

        if (isset($_SESSION['cart'][$cart_key])) {
            $_SESSION['cart'][$cart_key] += $quantity;
        } else {
            $_SESSION['cart'][$cart_key] = $quantity;
        }
        
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'cart_count' => array_sum($_SESSION['cart']),
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        exit;
    }

    if ($action === 'add_multiple') {
        $product_ids = $_POST['product_ids'] ?? [];
        if (is_array($product_ids)) {
            foreach ($product_ids as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) {
                    $stmt = $pdo->prepare("SELECT stock_quantity, expiry_date FROM products WHERE id = ? AND status = 'Active'");
                    $stmt->execute([$pid]);
                    $product = $stmt->fetch();
                    
                    $current_in_cart = $_SESSION['cart'][$pid] ?? 0;
                    if ($product && !isExpiredDateValue($product['expiry_date'] ?? null) && $product['stock_quantity'] > $current_in_cart) {
                        if (isset($_SESSION['cart'][$pid])) {
                            $_SESSION['cart'][$pid]++;
                        } else {
                            $_SESSION['cart'][$pid] = 1;
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'cart_count' => array_sum($_SESSION['cart']),
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        exit;
    }

    if ($action === 'remove') {
        $target_key = $_POST['cart_key'] ?? $cart_key;
        unset($_SESSION['cart'][$target_key]);
        echo json_encode([
            'success' => true,
            'status' => 'success',
            'cart_count' => array_sum($_SESSION['cart']),
            'total_items' => array_sum($_SESSION['cart'])
        ]);
        exit;
    }
    
    if ($action === 'update') {
        $target_key = $_POST['cart_key'] ?? $cart_key;
        $qty = (int)$_POST['quantity'];
        $incremental = isset($_POST['incremental']) && $_POST['incremental'] == 1;

        if ($qty > 0) {
            // Extract product and variant ID from target_key
            $parts = explode('_', $target_key);
            $pid = (int)$parts[0];
            $vid = isset($parts[1]) ? (int)$parts[1] : 0;

            if ($vid > 0) {
                $stmt = $pdo->prepare("SELECT pv.stock_quantity, pv.expiry_date, p.expiry_date AS product_expiry_date FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.id = ? AND p.status = 'Active'");
                $stmt->execute([$vid]);
            } else {
                $stmt = $pdo->prepare("SELECT stock_quantity, expiry_date FROM products WHERE id = ? AND status = 'Active'");
                $stmt->execute([$pid]);
            }
            $item = $stmt->fetch();

            if ($item && (isExpiredDateValue($item['expiry_date'] ?? null) || isExpiredDateValue($item['product_expiry_date'] ?? null))) {
                unset($_SESSION['cart'][$target_key]);
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => $expiredMessage,
                    'error' => $expiredMessage
                ]);
                exit;
            }

            if ($incremental) {
                $current_qty = $_SESSION['cart'][$target_key] ?? 0;
                $new_qty = $current_qty + $qty;
            } else {
                $new_qty = $qty;
            }

            if ($item && $item['stock_quantity'] >= $new_qty) {
                $_SESSION['cart'][$target_key] = $new_qty;
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'cart_count' => array_sum($_SESSION['cart']),
                    'total_items' => array_sum($_SESSION['cart'])
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Insufficient stock',
                    'error' => 'Insufficient stock'
                ]);
            }
        } else {
            unset($_SESSION['cart'][$target_key]);
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'cart_count' => array_sum($_SESSION['cart']),
                'total_items' => array_sum($_SESSION['cart'])
            ]);
        }
        exit;
    }
}
