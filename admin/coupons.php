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
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: coupons.php");
    exit;
}

// Add Coupon Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
    $code = strtoupper($_POST['code']);
    $type = $_POST['discount_type'];
    $value = $_POST['discount_value'];
    $min = $_POST['min_purchase'] ?: 0;
    $expiry = $_POST['expiry_date'];
    $usage_limit = $_POST['usage_limit'] ?: 1;
    $status = $_POST['status'] ?? 'Enabled';
    
    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_purchase, expiry_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $type, $value, $min, $expiry, $usage_limit, $status]);
    header("Location: coupons.php");
    exit;
}

// Edit Coupon Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_coupon'])) {
    $id = $_POST['coupon_id'];
    $code = strtoupper($_POST['code']);
    $type = $_POST['discount_type'];
    $value = $_POST['discount_value'];
    $min = $_POST['min_purchase'] ?: 0;
    $expiry = $_POST['expiry_date'];
    $usage_limit = $_POST['usage_limit'] ?: 1;
    $status = $_POST['status'] ?? 'Enabled';
    
    $stmt = $pdo->prepare("UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, min_purchase = ?, expiry_date = ?, usage_limit = ?, status = ? WHERE id = ?");
    $stmt->execute([$code, $type, $value, $min, $expiry, $usage_limit, $status, $id]);
    header("Location: coupons.php");
    exit;
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Coupons - Quick mart Admin</title>
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
        .modal-content { border: none; border-radius: 1.25rem; }
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
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin active"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            
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
                    <h2 class="fw-bold mb-0">Coupons & Offers</h2>
                    <p class="text-muted small mb-0">Manage discount codes and promotional offers.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Create Coupon
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Discount</th>
                            <th>Usage</th>
                            <th>Min Purchase</th>
                            <th>Expiry Status</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-ticket-perforated display-4 opacity-25 d-block mb-3"></i>
                                No coupons found.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $c): ?>
                            <tr>
                                <td class="ps-4"><code class="fw-bold text-primary fs-6 bg-primary-subtle px-2 py-1 rounded"><?php echo $c['code']; ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        <?php echo $c['discount_type'] === 'percentage' ? $c['discount_value'] . '%' : '₹' . $c['discount_value']; ?>
                                    </div>
                                    <div class="extra-small text-muted"><?php echo ucfirst($c['discount_type']); ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 60px;">
                                            <?php $percent = ($c['used_count'] / $c['usage_limit']) * 100; ?>
                                            <div class="progress-bar bg-info" style="width: <?php echo min($percent, 100); ?>%"></div>
                                        </div>
                                        <span class="extra-small fw-bold text-muted"><?php echo $c['used_count']; ?>/<?php echo $c['usage_limit']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="small fw-medium">₹<?php echo number_format($c['min_purchase'], 2); ?></div>
                                </td>
                                <td>
                                    <?php 
                                    $expiry_date = new DateTime($c['expiry_date']);
                                    $today = new DateTime();
                                    $diff = $today->diff($expiry_date);
                                    $days = (int)$diff->format("%r%a");
                                    
                                    $badgeClass = 'bg-info-subtle text-info';
                                    $stageText = 'Active';
                                    
                                    if ($days < 0) {
                                        $badgeClass = 'bg-dark-subtle text-dark';
                                        $stageText = 'Expired';
                                    } elseif ($days <= 2) {
                                        $badgeClass = 'bg-danger-subtle text-danger';
                                        $stageText = 'Expiring Soon';
                                    } elseif ($days <= 14) {
                                        $badgeClass = 'bg-warning-subtle text-warning';
                                        $stageText = 'Warning';
                                    }
                                    ?>
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge <?php echo $badgeClass; ?> border-0 w-fit" style="width: fit-content;"><?php echo $stageText; ?></span>
                                        <div class="extra-small text-muted"><?php echo date('M d, Y', strtotime($c['expiry_date'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $c['status'] === 'Enabled' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-3">
                                        <?php echo $c['status']; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm edit-btn" 
                                                data-id="<?php echo $c['id']; ?>"
                                                data-code="<?php echo $c['code']; ?>"
                                                data-type="<?php echo $c['discount_type']; ?>"
                                                data-value="<?php echo $c['discount_value']; ?>"
                                                data-min="<?php echo $c['min_purchase']; ?>"
                                                data-expiry="<?php echo $c['expiry_date']; ?>"
                                                data-limit="<?php echo $c['usage_limit']; ?>"
                                                data-status="<?php echo $c['status']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" onclick="return confirm('Delete this coupon?')">
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

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Create Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_coupon" value="1">
                    <div class="mb-3">
                        <label class="form-label">Coupon Code</label>
                        <input type="text" name="code" class="form-control" placeholder="E.g. SAVE20" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Value</label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Min Purchase (₹)</label>
                            <input type="number" step="0.01" name="min_purchase" class="form-control" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usage Limit (Times)</label>
                        <input type="number" name="usage_limit" class="form-control" value="1" min="1" required>
                        <small class="text-muted">Set to 1 for one-time use coupons.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Coupon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_coupon" value="1">
                    <input type="hidden" name="coupon_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Coupon Code</label>
                        <input type="text" name="code" id="edit_code" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" id="edit_type" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Value</label>
                            <input type="number" step="0.01" name="discount_value" id="edit_value" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Min Purchase (₹)</label>
                            <input type="number" step="0.01" name="min_purchase" id="edit_min" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" id="edit_limit" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Enabled">Enabled</option>
                            <option value="Disabled">Disabled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">Update Coupon</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit_id').value = btn.dataset.id;
                document.getElementById('edit_code').value = btn.dataset.code;
                document.getElementById('edit_type').value = btn.dataset.type;
                document.getElementById('edit_value').value = btn.dataset.value;
                document.getElementById('edit_min').value = btn.dataset.min;
                document.getElementById('edit_expiry').value = btn.dataset.expiry;
                document.getElementById('edit_limit').value = btn.dataset.limit;
                document.getElementById('edit_status').value = btn.dataset.status;
            });
        });
    </script>
</body>
</html>
