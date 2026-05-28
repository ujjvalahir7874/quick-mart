<?php
require_once 'config/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_id = (int)($_POST['card_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($card_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Card ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check if card exists and belongs to user
        $stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE id = ? AND user_id = ? AND is_scratched = 0");
        $stmt->execute([$card_id, $user_id]);
        $card = $stmt->fetch();

        if ($card) {
            // Update card status
            $pdo->prepare("UPDATE scratch_cards SET is_scratched = 1 WHERE id = ?")->execute([$card_id]);

            // Get wallet
            $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                // Create wallet if not exists
                $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
                $wallet_id = $pdo->lastInsertId();
                $wallet = ['id' => $wallet_id, 'balance' => 0.00];
            }

            if ($wallet) {
                $new_balance = $wallet['balance'] + $card['amount'];
                $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet['id']]);

                // Record transaction
                $desc = "Cashback from Scratch Card";
                if ($card['order_id']) {
                    $desc .= " (Order #" . $card['order_id'] . ")";
                } else {
                    $desc .= " (Wallet Top-up Reward)";
                }

                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) VALUES (?, 'Credit', ?, ?, ?, 'Completed')")
                    ->execute([$wallet['id'], $card['amount'], $desc, $card['order_id']]);

                $pdo->commit();
                $_SESSION['success_msg'] = "₹" . number_format($card['amount'], 2) . " cashback successfully added to your wallet!";
                echo json_encode(['success' => true, 'amount' => $card['amount']]);
            } else {
                throw new Exception("Wallet not found");
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid card or already scratched']);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>