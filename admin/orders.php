<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

// Fetch all orders with user and delivery person info
$user_id_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status_filter = isset($_GET['status']) ? $_GET['status'] : null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : null;
$delivery_person_id_filter = isset($_GET['delivery_person_id']) ? (int)$_GET['delivery_person_id'] : null;

// Fetch statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM orders" . ($delivery_person_id_filter ? " WHERE delivery_person_id = $delivery_person_id_filter" : ""))->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'" . ($delivery_person_id_filter ? " AND delivery_person_id = $delivery_person_id_filter" : ""))->fetchColumn(),
    'delivered' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Delivered'" . ($delivery_person_id_filter ? " AND delivery_person_id = $delivery_person_id_filter" : ""))->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Cancelled'" . ($delivery_person_id_filter ? " AND delivery_person_id = $delivery_person_id_filter" : ""))->fetchColumn()
];

// Calculate Dynamic Revenue based on current filter or default to Delivered
if ($status_filter) {
    $rev_sql = "SELECT SUM(total_amount) FROM orders WHERE status = " . $pdo->quote($status_filter);
    $revenue_label = $status_filter . " Amount";
} else {
    $rev_sql = "SELECT SUM(total_amount) FROM orders WHERE status = 'Delivered'";
    $revenue_label = "Total Revenue";
}

// Apply search and user filters to revenue calculation if they exist
if ($user_id_filter) {
    $rev_sql .= " AND user_id = $user_id_filter";
}
if ($delivery_person_id_filter) {
    $rev_sql .= " AND delivery_person_id = $delivery_person_id_filter";
}
if ($search_query) {
    $search_term = $pdo->quote('%' . $search_query . '%');
    $rev_sql .= " AND (id LIKE $search_term OR user_id IN (SELECT id FROM users WHERE full_name LIKE $search_term OR email LIKE $search_term))";
}

$stats['revenue'] = $pdo->query($rev_sql)->fetchColumn() ?: 0;
$stats['revenue_label'] = $revenue_label;

$sql = "SELECT o.*, u.full_name, u.email, dp.name as delivery_person_name, dp.mobile_no as delivery_person_mobile, dp.bike_number as delivery_person_bike 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id
        WHERE 1=1";

if ($user_id_filter) {
    $sql .= " AND o.user_id = $user_id_filter";
}
if ($status_filter) {
    $sql .= " AND o.status = " . $pdo->quote($status_filter);
}
if ($delivery_person_id_filter) {
    $sql .= " AND o.delivery_person_id = $delivery_person_id_filter";
}
if ($search_query) {
    $search_term = $pdo->quote('%' . $search_query . '%');
    $sql .= " AND (o.id LIKE $search_term OR u.full_name LIKE $search_term OR u.email LIKE $search_term)";
}

$sql .= " ORDER BY o.order_date DESC";
$orders = $pdo->query($sql)->fetchAll();

// Fetch all delivery persons
$delivery_persons = $pdo->query("SELECT * FROM delivery_persons WHERE status != 'Offline'")->fetchAll();

