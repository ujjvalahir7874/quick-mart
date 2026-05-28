<?php
require_once 'config/db.php';

try {
    // Add current location columns to delivery_persons
    $stmt = $pdo->query("SHOW COLUMNS FROM delivery_persons LIKE 'current_lat'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN current_lat DECIMAL(10, 8) DEFAULT NULL");
        $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN current_lng DECIMAL(11, 8) DEFAULT NULL");
        $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "Current location columns added to 'delivery_persons' table!\n";
    }

    // Create delivery_tracking table for movement history
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_tracking (
        id INT AUTO_INCREMENT PRIMARY KEY,
        delivery_person_id INT NOT NULL,
        order_id INT NOT NULL,
        latitude DECIMAL(10, 8) NOT NULL,
        longitude DECIMAL(11, 8) NOT NULL,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    )");
    echo "Table 'delivery_tracking' created successfully!\n";

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>