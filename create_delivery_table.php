<?php
require_once 'config/db.php';

try {
    // Create delivery_persons table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_persons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        mobile_no VARCHAR(20) NOT NULL,
        bike_number VARCHAR(20) NOT NULL,
        status ENUM('Available', 'Busy', 'Offline') DEFAULT 'Available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'delivery_persons' created successfully!\n";

    // Add delivery_person_id to orders table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_person_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_person_id INT DEFAULT NULL");
        $pdo->exec("ALTER TABLE orders ADD FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE SET NULL");
        echo "Column 'delivery_person_id' added to 'orders' table!\n";
    } else {
        echo "Column 'delivery_person_id' already exists in 'orders' table.\n";
    }

    // Insert a sample delivery person if the table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM delivery_persons");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO delivery_persons (name, mobile_no, bike_number) VALUES ('Rajesh Kumar', '9876543210', 'GJ-05-AB-1234')");
        echo "Sample delivery person added!\n";
    }

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>