// Handle Status Update
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $delivery_person_id = isset($_POST['delivery_person_id']) ? (int)$_POST['delivery_person_id'] : null;

    // Prevent manual update to 'Delivered' status
    if ($new_status === 'Delivered') {
        $_SESSION['error_msg'] = "Order cannot be marked as Delivered manually. Please use OTP verification.";
        header("Location: orders.php" . ($user_id_filter ? "?user_id=$user_id_filter" : ""));
        exit;
    }
    
    if ($delivery_person_id) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, delivery_person_id = ? WHERE id = ?");
        $success = $stmt->execute([$new_status, $delivery_person_id, $order_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $success = $stmt->execute([$new_status, $order_id]);
    }

    if ($success) {
        // Generate OTP if status is Shipped/Out for Delivery AND an agent is assigned
        if (in_array($new_status, ['Shipped', 'Out for Delivery'])) {
            $stmt = $pdo->prepare("SELECT delivery_otp, delivery_person_id, contact_number FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order_check = $stmt->fetch();
            
            if ($order_check['delivery_person_id']) {
                $otp = $order_check['delivery_otp'];
                if (!$otp) {
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $pdo->prepare("UPDATE orders SET delivery_otp = ? WHERE id = ?")->execute([$otp, $order_id]);
                }
                
                // Send SMS OTP to Customer
                $sms_msg = "Your Order #$order_id status updated to $new_status! Share OTP $otp with the delivery partner. - Quick mart";
                sendSMS($order_check['contact_number'], $sms_msg);
            }
        }

        // If order is Delivered or Cancelled, free up the delivery person
        if (in_array($new_status, ['Delivered', 'Cancelled'])) {
            $pdo->prepare("UPDATE delivery_persons SET status = 'Available' WHERE id = (SELECT delivery_person_id FROM orders WHERE id = ?)")->execute([$order_id]);
        }

        // Handle Wallet Refund & Scratch Card Reversal on Cancellation
        if ($new_status === 'Cancelled') {
            $stmt = $pdo->prepare("SELECT user_id, total_amount, payment_method FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            try {
                $pdo->beginTransaction();

                // 1. Handle Wallet Refund (if paid via wallet)
                $wallet = null;
                if (strpos($order['payment_method'], 'Digital Wallet') !== false) {
                    // Get user's wallet
                    $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
                    $stmt->execute([$order['user_id']]);
                    $wallet = $stmt->fetch();

                    if ($wallet) {
                        $refund_amount = $order['total_amount'];
                        $new_balance = $wallet['balance'] + $refund_amount;
                        
                        // Update wallet balance
                        $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $wallet['id']]);
                        
                        // Record refund transaction
                        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) VALUES (?, 'Credit', ?, ?, ?, 'Completed')")
                            ->execute([$wallet['id'], $refund_amount, "Refund for Cancelled Order #$order_id", $order_id]);
                    }
                }

                // 2. Handle Scratch Card / Cashback Reversal (for all payment methods)
                $stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $scratch_card = $stmt->fetch();

                if ($scratch_card) {
                    if ($scratch_card['is_scratched'] == 0) {
                        // Simply delete the unscratched card
                        $pdo->prepare("DELETE FROM scratch_cards WHERE id = ?")->execute([$scratch_card['id']]);
                    } else {
                        // It was already claimed, need to reverse from wallet
                        if (!$wallet) {
                            $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
                            $stmt->execute([$order['user_id']]);
                            $wallet = $stmt->fetch();
                        }

                        if ($wallet) {
                            $reversal_amount = $scratch_card['amount'];
                            
                            // Fetch latest balance (might have changed due to refund above)
                            $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE id = ?");
                            $stmt->execute([$wallet['id']]);
                            $current_balance = $stmt->fetchColumn();
                            
                            $final_balance = $current_balance - $reversal_amount;
                            
                            // Update wallet balance
                            $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$final_balance, $wallet['id']]);
                            
                            // Record reversal transaction
                            $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) 
                                                VALUES (?, 'Debit', ?, ?, ?, 'Completed')")
                                ->execute([$wallet['id'], $reversal_amount, "Cashback Reversal for Cancelled Order #$order_id", $order_id]);
                            
                            // Delete the scratch card record
                            $pdo->prepare("DELETE FROM scratch_cards WHERE id = ?")->execute([$scratch_card['id']]);
                        }
                    }
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        }

        $_SESSION['success_msg'] = "Order #$order_id status updated to $new_status.";
    } else {
        $_SESSION['error_msg'] = "Failed to update order status.";
    }
    header("Location: orders.php" . ($user_id_filter ? "?user_id=$user_id_filter" : ""));
    exit;
}

