<?php
require_once 'config/db.php';

try {
    // Check if status column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE products ADD COLUMN status ENUM('Active', 'Archived') DEFAULT 'Active' AFTER availability_status");
        echo "Successfully added 'status' column to 'products' table.<br>";
    } else {
        echo "Column 'status' already exists in 'products' table.<br>";
    }
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "<br>";
}
?>