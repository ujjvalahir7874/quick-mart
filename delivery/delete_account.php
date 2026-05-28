<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['delivery_partner_id'];

// Perform account deletion (In a real app, you might want to soft-delete or check for pending orders)
// For now, we will delete the record from delivery_persons
$stmt = $pdo->prepare("DELETE FROM delivery_persons WHERE id = ?");
if ($stmt->execute([$id])) {
    // Clear session and redirect to login
    session_destroy();
    header("Location: login.php?msg=account_deleted");
} else {
    header("Location: profile.php?error=delete_failed");
}
exit;
