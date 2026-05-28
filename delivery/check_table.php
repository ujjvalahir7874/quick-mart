<?php
require_once '../config/db.php';
$tables = $pdo->query("SHOW TABLES LIKE 'delivery_earnings'")->fetchAll();
if (count($tables) > 0) {
    echo "Table delivery_earnings exists.";
    // Check columns just in case
    $columns = $pdo->query("DESCRIBE delivery_earnings")->fetchAll();
    print_r($columns);
} else {
    echo "Table delivery_earnings DOES NOT EXIST.";
}
?>
