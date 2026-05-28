<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch wallet balance
$stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
$stmt->execute([$user_id]);
$wallet = $stmt->fetch();

if (!$wallet) {
    $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
}

// Fetch transactions
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC");
$stmt->execute([$wallet['id']]);
$transactions = $stmt->fetchAll();

// Fetch scratch cards
$stmt = $pdo->prepare("SELECT * FROM scratch_cards WHERE user_id = ? AND is_scratched = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$scratch_cards = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="container">
    <div class="wallet-main-wrapper">
        <!-- Alert Messages -->
    <div class="alert-container">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-5 me-3"></i>
                    <div class="small"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-3"></i>
                    <div class="small"><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 flex-grow-1 overflow-hidden mt-1">
        <!-- Left Column: Balance & Rewards -->
        <div class="col-lg-4 d-flex flex-column h-100">
            <div class="wallet-scroll-container pe-2">
                <!-- Wallet Header Inside Scroll -->
                <div class="d-flex align-items-center mb-4 animate__animated animate__fadeIn">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-4 p-2 me-3">
                        <i class="bi bi-wallet2 fs-3"></i>
                    </div>
                    <div>
                        <h3 class="fw-800 mb-0">My Wallet</h3>
                        <p class="text-muted mb-0 small">Manage your digital funds</p>
                    </div>
                </div>
                <!-- Wallet Balance Card -->
                <div class="card border-0 rounded-5 shadow-lg overflow-hidden position-relative mb-4" style="background: linear-gradient(135deg, #4158D0 0%, #C850C0 46%, #FFCC70 100%); min-height: 240px;">
                    <div class="card-body p-4 d-flex flex-column justify-content-between position-relative z-1 text-white">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-white-50 fw-600 text-uppercase tracking-wider small">Available Balance</span>
                                <button class="btn btn-link text-white p-0 shadow-none" id="toggleBalance">
                                    <i class="bi bi-eye fs-5"></i>
                                </button>
                            </div>
                            <h1 class="fw-800 mb-0 d-flex align-items-center">
                                <span class="fs-4 me-2">₹</span>
                                <span id="walletBalance"><?php echo number_format($wallet['balance'], 2); ?></span>
                                <span id="hiddenBalance" class="d-none">••••••</span>
                            </h1>
                            <p class="text-white-50 mt-2 smaller">
                                <i class="bi bi-shield-check me-1"></i> Safe & Secure Digital Assets
                            </p>
                        </div>
                        
                        <button class="btn btn-white w-100 rounded-pill py-2 fw-800 text-primary shadow-sm mt-3 hover-lift" data-bs-toggle="modal" data-bs-target="#addFundsModal">
                            <i class="bi bi-plus-circle-fill me-2"></i> Add Funds
                        </button>
                    </div>
                    <div class="position-absolute bottom-0 end-0 p-3 opacity-10">
                        <i class="bi bi-cpu fs-1"></i>
                    </div>
                </div>

                <!-- Total Cashback -->
                <?php 
                $total_cashback = 0;
                foreach ($transactions as $t) {
                    if (stripos($t['description'], 'Cashback') !== false) {
                        if ($t['type'] === 'Credit') $total_cashback += $t['amount'];
                        elseif ($t['type'] === 'Debit') $total_cashback -= $t['amount'];
                    }
                }
                ?>
                <div class="card border-0 rounded-4 shadow-sm mb-4 p-4" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3 me-3">
                            <i class="bi bi-trophy-fill fs-4 text-white"></i>
                        </div>
                        <div>
                            <p class="mb-0 opacity-75 fw-600 text-uppercase small tracking-wider">Total Cashback</p>
                            <h4 class="fw-800 mb-0">₹<?php echo number_format($total_cashback, 2); ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Rewards Section -->
                <div id="rewardsSection" class="card border-0 rounded-4 shadow-sm mb-4 p-4 <?php echo count($scratch_cards) == 0 ? 'd-none' : ''; ?>">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-3">
                            <i class="bi bi-gift-fill"></i>
                        </div>
                        <h6 class="fw-800 mb-0">My Rewards</h6>
                    </div>
                    <div class="row g-3" id="scratchCardsContainer">
                        <?php foreach ($scratch_cards as $card): ?>
                        <div class="col-6 scratch-card-item" id="card_item_<?php echo $card['id']; ?>">
                            <div class="scratch-card-thumb p-3 border border-dashed rounded-4 text-center cursor-pointer hover-lift" 
                                 onclick="openScratchModal(<?php echo $card['id']; ?>, <?php echo $card['amount']; ?>)">
                                <div class="mb-2">
                                    <i class="bi bi-stars text-warning fs-3"></i>
                                </div>
                                <span class="smaller fw-700 text-uppercase tracking-tighter">Tap to Scratch</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Security -->
                <div class="card border-0 rounded-4 shadow-sm p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 p-2 me-3">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h6 class="fw-800 mb-0">Security</h6>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Protection</span>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-600">Active</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Column -->
        <div class="col-lg-8 h-100">
            <div class="card border-0 rounded-4 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-800 mb-1">Recent Activity</h5>
                        <p class="text-muted small mb-0">Your latest wallet movements</p>
                    </div>
                    <div class="bg-light rounded-pill px-3 py-1 text-muted small fw-600">
                        <i class="bi bi-clock-history me-1"></i> <?php echo count($transactions); ?> Records
                    </div>
                </div>
                <div class="card-body p-0 wallet-scroll-container">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <img src="assets/images/empty-wallet.svg" class="mb-3" style="width: 120px; opacity: 0.5;">
                            <h6 class="text-muted">No transactions yet</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light sticky-top" style="top: 0; z-index: 10;">
                                    <tr>
                                        <th class="border-0 px-4 py-3 text-muted small fw-600 text-uppercase">Transaction</th>
                                        <th class="border-0 py-3 text-muted small fw-600 text-uppercase">Date</th>
                                        <th class="border-0 px-4 py-3 text-muted small fw-600 text-uppercase text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $t): ?>
                                        <tr>
                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="rounded-circle p-2 me-3 d-flex align-items-center justify-content-center <?php echo $t['type'] == 'Credit' ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?>" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-<?php echo $t['type'] == 'Credit' ? 'plus' : 'dash'; ?> fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-700"><?php echo $t['description']; ?></h6>
                                                        <?php if ($t['order_id']): ?>
                                                            <span class="badge bg-light text-dark border fw-600 smaller">#<?php echo $t['order_id']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="text-muted small fw-600"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                                                <div class="text-muted smaller"><?php echo date('h:i A', strtotime($t['created_at'])); ?></div>
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <h6 class="mb-0 fw-800 <?php echo $t['type'] == 'Credit' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $t['type'] == 'Credit' ? '+' : '-'; ?>₹<?php echo number_format($t['amount'], 2); ?>
                                                </h6>
                                                <span class="badge <?php echo $t['status'] == 'Completed' ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning'; ?> rounded-pill px-2 py-1 smaller">
                                                    <?php echo $t['status']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- MODALS -->

<!-- Add Funds Modal -->
<div class="modal fade" id="addFundsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable wallet-topup-dialog">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 p-4 pb-0">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3">
                        <i class="bi bi-wallet2 fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-800 mb-0">Add Money to Wallet</h5>
                        <p class="text-muted smaller mb-0">Secure & Instant Top-up</p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <form id="addFundsForm" action="wallet_action.php" method="POST">
                <input type="hidden" name="add_funds" value="1">
                <div class="modal-body p-0 position-relative">
                    <!-- Processing Overlay -->
                    <div id="paymentProcessingView" class="position-absolute top-0 start-0 w-100 h-100 bg-white d-none flex-column align-items-center justify-content-center z-3 animate__animated animate__fadeIn" style="border-radius: 1.25rem;">
                        <div class="payment-loader mb-4">
                            <div class="spinner-outer">
                                <div class="spinner-inner"></div>
                                <i class="bi bi-shield-lock-fill lock-icon"></i>
                            </div>
                        </div>
                        <h4 class="fw-800 mb-2">Processing Payment</h4>
                        <div class="progress w-50 rounded-pill" style="height: 6px;">
                            <div id="paymentProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="mt-3 smaller text-muted fw-600 tracking-wider text-uppercase" id="loaderStatus">Connecting...</div>
                    </div>

                    <div id="addFundsFormContent" class="p-4">
                        <div class="row g-4">
                            <!-- Left Column: Amount & Method -->
                            <div class="col-lg-6">
                                <div class="mb-3 p-3 rounded-4 border bg-white shadow-sm">
                                    <label class="form-label text-muted small fw-700 text-uppercase tracking-wider mb-2">Select Amount</label>
                                    <div class="input-group input-group-lg mb-3">
                                        <span class="input-group-text bg-light border-0 text-muted">₹</span>
                                        <input type="number" name="amount" id="fundAmount" class="form-control bg-light border-0 fw-800" placeholder="0" required>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach([100, 500, 1000, 2000] as $amt): ?>
                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 fw-600 preset-amount" data-amount="<?php echo $amt; ?>">₹<?php echo $amt; ?></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-0 p-3 rounded-4 border bg-white shadow-sm">
                                    <label class="form-label text-muted small fw-700 text-uppercase tracking-wider mb-2">Payment Method</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="radio" class="btn-check payment-method-radio" name="payment_method" id="pay_upi" value="UPI" checked>
                                            <label class="payment-method-card" for="pay_upi">
                                                <div class="icon-box mb-2"><i class="bi bi-qr-code fs-5"></i></div>
                                                <span class="smaller fw-700">UPI</span>
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="radio" class="btn-check payment-method-radio" name="payment_method" id="pay_debit" value="Debit Card">
                                            <label class="payment-method-card" for="pay_debit">
                                                <div class="icon-box mb-2"><i class="bi bi-credit-card fs-5"></i></div>
                                                <span class="smaller fw-700">Debit Card</span>
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="radio" class="btn-check payment-method-radio" name="payment_method" id="pay_credit" value="Credit Card">
                                            <label class="payment-method-card" for="pay_credit">
                                                <div class="icon-box mb-2"><i class="bi bi-credit-card-2-front fs-5"></i></div>
                                                <span class="smaller fw-700">Credit Card</span>
                                            </label>
                                        </div>
                                        <div class="col-6">
                                            <input type="radio" class="btn-check payment-method-radio" name="payment_method" id="pay_net" value="Net Banking">
                                            <label class="payment-method-card" for="pay_net">
                                                <div class="icon-box mb-2"><i class="bi bi-bank fs-5"></i></div>
                                                <span class="smaller fw-700">Net Banking</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-lg-6">
                                <div id="upiDetailsSection" class="payment-detail-panel h-100 bg-white rounded-4 p-3 border">
                                    <div class="text-center mb-3">
                                        <img src="" id="upiQR" class="img-fluid border p-2 rounded" style="width: 140px; height: 140px;">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small fw-700">YOUR UPI ID</label>
                                        <input type="text" name="upi_id" id="upiUserIdInput" class="form-control bg-light border-0" placeholder="yourname@upi">
                                    </div>
                                    <div class="mb-2">
                                        <label class="smaller text-muted fw-600">MERCHANT UPI ID</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control bg-light border-0 fw-700" value="grocery@upi" readonly id="upiId">
                                            <button class="btn btn-outline-primary border-0" type="button" onclick="copyUPI(event)">Copy</button>
                                        </div>
                                    </div>
                                </div>

                                <div id="cardDetailsSection" class="payment-detail-panel d-none h-100 bg-white rounded-4 p-3 border">
                                    <!-- Flip Card Visual -->
                                    <div class="credit-card-container mb-3" id="cardVisual">
                                        <div class="card-inner">
                                            <div class="card-front">
                                                <div class="card-type-logo">
                                                    <i class="bi bi-credit-card-2-front"></i>
                                                </div>
                                                <div class="card-chip"></div>
                                                <div class="card-number-display" id="cardNoDisplay">#### #### #### ####</div>
                                                <div class="card-holder-info">
                                                    <div>
                                                        <span class="info-label">Card Holder</span>
                                                        <span class="info-value" id="cardNameDisplay">FULL NAME</span>
                                                    </div>
                                                    <div>
                                                        <span class="info-label">Expires</span>
                                                        <span class="info-value" id="cardExpiryDisplay">MM/YY</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-back">
                                                <div class="card-magnetic-strip"></div>
                                                <div class="mt-4 px-3">
                                                    <span class="info-label text-white">CVV</span>
                                                    <div class="card-signature-area">
                                                        <span class="cvv-display" id="cardCvvDisplay">***</span>
                                                    </div>
                                                </div>
                                                <div class="text-end p-3 opacity-50">
                                                    <i class="bi bi-shield-check fs-2"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small fw-700">HOLDER NAME</label>
                                        <input type="text" name="card_name" id="cardHolderInput" class="form-control bg-light border-0" placeholder="e.g. JOHN DOE">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-700">CARD NUMBER</label>
                                        <input type="text" name="card_number" id="cardNumberInput" class="form-control bg-light border-0" placeholder="0000 0000 0000 0000">
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small fw-700">EXPIRY</label>
                                            <input type="text" name="card_expiry" id="cardExpiryInput" class="form-control bg-light border-0" placeholder="MM/YY">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-700">CVV</label>
                                            <input type="password" name="card_cvv" id="cardCvvInput" class="form-control bg-light border-0" placeholder="***">
                                        </div>
                                    </div>
                                </div>

                                <div id="netBankingSection" class="payment-detail-panel d-none h-100 bg-white rounded-4 p-3 border">
                                    <h6 class="mb-3 fw-700">Select Bank</h6>
                                    <select name="bank_name" class="form-select bg-light border-0">
                                        <option value="SBI">SBI</option>
                                        <option value="HDFC">HDFC</option>
                                        <option value="ICICI">ICICI</option>
                                        <option value="Axis">Axis</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" id="submitBtn" class="btn btn-primary w-100 rounded-pill py-3 fw-800 shadow-sm">Proceed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scratch Card Modal -->
<div class="modal fade" id="scratchCardModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-800 mb-0">Scratch & Win!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="scratch-container position-relative mx-auto mb-4" style="width: 240px; height: 240px;">
                    <div class="reward-content d-flex flex-column align-items-center justify-content-center h-100 w-100 bg-light rounded-4 border">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 mb-2"><i class="bi bi-cash-stack fs-1"></i></div>
                        <h2 class="fw-800 mb-0">₹<span id="rewardAmountDisplay">0</span></h2>
                        <p class="text-muted small">Cashback Earned!</p>
                        <button id="claimBtn" class="btn btn-success rounded-pill px-4 mt-2 d-none" onclick="claimReward()">Claim Now</button>
                    </div>
                    <canvas id="scratchCanvas" class="position-absolute top-0 start-0 rounded-4" width="240" height="240" style="touch-action: none;"></canvas>
                </div>
                <p id="scratchInstruction" class="text-muted small mb-0">Scratch to reveal your reward!</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Flip Card Animation */
.credit-card-container {
    perspective: 1000px;
    width: 100%;
    max-width: 300px;
    margin: 0 auto 15px auto;
    height: 180px;
}

.card-inner {
    position: relative;
    width: 100%;
    height: 100%;
    text-align: left;
    transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
}

.credit-card-container.flipped .card-inner {
    transform: rotateY(180deg);
}

.card-front, .card-back {
    position: absolute;
    width: 100%;
    height: 100%;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
    border-radius: 15px;
    padding: 20px;
    color: white;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.card-front {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
}

.card-back {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    transform: rotateY(180deg);
    padding: 0;
}

.card-chip {
    width: 45px;
    height: 35px;
    background: linear-gradient(135deg, #fcd34d 0%, #fbbf24 100%);
    border-radius: 8px;
    margin-bottom: 25px;
    position: relative;
}

.card-chip::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70%;
    height: 70%;
    border: 1px solid rgba(0,0,0,0.1);
    border-radius: 4px;
}

.card-number-display {
    font-family: 'Courier New', Courier, monospace;
    font-size: 1.25rem;
    letter-spacing: 2px;
    margin-bottom: 20px;
    display: block;
    height: 24px;
}

.card-holder-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.info-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    opacity: 0.7;
    display: block;
    margin-bottom: 2px;
}

.info-value {
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    display: block;
    height: 18px;
}

.card-magnetic-strip {
    width: 100%;
    height: 40px;
    background: #000;
    margin-top: 25px;
}

.card-signature-area {
    width: 80%;
    height: 35px;
    background: #fff;
    margin: 15px auto 0 20px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 10px;
    border-radius: 4px;
}

.cvv-display {
    color: #000;
    font-style: italic;
    font-weight: bold;
    letter-spacing: 1px;
}

.card-type-logo {
    position: absolute;
    top: 20px;
    right: 20px;
    font-size: 1.5rem;
    opacity: 0.8;
}

/* Clean & Stable Layout */
body {
    background-color: #f8fafc;
    overflow-x: hidden;
    padding-top: 80px; /* Consistent spacing */
}

.wallet-main-wrapper {
    padding-bottom: 20px;
    min-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
}

@media (min-width: 992px) {
    .wallet-scroll-container {
        max-height: calc(100vh - 220px);
        overflow-y: auto;
        padding-right: 10px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    .wallet-scroll-container::-webkit-scrollbar {
        width: 6px;
    }
    
    .wallet-scroll-container::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 10px;
    }
}

.alert-container {
    margin-bottom: 20px;
}

.scratch-card-thumb { transition: all 0.3s; background: #f8fafc; cursor: pointer; }
.scratch-card-thumb:hover { background: #fef3c7; border-color: #f59e0b !important; transform: translateY(-2px); }
.fw-800 { font-weight: 800; }
.fw-700 { font-weight: 700; }
.fw-600 { font-weight: 600; }
.smaller { font-size: 0.75rem; }

.wallet-topup-dialog { 
    max-width: 850px; 
    margin-top: 40px !important; 
    margin-left: auto; 
    margin-right: auto; 
}
.payment-detail-panel { min-height: 300px; display: flex; flex-direction: column; justify-content: center; }
.payment-method-card { 
    display: flex; flex-direction: column; align-items: center; padding: 0.75rem; 
    border: 1px solid #eef2f7; border-radius: 1rem; cursor: pointer; transition: all 0.2s; 
}
.payment-method-radio:checked + .payment-method-card { border-color: #10b981; background: #f0fdf4; }
.icon-box { width: 32px; height: 32px; background: #f8fafc; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; }

.payment-loader { width: 60px; height: 60px; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

#addFundsModal .modal-body { max-height: 60vh; overflow-y: auto; overflow-x: hidden; }
#addFundsModal .modal-footer { position: sticky; bottom: 0; background: #fff; z-index: 10; border-top: 1px solid #f1f5f9; padding: 0.5rem 1rem; }

#scratchCardModal .modal-dialog {
    margin: 1.75rem auto;
}
</style>

<script>
let currentCardId = null;
let isDrawing = false;

function openScratchModal(id, amount) {
    currentCardId = id;
    document.getElementById('rewardAmountDisplay').textContent = amount;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('scratchCardModal'));
    modal.show();
    setTimeout(initScratchCard, 400);
}

function initScratchCard() {
    const canvas = document.getElementById('scratchCanvas');
    canvas.style.pointerEvents = 'auto'; // Reset pointer events
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.globalCompositeOperation = 'source-over';
    const grad = ctx.createLinearGradient(0,0,240,240);
    grad.addColorStop(0, '#C0C0C0'); grad.addColorStop(1, '#808080');
    ctx.fillStyle = grad;
    ctx.fillRect(0,0,240,240);
    ctx.fillStyle = '#fff'; ctx.font = 'bold 20px Inter'; ctx.textAlign = 'center';
    ctx.fillText('SCRATCH HERE', 120, 120);

    isDrawing = false;
    document.getElementById('claimBtn').classList.add('d-none');
    document.getElementById('scratchInstruction').classList.remove('d-none');

    const scratch = (e) => {
        if(!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.touches[0].clientX) - rect.left;
        const y = (e.clientY || e.touches[0].clientY) - rect.top;
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath(); ctx.arc(x, y, 25, 0, Math.PI*2); ctx.fill();
        
        const data = ctx.getImageData(0,0,240,240).data;
        let trans = 0;
        for(let i=3; i<data.length; i+=4) if(data[i] === 0) trans++;
        if(trans/(240*240) > 0.45) {
            ctx.clearRect(0,0,240,240);
            canvas.style.pointerEvents = 'none'; // Allow clicking through to the button
            document.getElementById('claimBtn').classList.remove('d-none');
            document.getElementById('scratchInstruction').classList.add('d-none');
        }
    };

    canvas.onmousedown = canvas.ontouchstart = (e) => { isDrawing = true; if(e.type==='touchstart') e.preventDefault(); };
    window.onmouseup = window.ontouchend = () => isDrawing = false;
    canvas.onmousemove = canvas.ontouchmove = (e) => { scratch(e); if(e.type==='touchmove') e.preventDefault(); };
}

function claimReward() {
    if (!currentCardId) return;
    
    const btn = document.getElementById('claimBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Claiming...';

    const fd = new FormData(); 
    fd.append('card_id', currentCardId);
    
    fetch('claim_scratch_card.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error claiming reward');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function copyUPI(e) {
    const id = document.getElementById('upiId');
    navigator.clipboard.writeText(id.value).then(() => {
        e.target.textContent = 'Copied!';
        setTimeout(() => e.target.textContent = 'Copy', 2000);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const amt = document.getElementById('fundAmount');
    const qr = document.getElementById('upiQR');
    const form = document.getElementById('addFundsForm');
    const submitBtn = document.getElementById('submitBtn');

    const sync = () => {
        const active = document.querySelector('.payment-method-radio:checked');
        if (!active) return;
        
        const method = active.value;
        document.getElementById('upiDetailsSection').classList.toggle('d-none', method !== 'UPI');
        document.getElementById('cardDetailsSection').classList.toggle('d-none', !method.includes('Card'));
        document.getElementById('netBankingSection').classList.toggle('d-none', method !== 'Net Banking');
        
        const val = Number(amt.value || 0);
        const readable = val > 0 ? `₹${val.toLocaleString('en-IN')}` : 'money';
        submitBtn.textContent = `Add ${readable} via ${method}`;

        // Update card type logo
        const cardLogo = document.querySelector('.card-type-logo i');
        if (method === 'Credit Card') {
            cardLogo.className = 'bi bi-credit-card-2-front';
        } else {
            cardLogo.className = 'bi bi-credit-card';
        }
    };

    // Card Input Sync Logic
    const cardNoInput = document.getElementById('cardNumberInput');
    const cardNameInput = document.getElementById('cardHolderInput');
    const cardExpiryInput = document.getElementById('cardExpiryInput');
    const cardCvvInput = document.getElementById('cardCvvInput');
    const cardVisual = document.getElementById('cardVisual');

    cardNoInput.oninput = (e) => {
        let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        let matches = v.match(/\d{4,16}/g);
        let match = matches && matches[0] || '';
        let parts = [];
        for (let i=0, len=match.length; i<len; i+=4) {
            parts.push(match.substring(i, i+4));
        }
        if (parts.length) {
            e.target.value = parts.join(' ');
        }
        document.getElementById('cardNoDisplay').textContent = e.target.value || '#### #### #### ####';
    };

    cardNameInput.oninput = (e) => {
        document.getElementById('cardNameDisplay').textContent = e.target.value.toUpperCase() || 'FULL NAME';
    };

    cardExpiryInput.oninput = (e) => {
        let v = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
        if (v.length >= 2) {
            e.target.value = v.substring(0,2) + '/' + v.substring(2,4);
        }
        document.getElementById('cardExpiryDisplay').textContent = e.target.value || 'MM/YY';
    };

    cardCvvInput.oninput = (e) => {
        document.getElementById('cardCvvDisplay').textContent = e.target.value || '***';
    };

    cardCvvInput.onfocus = () => cardVisual.classList.add('flipped');
    cardCvvInput.onblur = () => cardVisual.classList.remove('flipped');

    document.querySelectorAll('.payment-method-radio').forEach(r => r.onchange = sync);
    document.querySelectorAll('.preset-amount').forEach(b => b.onclick = () => { amt.value = b.dataset.amount; sync(); amt.oninput(); });
    
    amt.oninput = () => {
        const data = `upi://pay?pa=grocery@upi&pn=Quick mart&am=${amt.value}&cu=INR`;
        qr.src = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" + encodeURIComponent(data);
        sync();
    };

    form.onsubmit = (e) => {
        e.preventDefault();
        document.getElementById('paymentProcessingView').classList.replace('d-none', 'd-flex');
        document.getElementById('addFundsFormContent').style.visibility = 'hidden';
        let p = 0;
        const iv = setInterval(() => {
            p += 5;
            document.getElementById('paymentProgressBar').style.width = p + "%";
            if(p >= 100) { clearInterval(iv); form.submit(); }
        }, 50);
    };
    
    sync();
    amt.oninput();

    document.getElementById('toggleBalance').onclick = function() {
        const bal = document.getElementById('walletBalance');
        const hid = document.getElementById('hiddenBalance');
        const isH = bal.classList.toggle('d-none');
        hid.classList.toggle('d-none');
        this.querySelector('i').classList.replace(isH ? 'bi-eye' : 'bi-eye-slash', isH ? 'bi-eye-slash' : 'bi-eye');
    };
});
</script>

<?php include 'includes/footer.php'; ?>