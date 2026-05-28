<?php
require_once '../config/db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// Fetch all wallet transactions with user names
$where = "";
$params = [];
$user_id = $_GET['user_id'] ?? null;
if ($user_id) {
    $where = "WHERE w.user_id = ?";
    $params[] = $user_id;
}

$stmt = $pdo->prepare("
    SELECT wt.*, u.full_name, u.email 
    FROM wallet_transactions wt 
    JOIN wallets w ON wt.wallet_id = w.id 
    JOIN users u ON w.user_id = u.id 
    $where
    ORDER BY wt.created_at DESC
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Transactions - Admin</title>
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
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); }
        .sidebar { width: var(--sidebar-width); height: 100vh; overflow-y: auto; position: fixed; left: 0; top: 0; background: var(--sidebar-bg); color: #fff; }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
        .nav-link-admin { padding: 0.85rem 1.5rem; color: #94a3b8; text-decoration: none; display: flex; align-items: center; }
        .nav-link-admin:hover, .nav-link-admin.active { background: #334155; color: #fff; border-left: 4px solid var(--primary-color); }
        .main-content { margin-left: var(--sidebar-width); padding: 2.5rem; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .sidebar-brand { padding: 2rem 1.5rem; font-size: 1.5rem; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; }
    </style>
</head>
<body>
    <div class="sidebar">
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
            <a href="wallet_transactions.php" class="nav-link-admin active"><i class="bi bi-wallet2"></i>Wallet Trans.</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Wallet Transactions</h2>
        <div class="card overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Transaction Details</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold small"><?php echo htmlspecialchars($t['description']); ?></div>
                                <?php if($t['order_id']): ?>
                                    <span class="badge bg-light text-dark border extra-small">Order #<?php echo $t['order_id']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="users.php?search=<?php echo urlencode($t['email']); ?>" class="text-decoration-none">
                                    <div class="small fw-bold text-dark"><?php echo htmlspecialchars($t['full_name']); ?></div>
                                    <div class="extra-small text-muted"><?php echo htmlspecialchars($t['email']); ?></div>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $t['type'] === 'Credit' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $t['type'] === 'Credit' ? 'success' : 'danger'; ?> rounded-pill px-3">
                                    <?php echo $t['type']; ?>
                                </span>
                            </td>
                            <td class="fw-bold text-<?php echo $t['type'] === 'Credit' ? 'success' : 'danger'; ?>">
                                <?php echo $t['type'] === 'Credit' ? '+' : '-'; ?>₹<?php echo number_format($t['amount'], 2); ?>
                            </td>
                            <td>
                                <span class="badge bg-success rounded-pill px-3"><?php echo $t['status']; ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="small fw-bold"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                                <div class="extra-small text-muted"><?php echo date('h:i A', strtotime($t['created_at'])); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No transactions recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