// Handle OTP Verification
if (isset($_POST['verify_otp'])) {
    $order_id = (int)$_POST['order_id'];
    $entered_otp = trim($_POST['otp']);
    
    $stmt = $pdo->prepare("SELECT delivery_otp FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if ($order && $order['delivery_otp'] === $entered_otp) {
        // OTP matches, mark as Delivered
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Delivered', delivery_date = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$order_id])) {
            // Free up delivery person
            $pdo->prepare("UPDATE delivery_persons SET status = 'Available' WHERE id = (SELECT delivery_person_id FROM orders WHERE id = ?)")->execute([$order_id]);
            $_SESSION['success_msg'] = "OTP Verified! Order #$order_id marked as Delivered.";
        } else {
            $_SESSION['error_msg'] = "Failed to update order status.";
        }
    } else {
        $_SESSION['error_msg'] = "Invalid OTP for Order #$order_id. Please try again.";
    }
    header("Location: orders.php");
    exit;
}

$customer_name = "";
if ($user_id_filter && !empty($orders)) {
    $customer_name = " for " . htmlspecialchars($orders[0]['full_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1e293b;
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            overflow-x: hidden;
        }
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh; overflow-y: auto;
            position: fixed;
            left: 0;
            top: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            z-index: 1000;
            transition: var(--transition);
        }
        .sidebar-brand {
            padding: 2rem 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .sidebar-brand:hover { color: var(--primary-color); }
        .nav-link-admin {
            padding: 0.85rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
            border-left: 4px solid transparent;
            font-weight: 500;
        }
        .nav-link-admin:hover, .nav-link-admin.active {
            background-color: #334155;
            color: #fff;
            border-left-color: var(--primary-color);
        }
        .nav-link-admin i {
            margin-right: 0.85rem;
            font-size: 1.25rem;
        }
        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2.5rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
        }
        .orders-table-shell {
            overflow: visible !important;
        }
        .orders-table-responsive {
            overflow: visible;
        }
        .status-dropdown-menu {
            min-width: 14rem;
        }
        .status-dropdown-menu .dropdown-item {
            white-space: nowrap;
        }
        .status-badge-btn {
            white-space: nowrap;
        }
        .btn-white {
            background-color: #fff;
            color: var(--text-main);
            border: none;
        }
        .btn-white:hover {
            background-color: #f8fafc;
        }
        .table thead th {
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--text-muted);
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            color: var(--text-main);
        }
        .extra-small { font-size: 0.75rem; }
        
        /* Stats Cards Styles */
        .stat-card {
            border: none;
            border-radius: 24px;
            padding: 1.5rem;
            color: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            min-height: 160px;
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            color: white;
        }
        .stat-card.active {
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15);
            border: 2px solid rgba(13, 110, 253, 0.5);
        }
        .stat-card.total { background: #0d6efd; }
        .stat-card.pending { background: #ffc107; color: #000; }
        .stat-card.pending:hover { color: #000; }
        .stat-card.delivered { background: #198754; }
        .stat-card.cancelled { background: #dc3545; }
        .stat-card.revenue { background: #1e293b; cursor: default; min-height: 160px; }
        .stat-card.revenue:hover { transform: none; box-shadow: none; }
        
        .stat-icon-container {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
        }
        .stat-card.pending .stat-icon-container { background: rgba(0, 0, 0, 0.1); }
        .stat-card.revenue .stat-icon-container { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        
        .stat-card .label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
        }
        .revenue-chart-icon {
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            font-size: 2rem;
            opacity: 0.15;
            color: #10b981;
        }
        
        .search-container {
            position: relative;
            min-width: 300px;
        }
        .search-container .bi-search {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .search-input {
            padding-left: 45px !important;
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            height: 48px;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .filter-btn {
            height: 48px;
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            font-weight: 600;
            padding: 0 1.5rem !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        @media (max-width: 991.98px) {
            .orders-table-responsive {
                overflow-x: auto;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar { left: -var(--sidebar-width); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <a href="../index.php" class="sidebar-brand">
            <div class="bg-success text-white rounded-3 p-1 me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                <i class="bi bi-basket2-fill fs-5"></i>
            </div>
            Quick mart
        </a>
        <div class="mt-3">
            <p class="px-4 text-muted small text-uppercase fw-bold mb-2 opacity-50">Menu</p>
            <a href="dashboard.php" class="nav-link-admin"><i class="bi bi-speedometer2"></i>Dashboard</a>
            <a href="products.php" class="nav-link-admin"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin"><i class="bi bi-tags"></i>Categories</a>
            <a href="orders.php" class="nav-link-admin active"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">Support</p>
            <a href="contact-messages.php" class="nav-link-admin"><i class="bi bi-chat-left-dots"></i>Messages</a>
            <a href="activity_logs.php" class="nav-link-admin"><i class="bi bi-journal-text"></i>Activity Logs</a>
            
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-5">
            <div class="d-flex align-items-center">
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h2 class="fw-bold mb-1">Order Management</h2>
                    <p class="text-muted mb-0">Track, manage and analyze customer orders</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="track_delivery.php" target="_blank" class="btn btn-white shadow-sm rounded-pill px-4 py-2 fw-bold">
                    <i class="bi bi-geo-alt text-primary me-2"></i>Track Deliveries
                </a>
                <form action="" method="GET" class="search-container">
                    <?php if ($status_filter): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <?php endif; ?>
                    <?php if ($delivery_person_id_filter): ?>
                        <input type="hidden" name="delivery_person_id" value="<?php echo htmlspecialchars($delivery_person_id_filter); ?>">
                    <?php endif; ?>
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control search-input" 
                           placeholder="Search ID, Name, Email..." value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                </form>

                <div class="dropdown">
                    <button class="btn filter-btn dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-funnel text-success"></i>
                        <span><?php echo $status_filter ?: 'All Orders'; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2 mt-2">
                        <li><a class="dropdown-item rounded-3 <?php echo !$status_filter ? 'active' : ''; ?>" href="orders.php<?php echo $delivery_person_id_filter ? '?delivery_person_id='.$delivery_person_id_filter : ''; ?>">All Orders</a></li>
                        <li><hr class="dropdown-divider opacity-10"></li>
                        <?php foreach(['Pending', 'Accepted', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $s): ?>
                            <li><a class="dropdown-item rounded-3 <?php echo $status_filter === $s ? 'active' : ''; ?>" 
                                   href="orders.php?status=<?php echo urlencode($s); ?><?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?><?php echo $delivery_person_id_filter ? '&delivery_person_id='.$delivery_person_id_filter : ''; ?>"><?php echo $s; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if ($user_id_filter || $status_filter || $search_query || $delivery_person_id_filter): ?>
                    <a href="orders.php" class="btn btn-light shadow-sm rounded-3 px-3 py-2 fw-bold text-danger border">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-5">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="orders.php<?php echo $search_query ? '?search='.urlencode($search_query) : ''; ?>" 
                   class="stat-card total <?php echo !$status_filter ? 'active' : ''; ?>">
                    <div class="stat-icon-container">
                        <i class="bi bi-cart3"></i>
                    </div>
                    <div>
                        <div class="label">Total Orders</div>
                        <div class="value"><?php echo $stats['total']; ?></div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="orders.php?status=Pending<?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>" 
                   class="stat-card pending <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">
                    <div class="stat-icon-container">
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <div class="label">Pending</div>
                        <div class="value"><?php echo $stats['pending']; ?></div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="orders.php?status=Delivered<?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>" 
                   class="stat-card delivered <?php echo $status_filter === 'Delivered' ? 'active' : ''; ?>">
                    <div class="stat-icon-container">
                        <i class="bi bi-check2-all"></i>
                    </div>
                    <div>
                        <div class="label">Delivered</div>
                        <div class="value"><?php echo $stats['delivered']; ?></div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="orders.php?status=Cancelled<?php echo $search_query ? '&search='.urlencode($search_query) : ''; ?>" 
                   class="stat-card cancelled <?php echo $status_filter === 'Cancelled' ? 'active' : ''; ?>">
                    <div class="stat-icon-container">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div>
                        <div class="label">Cancelled</div>
                        <div class="value"><?php echo $stats['cancelled']; ?></div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-lg-4">
                <div class="stat-card revenue">
                    <i class="bi bi-graph-up-arrow revenue-chart-icon"></i>
                    <div class="stat-icon-container">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                    <div>
                        <div class="label"><?php echo strtoupper($stats['revenue_label']); ?></div>
                        <div class="value">₹<?php echo number_format($stats['revenue'], 2); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm orders-table-shell">
            <div class="card-body p-0">
                <div class="table-responsive orders-table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Order Info</th>
                                <th>Customer Info</th>
                                <th>Amount & Payment</th>
                                <th>Delivery Staff</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-cart-x display-4 text-muted opacity-25 d-block mb-3"></i>
                                    <p class="text-muted mb-0">No orders found.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold fs-6">#<?php echo $o['id']; ?></div>
                                        <div class="text-muted small text-nowrap mb-1"><?php echo date('M d, Y h:i A', strtotime($o['order_date'])); ?></div>
                                        <button class="btn btn-link btn-sm p-0 text-primary text-decoration-none fw-bold small view-items" data-order-id="<?php echo $o['id']; ?>">
                                            <i class="bi bi-eye me-1"></i>View Items
                                        </button>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($o['full_name'] ?? 'G'); ?>&background=random" class="rounded-circle me-3 object-fit-cover shadow-sm" width="38" height="38">
                                            <div>
                                                <div class="fw-bold text-nowrap"><?php echo htmlspecialchars($o['full_name'] ?? 'Guest/Deleted'); ?></div>
                                                <div class="text-muted small text-nowrap"><?php echo htmlspecialchars($o['email'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success fs-6">₹<?php echo number_format((float)$o['total_amount'], 2); ?></div>
                                        <div class="text-muted extra-small text-uppercase fw-bold text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($o['payment_method'] ?? 'COD'); ?>">
                                            <?php echo htmlspecialchars($o['payment_method'] ?? 'COD'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($o['delivery_person_id']): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary-subtle text-primary rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 38px; height: 38px;">
                                                    <i class="bi bi-person-badge"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold small text-nowrap"><?php echo htmlspecialchars($o['delivery_person_name']); ?></div>
                                                    <div class="text-muted extra-small fw-bold text-nowrap"><?php echo htmlspecialchars($o['delivery_person_bike']); ?></div>
                                                    <div class="text-muted extra-small text-nowrap"><?php echo htmlspecialchars($o['delivery_person_mobile']); ?></div>
                                                </div>
                                            </div>
                                        <?php elseif (!in_array($o['status'], ['Cancelled', 'Delivered'])): ?>
                                            <div class="d-flex flex-column gap-2">
                                                <span class="badge bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 border extra-small rounded-pill px-2 py-1 w-fit">
                                                    <i class="bi bi-exclamation-circle me-1"></i>Unassigned
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = ucfirst(strtolower($o['status']));
                                            $badgeClass = 'bg-warning-subtle text-warning';
                                            if ($status === 'Accepted') $badgeClass = 'bg-info-subtle text-info';
                                            if ($status === 'Processing') $badgeClass = 'bg-info-subtle text-info';
                                            if ($status === 'Shipped') $badgeClass = 'bg-primary-subtle text-primary';
                                            if ($status === 'Delivered') $badgeClass = 'bg-success-subtle text-success';
                                            if ($status === 'Cancelled') $badgeClass = 'bg-danger-subtle text-danger';
                                        ?>
                                        <div class="dropdown status-dropdown">
                                            <button class="btn btn-sm <?php echo $badgeClass; ?> border-0 rounded-pill px-3 dropdown-toggle status-badge-btn" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                                <?php echo $status; ?>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2 status-dropdown-menu">
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Pending"><button type="submit" name="update_status" class="dropdown-item rounded-3">Pending</button></form></li>
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Accepted"><button type="submit" name="update_status" class="dropdown-item rounded-3">Accepted</button></form></li>
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Processing"><button type="submit" name="update_status" class="dropdown-item rounded-3">Processing</button></form></li>
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Shipped"><button type="submit" name="update_status" class="dropdown-item rounded-3">Shipped</button></form></li>
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Out for Delivery"><button type="submit" name="update_status" class="dropdown-item rounded-3">Out for Delivery</button></form></li>
                                                <li><hr class="dropdown-divider opacity-10"></li>
                                                <li><form action="" method="POST"><input type="hidden" name="order_id" value="<?php echo $o['id']; ?>"><input type="hidden" name="status" value="Cancelled"><button type="submit" name="update_status" class="dropdown-item rounded-3 text-danger">Cancelled</button></form></li>
                                            </ul>
                                        </div>
                                        

                                    </td>
                                    <td>
                                        <?php 
                                            $fstmt = $pdo->prepare("SELECT * FROM order_feedback WHERE order_id = ?");
                                            $fstmt->execute([$o['id']]);
                                            $feedback = $fstmt->fetch();
                                        ?>
                                        <?php if ($feedback): ?>
                                            <div class="text-warning extra-small mb-1">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $feedback['rating'] ? '-fill' : ''; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="text-muted extra-small text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($feedback['comment']); ?>">
                                                "<?php echo htmlspecialchars($feedback['comment']); ?>"
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">No feedback</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="../receipt.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Print Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr id="items-<?php echo $o['id']; ?>" class="order-items-row d-none bg-light bg-opacity-50">
                                    <td colspan="6" class="p-0">
                                        <div class="p-4">
                                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                                <div class="card-header bg-white py-3">
                                                    <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Order Items</h6>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-borderless align-middle mb-0">
                                                        <thead class="bg-light">
                                                            <tr>
                                                                <th class="ps-4">Product Name</th>
                                                                <th class="text-center">Qty</th>
                                                                <th class="text-end">Price</th>
                                                                <th class="text-end pe-4">Subtotal</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                                $istmt = $pdo->prepare("SELECT oi.*, p.name, pv.size_name 
                                                                                        FROM order_items oi 
                                                                                        LEFT JOIN products p ON oi.product_id = p.id 
                                                                                        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
                                                                                        WHERE oi.order_id = ?");
                                                                $istmt->execute([$o['id']]);
                                                                $items = $istmt->fetchAll();
                                                                foreach ($items as $item): 
                                                            ?>
                                                                <tr>
                                                                    <td class="ps-4 fw-medium">
                                                                        <?php echo htmlspecialchars($item['name'] ?? 'Unknown Product'); ?>
                                                                        <?php if (!empty($item['size_name'])): ?>
                                                                            <span class="badge bg-light text-success border ms-2 small fw-600"><?php echo htmlspecialchars($item['size_name']); ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                                    <td class="text-end">₹<?php echo number_format($item['price_at_time'], 2); ?></td>
                                                                    <td class="text-end pe-4 fw-bold">₹<?php echo number_format($item['price_at_time'] * $item['quantity'], 2); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="mt-3 ps-2">
                                                <div class="text-muted small mb-1"><i class="bi bi-geo-alt me-1"></i><strong>Shipping Address:</strong></div>
                                                <div class="small text-dark"><?php echo htmlspecialchars($o['shipping_address'] ?? 'N/A'); ?></div>
                                                <?php if($o['contact_number']): ?>
                                                    <div class="small text-muted mt-1"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($o['contact_number']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar Toggle
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        document.querySelectorAll('.view-items').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.dataset.orderId;
                const row = document.getElementById(`items-${orderId}`);
                if (row.classList.contains('d-none')) {
                    row.classList.remove('d-none');
                    this.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Hide Items';
                } else {
                    row.classList.add('d-none');
                    this.innerHTML = '<i class="bi bi-eye me-1"></i>View Items';
                }
            });
        });

        document.querySelectorAll('.status-dropdown').forEach(dropdownEl => {
            dropdownEl.addEventListener('show.bs.dropdown', function() {
                const toggle = dropdownEl.querySelector('[data-bs-toggle="dropdown"]');
                if (!toggle) return;

                const rect = toggle.getBoundingClientRect();
                const estimatedMenuHeight = 280;
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;

                dropdownEl.classList.toggle('dropup', spaceBelow < estimatedMenuHeight && spaceAbove > spaceBelow);
            });

            dropdownEl.addEventListener('hide.bs.dropdown', function() {
                dropdownEl.classList.remove('dropup');
            });
        });
    });
    </script>
</body>
</html>
