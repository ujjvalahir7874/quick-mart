<?php
require_once '../config/db.php';

echo "CHECKING Tables and Columns...\n";

// Check delivery_persons columns
echo "--- delivery_persons columns ---\n";
$cols = $pdo->query("DESCRIBE delivery_persons")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols) . "\n";

// Check delivery_earnings table
$tables = $pdo->query("SHOW TABLES LIKE 'delivery_earnings'")->fetchAll();
if (count($tables) > 0) {
    echo "--- delivery_earnings columns ---\n";
    $cols = $pdo->query("DESCRIBE delivery_earnings")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $cols) . "\n";
} else {
    echo "Table delivery_earnings DOES NOT EXIST.\n";
}
?>
