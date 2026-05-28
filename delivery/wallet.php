<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['delivery_partner_id'];
$partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $id")->fetch();

// Handle Add Funds
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_funds') {
    $amount = (float)$_POST['amount'];
    if ($amount > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE delivery_persons SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$amount, $id]);
            $pdo->prepare("INSERT INTO delivery_earnings (delivery_person_id, amount, type, description) VALUES (?, ?, 'Credit', ?)")
                ->execute([$id, $amount, "Money added to wallet via App"]);
            $pdo->commit();
            $success_msg = "₹" . number_format($amount, 2) . " added to your wallet successfully!";
            // Refresh partner data
            $partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $id")->fetch();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Failed to add funds. Please try again.";
        }
    }
}

// Fetch Wallet Transactions
$transactions = $pdo->query("SELECT * FROM delivery_earnings WHERE delivery_person_id = $id ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Earnings - Flash Delivery</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8fafc; 
            font-family: 'Outfit', sans-serif; 
            padding-bottom: 90px; 
            color: #1f2937;
        }
        .balance-card { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
            padding: 3rem 2rem; 
            border-radius: 0 0 40px 40px; 
            margin-bottom: 2rem; 
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        .balance-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .tx-card { 
            background: white; 
            border-radius: 20px; 
            padding: 18px; 
            margin-bottom: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 2px 4px -1px rgba(0,0,0,0.01);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s;
        }
        .tx-card:active {
            transform: scale(0.98);
        }
        .bottom-nav { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            width: 100%; 
            background: rgba(255, 255, 255, 0.9); 
            backdrop-filter: blur(10px);
            padding: 12px 10px; 
            border-top: 1px solid #f1f5f9; 
            display: flex; 
            justify-content: space-around; 
            z-index: 1000; 
            box-shadow: 0 -10px 15px -3px rgba(0,0,0,0.05); 
        }
        .nav-item { 
            text-align: center; 
            color: #94a3b8; 
            text-decoration: none; 
            font-size: 0.75rem; 
            font-weight: 600;
            transition: all 0.3s;
        }
        .nav-item.active { color: #10b981; }
        .nav-item i { 
            display: block; 
            font-size: 1.5rem; 
            margin-bottom: 4px; 
            transition: all 0.3s;
        }
        .nav-item.active i {
            transform: translateY(-2px);
        }
        .btn-withdraw {
            background: white;
            color: #10b981;
            border: none;
            border-radius: 15px;
            padding: 12px 25px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .btn-withdraw:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
            background: #f8fafc;
        }
        .section-title {
            font-weight: 800;
            color: #374151;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1.25rem;
            padding-left: 5px;
        }
        .modal-content {
            border-radius: 30px;
            border: none;
        }
        .form-control {
            border-radius: 15px;
            padding: 12px 18px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            font-weight: 500;
        }
        .form-control:focus {
            background: #fff;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
    </style>
</head>
<body>

<div class="balance-card text-center">
    <small class="opacity-75 text-uppercase fw-bold letter-spacing-1">Available Balance</small>
    <h1 class="display-3 fw-bold mb-3">₹<?= number_format($partner['wallet_balance'], 2) ?></h1>
    <div class="d-flex gap-2 justify-content-center">
        <button class="btn btn-withdraw" data-bs-toggle="modal" data-bs-target="#withdrawModal">
            <i class="bi bi-arrow-up-right-circle-fill me-2"></i>Withdraw
        </button>
        <button class="btn btn-withdraw" data-bs-toggle="modal" data-bs-target="#addFundsModal" style="background: rgba(255,255,255,0.2); color: white;">
            <i class="bi bi-plus-circle-fill me-2"></i>Add Money
        </button>
    </div>
</div>

<div class="container">
    <div id="alertContainer"></div>
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success rounded-4 fw-bold mb-3 border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
        </div>
    <?php endif; ?>
    <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger rounded-4 fw-bold mb-3 border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_msg ?>
        </div>
    <?php endif; ?>

    <h6 class="section-title">Transaction History</h6>
    
    <?php if(empty($transactions)): ?>
        <div class="text-center py-5 text-muted">
            <div class="bg-light rounded-circle d-inline-flex p-4 mb-3">
                <i class="bi bi-cash-stack display-4 opacity-25"></i>
            </div>
            <p class="fw-bold">No transactions yet.</p>
            <small>Your earnings will appear here.</small>
        </div>
    <?php else: ?>
        <?php foreach($transactions as $tx): ?>
        <div class="tx-card d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="<?= $tx['type'] == 'Credit' ? 'bg-success text-success' : 'bg-danger text-danger' ?> bg-opacity-10 rounded-circle p-3 me-3">
                    <i class="bi <?= $tx['type'] == 'Credit' ? 'bi-plus-lg' : 'bi-dash-lg' ?> fs-5"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($tx['description']) ?></h6>
                    <small class="text-muted fw-medium"><?= date('d M, h:i A', strtotime($tx['created_at'])) ?></small>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold fs-5 <?= $tx['type'] == 'Credit' ? 'text-success' : 'text-danger' ?>">
                    <?= $tx['type'] == 'Credit' ? '+' : '-' ?>₹<?= number_format($tx['amount'], 2) ?>
                </div>
                <small class="badge bg-light text-dark rounded-pill fw-normal px-2">Completed</small>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Funds Modal -->
<div class="modal fade" id="addFundsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-800">Add Money to Wallet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_funds">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label small text-secondary fw-bold text-uppercase">Select Amount</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-outline-success rounded-pill px-3 py-1 fw-bold" onclick="document.getElementById('addAmount').value=100">₹100</button>
                            <button type="button" class="btn btn-outline-success rounded-pill px-3 py-1 fw-bold" onclick="document.getElementById('addAmount').value=500">₹500</button>
                            <button type="button" class="btn btn-outline-success rounded-pill px-3 py-1 fw-bold" onclick="document.getElementById('addAmount').value=1000">₹1000</button>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-bold rounded-start-4 ps-3">₹</span>
                            <input type="number" name="amount" id="addAmount" class="form-control bg-light border-0 fw-bold fs-3 py-3" placeholder="0.00" min="1" step="1" required>
                        </div>
                    </div>

                    <div class="bg-light rounded-4 p-4 border border-dashed text-center">
                        <div class="bg-white d-inline-block p-3 rounded-4 shadow-sm mb-3">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi://pay?pa=grocery@upi&pn=Quick%20mart&am=0" class="img-fluid" style="width: 150px; height: 150px;">
                        </div>
                        <p class="text-muted smaller mb-0">Scan the QR code to pay via any UPI app. Funds will be credited instantly after payment.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light rounded-4 px-4 py-3 fw-bold flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-4 px-4 py-3 fw-bold flex-grow-1">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-800" id="withdrawTitle">Withdraw Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Success/Processing View -->
            <div id="processingView" class="modal-body p-5 text-center" style="display: none;">
                <div class="spinner-border text-success mb-4" style="width: 3rem; height: 3rem;" role="status" id="loadingSpinner">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="check-circle mx-auto mb-4" style="display: none; width: 70px; height: 70px; background: #10b981; border-radius: 50%; align-items: center; justify-content: center; color: white; font-size: 2.5rem; box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);" id="successIcon">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h4 class="fw-800 mb-2" id="statusTitle">Processing...</h4>
                <p class="text-muted fw-medium" id="statusMessage">Please wait while we verify your request.</p>
            </div>

            <form id="withdrawForm" method="POST" onsubmit="handleWithdraw(event)">
                <input type="hidden" name="action" value="withdraw">
                <div class="modal-body p-4" id="withdrawBody">
                    <div class="mb-4">
                        <label class="form-label small text-secondary fw-bold text-uppercase">Enter Amount</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 fw-bold rounded-start-4 ps-3">₹</span>
                            <input type="number" name="amount" class="form-control bg-light border-0 fw-bold fs-3 py-3" placeholder="0.00" min="1" max="<?= $partner['wallet_balance'] ?>" step="0.01" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-secondary fw-bold text-uppercase">Transfer Method</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="method" id="methodUPI" value="UPI" checked autocomplete="off">
                                <label class="btn btn-outline-success w-100 rounded-4 py-3 fw-bold" for="methodUPI">
                                    <i class="bi bi-phone fs-4 d-block mb-1"></i>UPI
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="method" id="methodBank" value="Bank" autocomplete="off">
                                <label class="btn btn-outline-success w-100 rounded-4 py-3 fw-bold" for="methodBank">
                                    <i class="bi bi-bank fs-4 d-block mb-1"></i>Bank
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="upiField">
                        <label class="form-label small text-secondary fw-bold text-uppercase">UPI ID</label>
                        <input type="text" name="details_upi" id="details_upi" class="form-control py-3" placeholder="e.g. 9876543210@ybl">
                    </div>

                    <div id="bankFields" style="display: none;">
                        <label class="form-label small text-secondary fw-bold text-uppercase">Bank Account Details</label>
                        <textarea name="details_bank" id="details_bank" class="form-control" rows="3" placeholder="A/C No: 1234567890&#10;IFSC: SBIN0001234&#10;Name: Account Holder"></textarea>
                    </div>

                    <input type="hidden" name="details" id="final_details">
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0" id="withdrawFooter">
                    <button type="button" class="btn btn-light rounded-4 px-4 py-3 fw-bold flex-grow-1" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-4 px-4 py-3 fw-bold flex-grow-1">Transfer Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const methodUPI = document.getElementById('methodUPI');
    const methodBank = document.getElementById('methodBank');
    const upiField = document.getElementById('upiField');
    const bankFields = document.getElementById('bankFields');

    methodUPI.addEventListener('change', () => {
        upiField.style.display = 'block';
        bankFields.style.display = 'none';
        document.getElementById('details_upi').required = true;
        document.getElementById('details_bank').required = false;
    });

    methodBank.addEventListener('change', () => {
        upiField.style.display = 'none';
        bankFields.style.display = 'block';
        document.getElementById('details_upi').required = false;
        document.getElementById('details_bank').required = true;
    });

    function prepareDetails() {
        if(methodUPI.checked) {
            document.getElementById('final_details').value = document.getElementById('details_upi').value;
        } else {
            document.getElementById('final_details').value = document.getElementById('details_bank').value.replace(/\n/g, ", ");
        }
    }

    async function handleWithdraw(event) {
        event.preventDefault();
        prepareDetails();
        
        const form = document.getElementById('withdrawForm');
        const formData = new FormData(form);
        
        // UI Transition to processing
        document.getElementById('withdrawBody').style.display = 'none';
        document.getElementById('withdrawFooter').style.display = 'none';
        document.getElementById('withdrawTitle').style.display = 'none';
        document.getElementById('processingView').style.display = 'block';
        
        try {
            const response = await fetch('api/withdraw.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if(result.success) {
                // Wait 5 seconds as requested
                document.getElementById('statusTitle').innerText = "Processing Successful!";
                document.getElementById('statusMessage').innerText = "Withdrawal verified. Finalizing in 5 seconds...";
                
                setTimeout(() => {
                    // Success View
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('successIcon').style.display = 'flex';
                    document.getElementById('statusTitle').innerText = "Withdrawal Success!";
                    document.getElementById('statusMessage').innerText = "Funds have been transferred to your account.";
                    
                    // Show alert in main page
                    document.getElementById('alertContainer').innerHTML = `
                        <div class="alert alert-success rounded-4 fw-bold mb-3">
                            <i class="bi bi-check-circle me-2"></i>₹${result.amount} Withdrawal Successful! Check your profile for details.
                        </div>
                    `;
                    
                    // Reload page after a short delay to update balance
                    setTimeout(() => window.location.reload(), 2000);
                }, 5000);
            } else {
                // Show Error
                alert(result.message);
                window.location.reload();
            }
        } catch (error) {
            console.error("Error:", error);
            alert("Something went wrong. Please try again.");
            window.location.reload();
        }
    }
</script>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="index.php" class="nav-item">
        <i class="bi bi-house-door"></i> Home
    </a>
    <a href="wallet.php" class="nav-item active">
        <i class="bi bi-wallet2-fill"></i> Wallet
    </a>
    <a href="history.php" class="nav-item">
        <i class="bi bi-clock-history"></i> History
    </a>
    <a href="profile.php" class="nav-item">
        <i class="bi bi-person"></i> Profile
    </a>
</div>

</body>
</html>
