<?php 
require_once 'config/db.php';
require_once 'includes/cart_pricing.php';

if (!isLoggedIn()) {
    header("Location: login.php?msg=Please login to checkout");
    exit;
}

if (empty($_SESSION['cart'])) {
    header("Location: products.php");
    exit;
}

require_once 'includes/header.php'; 

// Fetch user details for pre-filling
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch Wallet Balance
$stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$wallet_row = $stmt->fetch();

if (!$wallet_row) {
    // Create wallet if missing
    $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$_SESSION['user_id']]);
    $wallet_balance = 0.00;
} else {
    $wallet_balance = $wallet_row['balance'];
}

$error = '';
$success = false;

// Calculate summary for display and POST
$pricing = calculateCartPricing($pdo, $_SESSION['cart'] ?? [], $_SESSION['applied_coupon'] ?? null);
foreach ($pricing['invalid_cart_keys'] as $invalidCartKey) {
    unset($_SESSION['cart'][$invalidCartKey]);
}

if (isset($_SESSION['applied_coupon']) && !$pricing['coupon_still_valid']) {
    unset($_SESSION['applied_coupon']);
    $pricing = calculateCartPricing($pdo, $_SESSION['cart'] ?? []);
}

$cart_items = $pricing['cart_items'];
$subtotal = $pricing['subtotal'];
$total_tax = $pricing['total_tax'];
$avg_tax_percentage = $pricing['avg_tax_percentage'];
$bogo_discount = $pricing['bogo_discount'];
$total_inclusive_subtotal = $pricing['total_inclusive_subtotal'];
$delivery_charge = $pricing['delivery_charge'];
$grand_total = $pricing['grand_total'];
$grand_total_before_coupon = $pricing['grand_total_before_coupon'];
$discount_amount = $pricing['discount_amount'];
$coupon_id = $pricing['coupon_id'];
$coupon_code_display = $pricing['coupon_code'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    $payment_method = $_POST['payment_method'];
    $payment_detail = "";

    // Validation for specific payment methods
    if ($payment_method === 'Net Banking') {
        if (empty($_POST['bank_name'])) {
            $error = "Please select a bank for Net Banking.";
        } else {
            $payment_detail = " (" . $_POST['bank_name'] . ")";
        }
    } elseif ($payment_method === 'UPI / QR Code') {
        if (empty($_POST['upi_id'])) {
            $error = "Please enter your UPI ID.";
        } else {
            $payment_detail = " (" . $_POST['upi_id'] . ")";
        }
    } elseif ($payment_method === 'Credit / Debit Card') {
        if (empty($_POST['card_number']) || empty($_POST['card_expiry']) || empty($_POST['card_cvv'])) {
            $error = "Please fill in all card details.";
        } else {
            $payment_detail = " (Card ending in " . substr($_POST['card_number'], -4) . ")";
        }
    } elseif ($payment_method === 'Digital Wallet') {
        if ($wallet_balance < $grand_total) {
            $error = "Insufficient wallet balance. Please add funds or choose another payment method.";
        } else {
            $payment_detail = " (Wallet Balance: ₹" . number_format($wallet_balance, 2) . ")";
        }
    }

    if (!$error) {
        $final_payment_method = $payment_method . $payment_detail;
        try {
            $pdo->beginTransaction();
            
            // Final Stock Check
            foreach ($cart_items as $item) {
                if ($item['variant_id'] > 0) {
                    $stmt = $pdo->prepare("SELECT pv.stock_quantity, pv.expiry_date, p.expiry_date AS product_expiry_date FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.id = ? FOR UPDATE");
                    $stmt->execute([$item['variant_id']]);
                } else {
                    $stmt = $pdo->prepare("SELECT stock_quantity, expiry_date FROM products WHERE id = ? FOR UPDATE");
                    $stmt->execute([$item['id']]);
                }
                $p = $stmt->fetch();

                if ($p && (isExpiredDateValue($p['expiry_date'] ?? null) || isExpiredDateValue($p['product_expiry_date'] ?? null))) {
                    throw new Exception(getExpiredPurchaseMessage() . ' Item: ' . $item['name']);
                }
                
                if (!$p || $p['stock_quantity'] < $item['qty']) {
                    throw new Exception("Insufficient stock for item: " . $item['name']);
                }
            }

            // Process Wallet Deduction if applicable
            if ($payment_method === 'Digital Wallet') {
                $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$_SESSION['user_id']]);
                $wallet = $stmt->fetch();
                
                if (!$wallet) {
                    // This should ideally not happen because we checked/created it above,
                    // but for safety in transaction:
                    $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$_SESSION['user_id']]);
                    throw new Exception("Insufficient wallet balance.");
                }
                
                if ($wallet['balance'] < $grand_total) {
                    throw new Exception("Insufficient wallet balance.");
                }
                
                $new_wallet_balance = $wallet['balance'] - $grand_total;
                $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$new_wallet_balance, $wallet['id']]);
                
                // Record transaction (will link order_id after order is created)
            }

            // Insert Order
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, payment_method, shipping_address, contact_number, coupon_id, discount_amount, delivery_otp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $grand_total, $final_payment_method, $address, $contact, $coupon_id, $discount_amount, $otp]);
            $order_id = $pdo->lastInsertId();
            $_SESSION['last_order_id'] = $order_id;

            // Send SMS OTP to Customer
            $sms_message = "Order confirmed! Your Order #$order_id has been placed. Share OTP $otp with the delivery partner to receive your items. - Quick mart";
            sendSMS($contact, $sms_message);

            // Record Wallet Transaction if applicable
            if ($payment_method === 'Digital Wallet') {
                $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, type, amount, description, order_id, status) VALUES (?, 'Debit', ?, ?, ?, 'Completed')")
                    ->execute([$wallet['id'], $grand_total, "Payment for Order #$order_id", $order_id]);
            }

            // Insert Order Items and Update Stock
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, variant_id, quantity, price_at_time) VALUES (?, ?, ?, ?, ?)");
            $updateProductStockStmt = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?), availability_status = CASE WHEN (stock_quantity - ?) <= 0 THEN 'Out of Stock' ELSE availability_status END WHERE id = ?");
            $updateVariantStockStmt = $pdo->prepare("UPDATE product_variants SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
            
            foreach ($cart_items as $item) {
                // Insert item
                $stmt->execute([$order_id, $item['id'], $item['variant_id'], $item['qty'], $item['price']]);
                
                // Update stock
                if ($item['variant_id'] > 0) {
                    $updateVariantStockStmt->execute([$item['qty'], $item['variant_id']]);
                } else {
                    $updateProductStockStmt->execute([$item['qty'], $item['qty'], $item['id']]);
                }
            }

            // Mark Coupon as Used
            if ($coupon_id) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$coupon_id]);
                
                // If it was one-time use or limit reached, we could also update status to Disabled or Expired
                $pdo->prepare("UPDATE coupons SET status = 'Disabled' WHERE id = ? AND used_count >= usage_limit")->execute([$coupon_id]);
            }

            // Generate Scratch Card for Order
            $reward_amount = rand(1, 20); // Random reward between 1 and 20
            $pdo->prepare("INSERT INTO scratch_cards (user_id, amount, order_id) VALUES (?, ?, ?)")->execute([$_SESSION['user_id'], $reward_amount, $order_id]);
            $last_scratch_card_id = $pdo->lastInsertId();
            $scratch_card_reward = $reward_amount;

            $pdo->commit();
        unset($_SESSION['cart']);
        unset($_SESSION['applied_coupon']);
        $success = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Transaction failed: " . $e->getMessage();
    }
}
}

