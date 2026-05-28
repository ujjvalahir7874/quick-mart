<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['delivery_partner_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = $_SESSION['delivery_partner_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'withdraw') {
    $amount = (float)$_POST['amount'];
    $method = $_POST['method']; 
    $details = $_POST['details'];

    // Fetch current balance
    $partner = $pdo->query("SELECT wallet_balance, name FROM delivery_persons WHERE id = $id")->fetch();
    
    if ($amount > 0 && $partner['wallet_balance'] >= $amount) {
        // Start transaction
        $pdo->beginTransaction();
        try {
            // Deduct from balance
            $stmt = $pdo->prepare("UPDATE delivery_persons SET wallet_balance = wallet_balance - ? WHERE id = ?");
            $stmt->execute([$amount, $id]);

            // Add Debit Transaction
            $desc = "Withdrawal ($method: $details)";
            $stmt = $pdo->prepare("INSERT INTO delivery_earnings (delivery_person_id, amount, type, description) VALUES (?, ?, 'Debit', ?)");
            $stmt->execute([$id, $amount, $desc]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Withdrawal request processed successfully!', 'amount' => $amount]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance or invalid amount.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
