<?php
require_once '../config/db.php';

if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$user_id = $_GET['user_id'] ?? null;
if (!$user_id) {
    header("Location: users.php");
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, w.balance, w.id as wallet_id FROM users u LEFT JOIN wallets w ON w.user_id = u.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php");
    exit;
}

// Ensure wallet exists
if (!$user['wallet_id']) {
    $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
    header("Location: wallet_manage.php?user_id=$user_id");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $type = $_POST['type']; // 'Credit' or 'Debit'
    $description = $_POST['description'] ?: "Manual $type by Admin";
    
    try {
        $pdo->beginTransaction();
        
        if ($type === 'Credit') {
            $new_balance = $user['balance'] + $amount;
        } else {
            if ($user['balance'] < $amount) {
                throw new Exception("Insufficient balance for debit.");
            }
            $new_balance = $user['balance'] - $amount;
        }
        
        $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_balance, $user['wallet_id']]);
        $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, status) VALUES (?, ?, ?, ?, 'Completed')")
            ->execute([$user['wallet_id'], $type, $amount, $description]);
            
        $pdo->commit();
        $success = "Wallet updated successfully! New balance: ₹" . number_format($new_balance, 2);
        $user['balance'] = $new_balance;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch recent transactions for this user
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['wallet_id']]);
$transactions = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Wallet - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .card { border: none; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="p-4">
    <div class="container" style="max-width: 800px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="users.php" class="btn btn-light rounded-pill px-3 shadow-sm">
                <i class="bi bi-arrow-left me-2"></i>Back to Customers
            </a>
            <h4 class="fw-bold mb-0">Manage Wallet</h4>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 rounded-4 mb-4 shadow-sm"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 mb-4 shadow-sm"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Current Balance Card -->
            <div class="col-md-5">
                <div class="card bg-primary text-white p-4 h-100 shadow-lg border-0">
                    <p class="opacity-75 mb-1 text-uppercase small fw-bold tracking-wider">Current Balance</p>
                    <h1 class="fw-800 mb-0">₹<?php echo number_format($user['balance'], 2); ?></h1>
                    <div class="mt-4 pt-4 border-top border-white border-opacity-10">
                        <p class="small mb-1 fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p class="extra-small opacity-75 mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Update Balance Form -->
            <div class="col-md-7">
                <div class="card p-4">
                    <h6 class="fw-bold mb-3">Add/Deduct Balance</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Transaction Type</label>
                            <div class="d-flex gap-2">
                                <input type="radio" class="btn-check" name="type" id="type_credit" value="Credit" checked>
                                <label class="btn btn-outline-success flex-grow-1 py-2 fw-bold rounded-3" for="type_credit">
                                    <i class="bi bi-plus-circle me-1"></i>Credit
                                </label>
                                <input type="radio" class="btn-check" name="type" id="type_debit" value="Debit">
                                <label class="btn btn-outline-danger flex-grow-1 py-2 fw-bold rounded-3" for="type_debit">
                                    <i class="bi bi-dash-circle me-1"></i>Debit
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount (₹)</label>
                            <input type="number" name="amount" class="form-control form-control-lg bg-light border-0 fw-bold" placeholder="0.00" step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Description (Optional)</label>
                            <input type="text" name="description" class="form-control bg-light border-0" placeholder="e.g. Refund for Order #123">
                        </div>
                        <button type="submit" class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow-sm">
                            Update Wallet Balance
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-12">
                <div class="card overflow-hidden">
                    <div class="card-header bg-white border-0 py-3 px-4">
                        <h6 class="fw-bold mb-0">Recent Transactions</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Description</th>
                                    <th class="text-end pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="ps-4 small"><?php echo date('M d, Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $t['type'] === 'Credit' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $t['type'] === 'Credit' ? 'success' : 'danger'; ?> rounded-pill px-3">
                                            <?php echo $t['type']; ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">₹<?php echo number_format($t['amount'], 2); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($t['description']); ?></td>
                                    <td class="text-end pe-4">
                                        <span class="badge bg-success rounded-pill px-3">Completed</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted small">No transactions found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
