<?php
require_once 'config/db.php';

try {
    // Create scratch_cards table
    $pdo->exec("CREATE TABLE IF NOT EXISTS scratch_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) DEFAULT 0.00,
        order_id INT DEFAULT NULL,
        is_scratched TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Scratch cards table created successfully!";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>