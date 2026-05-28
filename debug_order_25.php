<?php
require_once 'config/db.php';

echo "--- Diagnostic for Order #25 ---\n";

try {
    // 1. Check Order
    $stmt = $pdo->prepare("SELECT id, user_id, status, total_amount FROM orders WHERE id = 25");
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        echo "Order #25 found: Status={$order['status']}, UserID={$order['user_id']}, Total={$order['total_amount']}\n";
    } else {
        echo "Order #25 NOT found in database.\n";
    }

    // 2. Check Scratch Card
    $stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE order_id = 25");
    $stmt->execute();
    $card = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($card) {
        echo "Scratch Card found: ID={$card['id']}, Amount={$card['amount']}, IsScratched={$card['is_scratched']}, CreatedAt={$card['created_at']}\n";
    } else {
        echo "Scratch Card for Order #25 NOT found.\n";
    }

    // 3. Check Wallet Transactions
    if ($order) {
        $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$order['user_id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($wallet) {
            echo "User Wallet found: ID={$wallet['id']}, Balance={$wallet['balance']}\n";
            
            $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? AND order_id = 25");
            $stmt->execute([$wallet['id']]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($transactions) {
                echo "Transactions for Order #25:\n";
                foreach ($transactions as $t) {
                    echo "- Type={$t['type']}, Amount={$t['amount']}, Desc={$t['description']}, Status={$t['status']}\n";
                }
            } else {
                echo "No wallet transactions found for Order #25.\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>