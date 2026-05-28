<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_method = trim($_POST['payment_method'] ?? '');
    $allowed_methods = ['UPI', 'Debit Card', 'Credit Card', 'Net Banking'];
    $method_descriptor = '';

    if ($amount <= 0) {
        $_SESSION['error_msg'] = "Invalid amount.";
        header("Location: wallet.php");
        exit;
    }

    if (!in_array($payment_method, $allowed_methods, true)) {
        $_SESSION['error_msg'] = "Please choose a valid payment method.";
        header("Location: wallet.php");
        exit;
    }

    if ($payment_method === 'UPI') {
        $upi_id = trim($_POST['upi_id'] ?? '');

        if ($upi_id === '' || !preg_match('/^[A-Za-z0-9._-]{2,}@[A-Za-z]{2,}$/', $upi_id)) {
            $_SESSION['error_msg'] = "Enter a valid UPI ID to continue.";
            header("Location: wallet.php");
            exit;
        }

        [$upi_prefix, $upi_suffix] = array_pad(explode('@', $upi_id, 2), 2, '');
        $visible_prefix = substr($upi_prefix, 0, min(2, strlen($upi_prefix)));
        $masked_prefix = $visible_prefix . str_repeat('*', max(strlen($upi_prefix) - strlen($visible_prefix), 1));
        $method_descriptor = " ({$masked_prefix}@{$upi_suffix})";
    } elseif (stripos($payment_method, 'Card') !== false) {
        $card_name = trim($_POST['card_name'] ?? '');
        $card_number = preg_replace('/\D+/', '', $_POST['card_number'] ?? '');
        $card_expiry = trim($_POST['card_expiry'] ?? '');
        $card_cvv = preg_replace('/\D+/', '', $_POST['card_cvv'] ?? '');

        if ($card_name === '' || strlen($card_number) < 12 || !preg_match('/^\d{2}\/\d{2}$/', $card_expiry) || !preg_match('/^\d{3,4}$/', $card_cvv)) {
            $_SESSION['error_msg'] = "Enter complete card details to continue.";
            header("Location: wallet.php");
            exit;
        }

        $method_descriptor = " ending " . substr($card_number, -4);
    } elseif ($payment_method === 'Net Banking') {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $other_bank = trim($_POST['other_bank'] ?? '');
        $selected_bank = $bank_name !== '' ? $bank_name : $other_bank;

        if ($selected_bank === '') {
            $_SESSION['error_msg'] = "Please select your bank to continue.";
            header("Location: wallet.php");
            exit;
        }

        $method_descriptor = " ({$selected_bank})";
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch();

        if (!$wallet) {
            $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
            $wallet_id = $pdo->lastInsertId();
            $current_balance = 0.00;
        } else {
            $wallet_id = $wallet['id'];
            $current_balance = $wallet['balance'];
        }

        $new_balance = $current_balance + $amount;
        $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet_id]);

        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, status) VALUES (?, 'Credit', ?, ?, 'Completed')")
            ->execute([$wallet_id, $amount, "Added money via {$payment_method}{$method_descriptor}"]);

        $pdo->commit();

        if ($amount >= 100) {
            $cashback_potential = rand(1, 20);
            $pdo->prepare("INSERT INTO scratch_cards (user_id, amount) VALUES (?, ?)")
                ->execute([$user_id, $cashback_potential]);
            $_SESSION['success_msg'] = "₹" . number_format($amount, 2) . " added via {$payment_method}! You've earned a Scratch Card! Check your rewards.";
        } else {
            $_SESSION['success_msg'] = "₹" . number_format($amount, 2) . " added to your wallet via {$payment_method} successfully!";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_msg'] = "Failed to add funds: " . $e->getMessage();
    }

    header("Location: wallet.php");
    exit;
} else {
    header("Location: wallet.php");
    exit;
}

// If reached here without redirecting, something is wrong
header("Location: wallet.php");
exit;

