<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['delivery_partner_id'];
$partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $id")->fetch();

function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.quantity, p.name, pv.size_name 
                           FROM order_items oi 
                           LEFT JOIN products p ON oi.product_id = p.id 
                           LEFT JOIN product_variants pv ON oi.variant_id = pv.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Actions
if (isset($_GET['action']) && !$partner['is_suspended']) {
    if ($_GET['action'] == 'toggle_status') {
        // Enforce: Pending Verification partners must stay Offline
        if (!$partner['is_verified']) {
            header("Location: index.php?error=not_verified");
            exit;
        }
        $new_status = ($partner['status'] == 'Offline') ? 'Available' : 'Offline';
        $pdo->prepare("UPDATE delivery_persons SET status = ? WHERE id = ?")->execute([$new_status, $id]);
        header("Location: index.php");
        exit;
    }
    
    // Accept Broadcast Order
    if ($_GET['action'] == 'accept' && isset($_GET['oid'])) {
        $oid = (int)$_GET['oid'];
        
        // Atomically assign to prevent double assignment
        $stmt = $pdo->prepare("UPDATE orders SET delivery_person_id = ?, status = 'Shipped' WHERE id = ? AND delivery_person_id IS NULL");
        $stmt->execute([$id, $oid]);
        
        if ($stmt->rowCount() > 0) {
            // Success - generate OTP
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $pdo->prepare("UPDATE orders SET delivery_otp = ? WHERE id = ?")->execute([$otp, $oid]);
            $pdo->prepare("UPDATE delivery_persons SET status = 'Busy' WHERE id = ?")->execute([$id]);
            header("Location: index.php?msg=accepted&oid=$oid");
        } else {
            // Already taken
            header("Location: index.php?error=taken");
        }
        exit;
    }

    // Accept Order (Move from 'Shipped' to 'Out for Delivery')
    if ($_GET['action'] == 'pickup' && isset($_GET['oid'])) {
        $oid = (int)$_GET['oid'];
        
        // Check if OTP exists, if not generate it
        $stmt = $pdo->prepare("SELECT delivery_otp FROM orders WHERE id = ?");
        $stmt->execute([$oid]);
        $order_data = $stmt->fetch();
        
        if (!$order_data['delivery_otp']) {
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $pdo->prepare("UPDATE orders SET delivery_otp = ? WHERE id = ?")->execute([$otp, $oid]);
        }
        
        // Add Pickup Notification
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Out for Delivery', pickup_notification_sent = 1 WHERE id = ? AND delivery_person_id = ?");
        $stmt->execute([$oid, $id]);
        
        // Fetch customer phone to send reminder
        $stmt = $pdo->prepare("SELECT contact_number, delivery_otp FROM orders WHERE id = ?");
        $stmt->execute([$oid]);
        $order_sms = $stmt->fetch();
        if ($order_sms) {
            $sms_msg = "Your Order #$oid is out for delivery! Please share OTP " . $order_sms['delivery_otp'] . " with the delivery partner. - Quick mart";
            sendSMS($order_sms['contact_number'], $sms_msg);
        }
        
        $pdo->prepare("UPDATE delivery_persons SET status = 'Busy' WHERE id = ?")->execute([$id]);
        header("Location: index.php");
        exit;
    }
}

// Deliver Order (POST Action)
if (isset($_POST['action']) && $_POST['action'] == 'deliver' && !$partner['is_suspended']) {
    $oid = (int)$_POST['oid'];
    $otp = trim($_POST['otp']);

    // Verify OTP
    $stmt = $pdo->prepare("SELECT delivery_otp FROM orders WHERE id = ? AND delivery_person_id = ?");
    $stmt->execute([$oid, $id]);
    $order_verify = $stmt->fetch();

    if ($order_verify && $order_verify['delivery_otp'] == $otp) {
        $pdo->prepare("UPDATE orders SET status = 'Delivered', delivery_date = CURRENT_TIMESTAMP WHERE id = ?")->execute([$oid]);
        $pdo->prepare("UPDATE delivery_persons SET status = 'Available' WHERE id = ?")->execute([$id]);
        
        // Add Earnings (Simplified +50 for now)
        $pdo->prepare("INSERT INTO delivery_earnings (delivery_person_id, order_id, amount, type, description) VALUES (?, ?, 50.00, 'Credit', 'Order Delivery')")->execute([$id, $oid]);
        $pdo->prepare("UPDATE delivery_persons SET wallet_balance = wallet_balance + 50 WHERE id = ?")->execute([$id]);
        
        header("Location: index.php?msg=delivered");
        exit;
    } else {
        $error = "Invalid OTP. Please ask customer for correct code.";
    }
}

