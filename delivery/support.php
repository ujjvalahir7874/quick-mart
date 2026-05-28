<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['delivery_partner_id'])) {
    header("Location: login.php");
    exit;
}

$dp_id = $_SESSION['delivery_partner_id'];
$partner = $pdo->query("SELECT * FROM delivery_persons WHERE id = $dp_id")->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = "Please fill all fields.";
    } else {
        try {
            // Check if contact_messages table has delivery_person_id column, if not, use a generic way or rely on name/email
            // Assuming contact_messages has: id, name, email, subject, message, status, created_at
            // Ideally we should add delivery_person_id to contact_messages to link it properly, 
            // but for now we'll put "Delivery Partner: Name" in the name field.
            
            $name = "DP: " . ($partner['name'] ?? 'Unknown');
            $email = $partner['email'] ?? ($partner['mobile_no'] . '@partner.com'); // Fallback or use mobile if email not present
            
            // Insert into contact_messages
            // Check schema of contact_messages first. Based on admin/contact-messages.php it seems standard.
            // Let's try to add a column 'delivery_person_id' if it doesn't exist?
            // For now, let's just insert as a normal message.
            
            $stmt = $pdo->prepare("INSERT INTO contact_messages (delivery_person_id, name, email, subject, message, status) VALUES (?, ?, ?, ?, ?, 'Unread')");
            $stmt->execute([$dp_id, $name, $email, $subject, $message]);
            
            $success = "Your message has been sent to Admin Support.";
        } catch (PDOException $e) {
            $error = "Failed to send message: " . $e->getMessage();
        }
    }
}

