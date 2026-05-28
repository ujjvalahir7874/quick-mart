<?php
header('Content-Type: application/json');
require_once '../config/db.php';

// In a real app, we would verify delivery person's session/token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_person_id = isset($_POST['delivery_person_id']) ? (int)$_POST['delivery_person_id'] : null;
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

    if (!$delivery_person_id || !$lat || !$lng) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update current location in delivery_persons table
        $stmt = $pdo->prepare("UPDATE delivery_persons SET current_lat = ?, current_lng = ?, last_updated = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$lat, $lng, $delivery_person_id]);

        // If an order_id is provided, record in history
        if ($order_id) {
            $stmt = $pdo->prepare("INSERT INTO delivery_tracking (delivery_person_id, order_id, latitude, longitude) VALUES (?, ?, ?, ?)");
            $stmt->execute([$delivery_person_id, $order_id, $lat, $lng]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>