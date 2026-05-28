<?php 
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

require_once 'includes/header.php'; 

$user_id = $_SESSION['user_id'];

// Handle customer re-reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_re_reply'])) {
    $parent_id = (int)$_POST['parent_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // Fetch original message to get subject and other info
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ? AND user_id = ?");
        $stmt->execute([$parent_id, $user_id]);
        $parent = $stmt->fetch();
        
        if ($parent) {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, parent_id, name, email, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, 'Unread')");
            $stmt->execute([
                $user_id, 
                $parent_id, 
                $parent['name'], 
                $parent['email'], 
                "Re: " . $parent['subject'], 
                $message
            ]);
            $msg_success = "Your reply has been sent to the admin.";
        }
    }
}

// Fetch all original messages by this user (where parent_id is NULL)
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE user_id = ? AND parent_id IS NULL ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll();

// Helper to get conversation thread
function getThread($pdo, $parent_id) {
    $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE parent_id = ? ORDER BY created_at ASC");
    $stmt->execute([$parent_id]);
    return $stmt->fetchAll();
}
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 animate__animated animate__fadeIn">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                        <li class="breadcrumb-item"><a href="account-settings.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Account</a></li>
                        <li class="breadcrumb-item active text-white small fw-600" aria-current="page">My Messages</li>
                    </ol>
                </nav>
                <h1 class="text-white fw-800 mb-1 display-5">My Messages</h1>
                <p class="text-white text-opacity-75 mb-0 fw-600">Communicate with our support team</p>
            </div>
            <div class="d-flex gap-2">
                <a href="contact.php" class="btn btn-white rounded-4 px-4 py-3 fw-800 shadow-lg transition-hover border-0">
                    <i class="bi bi-plus-circle-fill me-2 fs-5 text-success"></i> NEW MESSAGE
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <?php if (isset($msg_success)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 animate__animated animate__headShake" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                <div class="fw-800"><?php echo $msg_success; ?></div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- System Notifications / OTP Messages -->
    <?php
    $stmt = $pdo->prepare("SELECT o.id, o.status, o.delivery_otp, o.order_date, dp.name as partner_name, dp.mobile_no as partner_phone, dp.bike_number, dp.rating 
                           FROM orders o 
                           LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id 
                           WHERE o.user_id = ? AND o.status IN ('Shipped', 'Out for Delivery') AND o.delivery_otp IS NOT NULL 
                           ORDER BY o.order_date DESC");
    $stmt->execute([$user_id]);
    $otp_orders = $stmt->fetchAll();
    ?>

    <?php if (!empty($otp_orders)): ?>
        <div class="mb-5 animate__animated animate__fadeInUp">
            <h4 class="fw-800 mb-4 d-flex align-items-center">
                <i class="bi bi-bell-fill text-warning me-2"></i> Order Notifications
            </h4>
            <div class="row g-3">
                <?php foreach ($otp_orders as $oo): ?>
                    <?php if ($oo['status'] == 'Out for Delivery'): ?>
                        <!-- Pickup / On the Way Notification -->
                        <div class="col-12 mb-3">
                            <div class="card border-0 shadow-lg rounded-4 p-4" style="background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-left: 5px solid #198754 !important;">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-2 fw-800 small">
                                        <i class="bi bi-truck me-1"></i> Order Pickup / On the Way
                                    </div>
                                    <div class="text-muted small fw-600">
                                        <?php echo date('M d, H:i', strtotime($oo['order_date'])); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <h5 class="fw-800 mb-2">Hello 👋</h5>
                                    <p class="text-dark mb-1">I’m your delivery partner for <strong>Order #<?php echo $oo['id']; ?></strong>.</p>
                                    <p class="text-dark mb-3">I’ve picked up your order and I’m on the way. 🚴‍♂️</p>
                                    <div class="bg-light rounded-3 p-3 mb-3 border-0">
                                        <div class="row align-items-center">
                                            <div class="col-auto">
                                                <div class="bg-white rounded-circle p-2 shadow-sm">
                                                    <i class="bi bi-clock-history text-success fs-4"></i>
                                                </div>
                                            </div>
                                            <div class="col">
                                                <span class="text-muted small d-block fw-bold uppercase">Estimated delivery time</span>
                                                <span class="h5 fw-800 text-dark mb-0">25-35 Minutes</span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-0 italic">Thank you!</p>
                                </div>
                                
                                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-dark rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                            <i class="bi bi-person-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <span class="d-block small fw-800 text-dark">Name: <?php echo htmlspecialchars($oo['partner_name'] ?: 'Delivery Partner'); ?></span>
                                            <span class="d-block text-muted small fw-600">
                                                📞 Mobile: <?php 
                                                    $phone = $oo['partner_phone'] ?: '0000000000';
                                                    echo '****' . substr($phone, -4); 
                                                ?>
                                            </span>
                                            <span class="d-block text-muted small fw-600">🚲 Vehicle No: <?php echo htmlspecialchars($oo['bike_number'] ?: 'N/A'); ?></span>
                                            <span class="d-block text-warning small fw-800">⭐ Rating: <?php echo number_format($oo['rating'] ?: 0, 1); ?></span>
                                        </div>
                                    </div>
                                    <div class="bg-white rounded-3 px-3 py-2 border border-success border-dashed text-center">
                                        <span class="text-muted small d-block fw-bold mb-1">OTP CODE</span>
                                        <span class="h4 fw-800 text-success tracking-widest mb-0"><?php echo $oo['delivery_otp']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Standard Shipped / OTP Notification -->
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm rounded-4 p-4 h-100" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="bg-success bg-opacity-10 text-success rounded-3 p-2 fw-800 small">
                                        #<?php echo $oo['id']; ?> - <?php echo $oo['status']; ?>
                                    </div>
                                    <div class="text-muted small fw-600">
                                        <?php echo date('M d, H:i', strtotime($oo['order_date'])); ?>
                                    </div>
                                </div>
                                <h6 class="fw-800 mb-2">Delivery Verification Required</h6>
                                <p class="text-muted small mb-3">Your order is being delivered. Please share this OTP with the delivery agent to complete your order.</p>
                                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-dark rounded-circle text-white d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                            <i class="bi bi-person-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <span class="d-block small fw-800 text-dark">Name: <?php echo htmlspecialchars($oo['partner_name'] ?: 'Delivery Partner'); ?></span>
                                            <span class="d-block text-muted small fw-600">
                                                📞 Mobile: <?php 
                                                    $phone = $oo['partner_phone'] ?: '0000000000';
                                                    echo '****' . substr($phone, -4); 
                                                ?>
                                            </span>
                                            <span class="d-block text-muted small fw-600">🚲 Vehicle No: <?php echo htmlspecialchars($oo['bike_number'] ?: 'N/A'); ?></span>
                                            <span class="d-block text-warning small fw-800">⭐ Rating: <?php echo number_format($oo['rating'] ?: 0, 1); ?></span>
                                        </div>
                                    </div>
                                    <div class="bg-white rounded-3 px-3 py-2 border border-success border-dashed text-center">
                                        <span class="text-muted small d-block fw-bold mb-1">OTP CODE</span>
                                        <span class="h4 fw-800 text-success tracking-widest mb-0"><?php echo $oo['delivery_otp']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
        <div class="text-center py-5 animate__animated animate__fadeInUp">
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm" style="width: 140px; height: 140px;">
                <i class="bi bi-chat-left-dots text-success display-1"></i>
            </div>
            <h2 class="fw-800 mb-2">No messages found</h2>
            <p class="text-muted mb-5 fs-5">If you have any questions, feel free to contact our support team.</p>
            <a href="contact.php" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover btn-lg">
                CONTACT US <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($messages as $m): ?>
                <div class="col-12 animate__animated animate__fadeInUp">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-3">
                        <div class="card-header bg-white py-4 px-4 d-flex justify-content-between align-items-center border-0">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-success bg-opacity-10 rounded-4 p-3 text-center transition-hover" style="min-width: 65px;">
                                    <i class="bi bi-envelope-paper-fill text-success fs-3"></i>
                                </div>
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h5 class="mb-0 fw-800 text-dark"><?php echo htmlspecialchars($m['subject'] ?: '(No Subject)'); ?></h5>
                                        <span class="badge <?php echo $m['status'] === 'Replied' ? 'bg-success' : 'bg-secondary'; ?> rounded-pill small px-3 py-2 fw-800">
                                            <?php echo strtoupper($m['status']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <small class="text-muted fw-600 text-uppercase tracking-wider" style="font-size: 0.7rem;">
                                            <i class="bi bi-calendar3 me-1"></i> <?php echo date('M d, Y', strtotime($m['created_at'])); ?>
                                        </small>
                                        <span class="text-muted opacity-50">•</span>
                                        <small class="text-muted fw-600 text-uppercase tracking-wider" style="font-size: 0.7rem;">
                                            <i class="bi bi-clock me-1"></i> <?php echo date('h:i A', strtotime($m['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-light rounded-4 px-4 py-2 border-0 fw-800 shadow-sm transition-hover" type="button" data-bs-toggle="collapse" data-bs-target="#msg-<?php echo $m['id']; ?>">
                                <i class="bi bi-chat-left-text-fill me-2 text-success"></i> VIEW THREAD
                            </button>
                        </div>
                        <div class="collapse" id="msg-<?php echo $m['id']; ?>">
                            <div class="card-body bg-light bg-opacity-50 p-4 p-md-5 border-top">
                                <div class="conversation-thread mb-4">
                                    <!-- Original Message -->
                                    <div class="chat-bubble user-bubble mb-5">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="flex-shrink-0 bg-success rounded-4 shadow-lg d-flex align-items-center justify-content-center transition-hover" style="width: 45px; height: 45px; background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                                                <i class="bi bi-person-fill text-white fs-5"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="bg-white p-4 rounded-4 shadow-sm border-0 position-relative bubble-content">
                                                    <p class="mb-0 fw-600 text-dark"><?php echo nl2br(htmlspecialchars($m['message'])); ?></p>
                                                </div>
                                                <small class="text-muted fw-600 mt-2 d-block ms-2 smaller">YOU • <?php echo date('h:i A', strtotime($m['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Admin Reply -->
                                    <?php if ($m['admin_reply']): ?>
                                        <div class="chat-bubble admin-bubble mb-5">
                                            <div class="d-flex align-items-start justify-content-end gap-3">
                                                <div class="flex-grow-1 text-end">
                                                    <div class="bg-success text-white p-4 rounded-4 shadow-lg border-0 position-relative bubble-content text-start" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                                                        <p class="mb-0 fw-600"><?php echo nl2br(htmlspecialchars($m['admin_reply'])); ?></p>
                                                    </div>
                                                    <small class="text-muted fw-600 mt-2 d-block me-2 smaller">ADMIN • <?php echo date('h:i A', strtotime($m['replied_at'])); ?></small>
                                                </div>
                                                <div class="flex-shrink-0 bg-dark rounded-4 shadow-lg d-flex align-items-center justify-content-center transition-hover" style="width: 45px; height: 45px;">
                                                    <i class="bi bi-shield-lock-fill text-white fs-5"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Thread (Replies & Re-replies) -->
                                    <?php 
                                    $thread = getThread($pdo, $m['id']);
                                    foreach ($thread as $t): 
                                    ?>
                                        <div class="chat-bubble user-bubble mb-5">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="flex-shrink-0 bg-success rounded-4 shadow-lg d-flex align-items-center justify-content-center transition-hover" style="width: 45px; height: 45px; background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                                                    <i class="bi bi-person-fill text-white fs-5"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="bg-white p-4 rounded-4 shadow-sm border-0 position-relative bubble-content">
                                                        <p class="mb-0 fw-600 text-dark"><?php echo nl2br(htmlspecialchars($t['message'])); ?></p>
                                                    </div>
                                                    <small class="text-muted fw-600 mt-2 d-block ms-2 smaller">YOU • <?php echo date('h:i A', strtotime($t['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($t['admin_reply']): ?>
                                            <div class="chat-bubble admin-bubble mb-5">
                                                <div class="d-flex align-items-start justify-content-end gap-3">
                                                    <div class="flex-grow-1 text-end">
                                                        <div class="bg-success text-white p-4 rounded-4 shadow-lg border-0 position-relative bubble-content text-start" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                                                            <p class="mb-0 fw-600"><?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></p>
                                                        </div>
                                                        <small class="text-muted fw-600 mt-2 d-block me-2 smaller">ADMIN • <?php echo date('h:i A', strtotime($t['replied_at'])); ?></small>
                                                    </div>
                                                    <div class="flex-shrink-0 bg-dark rounded-4 shadow-lg d-flex align-items-center justify-content-center transition-hover" style="width: 45px; height: 45px;">
                                                        <i class="bi bi-shield-lock-fill text-white fs-5"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Re-reply Form -->
                                <div class="mt-5 pt-5 border-top border-dark border-opacity-10">
                                    <form action="my-messages.php" method="POST">
                                        <input type="hidden" name="parent_id" value="<?php echo $m['id']; ?>">
                                        <div class="bg-white rounded-4 p-4 shadow-lg border-0 animate__animated animate__fadeInUp">
                                            <label class="form-label small fw-800 text-muted text-uppercase mb-3 tracking-wider"><i class="bi bi-reply-fill me-2"></i>Quick Reply</label>
                                            <textarea name="message" class="form-control border-0 bg-light rounded-4 p-4 mb-4 shadow-none fw-600" rows="3" placeholder="Type your follow-up message here..." required style="resize: none;"></textarea>
                                            <div class="text-end">
                                                <button type="submit" name="send_re_reply" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover">
                                                    SEND REPLY <i class="bi bi-send-fill ms-2"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.tracking-wider { letter-spacing: 0.8px; }
.transition-hover {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.transition-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 1rem 3rem rgba(0,0,0,0.12)!important;
}
.bubble-content {
    max-width: 85%;
    display: inline-block;
}
.user-bubble .bubble-content::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 15px;
    border-width: 10px 10px 10px 0;
    border-style: solid;
    border-color: transparent white transparent transparent;
}
.admin-bubble .bubble-content::after {
    content: '';
    position: absolute;
    right: -10px;
    top: 15px;
    border-width: 10px 0 10px 10px;
    border-style: solid;
    border-color: transparent transparent transparent #198754;
}
.smaller { font-size: 0.7rem; letter-spacing: 0.5px; }
.btn-white {
    background: #fff;
    color: #198754;
}
.btn-white:hover {
    background: #f8f9fa;
    color: #157347;
}
.breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255,255,255,0.5);
}
.card-header .btn[aria-expanded="true"] {
    background: #198754!important;
    color: white!important;
}
.card-header .btn[aria-expanded="true"] .bi {
    color: white!important;
}
.form-control:focus {
    box-shadow: 0 0.5rem 1.5rem rgba(25, 135, 84, 0.1);
    background-color: #fff!important;
    border: 1px solid #198754!important;
}
</style>

<?php require_once 'includes/footer.php'; ?>