<?php
require_once '../config/db.php';

$oid = 6; // From screenshot
$stmt = $pdo->prepare("SELECT id, delivery_person_id, delivery_otp, status FROM orders WHERE id = ?");
$stmt->execute([$oid]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Order Order Content:\n";
print_r($order);

if ($order) {
    echo "\nOTP Type: " . gettype($order['delivery_otp']) . "\n";
    echo "OTP Value: '" . $order['delivery_otp'] . "'\n";
}

// Check session (simulated)
// We can't check session directly since this runs as a separate CLI script or HTTP request, 
// but we can list delivery persons to see who might be logged in or assigned.
if ($order) {
    echo "\nAssigned Delivery Person ID: " . $order['delivery_person_id'] . "\n";
    $dp = $pdo->query("SELECT * FROM delivery_persons WHERE id = " . $order['delivery_person_id'])->fetch();
    echo "Delivery Person:\n";
    print_r($dp);
}
?>
