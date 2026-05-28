<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/header.php'; 

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
        $stmt->execute([$token, $expiry, $user['id']]);

        $base_url = get_setting('app_base_url', 'http://localhost/major/');
        $reset_link = rtrim($base_url, '/') . "/reset_password.php?token=" . $token;
        
        // Email body
        $subject = "Password Reset Request - Quick mart";
        $email_message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #28a745;'>Password Reset Request</h2>
                <p>Hello,</p>
                <p>You requested a password reset for your Quick mart account. Click the button below to reset it:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$reset_link' style='background-color: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Reset Password</a>
                </div>
                <p>If you didn't request this, please ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'>Team Quick mart</p>
            </div>
        ";

        if (sendEmail($email, $subject, $email_message)) {
            $message = "A password reset link has been sent to your email address.";
            if (get_setting('email_provider') === 'simulation') {
                $message .= " <br><br><strong>(Simulation Mode)</strong> Check <code>email_sim_messages.txt</code> or <a href='$reset_link' class='alert-link'>Click here to test the link</a>";
            }
        } else {
            $error = "Failed to send email. Please try again later.";
        }
    } else {
        $error = "No account found with that email address.";
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                            <i class="bi bi-shield-lock fs-1"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Forgot Password</h3>
                        <p class="text-muted">Enter your email to receive a reset link</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success border-0 rounded-4 p-4 shadow-sm mb-0">
                            <div class="d-flex">
                                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                                <div>
                                    <div class="fw-bold mb-1">Success!</div>
                                    <div class="smaller"><?php echo $message; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-outline-success rounded-pill px-4 fw-bold">
                                <i class="bi bi-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-3"></i>
                                <div class="smaller"><?php echo $error; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted">Email address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0 rounded-end-3 bg-light" required placeholder="name@example.com">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm transition-all mb-3">
                                Send Reset Link <i class="bi bi-arrow-right ms-2"></i>
                            </button>
                        </form>
                        <div class="text-center">
                            <a href="login.php" class="text-success small text-decoration-none fw-bold">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-subtle { background-color: #e8f5e9 !important; }
    .smaller { font-size: 0.85rem; }
    .transition-all { transition: all 0.3s ease; }
    .btn-success:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3) !important; }
</style>

<?php require_once 'includes/footer.php'; ?>
