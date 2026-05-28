<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Fetch order details and verify ownership and status
    $stmt = $pdo->prepare("SELECT id, user_id, status, total_amount, payment_method, delivery_person_id FROM orders WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found or unauthorized.');
    }

    // Only allow cancellation for Pending or Accepted orders
    if (!in_array($order['status'], ['Pending', 'Accepted'])) {
        throw new Exception('This order can only be cancelled before processing starts. Current status: ' . $order['status'] . '.');
    }

    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
    $stmt->execute([$order_id]);

    // Free up delivery person if assigned
    if ($order['delivery_person_id']) {
        $stmt = $pdo->prepare("UPDATE delivery_persons SET status = 'Available' WHERE id = ?");
        $stmt->execute([$order['delivery_person_id']]);
    }

    // Handle Wallet Refund
    if (strpos($order['payment_method'], 'Digital Wallet') !== false) {
        // Get user's wallet
        $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();

        if ($wallet) {
            $refund_amount = $order['total_amount'];
            $new_balance = $wallet['balance'] + $refund_amount;
            
            // Update wallet balance
            $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet['id']]);
            
            // Record refund transaction
            $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) 
                                VALUES (?, 'Credit', ?, ?, ?, 'Completed')")
                ->execute([$wallet['id'], $refund_amount, "Refund for Cancelled Order #$order_id", $order_id]);
        }
    }

    // Handle Scratch Card / Cashback Reversal
    $stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $scratch_card = $stmt->fetch();

    if ($scratch_card) {
        if ($scratch_card['is_scratched'] == 0) {
            // Simply delete the unscratched card
            $pdo->prepare("DELETE FROM scratch_cards WHERE id = ?")->execute([$scratch_card['id']]);
        } else {
            // It was already claimed, need to reverse from wallet
            $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();

            if ($wallet) {
                $reversal_amount = $scratch_card['amount'];
                $new_balance = $wallet['balance'] - $reversal_amount;
                
                // Update wallet balance
                $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet['id']]);
                
                // Record reversal transaction
                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) 
                                    VALUES (?, 'Debit', ?, ?, ?, 'Completed')")
                    ->execute([$wallet['id'], $reversal_amount, "Cashback Reversal for Cancelled Order #$order_id", $order_id]);
                
                // Delete the scratch card record to prevent any future issues
                $pdo->prepare("DELETE FROM scratch_cards WHERE id = ?")->execute([$scratch_card['id']]);
            }
        }
    }

    // Log the action
    $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)")
        ->execute([$user_id, "Cancelled Order #$order_id"]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order #'.$order_id.' has been successfully cancelled.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
