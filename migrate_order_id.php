<?php
require_once 'config/db.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM scratch_cards LIKE 'order_id'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        $pdo->exec("ALTER TABLE scratch_cards ADD COLUMN order_id INT DEFAULT NULL AFTER amount");
        echo "Successfully added 'order_id' column to 'scratch_cards' table.\n";
    } else {
        echo "Column 'order_id' already exists in 'scratch_cards' table.\n";
    }

    // Also double check wallet_transactions just in case
    $stmt = $pdo->query("SHOW COLUMNS FROM wallet_transactions LIKE 'order_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE wallet_transactions ADD COLUMN order_id INT DEFAULT NULL AFTER description");
        echo "Successfully added 'order_id' column to 'wallet_transactions' table.\n";
    } else {
        echo "Column 'order_id' already exists in 'wallet_transactions' table.\n";
    }

} catch (PDOException $e) {
    echo "Error fixing schema: " . $e->getMessage() . "\n";
}
?>