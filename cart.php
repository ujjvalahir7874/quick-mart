<?php 
require_once 'config/db.php';
require_once 'includes/cart_pricing.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$pricing = calculateCartPricing($pdo, $_SESSION['cart'], $_SESSION['applied_coupon'] ?? null);
foreach ($pricing['invalid_cart_keys'] as $invalidCartKey) {
    unset($_SESSION['cart'][$invalidCartKey]);
}

if (isset($_SESSION['applied_coupon']) && !$pricing['coupon_still_valid']) {
    unset($_SESSION['applied_coupon']);
    $pricing = calculateCartPricing($pdo, $_SESSION['cart']);
}

$cart_items = $pricing['cart_items'];
$subtotal = $pricing['subtotal'];
$total_tax = $pricing['total_tax'];
$avg_tax_percentage = $pricing['avg_tax_percentage'];
$bogo_discount = $pricing['bogo_discount'];
$total_inclusive_subtotal = $pricing['total_inclusive_subtotal'];
$delivery_charge = $pricing['delivery_charge'];
$discount_amount = $pricing['discount_amount'];
$coupon_code = $pricing['coupon_code'];
$grand_total = $pricing['grand_total'];
$grand_total_before_coupon = $pricing['grand_total_before_coupon'];

require_once 'includes/header.php'; 

// Fetch available coupons for the "Available Offers" section
$available_coupons = $pdo->query("SELECT * FROM coupons WHERE status = 'Enabled' AND expiry_date >= CURDATE() AND used_count < usage_limit ORDER BY id DESC")->fetchAll();
?>

