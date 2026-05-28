<?php
require_once 'config/db.php';
$delivery_person_id = 1; // Rajesh Kumar
$lat = 19.0760; // Mumbai lat
$lng = 72.8777; // Mumbai lng
$order_id = 13;

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE delivery_persons SET current_lat = ?, current_lng = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$lat, $lng, $delivery_person_id]);
    
    $stmt = $pdo->prepare("INSERT INTO delivery_tracking (delivery_person_id, order_id, latitude, longitude) VALUES (?, ?, ?, ?)");
    $stmt->execute([$delivery_person_id, $order_id, $lat, $lng]);
    
    $pdo->commit();
    echo "Successfully updated location for Agent ID 1 and Order ID 13\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}
