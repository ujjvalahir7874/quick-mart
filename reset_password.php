<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/header.php'; 

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Verify token
$stmt = $pdo->prepare("SELECT id, reset_token_expiry FROM users WHERE reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    // Manual expiry check to handle timezone differences
    $current_time = time();
    $expiry_time = strtotime($user['reset_token_expiry']);
    
    if ($current_time > $expiry_time) {
        $user = false;
        $error = "Invalid or expired reset link. (Expired at: " . date('Y-m-d H:i:s', $expiry_time) . "). Please request a new one.";
    }
} else {
    $error = "Invalid or expired reset link. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Password strength validation
        $has_upper = preg_match('@[A-Z]@', $password);
        $has_lower = preg_match('@[a-z]@', $password);
        $has_special = preg_match('@[^\w]@', $password);
        $has_digit = preg_match('@[0-9]@', $password);

        if (!$has_upper || !$has_lower || !$has_special || !$has_digit || strlen($password) < 8) {
            $error = "Password must be at least 8 characters long and include capital letters, lowercase letters, numbers, and special characters.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            
            $success = "Your password has been reset successfully! You can now log in.";
        }
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
                            <i class="bi bi-key fs-1"></i>
                        </div>
                        <h3 class="fw-bold mb-1">Reset Password</h3>
                        <p class="text-muted">Create a secure new password</p>
                    </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success border-0 rounded-4 p-4 shadow-sm mb-0">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold mb-1">Successfully Reset!</div>
                                    <div class="smaller"><?php echo $success; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-3 mb-4 d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-3"></i>
                                <div class="smaller"><?php echo $error; ?></div>
                            </div>
                            <?php if (strpos($error, 'Invalid or expired') !== false): ?>
                                <div class="text-center mt-3">
                                    <a href="forgot_password.php" class="btn btn-outline-success rounded-pill px-4 fw-bold">
                                        <i class="bi bi-arrow-repeat me-2"></i>Request New Link
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($user && !$success): ?>
                            <form method="POST" class="mt-2">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" name="password" id="password" class="form-control border-start-0 bg-light" required minlength="8" placeholder="At least 8 characters">
                                        <button class="btn btn-light border border-start-0 rounded-end-3 text-muted" type="button" id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small text-muted mt-2">At least 8 chars with capital, small, digit, and special char.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-bold text-muted">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="bi bi-shield-check text-muted"></i></span>
                                        <input type="password" name="confirm_password" class="form-control border-start-0 rounded-end-3 bg-light" required minlength="8" placeholder="Re-type your password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow-sm transition-all mb-3">
                                    Reset Password <i class="bi bi-shield-lock ms-2"></i>
                                </button>
                            </form>
                        <?php endif; ?>
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

<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
