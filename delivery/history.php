<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['delivery_partner_id'];
$partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $id")->fetch();

// Get Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = "WHERE o.delivery_person_id = $id AND o.status = 'Delivered'";

if ($filter == 'today') {
    $where_clause .= " AND DATE(o.delivery_date) = CURDATE()";
} elseif ($filter == 'weekly') {
    $where_clause .= " AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter == 'monthly') {
    $where_clause .= " AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// Fetch Summary Stats (Filtered)
$stats_where = "WHERE delivery_person_id = $id AND status = 'Delivered'";
if ($filter == 'today') { $stats_where .= " AND DATE(delivery_date) = CURDATE()"; }
elseif ($filter == 'weekly') { $stats_where .= " AND delivery_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; }
elseif ($filter == 'monthly') { $stats_where .= " AND delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; }

$total_completed = $pdo->query("SELECT COUNT(*) FROM orders $stats_where")->fetchColumn();

// Earnings stats based on filtered orders
$earnings_where = "WHERE delivery_person_id = $id AND type = 'Credit'";
if ($filter == 'today') { $earnings_where .= " AND DATE(created_at) = CURDATE()"; }
elseif ($filter == 'weekly') { $earnings_where .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; }
elseif ($filter == 'monthly') { $earnings_where .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; }

$total_earnings = $pdo->query("SELECT SUM(amount) FROM delivery_earnings $earnings_where")->fetchColumn() ?: 0;
$today_completed = $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_person_id = $id AND status = 'Delivered' AND DATE(delivery_date) = CURDATE()")->fetchColumn();

// Fetch Completed Orders (Filtered)
$completed_orders = $pdo->query("SELECT o.*, u.full_name, u.address, e.amount as earning_amount 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id 
                                 LEFT JOIN delivery_earnings e ON o.id = e.order_id AND e.type = 'Credit'
                                 $where_clause 
                                 ORDER BY o.delivery_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Completed Deliveries History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --secondary-color: #6366f1;
            --bg-light: #f4f5f7;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: 'Outfit', sans-serif; 
            padding-bottom: 100px; 
            color: #1f2937;
        }
        .header-section { 
            background: white; 
            padding: 1.5rem; 
            border-radius: 0 0 30px 30px; 
            box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 1rem;
        }
        .stat-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
            overflow-x: auto;
            padding-bottom: 5px;
            scrollbar-width: none;
        }
        .filter-bar::-webkit-scrollbar { display: none; }
        .filter-btn {
            padding: 8px 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.2s;
            text-decoration: none;
        }
        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        .order-card { 
            background: white; 
            border-radius: 20px; 
            padding: 20px; 
            margin-bottom: 15px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.01);
            border: 1px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }
        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
        }
        .delivery-time {
            font-size: 0.75rem;
            font-weight: 600;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .customer-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #e2e8f0;
        }
        .customer-avatar {
            width: 40px;
            height: 40px;
            background: #ecfdf5;
            color: var(--primary-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .earning-badge {
            background: #ecfdf5;
            color: #065f46;
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .bottom-nav { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            width: 100%; 
            background: white; 
            padding: 16px 20px; 
            border-radius: 25px 25px 0 0; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            z-index: 1000; 
            box-shadow: 0 -5px 20px rgba(0,0,0,0.05);
        }
        .nav-item { 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            color: #9ca3af; 
            text-decoration: none; 
            font-size: 0.75rem; 
            font-weight: 600; 
            transition: all 0.2s;
        }
        .nav-item i { font-size: 1.4rem; margin-bottom: 4px; }
        .nav-item.active { color: var(--primary-color); transform: translateY(-3px); }
        .nav-item.active i { color: var(--primary-color); filter: drop-shadow(0 4px 6px rgba(16, 185, 129, 0.3)); }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-icon {
            font-size: 4rem;
            color: #e2e8f0;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="header-section">
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="index.php" class="text-dark"><i class="bi bi-arrow-left fs-4"></i></a>
        <div>
            <h4 class="fw-bold mb-0">
                <?php 
                if($filter == 'today') echo "Today's Summary";
                elseif($filter == 'weekly') echo "Weekly Summary";
                elseif($filter == 'monthly') echo "Monthly Summary";
                else echo "Overall History";
                ?>
            </h4>
            <p class="text-muted small mb-0">Track your completed deliveries</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Total Earnings</div>
            <div class="stat-value">₹<?= number_format($total_earnings, 2) ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?= $total_completed ?></div>
        </div>
    </div>

    <div class="filter-bar">
        <a href="?filter=all" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">All Records</a>
        <a href="?filter=today" class="filter-btn <?= $filter == 'today' ? 'active' : '' ?>">Today</a>
        <a href="?filter=weekly" class="filter-btn <?= $filter == 'weekly' ? 'active' : '' ?>">This Week</a>
        <a href="?filter=monthly" class="filter-btn <?= $filter == 'monthly' ? 'active' : '' ?>">This Month</a>
    </div>
</div>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold mb-0">
            <?php 
            if($filter == 'today') echo "Today's Deliveries";
            elseif($filter == 'weekly') echo "Weekly Records";
            elseif($filter == 'monthly') echo "Monthly Records";
            else echo "All Completed Orders";
            ?>
        </h6>
        <?php if($today_completed > 0 && $filter != 'today'): ?>
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">
                <?= $today_completed ?> Done Today
            </span>
        <?php endif; ?>
    </div>

    <?php if(empty($completed_orders)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-clock-history"></i></div>
            <h5 class="fw-bold">No Records Found</h5>
            <p class="text-muted">Start accepting orders to build your delivery history!</p>
            <a href="index.php" class="btn btn-success rounded-pill px-5 py-3 fw-bold mt-3">Find Orders</a>
        </div>
    <?php else: ?>
        <?php foreach($completed_orders as $order): ?>
        <div class="order-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div class="fw-bold text-dark">#ORD-<?= $order['id'] ?></div>
                    <div class="delivery-time">
                        <i class="bi bi-calendar-check"></i> 
                        <?= date('d M Y, h:i A', strtotime($order['delivery_date'])) ?>
                    </div>
                </div>
                <div class="earning-badge">
                    +₹<?= number_format($order['earning_amount'] ?: 50.00, 2) ?>
                </div>
            </div>
            
            <div class="customer-info">
                <div class="customer-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-bold text-dark small"><?= htmlspecialchars($order['full_name']) ?></div>
                    <div class="text-muted small text-truncate"><?= htmlspecialchars($order['address']) ?></div>
                </div>
                <a href="../secure-call.php?order_id=<?= $order['id'] ?>" class="text-success ms-2">
                    <i class="bi bi-telephone-fill fs-5"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="index.php" class="nav-item">
        <i class="bi bi-house-door"></i> Home
    </a>
    <a href="wallet.php" class="nav-item">
        <i class="bi bi-wallet2"></i> Wallet
    </a>
    <a href="history.php" class="nav-item active">
        <i class="bi bi-clock-history"></i> History
    </a>
    <a href="profile.php" class="nav-item">
        <i class="bi bi-person"></i> Profile
    </a>
</div>

</body>
</html>
