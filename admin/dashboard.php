<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

// Stats
$userCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn() ?: 0;
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
$productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?: 0;
$revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'Cancelled'")->fetchColumn() ?: 0;

// Sales Stats (Current Month)
$monthlyRevenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status != 'Cancelled' AND MONTH(order_date) = MONTH(CURRENT_DATE) AND YEAR(order_date) = YEAR(CURRENT_DATE)")->fetchColumn() ?: 0;
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn() ?: 0;

// Order Status Distribution for Doughnut Chart
$statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$deliveredCount = $statusCounts['Delivered'] ?? 0;
$cancelledCount = $statusCounts['Cancelled'] ?? 0;
$otherCount = $orderCount - $pendingOrders - $deliveredCount - $cancelledCount;

// Low Stock Alerts
$lowStockProducts = $pdo->query("
    SELECT p.name, p.stock_quantity, NULL as size_name
    FROM products p 
    WHERE p.stock_quantity <= 10 
      AND p.status != 'Archived'
      AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)
    UNION 
    SELECT p.name, v.stock_quantity, v.size_name
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE v.stock_quantity <= 10 AND p.status != 'Archived'
    ORDER BY stock_quantity ASC 
    LIMIT 5
")->fetchAll();

// Expiring Soon (Multi-stage Warning: 30, 14, 2 days)
$expiringProducts = $pdo->query("
    SELECT p.name, p.expiry_date, NULL as size_name, DATEDIFF(p.expiry_date, CURRENT_DATE) as days_left
    FROM products p 
    WHERE p.expiry_date IS NOT NULL 
      AND p.status != 'Archived'
      AND p.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) 
      AND p.expiry_date >= CURRENT_DATE
      AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)
    UNION
    SELECT p.name, v.expiry_date, v.size_name, DATEDIFF(v.expiry_date, CURRENT_DATE) as days_left
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE v.expiry_date IS NOT NULL 
      AND p.status != 'Archived'
      AND v.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) 
      AND v.expiry_date >= CURRENT_DATE
    ORDER BY days_left ASC 
    LIMIT 10
")->fetchAll();

// Latest Orders
$orders = $pdo->query("SELECT o.*, u.full_name FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY order_date DESC LIMIT 5")->fetchAll();

