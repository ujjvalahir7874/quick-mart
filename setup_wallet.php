<?php
require_once 'config/db.php';

try {
    // Create wallets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wallets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        balance DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Create wallet_transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        wallet_id INT NOT NULL,
        type ENUM('Credit', 'Debit') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        description VARCHAR(255) NOT NULL,
        order_id INT DEFAULT NULL,
        status ENUM('Completed', 'Pending', 'Failed') DEFAULT 'Completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
    )");

    // Initialize wallets for existing users if they don't have one
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll();
    
    foreach ($users as $user) {
        $check = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
        $check->execute([$user['id']]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user['id']]);
        }
    }

    echo "Wallet tables created and initialized successfully!";
} catch (PDOException $e) {
    die("Error creating wallet tables: " . $e->getMessage());
}
?>
