<?php 
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $user_id = $_SESSION['user_id'] ?? null;

    if (!empty($name) && !empty($email) && !empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $email, $subject, $message]);
            header("Location: contact.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Something went wrong. Please try again later.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch existing email IDs for suggestions
$stmt_emails = $pdo->query("SELECT DISTINCT email FROM contact_messages ORDER BY email ASC");
$existing_emails = $stmt_emails->fetchAll(PDO::FETCH_COLUMN);

require_once 'includes/header.php'; 
?>
<style>
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.transition-hover {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.transition-hover:hover {
    transform: translateY(-8px);
    box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.15)!important;
}
.form-control {
    border: 1px solid transparent;
    transition: all 0.3s ease;
}
.form-control:focus {
    box-shadow: 0 0.5rem 1.5rem rgba(25, 135, 84, 0.1);
    background-color: #fff!important;
    border-color: #198754!important;
}
.btn-success {
    background: linear-gradient(135deg, #198754 0%, #157347 100%);
    border: none;
}
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
</style>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="text-center animate__animated animate__fadeIn">
            <nav aria-label="breadcrumb" class="d-flex justify-content-center">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                    <li class="breadcrumb-item active text-white small fw-600" aria-current="page">Contact Us</li>
                </ol>
            </nav>
            <h1 class="text-white fw-800 mb-2 display-4">Get in <span class="text-white opacity-75">Touch</span></h1>
            <p class="text-white text-opacity-75 mb-0 fw-600 mx-auto" style="max-width: 600px;">Have questions about our products or services? We're here to help you 24/7 with the best organic support.</p>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4 mb-5">
        <!-- Contact Information Cards -->
        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp">
            <div class="card border-0 shadow-lg rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 mb-4 mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <i class="bi bi-geo-alt fs-1"></i>
                </div>
                <h5 class="fw-800 mb-3">Our Location</h5>
                <p class="text-muted fw-600 small mb-0">179 vijaya nager 1 udhna,<br>surat, pin 394210</p>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="card border-0 shadow-lg rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3 mb-4 mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <i class="bi bi-telephone fs-1"></i>
                </div>
                <h5 class="fw-800 mb-3">Helpline</h5>
                <p class="text-muted fw-600 small mb-0">+91 9173791005</p>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="card border-0 shadow-lg rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="bg-warning bg-opacity-10 text-warning-emphasis rounded-circle p-3 mb-4 mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <i class="bi bi-envelope fs-1"></i>
                </div>
                <h5 class="fw-800 mb-3">Email Us</h5>
                <p class="text-muted fw-600 small mb-0">QuickMart@gmail.com</p>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="card border-0 shadow-lg rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3 mb-4 mx-auto d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <i class="bi bi-clock fs-1"></i>
                </div>
                <h5 class="fw-800 mb-3">Working Hours</h5>
                <p class="text-muted fw-600 small mb-0">Mon - Sat: 9am - 9pm<br>Sun: 10am - 6pm</p>
            </div>
        </div>
    </div>

    <div class="row g-5 align-items-center">
        <!-- Contact Form -->
        <div class="col-lg-6 animate__animated animate__fadeInLeft">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden tilt-card">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-4">Send us a Message</h3>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success border-0 rounded-3 mb-4 animate__animated animate__tada">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                <div>
                                    <h6 class="fw-bold mb-0">Message Sent!</h6>
                                    <small>We'll get back to you within 24 hours.</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-800 small text-muted text-uppercase tracking-wider">Your Name</label>
                                <div class="input-group shadow-sm rounded-4 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-person text-success"></i></span>
                                    <input type="text" name="name" class="form-control bg-light border-0 py-3 fw-600" placeholder="Your name" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-800 small text-muted text-uppercase tracking-wider">Email Address</label>
                                <div class="input-group shadow-sm rounded-4 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-envelope text-success"></i></span>
                                    <input type="email" name="email" class="form-control bg-light border-0 py-3 fw-600" placeholder="name@example.com" list="emailSuggestions" required>
                                    <datalist id="emailSuggestions">
                                        <?php foreach ($existing_emails as $email_suggestion): ?>
                                            <option value="<?= htmlspecialchars($email_suggestion) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-800 small text-muted text-uppercase tracking-wider">Subject</label>
                            <div class="input-group shadow-sm rounded-4 overflow-hidden">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-info-circle text-success"></i></span>
                                <input type="text" name="subject" class="form-control bg-light border-0 py-3 fw-600" placeholder="How can we help?" required>
                            </div>
                        </div>
                        <div class="mb-5">
                            <label class="form-label fw-800 small text-muted text-uppercase tracking-wider">Message</label>
                            <div class="input-group shadow-sm rounded-4 overflow-hidden">
                                <span class="input-group-text bg-light border-0 align-items-start pt-3"><i class="bi bi-chat-left-text text-success"></i></span>
                                <textarea name="message" class="form-control bg-light border-0 py-3 fw-600" rows="5" placeholder="Tell us more about your inquiry..." required></textarea>
                            </div>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-success btn-lg px-5 py-3 rounded-4 shadow-lg transition-hover w-100 fw-800">
                            SEND MESSAGE <i class="bi bi-send-fill ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Map Section -->
        <div class="col-lg-6 animate__animated animate__fadeInRight">
            <div class="rounded-4 shadow-lg overflow-hidden position-relative" style="height: 500px;">
                <!-- Modern Map Placeholder with Overlay -->
                <div class="w-100 h-100" style="background: url('https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?auto=format&fit=crop&w=1000&q=80') center/cover;">
                    <div class="position-absolute inset-0 bg-dark opacity-25"></div>
                    <div class="position-absolute top-50 start-50 translate-middle text-center w-100 p-4">
                        <div class="bg-white d-inline-block p-4 rounded-4 shadow-lg animate__animated animate__pulse animate__infinite">
                            <i class="bi bi-geo-alt-fill text-danger display-4 mb-3 d-block"></i>
                            <h5 class="fw-bold mb-1">Find Us Here</h5>
                            <p class="text-muted small mb-3">Surat, Gujarat, India</p>
                            <a href="https://maps.google.com" target="_blank" class="btn btn-outline-danger btn-sm rounded-pill px-4">Open in Maps</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
