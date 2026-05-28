<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

$message = '';
$error = '';

// Handle offer deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Offer deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle offer toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->exec("UPDATE offers SET is_active = NOT is_active WHERE id = $id");
    header("Location: offers.php");
    exit;
}

// Fetch offers
$stmt = $pdo->query("SELECT * FROM offers ORDER BY id DESC");
$offers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Offers - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root { --sidebar-width: 260px; --sidebar-bg: #1e293b; --primary-color: #10b981; --bg-light: #f8fafc; --text-main: #1e293b; --text-muted: #64748b; --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--text-main); }
        .sidebar { width: var(--sidebar-width); height: 100vh; overflow-y: auto; position: fixed; left: 0; top: 0; background-color: var(--sidebar-bg); color: #fff; z-index: 1000; transition: var(--transition); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
        .sidebar-brand { padding: 2rem 1.5rem; font-size: 1.5rem; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; }
        .nav-link-admin { padding: 0.85rem 1.5rem; color: #94a3b8; text-decoration: none; display: flex; align-items: center; transition: var(--transition); border-left: 4px solid transparent; }
        .nav-link-admin:hover, .nav-link-admin.active { background-color: #334155; color: #fff; border-left-color: var(--primary-color); }
        .nav-link-admin i { margin-right: 0.85rem; font-size: 1.25rem; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: var(--transition); }
        .card { border: none; border-radius: 1rem; box-shadow: var(--card-shadow); }
        @media (max-width: 992px) { .sidebar { left: -var(--sidebar-width); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; } .sidebar.active { left: 0; } .main-content { margin-left: 0; padding: 1.5rem; } }
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
            <a href="offers.php" class="nav-link-admin active"><i class="bi bi-megaphone"></i>Offers</a>
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">System</p>
            <a href="settings.php" class="nav-link-admin"><i class="bi bi-gear"></i>Settings</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Manage Offers</h2>
                <p class="text-muted small mb-0">Manage banner creatives and structured BOGO rules from one place</p>
            </div>
            <a href="offer_add.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm d-flex align-items-center"><i class="bi bi-plus-lg me-2"></i>Add Offer</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Preview</th>
                                <th>Offer Title</th>
                                <th>Discount</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($offers)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No offers found. Create one above!</td></tr>
                            <?php else: ?>
                            <?php foreach ($offers as $o): ?>
                            <?php
                                $imageSrc = $o['image_url'] ?? '';
                                $isBogo = strtoupper((string)($o['offer_type'] ?? 'BANNER')) === 'BOGO';
                                $ruleText = $isBogo
                                    ? ('Buy ' . (int)$o['buy_quantity'] . ' Get ' . (int)$o['get_quantity'] . ' • ' . str_replace('_', ' ', (string)($o['offer_scope'] ?? 'same_product')))
                                    : 'Banner only';
                                if (!empty($imageSrc) && !preg_match('/^(https?:)?\/\//i', $imageSrc) && strpos($imageSrc, '../') !== 0) {
                                    $imageSrc = '../' . ltrim($imageSrc, '/');
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="rounded-3 overflow-hidden shadow-sm d-flex align-items-center justify-content-center" style="width: 120px; height: 80px; background: <?php echo htmlspecialchars($o['bg_gradient']); ?>">
                                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" class="w-100 h-100 object-fit-cover" style="opacity: 0.8" alt="<?php echo htmlspecialchars($o['title']); ?>">
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold fs-5"><?php echo $o['title']; ?></div>
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($ruleText); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-success border border-success border-opacity-25 rounded-pill px-3 py-1 d-inline-block bg-success-subtle"><?php echo htmlspecialchars($o['discount_text']); ?></div>
                                </td>
                                <td>
                                    <?php if ($o['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill px-3 py-2">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary border-opacity-25 rounded-pill px-3 py-2">Disabled</span>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <?php if($o['start_date']) echo "From: " . $o['start_date']; ?>
                                        <?php if($o['end_date']) echo "<br>To: " . $o['end_date']; ?>
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="?toggle=<?php echo $o['id']; ?>" class="btn btn-sm btn-<?php echo $o['is_active'] ? 'warning' : 'success'; ?> rounded-pill px-3 mx-1">
                                        <?php echo $o['is_active'] ? 'Disable' : 'Enable'; ?>
                                    </a>
                                    <a href="offer_edit.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-primary rounded-circle p-2 mx-1"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle p-2 mx-1" onclick="return confirm('Delete this offer banner?');"><i class="bi bi-trash"></i></a>
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
</body>
</html>
