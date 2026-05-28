<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$dp_id = $_SESSION['delivery_partner_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    die("Invalid Order ID");
}

// Verify this order belongs to the delivery person
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND delivery_person_id = ?");
$stmt->execute([$order_id, $dp_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Unauthorized access or Order not found.");
}

// Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'delivery', ?)");
        $stmt->execute([$order_id, $msg]);
    }
    // Redirect to avoid resubmission
    header("Location: chat.php?order_id=" . $order_id);
    exit;
}

// Fetch Messages
$stmt = $pdo->prepare("SELECT * FROM order_messages WHERE order_id = ? ORDER BY created_at ASC");
$stmt->execute([$order_id]);
$messages = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Customer - Order #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f5f7; display: flex; flex-direction: column; height: 100vh; }
        .chat-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; }
        .message { max-width: 75%; padding: 10px 15px; border-radius: 15px; font-size: 0.9rem; position: relative; word-wrap: break-word; }
        .message.delivery { align-self: flex-end; background-color: #10b981; color: white; border-radius: 15px 15px 0 15px; }
        .message.customer { align-self: flex-start; background-color: white; color: #374151; border-radius: 15px 15px 15px 0; border: 1px solid #e5e7eb; }
        .message-time { font-size: 0.65rem; opacity: 0.8; margin-top: 4px; text-align: right; }
        .chat-footer { background: white; padding: 15px; border-top: 1px solid #e5e7eb; }
        .btn-back { position: fixed; top: 15px; left: 15px; z-index: 1000; background: white; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; color: #374151; }
    </style>
</head>
<body>

    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i></a>

    <div class="bg-white text-center py-3 shadow-sm sticky-top">
        <h6 class="mb-0 fw-bold">Chat with Customer</h6>
        <small class="text-muted">Order #<?= $order_id ?></small>
    </div>

    <div class="chat-container" id="chatContainer">
        <?php if (empty($messages)): ?>
            <div class="text-center text-muted col-12 my-auto">
                <i class="bi bi-chat-dots fs-1 d-block mb-2 text-primary opacity-25"></i>
                <small>Start the conversation...</small>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['sender_type'] === 'delivery' ? 'delivery' : 'customer' ?>">
                    <?= htmlspecialchars($msg['message']) ?>
                    <div class="message-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chat-footer">
        <form method="POST" class="d-flex gap-2">
            <input type="text" name="message" class="form-control rounded-pill border-0 bg-light" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" class="btn btn-primary rounded-circle" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-send-fill text-white"></i>
            </button>
        </form>
    </div>

    <script>
        // Scroll to bottom
        const chatContainer = document.getElementById('chatContainer');
        chatContainer.scrollTop = chatContainer.scrollHeight;

        // Simple auto-refresh for demo purposes (could use AJAX for smoother exp)
        setInterval(() => {
            location.reload();
        }, 10000); 
    </script>
</body>
</html>
