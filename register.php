<?php 
require_once 'includes/header.php'; 
$allow_regs = (int)(get_setting('allow_registrations', '1') ?? 1);
if ($allow_regs !== 1) {
    echo '<div class="container py-5"><div class="alert alert-warning rounded-4 shadow-sm">New registrations are currently disabled. Please try again later.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $password_plain = $_POST['password'];

    // Password strength validation
    $has_upper = preg_match('@[A-Z]@', $password_plain);
    $has_lower = preg_match('@[a-z]@', $password_plain);
    $has_special = preg_match('@[^\w]@', $password_plain);
    $has_digit = preg_match('@[0-9]@', $password_plain);

    if (!$has_upper || !$has_lower || !$has_special || !$has_digit || strlen($password_plain) < 8) {
        $error = "Password must be at least 8 characters long and include capital letters, lowercase letters, numbers, and special characters.";
    } else {
        $password = password_hash($password_plain, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $password]);
            $user_id = $pdo->lastInsertId();

            // Create wallet for the new user
            $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)")->execute([$user_id]);
            
            $pdo->commit();
            header("Location: login.php?msg=Registration successful! Please login.");
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = "Email already exists.";
            } else {
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden animate__animated animate__fadeIn tilt-card">
                <div class="py-5 text-center position-relative" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                    <div class="bg-white d-inline-flex rounded-circle p-3 mb-3 shadow-sm position-relative" style="width: 80px; height: 80px; align-items: center; justify-content: center;">
                        <i class="bi bi-person-plus text-success fs-1"></i>
                    </div>
                    <h3 class="fw-800 text-white mb-1 position-relative" style="letter-spacing: -1px;">Create Account</h3>
                    <p class="text-white-50 small mb-0 position-relative">Join the Quick mart family today</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-4 small animate__animated animate__shakeX d-flex align-items-center bg-danger-subtle text-danger-emphasis">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" name="full_name" class="form-control bg-light border-start-0 rounded-end-3 py-2 ps-0" placeholder="Your full name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control bg-light border-start-0 rounded-end-3 py-2 ps-0" placeholder="name@example.com" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" id="password" class="form-control bg-light border-start-0 py-2 ps-0" placeholder="••••••••" required minlength="8">
                                <button class="btn btn-light border-start-0 rounded-end-3 px-3" type="button" id="togglePassword">
                                    <i class="bi bi-eye text-muted"></i>
                                </button>
                            </div>
                            <div class="form-text small text-muted mt-2">At least 8 characters with capital, small, digit, and special char.</div>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" class="form-check-input shadow-none cursor-pointer" id="terms" required>
                            <label class="form-check-label small text-muted cursor-pointer" for="terms">
                                I agree to the <a href="#" class="text-success text-decoration-none fw-bold">Terms</a> and <a href="#" class="text-success text-decoration-none fw-bold">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-3 rounded-4 fw-800 shadow-sm transition-hover">
                            CREATE ACCOUNT <i class="bi bi-check-circle ms-2"></i>
                        </button>
                    </form>

                    <div class="text-center mt-5 pt-4 border-top">
                        <p class="text-muted small mb-0">Already have an account? <a href="login.php" class="text-success fw-800 text-decoration-none ms-1">Sign In</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