// Fetch message history (Both linked by ID and legacy by email)
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE delivery_person_id = ? OR email = ? ORDER BY created_at DESC");
$email = $partner['email'] ?? ($partner['mobile_no'] . '@partner.com');
$stmt->execute([$dp_id, $email]);
$conversations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Support - Delivery Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f5f7; font-family: 'Outfit', sans-serif; padding-bottom: 90px; color: #1f2937; }
        .header-section { background: white; padding: 1.5rem; border-radius: 0 0 30px 30px; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 16px 20px; border-radius: 25px 25px 0 0; display: flex; justify-content: space-between; align-items: center; z-index: 1000; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
        .nav-item { display: flex; flex-direction: column; align-items: center; color: #9ca3af; text-decoration: none; font-size: 0.75rem; font-weight: 600; transition: all 0.2s; }
        .nav-item i { font-size: 1.4rem; margin-bottom: 4px; }
        .nav-item.active { color: #10b981; transform: translateY(-3px); }
        
        .support-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; border: 1px solid #f0f0f0; }
        .icon-circle { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 15px; }
        
        /* Conversation Styles */
        .admin-reply {
            position: relative;
        }
        .fw-500 { font-weight: 500; }
        .extra-small { font-size: 0.75rem; }
        .transition-scale { transition: transform 0.2s; }
        .transition-scale:active { transform: scale(0.95); }
    </style>
</head>
<body>

    <div class="header-section text-center">
        <div class="icon-circle bg-primary bg-opacity-10 text-primary mx-auto">
            <i class="bi bi-headset"></i>
        </div>
        <h4 class="fw-bold mb-1">Help & Support</h4>
        <p class="text-muted small mb-0">We are here to help you 24/7</p>
    </div>

    <div class="container px-3">
        <?php if ($success): ?>
            <div class="alert alert-success rounded-4 border-0 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4 border-0 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Contact Info Cards -->
        <div class="row g-3 mb-4">
            <!-- Location Card -->
            <div class="col-12 col-md-4">
                <div class="support-card h-100 text-center py-4 mb-0">
                    <div class="icon-circle bg-success bg-opacity-10 text-success mx-auto mb-3" style="width: 70px; height: 70px;">
                        <i class="bi bi-geo-alt fs-2"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Our Location</h5>
                    <p class="text-muted small mb-0 px-3">
                        179 vijaya nager 1 udhna,<br>surat, pin 394210
                    </p>
                </div>
            </div>

            <!-- Helpline Card -->
            <div class="col-6 col-md-4">
                <a href="tel:+919173791005" class="text-decoration-none">
                    <div class="support-card h-100 text-center py-4 mb-0 transition-scale">
                        <div class="icon-circle bg-primary bg-opacity-10 text-primary mx-auto mb-3" style="width: 70px; height: 70px;">
                            <i class="bi bi-telephone fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Helpline</h5>
                        <p class="text-muted small mb-0">
                            +91 9173791005
                        </p>
                    </div>
                </a>
            </div>

            <!-- Email Card -->
            <div class="col-6 col-md-4">
                <a href="mailto:QuickMart@gmail.com" class="text-decoration-none">
                    <div class="support-card h-100 text-center py-4 mb-0 transition-scale">
                        <div class="icon-circle bg-warning bg-opacity-10 text-warning mx-auto mb-3" style="width: 70px; height: 70px;">
                            <i class="bi bi-envelope fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-2">Email Us</h5>
                        <p class="text-muted small mb-0 text-break">
                            QuickMart@gmail.com
                        </p>
                    </div>
                </a>
            </div>
        </div>

        <!-- Message Form -->
        <div class="support-card">
            <h6 class="fw-bold mb-3 d-flex align-items-center">
                <i class="bi bi-envelope-fill me-2 text-primary"></i> Send a Message
            </h6>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Subject</label>
                    <select name="subject" class="form-select rounded-3 bg-light border-0 py-2">
                        <option value="Order Related Issue">Order Related Issue</option>
                        <option value="Payment Issue">Payment Issue</option>
                        <option value="App Issue">App Issue</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Message</label>
                    <textarea name="message" class="form-control rounded-4 bg-light border-0 p-3" rows="4" placeholder="Describe your issue..." required></textarea>
                </div>
                <button type="submit" class="btn btn-dark w-100 rounded-pill py-3 fw-bold">Send Message</button>
            </form>
        </div>

        <!-- Conversation History -->
        <?php if (!empty($conversations)): ?>
            <h6 class="fw-bold mb-3 mt-4 d-flex align-items-center">
                <i class="bi bi-chat-dots-fill me-2 text-success"></i> Recent Conversations
            </h6>
            <?php foreach ($conversations as $conv): ?>
                <div class="support-card mb-3 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($conv['subject']) ?></span>
                        <small class="text-muted"><?= date('M d, h:i A', strtotime($conv['created_at'])) ?></small>
                    </div>
                    <p class="small mb-2 text-dark fw-500"><?= nl2br(htmlspecialchars($conv['message'])) ?></p>
                    
                    <?php if ($conv['admin_reply']): ?>
                        <div class="admin-reply bg-success bg-opacity-10 rounded-3 p-3 mt-2 border-start border-success border-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="fw-bold text-success"><i class="bi bi-person-badge me-1"></i> Admin Response</small>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('M d, h:i A', strtotime($conv['replied_at'])) ?></small>
                            </div>
                            <p class="small mb-0 text-dark"><?= nl2br(htmlspecialchars($conv['admin_reply'])) ?></p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2 mt-2 bg-light rounded-3">
                            <small class="text-muted"><i class="bi bi-clock me-1"></i> Waiting for admin response...</small>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="index.php" class="nav-item">
            <i class="bi bi-grid"></i>
            <span>Home</span>
        </a>
        <a href="wallet.php" class="nav-item">
            <i class="bi bi-wallet2"></i>
            <span>Wallet</span>
        </a>
        <a href="history.php" class="nav-item">
            <i class="bi bi-clock-history"></i>
            <span>History</span>
        </a>
        <a href="profile.php" class="nav-item active">
            <i class="bi bi-person-fill"></i>
            <span>Profile</span>
        </a>
    </div>

</body>
</html>