// Fetch last 6 months revenue for the chart
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M', strtotime("-$i months"));
    $monthRevenue = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE status != 'Cancelled' AND DATE_FORMAT(order_date, '%Y-%m') = ?");
    $monthRevenue->execute([$month]);
    $rev = $monthRevenue->fetchColumn() ?: 0;
    $chartData[] = [
        'month' => $monthName,
        'revenue' => (float)$rev
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quick mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #1e293b;
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
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
            z-index: 1050;
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
            padding: 2rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        .stat-card {
            border: none;
            border-radius: 1.25rem;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
        }
        .fw-800 { font-weight: 800; }
        .tracking-wider { letter-spacing: 0.05em; }
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
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }
        @media (max-width: 992px) {
            .sidebar { 
                left: -var(--sidebar-width); 
                box-shadow: none;
            }
            .sidebar.active { 
                left: 0; 
                box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            }
            .main-content { 
                margin-left: 0; 
                padding: 1.25rem; 
            }
            .stat-card { margin-bottom: 0; }
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(4px);
                z-index: 1040;
                display: none;
                opacity: 0;
                transition: var(--transition);
            }
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
            .top-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
            }
            .top-header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
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
            <a href="dashboard.php" class="nav-link-admin active"><i class="bi bi-speedometer2"></i>Dashboard</a>
            <a href="products.php" class="nav-link-admin"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin"><i class="bi bi-tags"></i>Categories</a>
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="wallet_transactions.php" class="nav-link-admin"><i class="bi bi-wallet2"></i>Wallet Trans.</a>
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
        <div class="d-flex justify-content-between align-items-center mb-5 top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h4 class="fw-bold mb-0">Dashboard Overview</h4>
                    <p class="text-muted small mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 top-header-actions">
                <div class="dropdown">
                    <button class="btn btn-white shadow-sm rounded-circle p-2 border-0 position-relative" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if(!empty($lowStockProducts) || !empty($expiringProducts)): ?>
                            <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-3" style="width: 300px;">
                        <h6 class="fw-bold mb-3 px-2">Notifications</h6>
                        <?php if(empty($lowStockProducts) && empty($expiringProducts)): ?>
                            <li class="text-center py-3 text-muted small">No new notifications</li>
                        <?php endif; ?>
                        <?php foreach($lowStockProducts as $lp): ?>
                            <li class="mb-2">
                                <a href="products.php?search=<?php echo urlencode($lp['name']); ?>" class="dropdown-item rounded-3 p-2 bg-danger-subtle border-start border-danger border-3">
                                    <div class="fw-bold text-danger small">Low Stock Alert</div>
                                    <div class="small text-muted text-wrap"><?php echo htmlspecialchars($lp['name']) . (!empty($lp['size_name']) ? ' (' . htmlspecialchars($lp['size_name']) . ')' : ''); ?> is running low</div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-white shadow-sm rounded-pill px-3 py-2 border-0 dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_name'] ?? 'Admin'); ?>&background=10b981&color=fff" class="rounded-circle me-2" width="32">
                        <span class="small fw-bold d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 overflow-hidden">
                        <li><a class="dropdown-item py-2 px-4" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item py-2 px-4" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider m-0"></li>
                        <li><a class="dropdown-item py-2 px-4 text-danger" href="../logout.php?from=admin"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-4 mb-5">
            <!-- Stats Grid -->
            <div class="col-lg-12">
                <div class="row g-4 h-100">
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-success-subtle text-success me-3">
                                        <i class="bi bi-currency-rupee"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted small mb-0 fw-bold text-uppercase">Total Revenue</p>
                                        <h3 class="fw-bold mb-0">₹<?php echo number_format((float)$revenue, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-primary-subtle text-primary me-3">
                                        <i class="bi bi-cart-check"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted small mb-0 fw-bold text-uppercase">Total Orders</p>
                                        <h3 class="fw-bold mb-0"><?php echo $orderCount; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-warning-subtle text-warning me-3">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted small mb-0 fw-bold text-uppercase">Pending Orders</p>
                                        <h3 class="fw-bold mb-0"><?php echo $pendingOrders; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-info-subtle text-info me-3">
                                        <i class="bi bi-graph-up-arrow"></i>
                                    </div>
                                    <div>
                                        <p class="text-muted small mb-0 fw-bold text-uppercase">Monthly Sales</p>
                                        <h3 class="fw-bold mb-0">₹<?php echo number_format((float)$monthlyRevenue, 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Sales Chart -->
            <div class="col-lg-8">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-4 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark">Revenue Overview</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="dropdown">Last 6 Months</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Order Status Chart -->
            <div class="col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-4 border-0 text-center">
                        <h5 class="fw-bold mb-0 text-dark">Order Distribution</h5>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center">
                        <div style="height: 250px; width: 100%;">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="mt-4 w-100">
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted"><i class="bi bi-circle-fill text-warning me-2"></i>Pending</span>
                                <span class="fw-bold"><?php echo $pendingOrders; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted"><i class="bi bi-circle-fill text-success me-2"></i>Delivered</span>
                                <span class="fw-bold"><?php echo $deliveredCount; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small">
                                <span class="text-muted"><i class="bi bi-circle-fill text-danger me-2"></i>Cancelled</span>
                                <span class="fw-bold"><?php echo $cancelledCount; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Orders Table -->
            <div class="col-lg-8">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-white py-4 border-0 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1">Recent Orders</h5>
                            <p class="text-muted small mb-0">Monitor your latest customer activity</p>
                        </div>
                        <a href="orders.php" class="btn btn-sm btn-light rounded-pill px-3 fw-bold border">View All Orders</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-bold text-dark">#<?php echo $o['id']; ?></span>
                                            <div class="text-muted smaller"><?php echo date('M d, Y', strtotime($o['order_date'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($o['full_name'] ?? 'G'); ?>&background=random&size=32" class="rounded-circle" width="32">
                                                </div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($o['full_name'] ?? 'Guest'); ?></div>
                                            </div>
                                        </td>
                                        <td class="fw-bold text-success">₹<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'bg-secondary-subtle text-secondary';
                                            if ($o['status'] == 'Pending') $statusClass = 'bg-warning-subtle text-warning';
                                            elseif ($o['status'] == 'Delivered') $statusClass = 'bg-success-subtle text-success';
                                            elseif ($o['status'] == 'Cancelled') $statusClass = 'bg-danger-subtle text-danger';
                                            ?>
                                            <span class="badge rounded-pill <?php echo $statusClass; ?> px-3 py-2 fw-semibold" style="font-size: 0.7rem;">
                                                <?php echo strtoupper($o['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="orders.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-white shadow-sm rounded-circle p-2 border">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Critical Alerts -->
                <div class="card h-100 border-0 shadow-sm overflow-hidden">
                    <div class="card-header bg-white py-4 border-0">
                        <h5 class="fw-bold mb-1 text-danger"><i class="bi bi-lightning-fill me-2"></i>Inventory Alerts</h5>
                        <p class="text-muted small mb-0">Urgent stock & expiry updates</p>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($lowStockProducts) && empty($expiringProducts)): ?>
                                <div class="p-5 text-center">
                                    <div class="bg-success-subtle text-success rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-check2-circle fs-1"></i>
                                    </div>
                                    <p class="text-muted mb-0">Everything looks good!</p>
                                </div>
                            <?php endif; ?>
                            
                            <?php foreach($lowStockProducts as $lp): ?>
                                <a href="products.php?search=<?php echo urlencode($lp['name']); ?>" class="list-group-item list-group-item-action p-3 border-0">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-box bg-danger-subtle text-danger me-3" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small text-dark"><?php echo htmlspecialchars($lp['name']) . (!empty($lp['size_name']) ? ' (' . htmlspecialchars($lp['size_name']) . ')' : ''); ?></div>
                                            <div class="text-danger smaller fw-bold">Low Stock: <?php echo $lp['stock_quantity']; ?> left</div>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted small"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>

                            <?php foreach($expiringProducts as $ep): ?>
                                <a href="products.php?search=<?php echo urlencode($ep['name']); ?>" class="list-group-item list-group-item-action p-3 border-0">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-box bg-warning-subtle text-warning me-3" style="width: 40px; height: 40px; font-size: 1.2rem;">
                                            <i class="bi bi-calendar-event"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold small text-dark"><?php echo htmlspecialchars($ep['name']) . (!empty($ep['size_name']) ? ' (' . htmlspecialchars($ep['size_name']) . ')' : ''); ?></div>
                                            <div class="text-warning smaller fw-bold">Expires in <?php echo $ep['days_left']; ?> days</div>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted small"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-4 border-0">
                        <h5 class="fw-bold mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-grid gap-2">
                            <a href="products.php?action=add" class="btn btn-light border-0 text-start py-3 px-4 rounded-4 d-flex align-items-center shadow-sm">
                                <div class="bg-success text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-plus-lg fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Add Product</div>
                                    <small class="text-muted">List a new item</small>
                                </div>
                            </a>
                            <a href="coupons.php?action=add" class="btn btn-light border-0 text-start py-3 px-4 rounded-4 d-flex align-items-center shadow-sm">
                                <div class="bg-primary text-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-percent fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold">Create Coupon</div>
                                    <small class="text-muted">New discount offer</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }

        // Close sidebar on window resize if open
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Revenue Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesGradient = salesCtx.createLinearGradient(0, 0, 0, 400);
        salesGradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
        salesGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($chartData, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($chartData, 'revenue')); ?>,
                    borderColor: '#10b981',
                    borderWidth: 3,
                    backgroundColor: salesGradient,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { family: 'Inter', size: 14 },
                        bodyFont: { family: 'Inter', size: 13 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: (context) => ' ₹' + context.raw.toLocaleString()
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5], color: '#e2e8f0' },
                        ticks: {
                            font: { family: 'Inter' },
                            callback: (value) => '₹' + value.toLocaleString()
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter' } }
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Delivered', 'Cancelled', 'Other'],
                datasets: [{
                    data: [
                        <?php echo $pendingOrders; ?>,
                        <?php echo $deliveredCount; ?>,
                        <?php echo $cancelledCount; ?>,
                        <?php echo $otherCount; ?>
                    ],
                    backgroundColor: ['#f59e0b', '#10b981', '#ef4444', '#94a3b8'],
                    hoverOffset: 4,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>