?>

<div class="container py-4 py-lg-5">
    <?php if ($success): ?>
        <!-- Confetti Library -->
        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var duration = 5 * 1000;
                var animationEnd = Date.now() + duration;
                var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

                function randomInRange(min, max) {
                  return Math.random() * (max - min) + min;
                }

                var interval = setInterval(function() {
                  var timeLeft = animationEnd - Date.now();

                  if (timeLeft <= 0) {
                    return clearInterval(interval);
                  }

                  var particleCount = 50 * (timeLeft / duration);
                  // since particles fall down, start a bit higher than random
                  confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
                  confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
                }, 250);
            });
        </script>

        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card shadow-lg border-0 rounded-4 overflow-hidden py-5">
                    <div class="card-body py-4">
                        <div class="success-checkmark mb-4">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center shadow-lg" style="width: 100px; height: 100px;">
                                <i class="bi bi-check-lg display-1"></i>
                            </div>
                        </div>
                        <h2 class="fw-bold mb-3 firecracker-text">Order Placed Successfully!</h2>
                        <p class="text-muted mb-4 px-lg-5">Thank you for your purchase. Your order <b>#<?php echo htmlspecialchars($order_id ?? ($_SESSION['last_order_id'] ?? '')); ?></b> has been received and is being processed.</p>
                        
                        <?php if (isset($scratch_card_reward)): ?>
                        <!-- Scratch Card Section -->
                        <div class="scratch-card-wrapper mb-5 mt-4">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mx-auto" style="max-width: 300px;">
                                <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
                                    <h6 class="fw-800 text-warning mb-0 text-uppercase" style="letter-spacing: 1px;">
                                        <i class="bi bi-gift-fill me-2"></i>Lucky Reward
                                    </h6>
                                </div>
                                <div class="card-body p-4 text-center">
                                    <div class="scratch-container position-relative mx-auto mb-3" style="width: 200px; height: 200px;">
                                        <!-- The actual reward shown underneath -->
                                        <div class="reward-content d-flex flex-column align-items-center justify-content-center h-100 w-100 bg-light rounded-4 border">
                                            <div class="text-warning mb-1">
                                                <i class="bi bi-wallet2 fs-4"></i>
                                            </div>
                                            <div class="smaller text-muted fw-bold">You Won!</div>
                                            <h2 class="fw-800 mb-0">₹<span id="rewardAmountDisplay"><?php echo $scratch_card_reward; ?></span></h2>
                                            <div class="extra-small text-muted mt-1">Wallet Cash</div>
                                        </div>
                                        <!-- The scratchable canvas -->
                                        <canvas id="scratchCanvas" class="position-absolute top-0 start-0 rounded-4 cursor-crosshair" width="200" height="200"></canvas>
                                    </div>
                                    <p id="scratchInstruction" class="text-muted extra-small mb-0">Scratch the card to reveal your reward!</p>
                                    <button id="claimRewardBtn" class="btn btn-warning w-100 rounded-pill fw-bold d-none mt-2" onclick="claimReward(<?php echo $last_scratch_card_id; ?>)">
                                        Claim to Wallet <i class="bi bi-arrow-right-short"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const canvas = document.getElementById('scratchCanvas');
                            if (!canvas) return;
                            
                            const ctx = canvas.getContext('2d');
                            let isDrawing = false;
                            let scratchedPixels = 0;
                            const totalPixels = canvas.width * canvas.height;

                            // Fill with scratchable color/pattern
                            ctx.fillStyle = '#ced4da';
                            ctx.fillRect(0, 0, canvas.width, canvas.height);
                            
                            // Add a pattern or text to the top
                            ctx.fillStyle = '#6c757d';
                            ctx.font = 'bold 16px Arial';
                            ctx.textAlign = 'center';
                            ctx.fillText('SCRATCH HERE', canvas.width/2, canvas.height/2);

                            function scratch(e) {
                                if (!isDrawing) return;
                                
                                const rect = canvas.getBoundingClientRect();
                                const x = (e.clientX || e.touches[0].clientX) - rect.left;
                                const y = (e.clientY || e.touches[0].clientY) - rect.top;
                                
                                ctx.globalCompositeOperation = 'destination-out';
                                ctx.beginPath();
                                ctx.arc(x, y, 20, 0, Math.PI * 2);
                                ctx.fill();

                                checkScratchPercentage();
                            }

                            function checkScratchPercentage() {
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                let transparent = 0;
                                for (let i = 3; i < imageData.data.length; i += 4) {
                                    if (imageData.data[i] === 0) transparent++;
                                }
                                
                                const percentage = (transparent / totalPixels) * 100;
                                if (percentage > 50) {
                                    canvas.style.opacity = '0';
                                    canvas.style.transition = 'opacity 0.5s';
                                    setTimeout(() => canvas.remove(), 500);
                                    document.getElementById('scratchInstruction').classList.add('d-none');
                                    document.getElementById('claimRewardBtn').classList.remove('d-none');
                                }
                            }

                            canvas.addEventListener('mousedown', () => isDrawing = true);
                            canvas.addEventListener('touchstart', (e) => { isDrawing = true; e.preventDefault(); });
                            
                            window.addEventListener('mouseup', () => isDrawing = false);
                            window.addEventListener('touchend', () => isDrawing = false);
                            
                            canvas.addEventListener('mousemove', scratch);
                            canvas.addEventListener('touchmove', (e) => { scratch(e); e.preventDefault(); });
                        });

                        function claimReward(cardId) {
                            const btn = document.getElementById('claimRewardBtn');
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Claiming...';

                            fetch('claim_scratch_card.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'card_id=' + cardId
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    btn.className = 'btn btn-success w-100 rounded-pill fw-bold';
                                    btn.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Reward Claimed!';
                                    // Trigger a small confetti burst for reward
                                    confetti({
                                        particleCount: 100,
                                        spread: 70,
                                        origin: { y: 0.6 }
                                    });
                                } else {
                                    alert(data.message || 'Error claiming reward');
                                    btn.disabled = false;
                                    btn.innerHTML = 'Claim to Wallet <i class="bi bi-arrow-right-short"></i>';
                                }
                            });
                        }
                        </script>
                        <?php endif; ?>
                        
                        <div class="bg-light p-3 rounded-3 mb-4 mx-lg-5 border border-dashed">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted smaller">Order ID:</span>
                                <span class="fw-bold smaller">#<?php echo htmlspecialchars($order_id ?? ($_SESSION['last_order_id'] ?? '')); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted smaller">Payment Method:</span>
                                <span class="fw-bold smaller"><?php echo htmlspecialchars($final_payment_method ?? 'Cash on Delivery'); ?></span>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mt-2">
                            <a href="receipt.php?id=<?php echo htmlspecialchars($order_id ?? ($_SESSION['last_order_id'] ?? '')); ?>" class="btn btn-outline-success px-4 py-2 rounded-pill fw-bold">
                                <i class="bi bi-file-earmark-text me-2"></i>View Receipt
                            </a>
                            <a href="index.php" class="btn btn-success px-4 py-2 rounded-pill fw-bold shadow-sm">
                                <i class="bi bi-house-door me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h2 class="mb-1 fw-bold h3">Checkout</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 smaller">
                        <li class="breadcrumb-item"><a href="cart.php" class="text-decoration-none text-success">Cart</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-sm">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i>Secure 256-bit SSL
                </span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-body p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-4 mb-4 d-flex align-items-center shadow-sm bg-danger-subtle text-danger-emphasis">
                                <i class="bi bi-exclamation-circle-fill me-3 fs-4"></i>
                                <div>
                                    <div class="fw-bold">Payment Error</div>
                                    <div class="smaller"><?php echo $error; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="checkout-form">
                            <!-- Step 1: Delivery Information -->
                            <div class="checkout-step mb-5">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="step-number bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-800" style="width: 35px; height: 35px; font-size: 0.9rem;">1</div>
                                    <h5 class="fw-800 mb-0" style="letter-spacing: -0.5px;">Delivery Information</h5>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Shipping Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 rounded-start-3 align-items-start pt-2 px-3"><i class="bi bi-geo-alt text-muted"></i></span>
                                            <textarea name="address" class="form-control border-start-0 rounded-end-3 bg-light py-2" rows="3" required placeholder="Street, Apartment, City, Pincode"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Contact Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-telephone text-muted"></i></span>
                                            <input type="text" name="contact" class="form-control border-start-0 rounded-end-3 bg-light py-2" 
                                                   value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" required placeholder="10-digit mobile number">
                                        </div>
                                        <div class="form-text smaller text-muted mt-2">We'll call this number to coordinate delivery.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Step 2: Payment Method -->
                            <div class="checkout-step">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="step-number bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-800" style="width: 35px; height: 35px; font-size: 0.9rem;">2</div>
                                    <h5 class="fw-800 mb-0" style="letter-spacing: -0.5px;">Payment Method</h5>
                                </div>
                                
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6">
                                        <div class="payment-option h-100">
                                            <input type="radio" class="btn-check" name="payment_method" id="wallet" value="Digital Wallet">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 text-start d-flex align-items-center h-100 transition-all border-2 shadow-hover" for="wallet">
                                                <div class="payment-icon bg-warning-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-wallet2 fs-4 text-warning"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold d-flex justify-content-between align-items-center text-dark">
                                                        <span>Digital Wallet</span>
                                                        <i class="bi bi-check-circle-fill check-icon opacity-0"></i>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                                        <div class="smaller text-muted">Balance: ₹<?php echo number_format($wallet_balance, 2); ?></div>
                                                        <a href="wallet.php" target="_blank" class="badge bg-warning bg-opacity-10 text-warning text-decoration-none smaller fw-800 py-1 px-2 rounded-pill hover-lift">
                                                            <i class="bi bi-plus-circle me-1"></i>Top Up
                                                        </a>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="payment-option h-100">
                                            <input type="radio" class="btn-check" name="payment_method" id="cod" value="Cash on Delivery" checked>
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 text-start d-flex align-items-center h-100 transition-all border-2 shadow-hover" for="cod">
                                                <div class="payment-icon bg-success-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-cash-stack fs-4 text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold d-flex justify-content-between align-items-center text-dark">
                                                        <span>Cash on Delivery</span>
                                                        <i class="bi bi-check-circle-fill check-icon opacity-0"></i>
                                                    </div>
                                                    <div class="smaller text-muted">Pay when you receive</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="payment-option h-100">
                                            <input type="radio" class="btn-check" name="payment_method" id="upi" value="UPI / QR Code">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 text-start d-flex align-items-center h-100 transition-all border-2 shadow-hover" for="upi">
                                                <div class="payment-icon bg-success-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-qr-code-scan fs-4 text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold d-flex justify-content-between align-items-center text-dark">
                                                        <span>UPI / QR Scan</span>
                                                        <i class="bi bi-check-circle-fill check-icon opacity-0"></i>
                                                    </div>
                                                    <div class="smaller text-muted">Fast & Secure payment</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="payment-option h-100">
                                            <input type="radio" class="btn-check" name="payment_method" id="card" value="Credit / Debit Card">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 text-start d-flex align-items-center h-100 transition-all border-2 shadow-hover" for="card">
                                                <div class="payment-icon bg-success-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-credit-card fs-4 text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold d-flex justify-content-between align-items-center text-dark">
                                                        <span>Card Payment</span>
                                                        <i class="bi bi-check-circle-fill check-icon opacity-0"></i>
                                                    </div>
                                                    <div class="smaller text-muted">Visa, Master, Rupay</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="payment-option h-100">
                                            <input type="radio" class="btn-check" name="payment_method" id="netbanking" value="Net Banking">
                                            <label class="btn btn-outline-success w-100 p-3 rounded-4 text-start d-flex align-items-center h-100 transition-all border-2 shadow-hover" for="netbanking">
                                                <div class="payment-icon bg-success-subtle rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-bank fs-4 text-success"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold d-flex justify-content-between align-items-center text-dark">
                                                        <span>Net Banking</span>
                                                        <i class="bi bi-check-circle-fill check-icon opacity-0"></i>
                                                    </div>
                                                    <div class="smaller text-muted">All major Indian banks</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Payment Details -->
                                <div id="payment-details-container" class="rounded-4 overflow-hidden mb-4">
                                    <!-- UPI Details -->
                                    <div id="upi_details" class="payment-extra-details d-none bg-light p-4 border border-success-subtle rounded-4">
                                        <div class="row align-items-center g-4">
                                            <div class="col-md-5 text-center">
                                                <div class="bg-white p-3 rounded-4 shadow-sm d-inline-block border">
                                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi://pay?pa=quickmart@bank&pn=Quick%20mart&am=<?php echo $grand_total; ?>&cu=INR" 
                                                         alt="Payment QR" class="img-fluid" style="width: 150px;">
                                                </div>
                                                <p class="smaller text-muted mt-2 mb-0 fw-bold">Scan to pay <span class="text-success">₹<?php echo number_format($grand_total, 2); ?></span></p>
                                            </div>
                                            <div class="col-md-7">
                                                <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Enter UPI ID</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white border-end-0 rounded-start-3 px-3"><i class="bi bi-at text-muted"></i></span>
                                                    <input type="text" name="upi_id" class="form-control border-start-0 rounded-end-3 py-2 bg-white" placeholder="yourname@upi">
                                                </div>
                                                <p class="smaller text-muted mt-2 mb-0"><i class="bi bi-shield-check text-success me-1"></i>Secure payment powered by UPI</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card Details -->
                                    <div id="card_details" class="payment-extra-details d-none bg-light p-4 border border-success-subtle rounded-4">
                                        <div class="card-container mb-4">
                                            <div class="credit-card" id="checkoutCreditCard">
                                                <!-- Front -->
                                                <div class="card-front bg-dark p-4 text-white rounded-4 shadow-lg">
                                                    <div class="d-flex justify-content-between align-items-start mb-4">
                                                        <i class="bi bi-cpu fs-2 text-warning"></i>
                                                        <i class="bi bi-wifi fs-3 opacity-50"></i>
                                                    </div>
                                                    <div class="mb-4">
                                                        <h4 class="card-number-display fw-800 tracking-widest mb-0" id="checkoutCardNumberDisplay">•••• •••• •••• ••••</h4>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end">
                                                        <div class="flex-grow-1">
                                                            <label class="smaller opacity-50 text-uppercase d-block">Card Holder</label>
                                                            <span class="fw-700 tracking-wider text-uppercase" id="checkoutCardHolderDisplay">FULL NAME</span>
                                                        </div>
                                                        <div class="text-end">
                                                            <label class="smaller opacity-50 text-uppercase d-block">Expires</label>
                                                            <span class="fw-700 tracking-wider" id="checkoutCardExpiryDisplay">MM/YY</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Back -->
                                                <div class="card-back bg-dark py-4 text-white rounded-4 shadow-lg">
                                                    <div class="black-bar mt-2 mb-4 w-100" style="height: 45px; background: #000;"></div>
                                                    <div class="px-4">
                                                        <div class="bg-secondary bg-opacity-25 rounded p-2 text-end mb-3">
                                                            <span class="fw-800 tracking-widest italic" id="checkoutCardCvvDisplay">•••</span>
                                                        </div>
                                                        <p class="smaller opacity-50 mb-0">Authorized Signature. Not valid unless signed. This card remains the property of the issuer.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Card Holder Name</label>
                                                <input type="text" name="card_name" id="checkoutCardHolderInput" class="form-control rounded-3 py-2 bg-white shadow-sm" placeholder="FULL NAME" maxlength="25">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Card Number</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white border-end-0 rounded-start-3 px-3"><i class="bi bi-credit-card-2-front text-muted"></i></span>
                                                    <input type="text" name="card_number" id="checkoutCardNumberInput" class="form-control border-start-0 rounded-end-3 py-2 bg-white" placeholder="0000 0000 0000 0000" maxlength="19">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Expiry Date</label>
                                                <input type="text" name="card_expiry" id="checkoutCardExpiryInput" class="form-control rounded-3 py-2 bg-white shadow-sm" placeholder="MM/YY" maxlength="5">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">CVV</label>
                                                <div class="input-group shadow-sm">
                                                    <input type="password" name="card_cvv" id="checkoutCardCvvInput" class="form-control rounded-start-3 py-2 bg-white" placeholder="•••" maxlength="3">
                                                    <span class="input-group-text bg-white rounded-end-3 px-3"><i class="bi bi-lock text-muted"></i></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Net Banking Details -->
                                    <div id="netbanking_details" class="payment-extra-details d-none bg-light p-4 border border-success-subtle rounded-4">
                                        <label class="form-label small fw-bold text-muted mb-2 text-uppercase" style="letter-spacing: 0.5px;">Select Your Bank</label>
                                        <select name="bank_name" class="form-select rounded-3 mb-2 py-2 bg-white shadow-sm">
                                            <option value="" selected disabled>Choose a bank...</option>
                                            <option value="SBI">State Bank of India (SBI)</option>
                                            <option value="HDFC">HDFC Bank</option>
                                            <option value="ICICI">ICICI Bank</option>
                                            <option value="AXIS">Axis Bank</option>
                                            <option value="PNB">Punjab National Bank</option>
                                            <option value="KOTAK">Kotak Mahindra Bank</option>
                                            <option value="BOB">Bank of Baroda</option>
                                        </select>
                                        <div class="bg-info-subtle p-3 rounded-3 mt-3">
                                            <p class="smaller text-info-emphasis mb-0"><i class="bi bi-info-circle-fill me-2"></i>You will be redirected to your bank's secure portal for authentication.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100 py-3 rounded-4 fw-800 shadow-sm mt-2 text-uppercase" style="letter-spacing: 0.5px;">
                                Place Order (₹<?php echo number_format($grand_total, 2); ?>) <i class="bi bi-shield-check ms-2"></i>
                            </button>
                            <p class="text-center text-muted smaller mt-3 mb-0">
                                <i class="bi bi-lock-fill text-success me-1"></i> Your payment information is safe and encrypted.
                            </p>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top: 100px;">
                    <!-- Order Summary Card -->
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                        <div class="card-header bg-white border-0 py-4 px-4">
                            <h5 class="fw-800 mb-0" style="letter-spacing: -0.5px;">Order Summary</h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <!-- Mini Cart Items -->
                            <div class="mini-cart-items mb-4" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0 bg-light rounded-3 p-1 border overflow-hidden" style="width: 55px; height: 55px;">
                                            <img src="<?php echo getProductImage($item['image_url'], $item['name']); ?>" 
                                                 class="img-fluid rounded-2 w-100 h-100" 
                                                 style="object-fit: contain;"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </div>
                                        <div class="ms-3 flex-grow-1">
                                            <div class="smaller fw-bold text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div class="smaller text-muted"><?php echo $item['qty']; ?> x ₹<?php echo number_format($item['price'], 2); ?></div>
                                            <?php if ($item['free_qty'] > 0): ?>
                                                <div class="smaller text-success fw-bold"><?php echo $item['free_qty']; ?> FREE</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end fw-bold smaller">
                                            <?php if ($item['bogo_discount'] > 0): ?>
                                                <div class="text-muted text-decoration-line-through"><?php echo '₹' . number_format($item['original_total'], 2); ?></div>
                                            <?php endif; ?>
                                            <div>₹<?php echo number_format($item['total'], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-3 opacity-10">

                            <div class="summary-details">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted smaller">Subtotal (Excl. GST)</span>
                                    <span class="fw-bold smaller">₹<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted smaller">GST Amount (5%)</span>
                                    <span class="fw-bold text-info smaller">+ ₹<?php echo number_format($total_tax, 2); ?></span>
                                </div>
                                <?php if ($bogo_discount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span class="smaller fw-bold">BOGO Savings</span>
                                    <span class="fw-bold smaller">- ₹<?php echo number_format($bogo_discount, 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted smaller">Delivery</span>
                                    <?php if ($delivery_charge > 0): ?>
                                        <span class="fw-bold smaller">₹<?php echo number_format($delivery_charge, 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-bold smaller">FREE</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($discount_amount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span class="smaller fw-bold">Coupon (<?php echo htmlspecialchars($coupon_code_display); ?>)</span>
                                    <span class="fw-bold smaller">- ₹<?php echo number_format($discount_amount, 2); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <hr class="my-3 opacity-10">
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="fw-800 fs-5">Grand Total</span>
                                <div class="text-end">
                                    <?php if ($discount_amount > 0): ?>
                                        <div class="text-muted text-decoration-line-through smaller">₹<?php echo number_format($grand_total_before_coupon, 2); ?></div>
                                    <?php endif; ?>
                                    <span class="fw-800 fs-3 text-success">₹<?php echo number_format($grand_total, 2); ?></span>
                                </div>
                            </div>

                            <button type="submit" form="checkout-form" class="btn btn-success w-100 py-3 rounded-4 fw-800 shadow-sm mb-3 text-uppercase" style="letter-spacing: 0.5px;">
                                Place Order <i class="bi bi-shield-check ms-2"></i>
                            </button>
                            
                            <div class="bg-light p-3 rounded-4 text-center">
                                <p class="smaller text-muted mb-0"><i class="bi bi-shield-lock-fill me-1"></i> SSL Encrypted Payment</p>
                            </div>
                        </div>
                    </div>

                    <!-- Trust Badges -->
                    <div class="row g-2 px-2 pb-4 text-center">
                        <div class="col-4">
                            <i class="bi bi-shield-check text-success fs-3"></i>
                            <div class="smaller text-muted mt-1 fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">100% Secure</div>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-truck text-success fs-3"></i>
                            <div class="smaller text-muted mt-1 fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">Fresh Delivery</div>
                        </div>
                        <div class="col-4">
                            <i class="bi bi-arrow-clockwise text-success fs-3"></i>
                            <div class="smaller text-muted mt-1 fw-bold text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.5px;">Easy Returns</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .smaller { font-size: 0.75rem; }
    .transition-all { transition: all 0.3s ease; }
    .shadow-hover:hover { 
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
    }
    .payment-option input:checked + label {
        border-color: #198754 !important;
        background-color: #f8fff9;
    }
    .payment-option input:checked + label .check-icon {
        opacity: 1;
    }
    .border-dashed {
        border: 2px dashed #dee2e6 !important;
    }
    .mini-cart-items::-webkit-scrollbar {
        width: 4px;
    }
    .mini-cart-items::-webkit-scrollbar-thumb {
        background: #e9ecef;
        border-radius: 10px;
    }
    .checkout-step {
        position: relative;
    }
    .step-number {
        position: relative;
        z-index: 1;
    }
    
    /* Firecracker Text Animation */
    @keyframes pop-in {
        0% { transform: scale(0.8); opacity: 0; }
        60% { transform: scale(1.1); }
        100% { transform: scale(1); opacity: 1; }
    }
    .firecracker-text {
        animation: pop-in 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }

    /* Card Animation Styles */
    .card-container {
        perspective: 1000px;
        width: 100%;
        max-width: 350px;
        height: 200px;
        margin: 0 auto;
    }

    .credit-card {
        width: 100%;
        height: 100%;
        position: relative;
        transition: transform 0.8s;
        transform-style: preserve-3d;
    }

    .credit-card.flipped {
        transform: rotateY(180deg);
    }

    .card-front, .card-back {
        position: absolute;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        border-radius: 1rem;
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
    }

    .card-back {
        transform: rotateY(180deg);
    }

    .card-number-display {
        letter-spacing: 4px;
        font-size: 1.25rem;
    }

    .italic { font-style: italic; }
    .tracking-widest { letter-spacing: 0.15em; }
    .tracking-wider { letter-spacing: 0.05em; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Card Interaction Logic
    const checkoutCreditCard = document.getElementById('checkoutCreditCard');
    const checkoutCardHolderInput = document.getElementById('checkoutCardHolderInput');
    const checkoutCardNumberInput = document.getElementById('checkoutCardNumberInput');
    const checkoutCardExpiryInput = document.getElementById('checkoutCardExpiryInput');
    const checkoutCardCvvInput = document.getElementById('checkoutCardCvvInput');

    if (checkoutCardHolderInput) {
        checkoutCardHolderInput.addEventListener('input', function() {
            document.getElementById('checkoutCardHolderDisplay').textContent = this.value.toUpperCase() || 'FULL NAME';
        });
    }

    if (checkoutCardNumberInput) {
        checkoutCardNumberInput.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '');
            let formatted = val.replace(/(.{4})/g, '$1 ').trim();
            this.value = formatted;
            document.getElementById('checkoutCardNumberDisplay').textContent = formatted || '•••• •••• •••• ••••';
        });
    }

    if (checkoutCardExpiryInput) {
        checkoutCardExpiryInput.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '');
            if (val.length > 2) val = val.substring(0, 2) + '/' + val.substring(2, 4);
            this.value = val;
            document.getElementById('checkoutCardExpiryDisplay').textContent = val || 'MM/YY';
        });
    }

    if (checkoutCardCvvInput) {
        checkoutCardCvvInput.addEventListener('input', function() {
            document.getElementById('checkoutCardCvvDisplay').textContent = this.value || '•••';
        });

        checkoutCardCvvInput.addEventListener('focus', function() {
            checkoutCreditCard.classList.add('flipped');
        });

        checkoutCardCvvInput.addEventListener('blur', function() {
            checkoutCreditCard.classList.remove('flipped');
        });
    }

    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');

    const detailsContainer = document.getElementById('payment-details-container');
    const detailSections = {
        'upi': document.getElementById('upi_details'),
        'card': document.getElementById('card_details'),
        'netbanking': document.getElementById('netbanking_details')
    };

    function updatePaymentDetails() {
        // Hide all
        Object.values(detailSections).forEach(el => {
            if (el) el.classList.add('d-none');
        });

        // Show selected
        const selectedInput = document.querySelector('input[name="payment_method"]:checked');
        const selected = selectedInput ? selectedInput.id : null;
        if (selected && detailSections[selected]) {
            detailSections[selected].classList.remove('d-none');
            detailsContainer.classList.remove('d-none');
        } else {
            // For COD
            detailsContainer.classList.add('d-none');
        }
    }

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentDetails);
    });

    // Initialize
    updatePaymentDetails();
});
</script>

<?php require_once 'includes/footer.php'; ?>

