<?php
require_once 'config/db.php';

try {
    // Add otp column to orders table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'delivery_otp'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_otp VARCHAR(6) DEFAULT NULL");
        echo "Column 'delivery_otp' added to 'orders' table!\n";
    } else {
        echo "Column 'delivery_otp' already exists in 'orders' table.\n";
    }

    // Generate OTP for existing orders that are not delivered/cancelled
    $stmt = $pdo->query("SELECT id FROM orders WHERE delivery_otp IS NULL AND status NOT IN ('Delivered', 'Cancelled')");
    $orders = $stmt->fetchAll();
    
    foreach ($orders as $order) {
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $pdo->prepare("UPDATE orders SET delivery_otp = ? WHERE id = ?")->execute([$otp, $order['id']]);
    }
    
    if (count($orders) > 0) {
        echo count($orders) . " orders updated with random OTPs.\n";
    }

} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
