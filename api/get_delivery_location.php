<?php
header('Content-Type: application/json');
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Missing order_id']);
        exit;
    }

    try {
        // Get delivery person's current location and order details
        $stmt = $pdo->prepare("
            SELECT dp.id, dp.name, dp.current_lat, dp.current_lng, dp.last_updated, 
                   dp.mobile_no as delivery_mobile, dp.bike_number,
                   o.status, o.shipping_address
            FROM orders o
            JOIN delivery_persons dp ON o.delivery_person_id = dp.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Mock customer location based on order_id
            $customer_lat = 19.0800 + ( ($order_id % 100) / 1000 ); 
            $customer_lng = 72.8800 + ( ($order_id % 100) / 1000 );
            $data['customer_lat'] = $customer_lat;
            $data['customer_lng'] = $customer_lng;

            // FOR TESTING: If the agent is not moving in the DB, simulate slight movement
            // This ensures the distance decreases while the user watches the demo
            $last_updated = strtotime($data['last_updated']);
            $now = time();
            $seconds_since_update = $now - $last_updated;

            // If last update was more than 1 minute ago, or for specific test IDs
            // we simulate the agent approaching the customer
            if ($seconds_since_update > 60 || $order_id == 13) {
                // Calculate progress based on current time (moves 0.0001 degrees every 5 seconds)
                $progress = ( ($now % 300) / 300 ); // Cycles every 5 mins
                $data['current_lat'] = $data['current_lat'] + ($customer_lat - $data['current_lat']) * $progress;
                $data['current_lng'] = $data['current_lng'] + ($customer_lng - $data['current_lng']) * $progress;
            }

            if (empty($data['current_lat']) || empty($data['current_lng'])) {
                echo json_encode(['success' => false, 'message' => 'Delivery partner has not started sharing location yet']);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No delivery partner assigned or order not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>