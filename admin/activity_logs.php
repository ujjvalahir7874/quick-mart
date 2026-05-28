<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

$logs = $pdo->query("SELECT l.*, u.full_name as admin_name FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .nav-link-admin i { margin-right: 0.85rem; font-size: 1.25rem; }
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
        .table thead th {
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--text-muted);
            padding: 1rem;
        }
        .table tbody td { padding: 1rem; }
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
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">Support</p>
            <a href="contact-messages.php" class="nav-link-admin"><i class="bi bi-chat-left-dots"></i>Messages</a>
            <a href="activity_logs.php" class="nav-link-admin active"><i class="bi bi-journal-text"></i>Activity Logs</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="fw-bold mb-0">Activity Logs</h4>
                <p class="text-muted small mb-0">Monitor administrative actions and system events</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Time & Date</th>
                                <th>Administrator</th>
                                <th>Action Performed</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-info-circle fs-2 mb-3 d-block"></i>
                                    No activity logs found.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-medium text-dark"><?php echo date('h:i A', strtotime($l['created_at'])); ?></div>
                                        <div class="small text-muted"><?php echo date('M d, Y', strtotime($l['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($l['admin_name'] ?? 'S'); ?>&background=random" class="rounded-circle me-2" width="32">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($l['admin_name'] ?? 'System'); ?></div>
                                                <div class="small text-muted">Admin ID: #<?php echo $l['user_id'] ?? '0'; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-dark bg-light rounded-3 p-2 small border-start border-3 border-primary">
                                            <?php echo htmlspecialchars($l['action']); ?>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3">Success</span>
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
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
