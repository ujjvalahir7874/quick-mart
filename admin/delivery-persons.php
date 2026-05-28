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

// Add Logic
if (isset($_POST['add_delivery_person'])) {
    $name = trim($_POST['name']);
    $mobile_no = trim($_POST['mobile_no']);
    $email = trim($_POST['email']);
    $bike_number = trim($_POST['bike_number']);
    $password = $_POST['password'];

    if (empty($name) || empty($mobile_no) || empty($email) || empty($bike_number) || empty($password)) {
        $error = "All fields are required.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO delivery_persons (name, mobile_no, email, bike_number, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $mobile_no, $email, $bike_number, $hash]);
            $message = "Delivery person added successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                $error = "Mobile number or Email already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Delete Logic
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM delivery_persons WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: delivery-persons.php?msg=deleted");
    exit;
}

// Toggle Verification Logic
if (isset($_GET['toggle_verify'])) {
    $id = $_GET['toggle_verify'];
    $status = $_GET['status'] == '1' ? 0 : 1;
    
    // If verifying, clear all rejection flags, reasons, and lift suspension
    if ($status == 1) {
        $stmt = $pdo->prepare("UPDATE delivery_persons SET 
            is_verified = 1, 
            is_suspended = 0,
            suspension_reason = NULL,
            rejected_docs = NULL, 
            rejection_reason_aadhaar = NULL, 
            rejection_reason_license = NULL, 
            rejection_reason_rc = NULL, 
            rejection_reason_photo = NULL 
            WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // If unverifying, force offline status
        $stmt = $pdo->prepare("UPDATE delivery_persons SET is_verified = 0, status = 'Offline' WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: delivery-persons.php?msg=updated");
    exit;
}

// Reject Document Logic
if (isset($_POST['reject_docs'])) {
    $id = $_POST['staff_id'];
    $rejected_types = $_POST['rejected_types'] ?? [];
    $reasons = [
        'aadhaar' => trim($_POST['reason_aadhaar'] ?? ''),
        'license' => trim($_POST['reason_license'] ?? ''),
        'rc' => trim($_POST['reason_rc'] ?? ''),
        'photo' => trim($_POST['reason_photo'] ?? '')
    ];

    if (!empty($rejected_types)) {
        $rejected_str = implode(',', $rejected_types);
        // Set as Rejected: is_verified=0, is_suspended=1, status='Offline'
        $stmt = $pdo->prepare("UPDATE delivery_persons SET 
            is_verified = 0, 
            is_suspended = 1,
            suspension_reason = 'Documents Rejected. Please re-upload valid documents.',
            status = 'Offline',
            rejected_docs = ?, 
            rejection_reason_aadhaar = ?, 
            rejection_reason_license = ?, 
            rejection_reason_rc = ?, 
            rejection_reason_photo = ? 
            WHERE id = ?");
        $stmt->execute([
            $rejected_str,
            $reasons['aadhaar'],
            $reasons['license'],
            $reasons['rc'],
            $reasons['photo'],
            $id
        ]);
        header("Location: delivery-persons.php?msg=rejected");
        exit;
    }
}

// Suspension Logic
if (isset($_POST['suspend_staff'])) {
    $id = $_POST['staff_id'];
    $reason_type = $_POST['reason_type'];
    $custom_reason = trim($_POST['custom_reason']);
    
    $final_reason = ($reason_type === 'Other' && !empty($custom_reason)) ? $custom_reason : $reason_type;
    
    $stmt = $pdo->prepare("UPDATE delivery_persons SET is_suspended = 1, suspension_reason = ? WHERE id = ?");
    $stmt->execute([$final_reason, $id]);
    header("Location: delivery-persons.php?msg=suspended");
    exit;
}

if (isset($_GET['unsuspend'])) {
    $id = $_GET['unsuspend'];
    $stmt = $pdo->prepare("UPDATE delivery_persons SET is_suspended = 0, suspension_reason = NULL WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: delivery-persons.php?msg=unsuspended");
    exit;
}

// Search Logic
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = '';
$params = [];
if ($search_query !== '') {
    $search_sql = " WHERE dp.name LIKE ? OR dp.mobile_no LIKE ? OR dp.id = ?";
    $params = ["%$search_query%", "%$search_query%", $search_query];
}

// Fetch all delivery persons with their active and delivered order count
$stmt = $pdo->prepare("SELECT dp.*, 
    (SELECT COUNT(*) FROM orders WHERE delivery_person_id = dp.id AND status NOT IN ('Delivered', 'Cancelled')) as active_deliveries,
    (SELECT COUNT(*) FROM orders WHERE delivery_person_id = dp.id AND status = 'Delivered') as delivered_orders,
    (SELECT COUNT(*) FROM orders WHERE delivery_person_id = dp.id AND status = 'Pending') as pending_orders
    FROM delivery_persons dp 
    $search_sql
    ORDER BY id DESC");
$stmt->execute($params);
$delivery_persons = $stmt->fetchAll();

// Calculate Top Performer and Chart Data
$topPerformer = null;
$maxDelivered = -1;
foreach ($delivery_persons as $dp) {
    if ($dp['delivered_orders'] > $maxDelivered && $dp['delivered_orders'] > 0) {
        $maxDelivered = $dp['delivered_orders'];
        $topPerformer = $dp;
    }
}

$chartLabels = [];
$chartData = [];
$sorted_dp = $delivery_persons;
usort($sorted_dp, function($a, $b) { return $b['delivered_orders'] <=> $a['delivered_orders']; });
$top5 = array_slice($sorted_dp, 0, 5);
foreach($top5 as $dp) {
    if($dp['delivered_orders'] > 0) {
        $chartLabels[] = $dp['name'];
        $chartData[] = $dp['delivered_orders'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Delivery Staff - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin active"><i class="bi bi-truck"></i>Delivery Staff</a>
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
                    <h2 class="fw-bold mb-0">Delivery Staff Management</h2>
                    <p class="text-muted small mb-0">Manage your delivery persons and their vehicles.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-success rounded-3 px-4 py-2 fw-600" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="bi bi-plus-lg me-2"></i>Add Delivery Staff
                </button>
            </div>
        </div>

        <?php if ($message || isset($_GET['msg'])): ?>
            <?php 
            $msg = $message;
            if (isset($_GET['msg'])) {
                if ($_GET['msg'] == 'deleted') $msg = "Staff deleted successfully!";
                if ($_GET['msg'] == 'updated') $msg = "Verification status updated!";
                if ($_GET['msg'] == 'suspended') $msg = "Staff suspended successfully!";
                if ($_GET['msg'] == 'unsuspended') $msg = "Staff unsuspended successfully!";
                if ($_GET['msg'] == 'rejected') $msg = "Documents marked as rejected!";
            }
            ?>
            <div class="alert alert-success border-0 rounded-4 mb-4"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Performance Overview -->
        <div class="row g-4 mb-4">
            <?php if ($topPerformer): ?>
            <div class="col-lg-4">
                <div class="card border-0 rounded-4 shadow-sm h-100 bg-success bg-gradient text-white position-relative overflow-hidden">
                    <div class="card-body p-4 position-relative z-1">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Top Performer</h5>
                            <span class="badge bg-white text-success rounded-pill px-3 py-2">ID: #<?= $topPerformer['id'] ?></span>
                        </div>
                        <div class="d-flex align-items-center mt-3">
                            <?php if ($topPerformer['doc_photo']): ?>
                                <img src="../<?= htmlspecialchars($topPerformer['doc_photo']) ?>" class="rounded-circle me-3 border border-3 border-white shadow-sm object-fit-cover" width="60" height="60">
                            <?php else: ?>
                                <div class="bg-white bg-opacity-25 text-white rounded-circle me-3 d-flex align-items-center justify-content-center border border-3 border-white shadow-sm" style="width: 60px; height: 60px;">
                                    <i class="bi bi-person-fill fs-3"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="fw-bold mb-0 text-white"><?= htmlspecialchars($topPerformer['name']) ?></h4>
                                <div class="opacity-75 small"><?= htmlspecialchars($topPerformer['mobile_no']) ?></div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-top border-white border-opacity-25 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="opacity-75 small text-uppercase fw-bold" style="letter-spacing: 0.05em;">Delivered</div>
                                <h3 class="fw-bold mb-0"><?= $topPerformer['delivered_orders'] ?> Orders</h3>
                            </div>
                            <i class="bi bi-graph-up-arrow fs-1 opacity-25 position-absolute bottom-0 end-0 mb-3 me-3"></i>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-lg-<?= $topPerformer ? '8' : '12' ?>">
                <div class="card border-0 rounded-4 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark">Top Delivery Staff (By Delivered Orders)</h6>
                        </div>
                        <div style="height: 180px;">
                            <?php if(empty($chartLabels)): ?>
                                <div class="h-100 d-flex align-items-center justify-content-center text-muted">
                                    No delivery data available yet.
                                </div>
                            <?php else: ?>
                                <canvas id="topDeliveryChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <div class="card-header bg-white border-0 p-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <form method="GET" class="d-flex shadow-sm rounded-pill overflow-hidden border w-100">
                    <input type="text" name="search" class="form-control border-0 shadow-none px-4 py-2" placeholder="Search by name, ID or mobile..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit" class="btn btn-light px-4 border-start"><i class="bi bi-search text-muted"></i></button>
                    <?php if($search_query): ?>
                        <a href="delivery-persons.php" class="btn btn-light px-3 border-start text-danger" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        <div class="card border-0 shadow-none overflow-hidden mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                                <th class="ps-4">Staff Name</th>
                                <th>Mobile Number</th>
                                <th>Bike Number</th>
                                <th>Wallet</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <th>Pending</th>
                                <th>Delivered</th>
                                <th>Joined</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($delivery_persons)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-truck display-4 opacity-25 d-block mb-3"></i>
                                No delivery staff found.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($delivery_persons as $staff): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <?php if ($staff['doc_photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($staff['doc_photo']); ?>" class="rounded-circle me-3 object-fit-cover" width="40" height="40" style="cursor: pointer;" onclick="window.open('../<?php echo htmlspecialchars($staff['doc_photo']); ?>')">
                                        <?php else: ?>
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($staff['name']); ?></div>
                                            <div class="extra-small text-muted">ID: #<?php echo $staff['id']; ?></div>
                                            <div class="extra-small text-muted text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($staff['email'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($staff['mobile_no']); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($staff['bike_number']); ?></span></td>
                                <td>
                                    <div class="fw-bold text-success">₹<?php echo number_format((float)$staff['wallet_balance'], 2); ?></div>
                                    <div class="extra-small text-muted">Earnings</div>
                                </td>
                                <td>
                                    <?php if ($staff['is_verified']): ?>
                                        <a href="?toggle_verify=<?php echo $staff['id']; ?>&status=1" class="badge bg-success text-decoration-none" title="Click to Unverify">
                                            <i class="bi bi-patch-check-fill me-1"></i>Verified
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle_verify=<?php echo $staff['id']; ?>&status=0" class="badge bg-danger text-decoration-none" title="Click to Verify">
                                            <i class="bi bi-patch-exclamation-fill me-1"></i>Pending
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = 'bg-success';
                                    if ($staff['status'] == 'Busy') $statusClass = 'bg-warning';
                                    if ($staff['status'] == 'Offline') $statusClass = 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $staff['status']; ?></span>
                                    <?php if ($staff['is_suspended']): ?>
                                        <div class="mt-1"><span class="badge bg-danger">Suspended</span></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($staff['pending_orders'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i><?php echo $staff['pending_orders']; ?> Pending</span>
                                    <?php else: ?>
                                        <span class="text-muted extra-small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($staff['delivered_orders'] > 0): ?>
                                        <span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i><?php echo $staff['delivered_orders']; ?> Delivered</span>
                                    <?php else: ?>
                                        <span class="text-muted extra-small">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($staff['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <a href="orders.php?delivery_person_id=<?php echo $staff['id']; ?>" class="btn btn-light btn-sm text-info rounded-3 me-1" title="View Deliveries">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button class="btn btn-light btn-sm text-primary rounded-3 me-1" 
                                            onclick='viewDocs(<?= htmlspecialchars(json_encode([
                                                "id" => $staff["id"],
                                                "aadhaar" => $staff["doc_aadhaar"],
                                                "license" => $staff["doc_license"],
                                                "rc" => $staff["doc_rc"],
                                                "photo" => $staff["doc_photo"],
                                                "name" => $staff["name"],
                                                "rejected_docs" => $staff["rejected_docs"],
                                                "reason_aadhaar" => $staff["rejection_reason_aadhaar"],
                                                "reason_license" => $staff["rejection_reason_license"],
                                                "reason_rc" => $staff["rejection_reason_rc"],
                                                "reason_photo" => $staff["rejection_reason_photo"]
                                            ]), ENT_QUOTES, "UTF-8") ?>)' 
                                            title="View Documents">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </button>
                                    
                                    <?php if ($staff['is_suspended']): ?>
                                        <a href="?unsuspend=<?php echo $staff['id']; ?>" class="btn btn-light btn-sm text-success rounded-3 me-1" onclick="return confirm('Unsuspend this staff member?')" title="Unsuspend Staff">
                                            <i class="bi bi-person-check-fill"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm text-warning rounded-3 me-1" 
                                                onclick="openSuspendModal(<?= $staff['id'] ?>, '<?= htmlspecialchars($staff['name']) ?>')" 
                                                title="Suspend Staff">
                                            <i class="bi bi-person-x-fill"></i>
                                        </button>
                                    <?php endif; ?>

                                    <a href="?delete=<?php echo $staff['id']; ?>" class="btn btn-light btn-sm text-danger rounded-3" onclick="return confirm('Are you sure?')" title="Delete Staff">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Delivery Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-600">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-3" required placeholder="Enter name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600">Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control rounded-3" required placeholder="Enter mobile number">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600">Email Address</label>
                            <input type="email" name="email" class="form-control rounded-3" required placeholder="Enter email address">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600">Bike Number</label>
                            <input type="text" name="bike_number" class="form-control rounded-3" required placeholder="Enter bike number (e.g. GJ-05-AB-1234)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-600">Login Password</label>
                            <input type="password" name="password" class="form-control rounded-3" required placeholder="Create a password">
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_delivery_person" class="btn btn-success rounded-3 px-4">Save Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Documents Modal -->
    <div class="modal fade" id="viewDocsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Staff Documents - <span id="staffDocName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="staff_id" id="docStaffId">
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <!-- Aadhaar -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="small text-muted fw-bold">AADHAAR CARD</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rejected_types[]" value="aadhaar" id="rejectAadhaar">
                                        <label class="form-check-label small text-danger" for="rejectAadhaar">Reject</label>
                                    </div>
                                </div>
                                <div id="docAadhaarCont" class="doc-preview border rounded-3 d-flex align-items-center justify-content-center overflow-hidden bg-light" style="height: 200px;"></div>
                                <input type="text" name="reason_aadhaar" id="reasonAadhaar" class="form-control form-control-sm mt-2 d-none" placeholder="Rejection reason for Aadhaar">
                            </div>
                            <!-- License -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="small text-muted fw-bold">DRIVING LICENSE</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rejected_types[]" value="license" id="rejectLicense">
                                        <label class="form-check-label small text-danger" for="rejectLicense">Reject</label>
                                    </div>
                                </div>
                                <div id="docLicenseCont" class="doc-preview border rounded-3 d-flex align-items-center justify-content-center overflow-hidden bg-light" style="height: 200px;"></div>
                                <input type="text" name="reason_license" id="reasonLicense" class="form-control form-control-sm mt-2 d-none" placeholder="Rejection reason for License">
                            </div>
                            <!-- RC Book -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="small text-muted fw-bold">RC BOOK</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rejected_types[]" value="rc" id="rejectRc">
                                        <label class="form-check-label small text-danger" for="rejectRc">Reject</label>
                                    </div>
                                </div>
                                <div id="docRcCont" class="doc-preview border rounded-3 d-flex align-items-center justify-content-center overflow-hidden bg-light" style="height: 200px;"></div>
                                <input type="text" name="reason_rc" id="reasonRc" class="form-control form-control-sm mt-2 d-none" placeholder="Rejection reason for RC Book">
                            </div>
                            <!-- Profile Photo -->
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="small text-muted fw-bold">PROFILE PHOTO</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rejected_types[]" value="photo" id="rejectPhoto">
                                        <label class="form-check-label small text-danger" for="rejectPhoto">Reject</label>
                                    </div>
                                </div>
                                <div id="docPhotoCont" class="doc-preview border rounded-3 d-flex align-items-center justify-content-center overflow-hidden bg-light" style="height: 200px;"></div>
                                <input type="text" name="reason_photo" id="reasonPhoto" class="form-control form-control-sm mt-2 d-none" placeholder="Rejection reason for Photo">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="reject_docs" id="rejectBtn" class="btn btn-danger rounded-3 px-4 d-none">Save Rejections</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Suspend Staff Modal -->
    <div class="modal fade" id="suspendStaffModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Suspend Delivery Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="staff_id" id="suspendStaffId">
                    <div class="modal-body p-4">
                        <p>You are about to suspend <strong id="suspendStaffName"></strong>. They will be blocked from accessing the delivery partner interface.</p>
                        <div class="mb-3">
                            <label class="form-label fw-600">Reason for Suspension</label>
                            <select name="reason_type" id="reasonType" class="form-select rounded-3 mb-3" required onchange="toggleCustomReason(this.value)">
                                <option value="" selected disabled>Select a reason...</option>
                                <option value="Temporary suspension – issue detected, can be restored">Temporary suspension – issue detected, can be restored</option>
                                <option value="Permanent suspension – serious policy violation">Permanent suspension – serious policy violation</option>
                                <option value="Verification suspension – documents pending/rejected">Verification suspension – documents pending/rejected</option>
                                <option value="Payment-related suspension – KYC/bank issue">Payment-related suspension – KYC/bank issue</option>
                                <option value="Behavioral suspension – complaints, cancellations, fraud signals">Behavioral suspension – complaints, cancellations, fraud signals</option>
                                <option value="Other">Other (Please specify)</option>
                            </select>
                            <textarea name="custom_reason" id="customReason" class="form-control rounded-3 d-none" rows="3" placeholder="Please specify the reason..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="suspend_staff" class="btn btn-warning rounded-3 px-4">Suspend Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const docsModal = new bootstrap.Modal(document.getElementById('viewDocsModal'));
        const suspendModal = new bootstrap.Modal(document.getElementById('suspendStaffModal'));
        
        function openSuspendModal(id, name) {
            document.getElementById('suspendStaffId').value = id;
            document.getElementById('suspendStaffName').innerText = name;
            // Reset modal state
            document.getElementById('reasonType').value = '';
            document.getElementById('customReason').classList.add('d-none');
            document.getElementById('customReason').required = false;
            suspendModal.show();
        }

        function toggleCustomReason(val) {
            const customArea = document.getElementById('customReason');
            if (val === 'Other') {
                customArea.classList.remove('d-none');
                customArea.required = true;
                customArea.focus();
            } else {
                customArea.classList.add('d-none');
                customArea.required = false;
            }
        }

        function viewDocs(data) {
            document.getElementById('staffDocName').innerText = data.name;
            document.getElementById('docStaffId').value = data.id;
            
            // Reset checkboxes and reasons
            const types = ['aadhaar', 'license', 'rc', 'photo'];
            const rejectedArray = data.rejected_docs ? data.rejected_docs.split(',') : [];
            
            types.forEach(type => {
                const cb = document.getElementById('reject' + type.charAt(0).toUpperCase() + type.slice(1));
                const reasonInput = document.getElementById('reason' + type.charAt(0).toUpperCase() + type.slice(1));
                
                cb.checked = rejectedArray.includes(type);
                reasonInput.value = data['reason_' + type] || '';
                
                if (cb.checked) {
                    reasonInput.classList.remove('d-none');
                } else {
                    reasonInput.classList.add('d-none');
                }
                
                // Add event listener for real-time toggle
                cb.onchange = function() {
                    if (this.checked) {
                        reasonInput.classList.remove('d-none');
                        reasonInput.focus();
                    } else {
                        reasonInput.classList.add('d-none');
                    }
                    toggleRejectButton();
                };
            });

            function toggleRejectButton() {
                const anyChecked = types.some(type => document.getElementById('reject' + type.charAt(0).toUpperCase() + type.slice(1)).checked);
                document.getElementById('rejectBtn').classList.toggle('d-none', !anyChecked);
            }
            
            toggleRejectButton();

            const renderDoc = (containerId, path) => {
                const container = document.getElementById(containerId);
                if (!path) {
                    container.innerHTML = '<span class="text-muted small">Not Uploaded</span>';
                    return;
                }
                
                const fullPath = '../' + path.trim();
                const isImg = path.trim().match(/\.(jpg|jpeg|png|gif|webp)/i);
                
                if (isImg) {
                    container.innerHTML = `<img src="${fullPath}" class="w-100 h-100 object-fit-contain cursor-pointer" onclick="window.open('${fullPath}')">`;
                } else {
                    container.innerHTML = `<a href="${fullPath}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-pdf me-2"></i>View PDF</a>`;
                }
            };
            
            renderDoc('docAadhaarCont', data.aadhaar);
            renderDoc('docLicenseCont', data.license);
            renderDoc('docRcCont', data.rc);
            renderDoc('docPhotoCont', data.photo);
            
            docsModal.show();
        }

        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        <?php if(!empty($chartLabels)): ?>
        const ctx = document.getElementById('topDeliveryChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.5)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Delivered Orders',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: gradient,
                    borderColor: '#10b981',
                    borderWidth: 2,
                    borderRadius: 4,
                    barPercentage: 0.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (context) => ' ' + context.raw + ' Orders'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5], color: '#e2e8f0' },
                        ticks: { stepSize: 1, font: { family: 'Inter' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', weight: '600' } }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
