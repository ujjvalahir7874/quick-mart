<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    die("Invalid Order ID");
}

// Verify this order belongs to the user
$stmt = $pdo->prepare("SELECT o.*, dp.name as delivery_name FROM orders o LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found.");
}

// Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'customer', ?)");
        $stmt->execute([$order_id, $msg]);
    }
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
    <title>Chat with Delivery Agent - Order #<?= $order_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Outfit', sans-serif; height: 100vh; display: flex; flex-direction: column; }
        .chat-container { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 15px; background: #f8fafc; }
        .message { max-width: 80%; padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; position: relative; word-wrap: break-word; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .message.customer { align-self: flex-end; background-color: #10b981; color: white; border-radius: 18px 18px 0 18px; }
        .message.delivery { align-self: flex-start; background-color: white; color: #1f2937; border-radius: 18px 18px 18px 0; border: 1px solid #e2e8f0; }
        .message-time { font-size: 0.65rem; opacity: 0.8; margin-top: 6px; text-align: right; }
        .chat-header { background: white; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
        .chat-footer { background: white; padding: 20px; border-top: 1px solid #e2e8f0; position: sticky; bottom: 0; z-index: 10; }
        .btn-close-chat { background: #f3f4f6; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #1f2937; text-decoration: none; transition: background 0.2s; }
        .btn-close-chat:hover { background: #e5e7eb; }
        .delivery-avatar { width: 45px; height: 45px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    </style>
</head>
<body>

    <div class="chat-header">
        <div class="d-flex align-items-center gap-3">
            <div class="delivery-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($order['delivery_name'] ?: 'Delivery Agent') ?></h6>
                <small class="text-success fw-bold" style="font-size: 0.7rem;">● Online</small>
            </div>
        </div>
        <a href="my-orders.php" class="btn-close-chat"><i class="bi bi-x-lg"></i></a>
    </div>

    <div class="chat-container" id="chatContainer">
        <?php if (empty($messages)): ?>
            <div class="text-center text-muted col-12 my-auto">
                <div class="bg-white p-4 rounded-circle d-inline-block shadow-sm mb-3">
                    <i class="bi bi-chat-text-fill fs-1 text-success opacity-50"></i>
                </div>
                <h6 class="fw-bold mb-1">Say Hello! 👋</h6>
                <small>Ask your delivery partner about your order status.</small>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?= $msg['sender_type'] === 'customer' ? 'customer' : 'delivery' ?>">
                    <div class="fw-bold mb-1" style="font-size: 0.7rem; opacity: 0.9;"><?= $msg['sender_type'] === 'customer' ? 'You' : $order['delivery_name'] ?></div>
                    <?= htmlspecialchars($msg['message']) ?>
                    <div class="message-time"><?= date('h:i A', strtotime($msg['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chat-footer">
        <form method="POST" class="d-flex gap-3">
            <input type="text" name="message" class="form-control form-control-lg rounded-pill border bg-light px-4 fs-6" placeholder="Type your message..." required autocomplete="off" style="border-color: #e2e8f0;">
            <button type="submit" class="btn btn-success rounded-circle shadow-lg hover-scale" style="width: 50px; height: 50px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-send-fill text-white fs-5"></i>
            </button>
        </form>
    </div>

    <script>
        // Scroll to bottom
        const chatContainer = document.getElementById('chatContainer');
        chatContainer.scrollTop = chatContainer.scrollHeight;

        // Auto-refresh
        setInterval(() => {
            // In a real app, use AJAX to fetch only new messages. 
            // For this quick implementation, reload is simplest but jarring.
            // Let's rely on user refresh for now to avoid UX issues or add a meta refresh if needed.
             location.reload(); 
        }, 15000); 
    </script>
</body>
</html>