<div class="container py-4 py-lg-5 animate__animated animate__fadeIn">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="mb-1 fw-800 h3 text-dark">Your Shopping Cart</h2>
            <p class="text-muted mb-0 small"><i class="bi bi-info-circle-fill me-1 text-success"></i>Items are saved for 30 days in your cart</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success px-4 py-2 rounded-4 border border-success-subtle fw-800 shadow-sm">
                <i class="bi bi-cart3 me-2"></i><?php echo count($cart_items); ?> Items
            </span>
            <a href="products.php" class="btn btn-outline-success btn-sm rounded-4 px-3 py-2 d-none d-md-inline-flex align-items-center fw-800 transition-hover">
                <i class="bi bi-arrow-left me-2"></i>Continue Shopping
            </a>
        </div>
    </div>
    
    <?php if (empty($cart_items)): ?>
        <div class="card text-center py-5 shadow-lg border-0 rounded-4 overflow-hidden animate__animated animate__zoomIn tilt-card">
            <div class="card-body py-5">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm" style="width: 140px; height: 140px;">
                    <i class="bi bi-cart-x display-1 text-muted opacity-50"></i>
                </div>
                <h3 class="fw-800 mb-2">Your cart is empty</h3>
                <p class="text-muted mb-4 mx-auto" style="max-width: 450px;">Looks like you haven't added anything to your cart yet. Explore our fresh products and start shopping!</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="products.php" class="btn btn-success px-5 py-3 rounded-4 fw-800 shadow-lg transition-hover">
                        <i class="bi bi-shop me-2"></i>Start Shopping Now
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
                    <div class="card-body p-0">
                        <!-- Table Header (Desktop Only) -->
                        <div class="d-none d-md-flex bg-light p-3 border-bottom text-muted smaller fw-800 text-uppercase ls-1">
                            <div style="width: 50%;">Product Details</div>
                            <div style="width: 25%; text-align: center;">Quantity</div>
                            <div style="width: 25%; text-align: right;">Total Price</div>
                        </div>

                        <?php foreach ($cart_items as $index => $item): ?>
                        <div class="p-3 p-md-4 <?php echo $index < count($cart_items) - 1 ? 'border-bottom' : ''; ?> transition-all cart-item-row">
                            <div class="row align-items-center">
                                <!-- Product Info Section -->
                                <div class="col-12 col-md-6 mb-3 mb-md-0">
                                    <div class="d-flex align-items-center">
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0" style="width: 90px;">
                                            <div class="bg-light rounded-4 p-2 border position-relative overflow-hidden group">
                                                <img src="<?php echo getProductImage($item['image_url'], $item['name']); ?>" 
                                                     class="img-fluid rounded-3 transition-hover" alt="<?php echo $item['name']; ?>"
                                                     style="aspect-ratio: 1/1; object-fit: contain;">
                                            </div>
                                        </div>
                                        
                                        <!-- Product Text -->
                                        <div class="ms-3 flex-grow-1">
                                            <h6 class="mb-1 fw-800 text-truncate-2">
                                                <a href="product-details.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark hover-success">
                                                    <?php echo $item['name']; ?>
                                                    <?php if ($item['size_name']): ?>
                                                        <span class="text-success small">(<?php echo $item['size_name']; ?>)</span>
                                                    <?php endif; ?>
                                                </a>
                                            </h6>
                                            <?php if (!empty($item['bogo_label'])): ?>
                                                <div class="mb-2">
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-2 fw-800"><?php echo htmlspecialchars($item['bogo_label']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <?php if (!empty($item['discount_price']) && $item['discount_price'] > 0): ?>
                                                    <span class="text-success fw-800">₹<?php echo number_format($item['discount_price'], 2); ?></span>
                                                    <span class="text-muted text-decoration-line-through small opacity-50">₹<?php echo number_format($item['price'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="text-dark fw-800">₹<?php echo number_format($item['price'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($item['free_qty'] > 0): ?>
                                                <div class="small text-success fw-800 mb-2">
                                                    <i class="bi bi-gift-fill me-1"></i><?php echo $item['free_qty']; ?> FREE � Pay for <?php echo $item['payable_qty']; ?>
                                                </div>
                                            <?php elseif ($item['unlock_free_qty'] > 0): ?>
                                                <div class="small text-muted fw-800 mb-2">
                                                    <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Add 1 more item to unlock 1 FREE
                                                </div>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none remove-item smaller fw-800 transition-hover" 
                                                    data-cart-key="<?php echo $item['cart_key']; ?>">
                                                <i class="bi bi-trash3-fill me-1"></i>Remove Item
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quantity Controls Section -->
                                <div class="col-6 col-md-3">
                                    <div class="d-md-none text-muted smaller mb-2 fw-800 text-uppercase">Quantity</div>
                                    <div class="input-group input-group-sm quantity-control rounded-4 overflow-hidden border-2 border-light shadow-sm mx-auto mx-md-0" style="max-width: 130px;">
                                        <button class="btn btn-light border-0 qty-minus px-3 py-2 transition-hover" data-cart-key="<?php echo $item['cart_key']; ?>">
                                            <i class="bi bi-dash-lg"></i>
                                        </button>
                                        <input type="number" class="form-control border-0 text-center qty-input bg-white fw-800 p-0 fs-6" 
                                               value="<?php echo $item['qty']; ?>" 
                                               min="0" 
                                               max="<?php echo $item['stock_quantity']; ?>"
                                               data-cart-key="<?php echo $item['cart_key']; ?>" readonly>
                                        <button class="btn btn-light border-0 qty-plus px-3 py-2 transition-hover" data-cart-key="<?php echo $item['cart_key']; ?>">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                    <?php if ($item['qty'] > $item['stock_quantity']): ?>
                                        <small class="text-danger d-block mt-2 smaller text-center text-md-start fw-800"><i class="bi bi-exclamation-circle-fill me-1"></i>Only <?php echo $item['stock_quantity']; ?> left</small>
                                    <?php elseif ($item['stock_quantity'] <= 5): ?>
                                        <small class="text-warning d-block mt-2 smaller text-center text-md-start fw-800"><i class="bi bi-lightning-charge-fill me-1"></i>Low Stock: <?php echo $item['stock_quantity']; ?> left</small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Total Price Section -->
                                <div class="col-6 col-md-3 text-end">
                                    <div class="text-muted smaller d-md-none fw-800 text-uppercase mb-1">Subtotal</div>
                                    <?php if ($item['bogo_discount'] > 0): ?>
                                        <div class="text-muted text-decoration-line-through smaller opacity-75">₹<?php echo number_format($item['original_total'], 2); ?></div>
                                    <?php endif; ?>
                                    <div class="fw-800 h4 mb-0 text-success">₹<?php echo number_format($item['total'], 2); ?></div>
                                    <?php if ($item['bogo_discount'] > 0): ?>
                                        <div class="smaller text-success fw-800">Saved ₹<?php echo number_format($item['bogo_discount'], 2); ?></div>
                                    <?php endif; ?>
                                    <div class="smaller text-muted d-none d-md-block opacity-75">Inclusive of GST</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Available Offers (Desktop) -->
                <div class="d-none d-lg-block animate__animated animate__fadeInUp">
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-header bg-white border-0 py-3 mt-2">
                            <h6 class="fw-800 mb-0 d-flex align-items-center">
                                <span class="bg-success-subtle p-2 rounded-3 me-2">
                                    <i class="bi bi-tags-fill text-success"></i>
                                </span>
                                Available Special Offers
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <?php if (empty($available_coupons)): ?>
                                    <div class="col-12">
                                        <div class="p-3 bg-light rounded-4 text-center">
                                            <p class="text-muted small mb-0 fw-800">No offers available at the moment.</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($available_coupons, 0, 3) as $c): ?>
                                        <div class="col-md-4">
                                            <div class="coupon-card p-3 rounded-4 border-dashed position-relative transition-hover cursor-pointer <?php echo ($coupon_code === $c['code']) ? 'active' : ''; ?>"
                                                 onclick="applyCouponCode('<?php echo $c['code']; ?>')"
                                                 style="background: #f8fff9; border: 2px dashed #198754 !important;">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <span class="badge bg-success rounded-3 px-2 py-1 fw-800 shadow-sm"><?php echo $c['code']; ?></span>
                                                    <?php if ($coupon_code === $c['code']): ?>
                                                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="fw-800 text-dark mb-1">
                                                    <?php echo $c['discount_type'] === 'Percentage' ? $c['discount_value'].'%' : '₹'.number_format($c['discount_value'], 2); ?> FLAT OFF
                                                </div>
                                                <div class="smaller text-muted fw-800 opacity-75">On orders above ₹<?php echo number_format($c['min_purchase'], 0); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="position-sticky" style="top: 100px;">
                    <!-- Order Summary -->
                    <div class="card shadow-lg border-0 rounded-4 mb-4 overflow-hidden animate__animated animate__fadeInRight tilt-card">
                        <div class="card-header bg-white border-0 py-4 px-4">
                            <h5 class="fw-800 mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <div class="summary-details">
                                <div class="d-flex justify-content-between mb-3 align-items-center">
                                    <span class="text-muted fw-800">Subtotal (Excl. GST)</span>
                                    <span class="fw-800 text-dark">₹<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3 align-items-center">
                                    <span class="text-muted fw-800">GST Amount (5%)</span>
                                    <span class="fw-800 text-danger">+ ₹<?php echo number_format($total_tax, 2); ?></span>
                                </div>
                                <?php if ($bogo_discount > 0): ?>
                                <div class="d-flex justify-content-between mb-3 align-items-center text-success">
                                    <span class="fw-800"><i class="bi bi-gift-fill me-2"></i>BOGO Savings</span>
                                    <span class="fw-800">- ₹<?php echo number_format($bogo_discount, 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-3 align-items-center">
                                    <span class="text-muted fw-800">Delivery Charges</span>
                                    <?php if ($delivery_charge > 0): ?>
                                        <span class="fw-800 text-dark">₹<?php echo number_format($delivery_charge, 2); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success rounded-4 px-3 py-2 fw-800 border border-success-subtle shadow-sm">FREE</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($delivery_charge > 0): ?>
                                    <div class="bg-warning-subtle text-warning-emphasis p-3 rounded-4 smaller mb-4 border border-warning-subtle d-flex align-items-center shadow-sm">
                                        <i class="bi bi-truck-flat me-3 fs-4"></i>
                                        <span class="fw-800">Add items worth <b>₹<?php echo number_format(250 - $total_inclusive_subtotal, 2); ?></b> more for <b class="text-success">FREE delivery</b>!</span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($discount_amount > 0): ?>
                                <div class="d-flex justify-content-between mb-4 text-success bg-success-subtle p-3 rounded-4 border border-success-subtle shadow-sm animate__animated animate__pulse">
                                    <span class="smaller fw-800"><i class="bi bi-tag-fill me-2"></i>Coupon Applied (<?php echo htmlspecialchars($coupon_code); ?>)</span>
                                    <span class="fw-800">- ₹<?php echo number_format($discount_amount, 2); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="p-4 rounded-4 bg-light mb-4 shadow-sm border border-2 border-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h6 fw-800 mb-0 text-muted">Grand Total</span>
                                    <div class="text-end">
                                        <?php if ($discount_amount > 0): ?>
                                            <div class="text-muted text-decoration-line-through smaller fw-800 opacity-50">₹<?php echo number_format($grand_total_before_coupon, 2); ?></div>
                                        <?php endif; ?>
                                        <span class="h3 fw-800 text-success mb-0 shadow-text">₹<?php echo number_format($grand_total, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coupon Input -->
                            <div class="coupon-section mb-4">
                                <form action="apply_coupon.php" method="POST" id="coupon-form">
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border-2 border-light">
                                        <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-tag-fill text-success"></i></span>
                                        <input type="text" name="coupon_code" id="coupon_input" 
                                               class="form-control border-0 ps-2 bg-white fw-800 fs-6" 
                                               placeholder="Promo Code" 
                                               value="<?php echo htmlspecialchars($coupon_code); ?>" 
                                               <?php echo !empty($coupon_code) ? 'readonly' : ''; ?>
                                               style="box-shadow: none;">
                                        <?php if (empty($coupon_code)): ?>
                                            <button class="btn btn-success px-4 fw-800 transition-hover" type="submit">Apply</button>
                                        <?php else: ?>
                                            <a href="apply_coupon.php?remove=1" class="btn btn-danger px-4 fw-800 transition-hover">Remove</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($_GET['coupon_error'])): ?>
                                        <div class="text-danger mt-3 smaller fw-800 animate__animated animate__headShake">
                                            <i class="bi bi-exclamation-circle-fill me-2"></i><?php echo htmlspecialchars($_GET['coupon_error']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($_GET['coupon_success'])): ?>
                                        <div class="text-success mt-2 smaller fw-800 animate__animated animate__fadeIn"><i class="bi bi-check-circle-fill me-2"></i>Coupon applied successfully!</div>
                                    <?php endif; ?>
                                </form>
                            </div>

                            <a href="checkout.php" class="btn btn-success w-100 btn-lg py-3 rounded-4 fw-800 shadow-lg mb-4 transition-hover d-flex align-items-center justify-content-center">
                                PROCEED TO CHECKOUT <i class="bi bi-arrow-right-circle-fill ms-3 fs-5"></i>
                            </a>
                            <div class="text-center bg-light p-3 rounded-4 border border-2 border-white shadow-sm">
                                <p class="smaller text-muted fw-800 mb-0"><i class="bi bi-shield-lock-fill me-2 text-success"></i>100% Secure Checkout</p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Trust Badges -->
                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden bg-light animate__animated animate__fadeInRight animate__delay-1s">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-white rounded-circle p-2 shadow-sm me-3">
                                    <i class="bi bi-truck text-success fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-800 mb-0">Fast Delivery</h6>
                                    <p class="smaller text-muted mb-0">Within 60 min</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="bg-white rounded-circle p-2 shadow-sm me-3">
                                    <i class="bi bi-arrow-counterclockwise text-success fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="fw-800 mb-0">Easy Returns</h6>
                                    <p class="smaller text-muted mb-0">7 days return policy</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .smaller { font-size: 0.75rem; }
    .ls-1 { letter-spacing: 0.5px; }
    
    .cart-item-row {
        transition: all 0.3s ease;
    }
    .cart-item-row:hover {
        background-color: #f8fff9;
    }
    
    .transition-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .transition-hover:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,.1)!important;
    }
    
    .cursor-pointer { cursor: pointer; }
    
    .coupon-card {
        transition: all 0.3s ease;
    }
    .coupon-card:hover {
        transform: scale(1.02);
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.1);
    }
    .coupon-card.active {
        background: #e8f5e9 !important;
        border-style: solid !important;
    }
    
    .quantity-control .btn:hover {
        background-color: #198754 !important;
        color: #fff !important;
    }
    
    .shadow-text {
        text-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .hover-success:hover {
        color: #198754 !important;
    }

    @media (max-width: 767.98px) {
        .h3 { font-size: 1.5rem; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cartCountEl = document.getElementById('cart-count');
    function postForm(body) {
        return fetch('manage_cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        }).then(res => {
            try { return res.json(); } catch(e) { return {}; }
        });
    }
    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.cartKey;
            const input = document.querySelector('.qty-input[data-cart-key="'+key+'"]');
            const next = parseInt(input.value || '1', 10) + 1;
            postForm(`action=update&cart_key=${key}&quantity=${next}`).then(data => {
                if (data && data.success) {
                    location.reload();
                } else if (data && data.error) {
                    alert(data.error);
                }
            });
        });
    });

    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.cartKey;
            const input = document.querySelector('.qty-input[data-cart-key="'+key+'"]');
            const next = parseInt(input.value || '1', 10) - 1;
            
            if (next === 0) {
                if (confirm('Do you want to remove this item from the cart?')) {
                    postForm(`action=remove&cart_key=${key}`).then(() => location.reload());
                }
            } else if (next >= 1) {
                postForm(`action=update&cart_key=${key}&quantity=${next}`).then(data => {
                    if (data && data.success) {
                        location.reload();
                    } else if (data && data.error) {
                        alert(data.error);
                    }
                });
            }
        });
    });

    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('change', function() {
            const key = this.dataset.cartKey;
            const next = parseInt(this.value || '1', 10);
            
            if (next === 0) {
                if (confirm('Do you want to remove this item from the cart?')) {
                    postForm(`action=remove&cart_key=${key}`).then(() => location.reload());
                } else {
                    location.reload();
                }
            } else if (next >= 1) {
                postForm(`action=update&cart_key=${key}&quantity=${next}`).then(data => {
                    if (data && data.success) {
                        location.reload();
                    } else if (data && data.error) {
                        alert(data.error);
                        location.reload();
                    }
                });
            } else {
                location.reload();
            }
        });
    });
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const key = this.dataset.cartKey;
            postForm(`action=remove&cart_key=${key}`).then(data => {
                location.reload();
            });
        });
    });
});

function applyCouponCode(code) {
    const input = document.getElementById('coupon_input');
    const form = document.getElementById('coupon-form');
    if (input && form && !input.readOnly) {
        input.value = code;
        form.submit();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

