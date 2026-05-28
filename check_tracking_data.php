<?php
require_once 'config/db.php';
try {
    $stmt = $pdo->query("SELECT o.id, dp.name as agent_name, dp.current_lat, dp.current_lng, o.status 
                         FROM orders o 
                         JOIN delivery_persons dp ON o.delivery_person_id = dp.id 
                         WHERE o.status IN ('Shipped', 'Out for Delivery')");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($results) . " active tracking orders:\n";
    print_r($results);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
