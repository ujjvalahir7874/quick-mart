<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

$msg = '';
$err = '';
$admin_id = $_SESSION['admin_id'];

$stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE id = ? AND role = 'admin'");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        if ($name === '' || $email === '') {
            $err = "Name and Email are required.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $admin_id]);
            if ($check->fetch()) {
                $err = "Email is already in use.";
            } else {
                $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $upd->execute([$name, $email, $admin_id]);
                $_SESSION['admin_name'] = $name;
                $msg = "Profile updated successfully.";
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch();
            }
        }
    }
    if (isset($_POST['change_password'])) {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) {
            $err = "Password must be at least 6 characters.";
        } elseif ($new !== $confirm) {
            $err = "Passwords do not match.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $admin_id]);
            $msg = "Password changed successfully.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">Account</p>
            <a href="profile.php" class="nav-link-admin active"><i class="bi bi-person"></i>My Profile</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">My Profile</h2>
                <p class="text-muted small mb-0">Update your personal information and password</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success border-0 rounded-4"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
            <div class="alert alert-danger border-0 rounded-4"><?php echo $err; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Profile Details</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-600">Full Name</label>
                                <input type="text" name="full_name" class="form-control rounded-3" required value="<?php echo htmlspecialchars($admin['full_name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">Email Address</label>
                                <input type="email" name="email" class="form-control rounded-3" required value="<?php echo htmlspecialchars($admin['email']); ?>">
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-success rounded-3 px-4">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Change Password</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-600">New Password</label>
                                <input type="password" name="new_password" class="form-control rounded-3" required minlength="6" placeholder="••••••">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-600">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control rounded-3" required minlength="6" placeholder="••••••">
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary rounded-3 px-4">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

