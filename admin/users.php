<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

// Delete Logic
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$_GET['delete']]);
    header("Location: users.php");
    exit;
}

// Fetch all customers with order counts, total spent, and wallet balance
$users = $pdo->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as order_count,
    (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id) as total_spent,
    (SELECT balance FROM wallets WHERE user_id = u.id) as wallet_balance
    FROM users u 
    WHERE u.role = 'customer' 
    ORDER BY u.id ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Quick mart Admin</title>
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
        }
        .card { border: none; border-radius: 1rem; box-shadow: var(--card-shadow); }
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
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin active"><i class="bi bi-people"></i>Customers</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h2 class="fw-bold mb-0">Customer Management</h2>
                    <p class="text-muted small mb-0">Manage your registered customers and their activity.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="searchInput" class="form-control rounded-pill ps-5 bg-white border-0 shadow-sm" placeholder="Search customers..." style="width: 250px;">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                                <td class="ps-4">Customer</td>
                                <th>Contact Info</th>
                                <th>Address</th>
                                <th>Wallet</th>
                                <th>Stats</th>
                                <th>Joined</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-people display-4 opacity-25 d-block mb-3"></i>
                                No customers found.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($users as $u): ?>
                            <tr class="customer-row">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <?php if ($u['profile_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($u['profile_photo']); ?>" class="rounded-circle me-3 object-fit-cover" width="40" height="40">
                                        <?php else: ?>
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['full_name']); ?>&background=random" class="rounded-circle me-3" width="40" height="40">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            <div class="extra-small text-muted">ID: #<?php echo $counter++; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><i class="bi bi-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($u['email']); ?></div>
                                    <?php if ($u['phone_number']): ?>
                                        <div class="small text-muted mt-1"><i class="bi bi-telephone me-1 text-muted"></i> <?php echo htmlspecialchars($u['phone_number']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-muted text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($u['address'] ?? 'N/A'); ?>">
                                        <?php echo htmlspecialchars($u['address'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success">₹<?php echo number_format((float)($u['wallet_balance'] ?? 0), 2); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-light text-dark border-0 shadow-sm" style="width: fit-content;"><?php echo $u['order_count']; ?> Orders</span>
                                        <div class="text-success fw-bold small">₹<?php echo number_format((float)$u['total_spent'], 2); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="wallet_manage.php?user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-primary rounded-circle shadow-sm" title="Manage Wallet">
                                            <i class="bi bi-wallet2"></i>
                                        </a>
                                        <a href="wallet_transactions.php?user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-warning rounded-circle shadow-sm" title="View Transactions">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="orders.php?user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light rounded-circle shadow-sm" title="View Orders">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" onclick="return confirm('Delete this customer? This action cannot be undone.')" title="Delete Customer">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.customer-row');
                
                rows.forEach(row => {
                    const textContent = row.textContent.toLowerCase();
                    if (textContent.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Check for search parameter in URL
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            if (searchParam) {
                searchInput.value = searchParam;
                searchInput.dispatchEvent(new Event('input'));
            }
        }
    </script>
</body>
</html>
