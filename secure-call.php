<?php
require_once 'config/db.php';

$actor_type = null;
$actor_id = null;

if (isset($_SESSION['delivery_partner_id'])) {
    $actor_type = 'delivery';
    $actor_id = (int)$_SESSION['delivery_partner_id'];
} elseif (isLoggedIn()) {
    $actor_type = 'customer';
    $actor_id = (int)$_SESSION['user_id'];
}

if (!$actor_type || !$actor_id) {
    http_response_code(403);
    exit('Unauthorized');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$error = '';
$call_request = null;

if ($order_id <= 0) {
    $error = 'Invalid order selected for secure calling.';
}

$relay_enabled = (int)(get_setting('masked_call_enabled', '1') ?? 1);
$relay_label = trim((string)(get_setting('masked_call_relay_label', 'Quick mart Secure Call Desk') ?? 'Quick mart Secure Call Desk'));
$relay_number = trim((string)(get_setting('masked_call_relay_number', '1800123456') ?? '1800123456'));
$relay_number_dial = preg_replace('/[^0-9+]/', '', $relay_number);

if (!$error) {
    if ($actor_type === 'delivery') {
        $stmt = $pdo->prepare("SELECT o.id, o.status, o.delivery_person_id, u.full_name AS customer_name
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.id = ? AND o.delivery_person_id = ?");
        $stmt->execute([$order_id, $actor_id]);
        $order = $stmt->fetch();
        $target_type = 'customer';
        $target_label = 'customer';
    } else {
        $stmt = $pdo->prepare("SELECT o.id, o.status, o.delivery_person_id, dp.name AS delivery_name
            FROM orders o
            LEFT JOIN delivery_persons dp ON dp.id = o.delivery_person_id
            WHERE o.id = ? AND o.user_id = ?");
        $stmt->execute([$order_id, $actor_id]);
        $order = $stmt->fetch();
        $target_type = 'delivery';
        $target_label = 'delivery partner';
    }

    if (!$order) {
        $error = 'Secure calling is not available for this order.';
    } elseif ($actor_type === 'customer' && empty($order['delivery_person_id'])) {
        $error = 'A delivery partner has not been assigned yet. Please try again after assignment.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM call_bridge_requests
            WHERE order_id = ? AND requester_type = ? AND requester_id = ? AND target_type = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute([$order_id, $actor_type, $actor_id, $target_type]);
        $call_request = $stmt->fetch();

        if (!$call_request) {
            $reference_code = 'QC-' . $order_id . '-' . strtoupper(substr(md5($actor_type . '-' . $actor_id . '-' . microtime(true)), 0, 6));
            $notes = 'Secure call requested while order status was ' . ($order['status'] ?? 'Unknown');
            $stmt = $pdo->prepare("INSERT INTO call_bridge_requests
                (order_id, requester_type, requester_id, target_type, relay_number, reference_code, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'Requested', ?)");
            $stmt->execute([$order_id, $actor_type, $actor_id, $target_type, $relay_number, $reference_code, $notes]);

            $stmt = $pdo->prepare("SELECT * FROM call_bridge_requests WHERE id = ?");
            $stmt->execute([$pdo->lastInsertId()]);
            $call_request = $stmt->fetch();

            if (function_exists('logActivity') && $actor_type === 'customer') {
                logActivity($pdo, 'Secure call requested for Order #' . $order_id);
            }
        }
    }
}

$page_title = 'Secure Relay Call';
$counterparty_name = '';
if (!empty($order)) {
    $counterparty_name = $actor_type === 'delivery'
        ? ($order['customer_name'] ?? 'Customer')
        : ($order['delivery_name'] ?? 'Delivery Partner');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(180deg, #f8fafc 0%, #eef6f2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .secure-card {
            width: 100%;
            max-width: 520px;
            border: 0;
            border-radius: 28px;
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .secure-hero {
            background: linear-gradient(135deg, #198754 0%, #0f9b6c 100%);
            color: #fff;
            padding: 28px;
        }
        .hero-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.16);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 16px;
        }
        .secure-body {
            padding: 28px;
        }
        .info-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 14px 16px;
        }
    </style>
</head>
<body>
    <div class="card secure-card">
        <div class="secure-hero">
            <div class="hero-icon"><i class="bi bi-telephone-forward-fill"></i></div>
            <h2 class="fw-bold mb-2">Secure Relay Call</h2>
            <p class="mb-0 opacity-75">Private numbers stay hidden while you contact the <?php echo htmlspecialchars($target_label ?? 'other side'); ?>.</p>
        </div>
        <div class="secure-body">
            <?php if ($error): ?>
                <div class="alert alert-danger rounded-4 border-0"><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="info-pill mb-3">
                    <div class="small text-uppercase text-muted fw-bold mb-1">Order Reference</div>
                    <div class="fw-bold">#<?php echo (int)$order_id; ?></div>
                </div>
                <div class="info-pill mb-3">
                    <div class="small text-uppercase text-muted fw-bold mb-1">Secure Call Code</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($call_request['reference_code'] ?? 'Pending'); ?></div>
                </div>
                <div class="info-pill mb-4">
                    <div class="small text-uppercase text-muted fw-bold mb-1">Connecting Through</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($relay_label); ?></div>
                    <div class="text-muted small mt-1">The real phone number of the <?php echo htmlspecialchars($target_label); ?> is not shown to you.</div>
                </div>

                <?php if ($relay_enabled && $relay_number_dial !== ''): ?>
                    <?php if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false && $relay_number === '1800123456'): ?>
                        <div class="alert alert-info rounded-4 border-0 small mb-3">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Developer Tip:</strong> You are using the default relay number. You can change this in 
                            <a href="admin/settings.php" class="alert-link">Admin Settings</a> to a real number for testing.
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2 mb-3">
                        <a href="tel:<?php echo htmlspecialchars($relay_number_dial); ?>" class="btn btn-success flex-grow-1 rounded-pill py-3 fw-bold shadow-sm">
                            <i class="bi bi-telephone-fill me-2"></i>Call Secure Relay
                        </a>
                        <button onclick="copyNumber('<?php echo htmlspecialchars($relay_number_dial); ?>')" class="btn btn-outline-success rounded-pill px-4 shadow-sm" title="Copy Number">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    
                    <div class="text-center text-muted small bg-light p-3 rounded-4 mb-3">
                        <div class="mb-2">Relay Number: <strong class="text-dark"><?php echo htmlspecialchars($relay_number); ?></strong></div>
                        If the call does not start automatically, dial the number above and share code
                        <strong><?php echo htmlspecialchars($call_request['reference_code'] ?? ''); ?></strong> with the relay desk.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning rounded-4 border-0 mb-0">
                        Secure relay is not configured yet. Please contact support to complete masked calling setup.
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top text-center">
                    <div class="fw-semibold"><?php echo htmlspecialchars($counterparty_name); ?></div>
                    <div class="text-muted small">Private contact protected by Quick mart secure relay</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="copyToast" class="toast-container position-fixed bottom-0 start-50 translate-middle-x p-3">
        <div class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">Number copied to clipboard!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyNumber(num) {
            navigator.clipboard.writeText(num).then(() => {
                const toastEl = document.querySelector('.toast');
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            });
        }

        // Only auto-redirect on mobile devices to avoid annoying popups on desktop
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        <?php if (!$error && $relay_enabled && $relay_number_dial !== ''): ?>
        if (isMobile) {
            window.setTimeout(function () {
                window.location.href = <?php echo json_encode('tel:' . $relay_number_dial); ?>;
            }, 1200);
        }
        <?php endif; ?>
    </script>
</body>
</html>
