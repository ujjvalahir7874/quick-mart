<?php
session_start();
require_once 'config/db.php';
require_once 'includes/cart_pricing.php';

if (isset($_GET['remove'])) {
    unset($_SESSION['applied_coupon']);
    header("Location: cart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_code'])) {
    $code = strtoupper(trim($_POST['coupon_code']));

    if (empty($code)) {
        header("Location: cart.php?coupon_error=Please enter a coupon code.");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'Enabled' AND expiry_date >= CURRENT_DATE");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        header("Location: cart.php?coupon_error=Invalid or expired coupon code.");
        exit;
    }

    if ($coupon['used_count'] >= $coupon['usage_limit']) {
        header("Location: cart.php?coupon_error=This coupon has already reached its maximum usage limit.");
        exit;
    }

    $pricing = calculateCartPricing($pdo, $_SESSION['cart'] ?? []);
    $grand_total = $pricing['grand_total_before_coupon'];

    if ($grand_total < $coupon['min_purchase']) {
        header("Location: cart.php?coupon_error=Minimum purchase of Rs." . number_format($coupon['min_purchase'], 2) . " required for this coupon.");
        exit;
    }

    $_SESSION['applied_coupon'] = [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $coupon['discount_value'],
        'min_purchase' => $coupon['min_purchase']
    ];

    header("Location: cart.php?coupon_success=1");
    exit;
}

header("Location: cart.php");
exit;
