<?php
require_once 'config/db.php';

echo "--- Manually Claiming Reward for Order #25 ---\n";

try {
    $pdo->beginTransaction();

    // 1. Get the unscratched card
    $stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE order_id = 25 AND is_scratched = 0");
    $stmt->execute();
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        throw new Exception("No unscratched card found for Order #25.");
    }

    $card_id = $card['id'];
    $user_id = $card['user_id'];
    $amount = $card['amount'];

    // 2. Update card status
    $pdo->prepare("UPDATE scratch_cards SET is_scratched = 1 WHERE id = ?")->execute([$card_id]);
    echo "Marked scratch card #$card_id as scratched.\n";

    // 3. Get wallet
    $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet) {
        throw new Exception("Wallet not found for User #$user_id.");
    }

    // 4. Update wallet balance
    $new_balance = $wallet['balance'] + $amount;
    $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet['id']]);
    echo "Updated wallet #{$wallet['id']} balance: {$wallet['balance']} -> $new_balance.\n";

    // 5. Record transaction
    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) VALUES (?, 'Credit', ?, ?, ?, 'Completed')")
        ->execute([$wallet['id'], $amount, "Cashback from Scratch Card (Order #25)", 25]);
    echo "Recorded wallet transaction for ₹$amount.\n";

    $pdo->commit();
    echo "SUCCESS: Reward claimed successfully for Order #25.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>