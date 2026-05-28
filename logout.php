<?php
require_once 'config/db.php';

// Clear Remember Me token from DB if it exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    // Clear from users
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);
    // Clear from delivery_persons
    $stmt = $pdo->prepare("UPDATE delivery_persons SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);
    
    setcookie('remember_token', '', time() - 3600, "/");
}

// If logging out from admin
if (isset($_GET['from']) && $_GET['from'] === 'admin') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_role']);
    header("Location: admin/login.php");
    exit;
}

// Default: logging out from user site
unset($_SESSION['cart']);
unset($_SESSION['applied_coupon']);
session_unset();
session_destroy();
session_start();
session_regenerate_id(true);
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_role']);

header("Location: login.php");
exit;