// Fetch stats
$count_assigned = $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_person_id = $id AND status = 'Shipped'")->fetchColumn();
$count_active = $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_person_id = $id AND status = 'Out for Delivery'")->fetchColumn();
$count_completed = $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_person_id = $id AND status = 'Delivered' AND DATE(delivery_date) = CURDATE()")->fetchColumn();
$today_earnings = $pdo->query("SELECT SUM(amount) FROM delivery_earnings WHERE delivery_person_id = $id AND type = 'Credit' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;

// Fetch Broadcast Orders (unassigned orders that are ready to be taken by available partners)
$broadcast_orders = $pdo->query("SELECT o.*, u.full_name, u.address 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.delivery_person_id IS NULL 
      AND o.status IN ('Pending', 'Accepted', 'Processing', 'Shipped', 'Out for Delivery')
    ORDER BY o.order_date ASC")->fetchAll();

// Fetch Assigned Orders (Shipped) and Active Orders (Out for Delivery)
$assigned_orders = $pdo->query("SELECT o.*, u.full_name, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.delivery_person_id = $id AND o.status = 'Shipped'")->fetchAll();
$active_orders = $pdo->query("SELECT o.*, u.full_name, u.address FROM orders o JOIN users u ON o.user_id = u.id WHERE o.delivery_person_id = $id AND o.status = 'Out for Delivery'")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Delivery Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html { scroll-behavior: smooth; }
        body { background-color: #f4f5f7; font-family: 'Outfit', sans-serif; padding-bottom: 90px; color: #1f2937; }
        .header-section { background: white; padding: 1.5rem 1.5rem 1rem; border-radius: 0 0 30px 30px; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05); }
        .avatar-circle { width: 55px; height: 55px; object-fit: cover; border-radius: 50%; border: 3px solid #ecfdf5; }
        
        .stat-row { background: white; border-radius: 16px; padding: 12px 18px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(0,0,0,0.02); transition: transform 0.2s; cursor: pointer; text-decoration: none; color: inherit; }
        .stat-row:active { transform: scale(0.98); }
        .stat-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-right: 15px; }
        .stat-count { background: #f3f4f6; color: #374151; padding: 4px 12px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; }
        
        .section-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 1rem; color: #111827; display: flex; align-items: center; }
        .section-title::before { content: ''; width: 4px; height: 18px; background: #10b981; margin-right: 10px; border-radius: 2px; }

        .order-card { background: white; border-radius: 20px; padding: 0; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border: 1px solid #f0f0f0; }
        .order-header { padding: 15px 20px; border-bottom: 1px dashed #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
        .order-body { padding: 20px; }
        .info-row { display: flex; align-items: flex-start; margin-bottom: 15px; }
        .info-icon { min-width: 35px; height: 35px; border-radius: 50%; background: #f0fdf4; color: #10b981; display: flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 0.9rem; }
        
        .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; }
        .btn-custom { padding: 12px; border-radius: 12px; font-weight: 600; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; border: none; }
        .btn-call { background: #fee2e2; color: #ef4444; }
        .btn-map { background: #e0f2fe; color: #0ea5e9; }
        .btn-main { background: #10b981; color: white; width: 100%; margin-top: 15px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2); }
        .btn-main:hover { background: #059669; color: white; }

        .otp-input-group { background: #f8fafc; padding: 8px; border-radius: 15px; border: 1px solid #e2e8f0; display: flex; }
        .otp-input { border: none; background: transparent; font-weight: 700; letter-spacing: 2px; text-align: center; }
        .otp-input:focus { outline: none; }
        
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 16px 20px; border-radius: 25px 25px 0 0; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #9ca3af; text-decoration: none; font-size: 0.75rem; font-weight: 600; transition: all 0.2s; }
        .nav-item i { font-size: 1.4rem; margin-bottom: 4px; }
        .nav-item.active { color: #10b981; transform: translateY(-3px); }
        .nav-item.active i { color: #10b981; filter: drop-shadow(0 4px 6px rgba(16, 185, 129, 0.3)); }

        .success-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.95); z-index: 2000; display: flex; flex-direction: column; align-items: center; justify-content: center; animation: fadeIn 0.3s ease; backdrop-filter: blur(5px); }
        .check-circle { width: 80px; height: 80px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); animation: scaleUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        
        @keyframes scaleUp { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Suspension Screen Styles */
        .suspended-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            padding: 0;
            font-family: 'Outfit', sans-serif;
        }
        .suspended-header {
            background: #1e293b;
            color: #fff;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .suspended-content {
            flex-grow: 1;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .suspended-banner {
            background: #ef4444;
            color: #fff;
            width: 100%;
            padding: 40px 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .suspended-icon-wrapper {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
        }
        .support-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            width: 100%;
            max-width: 350px;
            margin-top: auto;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<!-- Suspension Screen Overlay -->
<?php if($partner['is_suspended']): ?>
<div class="suspended-screen">
    <div class="suspended-header">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-list fs-3"></i>
            <span class="fw-bold fs-5">PORTER <span class="fw-normal">Partner</span></span>
        </div>
        <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-2">
            <i class="bi bi-headset fs-4"></i>
        </div>
    </div>
    
    <div class="suspended-content">
        <div class="suspended-banner">
            <div class="suspended-icon-wrapper">
                <i class="bi bi-person-x-fill"></i>
            </div>
            <h1 class="fw-bold mb-1" style="letter-spacing: 1px;">YOU ARE SUSPENDED</h1>
            <h5 class="opacity-90 fw-semibold"><?= htmlspecialchars($partner['suspension_reason'] ?: 'Unprofessional behaviour') ?></h5>
        </div>
        
        <div class="px-4 mb-5">
            <p class="text-secondary fs-5">Your account has been suspended for violating our partner conduct policy.</p>
        </div>

        <div class="support-card">
            <p class="text-muted mb-3">For any queries, please contact call center</p>
            <div class="d-grid gap-2">
                <a href="profile.php" class="btn btn-success rounded-pill py-3 fw-bold mb-1">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i> Update Documents
                </a>
                <a href="tel:1800123456" class="btn btn-dark rounded-pill py-3 fw-bold">
                    <i class="bi bi-telephone-fill me-2"></i> Contact Support
                </a>
                <a href="logout.php" class="btn btn-outline-danger border-0 mt-2">Log Out</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Setup Success Overlay -->
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'delivered'): ?>
<div class="success-overlay" onclick="this.style.display='none'; window.history.replaceState(null, null, window.location.pathname);">
    <div class="check-circle"><i class="bi bi-check-lg"></i></div>
    <h3 class="fw-bold mb-2">Order Delivered!</h3>
    <p class="text-secondary mb-4">Great job! ₹50.00 credited to wallet.</p>
    <button class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-lg">Continue Work</button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="header-section">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center border overflow-hidden" style="width:55px; height:55px;">
                <?php if (!empty($partner['doc_photo'])): ?>
                    <img src="../<?= htmlspecialchars($partner['doc_photo']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="bi bi-person-fill fs-3 text-secondary"></i>
                <?php endif; ?>
            </div>
            <div>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Welcome Back</small>
                <h5 class="mb-0 fw-bold"><?= htmlspecialchars($partner['name']) ?></h5>
            </div>
        </div>
        <div class="text-end d-flex gap-2">
            <a href="support.php" class="btn btn-outline-danger btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Help & Support">
                <i class="bi bi-headset fs-5"></i>
            </a>
            <a href="logout.php" class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Logout">
                <i class="bi bi-box-arrow-right fs-5"></i>
            </a>
            <?php if ($partner['is_verified']): ?>
            <a href="?action=toggle_status" class="badge rounded-pill text-decoration-none px-3 py-2 <?= $partner['status'] == 'Offline' ? 'bg-secondary' : 'bg-success' ?> d-flex align-items-center">
                <?= $partner['status'] ?> <i class="bi bi-arrow-repeat ms-1"></i>
            </a>
            <?php else: ?>
            <span class="badge rounded-pill bg-secondary px-3 py-2 d-flex align-items-center opacity-75" title="Verification Pending">
                Offline <i class="bi bi-lock-fill ms-1"></i>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Row in Header (Optional, or keep clean) -->
    <div class="d-flex justify-content-between align-items-center bg-dark text-white p-3 rounded-4 shadow-sm">
        <div>
            <small class="opacity-75">Today's Earnings</small>
            <h3 class="mb-0 fw-bold">₹<?= number_format($today_earnings, 2) ?></h3>
        </div>
        <a href="wallet.php" class="bg-white bg-opacity-10 rounded-3 p-2 text-white text-decoration-none d-flex flex-column align-items-end">
            <small class="opacity-75" style="font-size: 0.6rem;">Total Wallet</small>
            <div class="fw-bold">₹<?= number_format($partner['wallet_balance'], 2) ?></div>
        </a>
    </div>
</div>

<div class="container py-4">

    <!-- Stats Dashboard List -->
    <div class="mb-4">
        <h6 class="section-title">Overview</h6>
        
        <a href="#section-available" class="stat-row">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-broadcast"></i></div>
                <div class="fw-bold">Available Orders</div>
            </div>
            <div class="stat-count text-info"><?= count($broadcast_orders) ?></div>
        </a>

        <a href="#section-new-assignments" class="stat-row">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-box-seam"></i></div>
                <div class="fw-bold">New Assignments</div>
            </div>
            <div class="stat-count text-warning"><?= $count_assigned ?></div>
        </a>

        <a href="#section-out-for-delivery" class="stat-row">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-scooter"></i></div>
                <div class="fw-bold">Out for Delivery</div>
            </div>
            <div class="stat-count text-primary"><?= $count_active ?></div>
        </a>

        <a href="history.php?filter=today" class="stat-row">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-clock-history"></i></div>
                <div class="fw-bold">Today Deliveries</div>
            </div>
            <div class="stat-count text-success"><?= $count_completed ?></div>
        </a>

        <a href="wallet.php" class="stat-row">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-wallet2"></i></div>
                <div class="fw-bold">My Earnings & Wallet</div>
            </div>
            <div class="stat-count text-warning">₹<?= number_format($partner['wallet_balance'], 2) ?></div>
        </a>
    </div>

    <!-- Setup Success Overlay -->
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'accepted'): ?>
    <div class="success-overlay" onclick="this.style.display='none'; window.history.replaceState(null, null, window.location.pathname);">
        <div class="check-circle"><i class="bi bi-check-lg"></i></div>
        <h3 class="fw-bold mb-2">Order Accepted!</h3>
        <p class="text-secondary mb-4">You have been assigned to Order #ORD-<?= isset($_GET['oid']) ? (int)$_GET['oid'] : '' ?></p>
        <button class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-lg">View Details</button>
    </div>
    <?php endif; ?>

    <?php if(isset($_GET['error']) && $_GET['error'] == 'taken'): ?>
    <div class="alert alert-danger py-3 mb-4 rounded-4 shadow-sm fw-bold animate__animated animate__shakeX">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> This order has already been accepted by another partner.
    </div>
    <?php endif; ?>

    <?php if(isset($_GET['error']) && $_GET['error'] == 'not_verified'): ?>
    <div class="alert alert-warning py-3 mb-4 rounded-4 shadow-sm fw-bold animate__animated animate__shakeX">
        <i class="bi bi-shield-lock-fill me-2"></i> Your account is pending verification. You must stay Offline until approved.
    </div>
    <?php endif; ?>

    <!-- Broadcast Orders (New Order Requests) -->
    <?php if(!empty($broadcast_orders)): ?>
    <h6 class="section-title" id="section-available">New Order Requests</h6>
    <?php foreach($broadcast_orders as $order): ?>
    <div class="order-card border-info border-2 border-dashed bg-info bg-opacity-10 mb-4">
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-info text-white px-3 py-2 rounded-pill">#ORD-<?= $order['id'] ?></span>
                <span class="text-info fw-bold small"><i class="bi bi-lightning-fill"></i> New Request</span>
            </div>
            <div class="info-row">
                <div class="info-icon bg-info bg-opacity-10 text-info"><i class="bi bi-geo-alt"></i></div>
                <div>
                    <div class="small text-muted">Delivery Address</div>
                    <div class="fw-bold lh-sm"><?= htmlspecialchars($order['address']) ?></div>
                </div>
            </div>


            <div class="row g-2 mt-2">
                <div class="col-6">
                    <button type="button" class="btn btn-light w-100 rounded-pill py-2 fw-bold" onclick="this.closest('.order-card').style.display='none'">
                        <i class="bi bi-x-lg me-1"></i> Ignore
                    </button>
                </div>
                <div class="col-6">
                    <a href="?action=accept&oid=<?= $order['id'] ?>" class="btn btn-info text-white w-100 rounded-pill py-2 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Accept
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Active Details Section -->
    <?php if(!empty($active_orders)): ?>
    <h6 class="section-title" id="section-out-for-delivery">Current Delivery</h6>
    <?php foreach($active_orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">#ORD-<?= $order['id'] ?></span>
            <span class="fw-bold text-primary small">Out For Delivery</span>
        </div>
        <div class="order-body">
            <div class="info-row">
                <div class="info-icon"><i class="bi bi-person"></i></div>
                <div>
                    <div class="small text-muted">Customer Name</div>
                    <div class="fw-bold"><?= htmlspecialchars($order['full_name']) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                <div>
                    <div class="small text-muted">Delivery Address</div>
                    <div class="fw-bold lh-sm"><?= htmlspecialchars($order['address']) ?></div>
                </div>
            </div>

            <!-- Items List -->
            <div class="bg-light rounded-3 p-3 mb-3 border">
                <small class="text-muted fw-bold d-block mb-2 font-monospace" style="font-size: 0.7rem;">ORDER ITEMS</small>
                <ul class="list-unstyled mb-0">
                    <?php foreach(getOrderItems($pdo, $order['id']) as $item): ?>
                    <li class="d-flex justify-content-between align-items-center mb-2 last-mb-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-dot text-primary me-1"></i>
                            <span class="fw-medium text-dark">
                                <?= htmlspecialchars($item['name']) ?>
                                <?php if($item['size_name']): ?>
                                    <small class="text-muted ms-1">(<?= htmlspecialchars($item['size_name']) ?>)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="fw-bold font-monospace bg-white px-2 py-1 rounded border">x<?= $item['quantity'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="action-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <a href="../secure-call.php?order_id=<?= $order['id'] ?>" class="btn-custom btn-call text-decoration-none flex-column gap-1">
                    <i class="bi bi-telephone-fill fs-5"></i> <span style="font-size: 0.8rem;">Secure Call</span>
                </a>
                <a href="chat.php?order_id=<?= $order['id'] ?>" class="btn-custom btn-light text-dark border text-decoration-none flex-column gap-1">
                    <i class="bi bi-chat-dots-fill fs-5 text-primary"></i> <span style="font-size: 0.8rem;">Chat</span>
                </a>
                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($order['address']) ?>" target="_blank" class="btn-custom btn-map text-decoration-none flex-column gap-1">
                    <i class="bi bi-map-fill fs-5"></i> <span style="font-size: 0.8rem;">Map</span>
                </a>
            </div>

            <div class="mt-4 pt-3 border-top">
                <form method="POST" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="deliver">
                    <input type="hidden" name="oid" value="<?= $order['id'] ?>">
                    <div class="col-8">
                        <div class="otp-input-group">
                            <input type="text" name="otp" class="form-control otp-input py-2" placeholder="Enter 6-digit OTP" maxlength="6" pattern="\d{6}" inputmode="numeric" required>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2 fw-bold">
                            Verify
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger py-3 mb-4 rounded-4 shadow-sm fw-bold animate__animated animate__shakeX">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>


    <!-- New Assignments List -->
    <?php if(!empty($assigned_orders)): ?>
    <h6 class="section-title mt-4" id="section-new-assignments">Ready for Pickup</h6>
    <?php foreach($assigned_orders as $order): ?>
    <div class="order-card">
        <div class="order-header bg-warning bg-opacity-10">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">#ORD-<?= $order['id'] ?></span>
            <span class="fw-bold text-warning small">Ready for Pickup</span>
        </div>
        <div class="order-body">
            <div class="info-row">
                <div class="info-icon"><i class="bi bi-person"></i></div>
                <div>
                    <div class="small text-muted">Customer Name</div>
                    <div class="fw-bold"><?= htmlspecialchars($order['full_name']) ?></div>
                </div>
            </div>
            <div class="info-row">
                <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                <div>
                    <div class="small text-muted">Delivery Address</div>
                    <div class="fw-bold lh-sm"><?= htmlspecialchars($order['address']) ?></div>
                </div>
            </div>

            <!-- Items List -->
            <div class="bg-light rounded-3 p-3 mb-3 border">
                <small class="text-muted fw-bold d-block mb-2 font-monospace" style="font-size: 0.7rem;">ORDER ITEMS</small>
                <ul class="list-unstyled mb-0">
                    <?php foreach(getOrderItems($pdo, $order['id']) as $item): ?>
                    <li class="d-flex justify-content-between align-items-center mb-2 last-mb-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-dot text-warning me-1"></i>
                            <span class="fw-medium text-dark">
                                <?= htmlspecialchars($item['name']) ?>
                                <?php if($item['size_name']): ?>
                                    <small class="text-muted ms-1">(<?= htmlspecialchars($item['size_name']) ?>)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                        <span class="fw-bold font-monospace bg-white px-2 py-1 rounded border">x<?= $item['quantity'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="action-grid mt-3" style="grid-template-columns: 1fr 1fr;">
                <a href="../secure-call.php?order_id=<?= $order['id'] ?>" class="btn-custom btn-call text-decoration-none">
                    <i class="bi bi-telephone-fill me-2"></i> Secure Call
                </a>
                <a href="chat.php?order_id=<?= $order['id'] ?>" class="btn-custom btn-light text-dark border text-decoration-none">
                    <i class="bi bi-chat-dots-fill me-2 text-primary"></i> Chat
                </a>
            </div>
            <a href="?action=pickup&oid=<?= $order['id'] ?>" class="btn btn-warning rounded-3 py-2 fw-bold w-100 mt-3">
                <i class="bi bi-box-seam me-2"></i> Pick Up Order
            </a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="index.php" class="nav-item active">
        <i class="bi bi-grid-fill"></i>
        <span>Home</span>
    </a>
    <a href="wallet.php" class="nav-item">
        <i class="bi bi-wallet2"></i>
        <span>Wallet</span>
    </a>
    <a href="history.php" class="nav-item">
        <i class="bi bi-clock-history"></i>
        <span>History</span>
    </a>
    <a href="profile.php" class="nav-item">
        <i class="bi bi-person"></i>
        <span>Profile</span>
    </a>
</div>

<script>
    // Live Location Tracking
    function updateLocation() {
        if('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const formData = new FormData();
                formData.append('lat', lat);
                formData.append('lng', lng);
                fetch('api/update_location.php', { method: 'POST', body: formData });
            }, function(error) { console.error("Location error:", error); });
        }
    }
    setInterval(updateLocation, 10000);
    updateLocation();
</script>

</body>
</html>
