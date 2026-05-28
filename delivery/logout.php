<?php
require_once '../config/db.php';

// Clear Remember Me token from DB if it exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    // Clear from delivery_persons
    $stmt = $pdo->prepare("UPDATE delivery_persons SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);
    // Also clear from users just in case
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $stmt->execute([$token]);
    
    setcookie('remember_token', '', time() - 3600, "/");
}

unset($_SESSION['delivery_partner_id']);
unset($_SESSION['delivery_partner_name']);
session_destroy();
session_start();
session_regenerate_id(true);
header("Location: login.php");
exit;
?>
