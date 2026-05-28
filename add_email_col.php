<?php
require_once 'config/db.php';

try {
    // Add email column to delivery_persons table
    $sql = "ALTER TABLE delivery_persons ADD COLUMN email VARCHAR(100) UNIQUE AFTER name";
    $pdo->exec($sql);
    echo "Column 'email' added to 'delivery_persons' table successfully.";
} catch (PDOException $e) {
    echo "Error (or column likely already exists): " . $e->getMessage();
}
?>
