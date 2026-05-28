<?php
require_once 'config/db.php';

try {
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME DEFAULT NULL");
        echo "Reset token columns added successfully!\n";
    } else {
        echo "Reset token columns already exist.\n";
    }
} catch (PDOException $e) {
    echo "Error updating users table: " . $e->getMessage() . "\n";
}
?>