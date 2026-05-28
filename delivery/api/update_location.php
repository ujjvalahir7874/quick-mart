<?php
// session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;
    $id = $_SESSION['delivery_partner_id'];

    if ($lat && $lng) {
        $stmt = $pdo->prepare("UPDATE delivery_persons SET current_lat = ?, current_lng = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $id]);
        
        // Optional: Log location (can be expensive if too frequent)
        // $pdo->prepare("INSERT INTO delivery_location_logs (delivery_person_id, lat, lng) VALUES (?, ?, ?)")->execute([$id, $lat, $lng]);

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid coordinates']);
    }
}
?>
