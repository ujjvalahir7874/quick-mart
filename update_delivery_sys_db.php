<?php
require_once 'config/db.php';

try {
    echo "Starting Delivery System Database Update...\n";

    // 1. Update delivery_persons table
    $columns = [
        "password_hash VARCHAR(255) NULL",
        "otp VARCHAR(10) NULL",
        "documents TEXT NULL",
        "current_lat DECIMAL(10, 8) NULL",
        "current_lng DECIMAL(11, 8) NULL",
        "wallet_balance DECIMAL(10, 2) DEFAULT 0.00",
        "is_verified TINYINT(1) DEFAULT 0",
        "fcm_token TEXT NULL"
    ];

    foreach ($columns as $col) {
        try {
            // parsing column name to check existence (simple check typically requires query, but here we try-catch ALTER)
            $colName = explode(' ', $col)[0];
            // Check if column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM delivery_persons LIKE '$colName'");
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN $col");
                echo "Added column: $colName\n";
            } else {
                echo "Column $colName already exists.\n";
            }
        } catch (PDOException $e) {
            echo "Error adding $col: " . $e->getMessage() . "\n";
        }
    }

    // 2. Create delivery_earnings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_earnings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_person_id INT NOT NULL,
        order_id INT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        type ENUM('Credit', 'Debit') NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
    )");
    echo "Table 'delivery_earnings' check/create done.\n";

    // 3. Create delivery_location_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_location_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_person_id INT NOT NULL,
        lat DECIMAL(10, 8) NOT NULL,
        lng DECIMAL(11, 8) NOT NULL,
        logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
    )");
    echo "Table 'delivery_location_logs' check/create done.\n";

    echo "Database update completed successfully!\n";

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
