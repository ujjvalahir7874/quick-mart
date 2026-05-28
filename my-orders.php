<?php 
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $order_id = $_POST['order_id'];
    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $service_rating = $_POST['service_rating'];
    $comment = $_POST['comment'];

    try {
        $pdo->beginTransaction();

        // 1. Insert feedback
        $stmt = $pdo->prepare("INSERT INTO order_feedback (order_id, user_id, rating, service_rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $user_id, $rating, $service_rating, $comment]);

        // 2. Update delivery person rating if exists
        $stmt = $pdo->prepare("SELECT delivery_person_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order_info = $stmt->fetch();

        if ($order_info && $order_info['delivery_person_id']) {
            $dp_id = $order_info['delivery_person_id'];
            
            // Calculate new average rating for the delivery person
            // We join orders with order_feedback to get all service ratings for this delivery person
            $stmt = $pdo->prepare("SELECT AVG(f.service_rating) as avg_rating 
                                 FROM order_feedback f 
                                 JOIN orders o ON f.order_id = o.id 
                                 WHERE o.delivery_person_id = ?");
            $stmt->execute([$dp_id]);
            $rating_info = $stmt->fetch();
            
            if ($rating_info && $rating_info['avg_rating']) {
                $new_rating = $rating_info['avg_rating'];
                $stmt = $pdo->prepare("UPDATE delivery_persons SET rating = ? WHERE id = ?");
                $stmt->execute([$new_rating, $dp_id]);
            }
        }

        $pdo->commit();
        $_SESSION['success_msg'] = "Thank you for your feedback!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate entry)
            $_SESSION['error_msg'] = "You have already submitted feedback for this order.";
        } else {
            $_SESSION['error_msg'] = "Something went wrong: " . $e->getMessage();
        }
    }
    header("Location: my-orders.php");
    exit();
}

require_once 'includes/header.php'; 

$user_id = $_SESSION['user_id'];

// Fetch orders with their items and delivery person details
$stmt = $pdo->prepare("SELECT o.*, dp.name as delivery_name, dp.bike_number 
                        FROM orders o 
                        LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id 
                        WHERE o.user_id = ? 
                        ORDER BY o.order_date DESC");
$stmt->execute([$user_id]);
$my_orders = $stmt->fetchAll();

// For each order, fetch items and feedback
foreach ($my_orders as &$order) {
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.image_url, pv.size_name 
                            FROM order_items oi 
                            JOIN products p ON oi.product_id = p.id 
                            LEFT JOIN product_variants pv ON oi.variant_id = pv.id
                            WHERE oi.order_id = ?");
    $stmt->execute([$order['id']]);
    $order['items'] = $stmt->fetchAll();

    // Fetch feedback if any
    $stmt = $pdo->prepare("SELECT * FROM order_feedback WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $order['feedback'] = $stmt->fetch();
}
unset($order);
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5 gap-3 animate__animated animate__fadeIn">
        <div>
            <h2 class="fw-800 mb-1 text-dark">My Orders</h2>
            <p class="text-muted mb-0 small">Track and manage your recent orders</p>
        </div>
        <div class="d-flex gap-3">
            <a href="purchase-history.php" class="btn btn-white border rounded-4 px-4 py-2 fw-800 shadow-sm transition-hover">
                <i class="bi bi-clock-history me-2 text-primary"></i> PURCHASE HISTORY
            </a>
            <a href="products.php" class="btn btn-success rounded-4 px-4 py-2 fw-800 shadow-lg transition-hover">
                <i class="bi bi-cart-plus-fill me-2"></i> NEW ORDER
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 animate__animated animate__headShake" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                <div class="fw-800"><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?></div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 animate__animated animate__headShake" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                <div class="fw-800"><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?></div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($my_orders)): ?>
        <div class="text-center py-5 animate__animated animate__fadeInUp">
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm" style="width: 120px; height: 120px;">
                <i class="bi bi-bag-x text-success display-3"></i>
            </div>
            <h4 class="fw-800 mb-2">You haven't placed any orders yet.</h4>
            <p class="text-muted mb-4">Start shopping to see your orders here!</p>
            <a href="products.php" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover">START SHOPPING</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($my_orders as $index => $order): ?>
                <div class="col-12 animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.1; ?>s">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3 order-card transition-hover">
                        <div class="card-header bg-white border-bottom-0 d-flex flex-column flex-md-row justify-content-between align-items-md-center py-4 px-4 gap-3">
                            <div class="d-flex align-items-center gap-4">
                                <div class="bg-light rounded-4 p-3 text-center transition-hover" style="min-width: 80px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                    <div class="smaller text-uppercase text-muted fw-800 tracking-wider mb-1" style="font-size: 0.65rem;">Order</div>
                                    <div class="fw-800 text-dark">#<?php echo $order['id']; ?></div>
                                </div>
                                <div>
                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-1" style="font-size: 0.65rem;">Placed On</div>
                                    <div class="fw-800 text-dark small"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <?php 
                                    $status = ucfirst(strtolower($order['status']));
                                    $badgeClass = 'bg-warning bg-opacity-10 text-warning';
                                    $icon = 'bi-clock-history';
                                    if ($status === 'Accepted') { $badgeClass = 'bg-info bg-opacity-10 text-info'; $icon = 'bi-check2-circle'; }
                                    if ($status === 'Processing') { $badgeClass = 'bg-info bg-opacity-10 text-info'; $icon = 'bi-gear-fill animate-spin'; }
                                    if ($status === 'Shipped') { $badgeClass = 'bg-primary bg-opacity-10 text-primary'; $icon = 'bi-truck'; }
                                    if ($status === 'Out for delivery') { $badgeClass = 'bg-primary bg-opacity-10 text-primary'; $icon = 'bi-truck'; }
                                    if ($status === 'Delayed') { $badgeClass = 'bg-dark bg-opacity-10 text-dark'; $icon = 'bi-exclamation-triangle-fill'; }
                                    if ($status === 'Delivered') { $badgeClass = 'bg-success bg-opacity-10 text-success'; $icon = 'bi-check-all'; }
                                    if ($status === 'Cancelled') { $badgeClass = 'bg-danger bg-opacity-10 text-danger'; $icon = 'bi-x-circle-fill'; }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> px-4 py-2 rounded-4 d-flex align-items-center gap-2 fw-800" style="font-size: 0.85rem;">
                                    <i class="bi <?php echo $icon; ?> fs-5"></i> <?php echo $status; ?>
                                </span>
                                <a href="receipt.php?id=<?php echo $order['id']; ?>" class="btn btn-white btn-sm rounded-4 px-4 py-2 border-0 fw-800 shadow-sm transition-hover">
                                    <i class="bi bi-file-earmark-text-fill me-2 text-success"></i> RECEIPT
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-4 p-md-5 bg-white">
                            <?php if ($status === 'Delayed'): ?>
                                <div class="alert alert-dark border-0 rounded-4 py-3 mb-4 small d-flex align-items-center shadow-sm animate__animated animate__pulse animate__infinite">
                                    <i class="bi bi-exclamation-triangle-fill me-3 text-warning fs-4"></i>
                                    <div class="fw-800 text-white">Your order is experiencing a slight delay. We apologize for the inconvenience.</div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-5">
                                <div class="col-lg-7">
                                    <div class="bg-light bg-opacity-50 rounded-4 p-4 p-md-5 border border-white shadow-sm h-100 d-flex flex-column">
                                        <h6 class="fw-800 mb-4 d-flex align-items-center text-dark text-uppercase tracking-wider">
                                            <i class="bi bi-box-seam-fill text-success me-3 fs-4"></i> Order Items
                                        </h6>
                                        <div class="order-items-list mb-4 custom-scrollbar" style="max-height: 350px; overflow-y: auto;">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="d-flex align-items-center p-3 rounded-4 mb-3 bg-white border border-light transition-hover shadow-sm">
                                                    <div class="flex-shrink-0 bg-light rounded-4 p-2 border overflow-hidden transition-hover" style="width: 70px; height: 70px;">
                                                        <img src="<?php echo getProductImage($item['image_url'], $item['name']); ?>" 
                                                             class="img-fluid rounded-3 w-100 h-100 product-thumb" 
                                                             style="object-fit: contain;"
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                    </div>
                                                    <div class="ms-4 flex-grow-1">
                                                        <div class="fw-800 text-dark mb-1">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                            <?php if (!empty($item['size_name'])): ?>
                                                                <span class="badge bg-light text-success border ms-2 small fw-600"><?php echo htmlspecialchars($item['size_name']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="smaller text-muted fw-600"><?php echo $item['quantity']; ?> x ₹<?php echo number_format($item['price_at_time'], 2); ?></div>
                                                    </div>
                                                    <div class="text-end fw-800 text-success fs-5">
                                                        ₹<?php echo number_format($item['quantity'] * $item['price_at_time'], 2); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="pt-4 border-top mt-auto">
                                            <div class="d-flex justify-content-between align-items-end">
                                                <div>
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-2" style="font-size: 0.65rem;">Payment Method</div>
                                                    <div class="fw-800 text-dark d-flex align-items-center gap-2">
                                                        <i class="bi bi-credit-card-2-front-fill text-success fs-5"></i> 
                                                        <?php echo htmlspecialchars($order['payment_method'] ?? 'Cash on Delivery'); ?>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-2" style="font-size: 0.65rem;">Order Total</div>
                                                    <div class="h3 fw-800 text-success mb-0">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-5">
                                    <div class="bg-white rounded-4 p-4 p-md-5 border border-light shadow-sm h-100 d-flex flex-column">
                                        <h6 class="fw-800 mb-5 d-flex align-items-center text-dark text-uppercase tracking-wider">
                                            <i class="bi bi-geo-alt-fill text-success me-3 fs-4"></i> Tracking & Delivery
                                        </h6>
                                        
                                        <!-- Tracking Stepper -->
                                        <div class="tracking-stepper mb-5 px-1">
                                            <div class="d-flex justify-content-between position-relative">
                                                <?php 
                                                $steps = [
                                                    ['status' => 'Pending', 'icon' => 'bi-clock'],
                                                    ['status' => 'Accepted', 'icon' => 'bi-hand-thumbs-up'],
                                                    ['status' => 'Processing', 'icon' => 'bi-gear'],
                                                    ['status' => 'Shipped', 'icon' => 'bi-truck'],
                                                    ['status' => 'Delivered', 'icon' => 'bi-check-circle-fill']
                                                ];
                                                
                                                $status_map = ['Pending' => 0, 'Accepted' => 1, 'Processing' => 2, 'Shipped' => 3, 'Out for Delivery' => 3, 'Delivered' => 4, 'Delayed' => 2, 'Cancelled' => -1];
                                                $current_status_idx = $status_map[$order['status']] ?? 0;
                                                ?>
                                                
                                                <?php foreach ($steps as $idx => $s): ?>
                                                    <div class="step text-center position-relative <?php echo $idx <= $current_status_idx ? 'active' : ''; ?>" style="z-index: 2; flex: 1;">
                                                        <div class="step-icon-wrapper rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 shadow-sm transition-hover" 
                                                             style="width: 42px; height: 42px; background: <?php echo $idx <= $current_status_idx ? '#198754' : '#f8f9fa'; ?>; color: <?php echo $idx <= $current_status_idx ? '#fff' : '#adb5bd'; ?>;">
                                                            <i class="bi <?php echo $s['icon']; ?> fs-5"></i>
                                                        </div>
                                                        <div class="step-label text-muted tracking-wider" style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase;"><?php echo $s['status']; ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <!-- Progress Line -->
                                                <div class="progress-line position-absolute w-100 bg-light rounded-pill shadow-sm" style="height: 6px; top: 18px; left: 0; z-index: 1;">
                                                    <div class="progress-fill bg-success h-100 rounded-pill" style="width: <?php echo ($current_status_idx / 4) * 100; ?>%; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (in_array($order['status'], ['Shipped', 'Out for Delivery']) && $order['delivery_otp'] && $order['delivery_person_id']): ?>
                                            <div class="alert alert-success border-0 rounded-4 py-3 mb-4 shadow-sm animate__animated animate__fadeIn">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-success text-white rounded-3 p-2 me-3">
                                                            <i class="bi bi-shield-lock-fill fs-5"></i>
                                                        </div>
                                                        <div>
                                                            <div class="smaller text-success fw-800 text-uppercase tracking-wider" style="font-size: 0.6rem;">Delivery OTP</div>
                                                            <div class="h4 fw-800 mb-0 tracking-widest text-success"><?php echo $order['delivery_otp']; ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <p class="small text-muted mb-0 fw-600">Share this with agent<br>only during delivery</p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="delivery-info bg-light rounded-4 p-4 mb-5 border border-white shadow-sm transition-hover">
                                            <div class="row g-3">
                                                <div class="col-md-6 border-end-md">
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-3" style="font-size: 0.65rem;">Shipping Address</div>
                                                    <div class="text-dark lh-base mb-3 fw-600">
                                                        <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                                        <?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?>
                                                    </div>
                                                    <?php if (!empty($order['contact_number'])): ?>
                                                        <div class="small text-muted fw-600">
                                                            <i class="bi bi-telephone-fill text-success me-2"></i> <?php echo htmlspecialchars($order['contact_number']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($order['delivery_person_id']): ?>
                                                <div class="col-md-6 ps-md-4">
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-3" style="font-size: 0.65rem;">Delivery Agent</div>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="bg-success bg-opacity-10 rounded-circle p-2">
                                                            <i class="bi bi-person-badge-fill text-success fs-5"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($order['delivery_name']); ?></div>
                                                            <div class="smaller text-muted fw-800"><?php echo htmlspecialchars($order['bike_number']); ?></div>
                                                            <div class="smaller text-muted fw-600">Private number protected by secure relay</div>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 d-flex gap-2">
                                                        <a href="secure-call.php?order_id=<?php echo $order['id']; ?>" class="btn btn-white btn-sm rounded-3 px-3 fw-800 border shadow-sm transition-hover">
                                                            <i class="bi bi-telephone-fill me-2 text-success"></i> SECURE CALL
                                                        </a>
                                                        <a href="chat.php?order_id=<?php echo $order['id']; ?>" class="btn btn-white btn-sm rounded-3 px-3 fw-800 border shadow-sm transition-hover">
                                                            <i class="bi bi-chat-dots-fill me-2 text-primary"></i> CHAT
                                                        </a>
                                                        <?php if (in_array($order['status'], ['Shipped', 'Out for Delivery'])): ?>
                                                            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 fw-800 border shadow-sm transition-hover" 
                                                                    onclick="openLiveTracking(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['delivery_name']); ?>')">
                                                                <i class="bi bi-geo-alt-fill me-2"></i> TRACK LIVE
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="col-md-6 ps-md-4">
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-3" style="font-size: 0.65rem;">Delivery Agent</div>
                                                    <div class="text-muted small italic fw-600">
                                                        <i class="bi bi-info-circle me-2"></i> Assigning soon...
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="mt-auto">
                                            <?php if (in_array($order['status'], ['Pending', 'Accepted'])): ?>
                                                <button type="button" class="btn btn-danger btn-lg w-100 rounded-4 py-3 fw-800 shadow-sm transition-hover mb-2" 
                                                        onclick="confirmCancelOrder(<?php echo $order['id']; ?>)">
                                                    <i class="bi bi-x-circle-fill me-2"></i> CANCEL ORDER
                                                </button>
                                                <div class="text-center smaller text-muted fw-600 mb-4">
                                                    Cancellation is available only before processing starts.
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($status === 'Delivered'): ?>
                                                <?php if (!empty($order['delivery_date'])): ?>
                                                    <div class="d-flex align-items-center gap-3 mb-4 bg-success bg-opacity-10 p-3 rounded-4 shadow-sm animate__animated animate__fadeIn">
                                                        <i class="bi bi-calendar-check-fill text-success fs-4"></i>
                                                        <div class="smaller fw-800 text-success">
                                                            Delivered on <?php echo date('M d, Y', strtotime($order['delivery_date'])); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['feedback']): ?>
                                                    <div class="feedback-display bg-light p-4 rounded-4 border border-white shadow-sm transition-hover">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div class="smaller fw-800 text-uppercase text-muted tracking-wider" style="font-size: 0.65rem;">Your Feedback</div>
                                                            <div class="d-flex flex-column align-items-end">
                                                                <div class="text-warning fs-6">
                                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                                        <i class="bi bi-star<?php echo $i <= $order['feedback']['rating'] ? '-fill' : ''; ?> shadow-sm"></i>
                                                                    <?php endfor; ?>
                                                                </div>
                                                                <?php if (isset($order['feedback']['service_rating'])): ?>
                                                                    <small class="text-muted smaller fw-800 mt-1" style="font-size: 0.6rem;">Service: <?php echo $order['feedback']['service_rating']; ?>/5 ★</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <p class="text-dark fw-600 mb-0 italic" style="font-style: italic;">"<?php echo htmlspecialchars($order['feedback']['comment']); ?>"</p>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-success w-100 rounded-4 py-3 fw-800 shadow-lg transition-hover animate__animated animate__pulse animate__infinite" data-bs-toggle="modal" data-bs-target="#feedbackModal<?php echo $order['id']; ?>">
                                                        <i class="bi bi-star-fill me-2 fs-5"></i> RATE & REVIEW ORDER
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="text-center p-4 bg-light bg-opacity-50 rounded-4 border border-white border-dashed shadow-sm transition-hover">
                                                    <i class="bi bi-clock-history text-muted fs-3 mb-3 d-block"></i>
                                                    <div class="smaller text-muted fw-800 text-uppercase tracking-wider">Order is being processed</div>
                                                    <div class="smaller text-muted fw-600 mt-1">You can review after delivery</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modern Feedback Modal -->
                <?php if ($status === 'Delivered' && !$order['feedback']): ?>
                <div class="modal fade" id="feedbackModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <div class="modal-header border-0 pb-0">
                                    <button type="button" class="btn-close shadow-none m-2" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4 p-md-5">
                                    <div class="text-center mb-5">
                                        <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm animate__animated animate__zoomIn" style="width: 100px; height: 100px;">
                                            <i class="bi bi-chat-heart-fill text-success display-4"></i>
                                        </div>
                                        <h3 class="fw-800 mb-2">Order Feedback</h3>
                                        <p class="text-muted px-3">Your feedback helps us improve our service and helps others shop better!</p>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label small fw-800 text-muted text-uppercase tracking-wider mb-4 d-block text-center">Rate the Product Quality</label>
                                        <div class="star-rating-input-group h2 text-warning">
                                            <?php for($i=5; $i>=1; $i--): ?>
                                                <input type="radio" class="btn-check" name="rating" id="r<?php echo $order['id'].$index.$i; ?>" value="<?php echo $i; ?>" required>
                                                <label class="star-label px-1" for="r<?php echo $order['id'].$index.$i; ?>">
                                                    <i class="bi bi-star-fill"></i>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="mb-5">
                                        <label class="form-label small fw-800 text-muted text-uppercase tracking-wider mb-4 d-block text-center">Rate Delivery Service</label>
                                        <div class="d-flex justify-content-center gap-3">
                                            <?php for($i=1; $i<=5; $i++): ?>
                                                <input type="radio" class="btn-check" name="service_rating" id="s<?php echo $order['id'].$index.$i; ?>" value="<?php echo $i; ?>" required>
                                                <label class="btn btn-white bg-light border-0 rounded-4 px-4 py-3 fw-800 fs-5 transition-hover shadow-sm" for="s<?php echo $order['id'].$index.$i; ?>">
                                                    <?php echo $i; ?> <span class="fs-6 text-muted">★</span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="mb-0">
                                        <label class="form-label small fw-800 text-muted text-uppercase tracking-wider mb-3">Write your review</label>
                                        <textarea name="comment" class="form-control rounded-4 border-0 bg-light p-4 fw-600" rows="4" placeholder="How was your experience? Any suggestions?"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 p-4 p-md-5 pt-0">
                                    <button type="button" class="btn btn-light rounded-4 px-4 py-3 fw-800 border-0 flex-grow-1 transition-hover" data-bs-dismiss="modal">CANCEL</button>
                                    <button type="submit" name="submit_feedback" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg flex-grow-1 transition-hover">
                                        SUBMIT REVIEW <i class="bi bi-send-fill ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Include Shared Tracking Modal -->
<?php require_once 'includes/tracking-modal.php'; ?>

<!-- Cancel Order Confirmation Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-5">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <h5 class="modal-title fw-800 text-dark">Cancel Order?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted fw-600">Are you sure you want to cancel order #<span id="cancelOrderIdText"></span>? This action cannot be undone.</p>
                <div class="alert alert-warning border-0 rounded-4 smaller fw-700 d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle-fill mt-1"></i>
                    <div>Orders can be cancelled only while they are in <strong>Pending</strong> or <strong>Accepted</strong> status. Once processing starts, cancellation is blocked.</div>
                </div>
                <div id="cancelOrderAlert" class="alert alert-danger d-none rounded-4 smaller fw-600"></div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4 fw-800" data-bs-dismiss="modal">Go Back</button>
                <button type="button" id="confirmCancelBtn" class="btn btn-danger rounded-pill px-4 fw-800 transition-hover">
                    Yes, Cancel Order
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.order-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.order-card:hover {
    box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.1)!important;
}
.product-thumb {
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.order-card:hover .product-thumb {
    transform: scale(1.1);
}
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e9ecef; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #dee2e6; }

.animate-spin {
    animation: spin 2s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.star-rating-input-group {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
}
.star-rating-input-group label {
    color: #dee2e6;
    transition: all 0.2s ease;
    cursor: pointer;
}
.star-rating-input-group label:hover,
.star-rating-input-group label:hover ~ label,
.star-rating-input-group input:checked ~ label {
    color: #ffc107;
}
.star-rating-input-group label:active {
    transform: scale(0.9);
}

.btn-check:checked + label {
    transform: scale(1.1);
    background: #198754!important;
    color: #fff!important;
}

.cursor-pointer { cursor: pointer; }

@media (max-width: 768px) {
    .tracking-stepper .step-label {
        display: none;
    }
    .tracking-stepper .step.active .step-label {
        display: block;
        position: absolute;
        width: 100px;
        left: 50%;
        transform: translateX(-50%);
        top: 50px;
    }
    .tracking-stepper {
        margin-bottom: 70px!important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tracking logic is now handled by includes/tracking-modal.php
    
    // Cancellation Logic
    let currentCancelOrderId = null;
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const cancelAlert = document.getElementById('cancelOrderAlert');

    window.confirmCancelOrder = function(orderId) {
        currentCancelOrderId = orderId;
        document.getElementById('cancelOrderIdText').textContent = orderId;
        cancelAlert.classList.add('d-none');
        cancelModal.show();
    };

    confirmCancelBtn.addEventListener('click', function() {
        if (!currentCancelOrderId) return;

        confirmCancelBtn.disabled = true;
        confirmCancelBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';

        fetch('api/cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: currentCancelOrderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                cancelAlert.textContent = data.message;
                cancelAlert.classList.remove('d-none');
                confirmCancelBtn.disabled = false;
                confirmCancelBtn.textContent = 'Yes, Cancel Order';
            }
        })
        .catch(err => {
            console.error('Cancellation error:', err);
            cancelAlert.textContent = 'An error occurred. Please try again.';
            cancelAlert.classList.remove('d-none');
            confirmCancelBtn.disabled = false;
            confirmCancelBtn.textContent = 'Yes, Cancel Order';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
