<?php
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php?msg=Please login to review");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    try {
        $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $rating, $comment]);
        
        header("Location: product-details.php?id=$product_id&msg=Review submitted successfully!");
    } catch (PDOException $e) {
        header("Location: product-details.php?id=$product_id&msg=Error: " . $e->getMessage());
    }
} else {
    header("Location: products.php");
}
?>