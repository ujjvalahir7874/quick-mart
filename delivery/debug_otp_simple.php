<?php
require_once '../config/db.php';
$stmt = $pdo->prepare("SELECT delivery_otp FROM orders WHERE id = 6");
$stmt->execute();
$otp = $stmt->fetchColumn();
echo "OTP_VALUE: [" . $otp . "]";
?>
