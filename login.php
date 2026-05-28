<?php 
require_once 'includes/header.php'; 

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === 'admin') {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_role'] = $user['role'];
            header("Location: admin/dashboard.php");
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['cart'] = []; // ensure fresh cart per account after login
            
            // Redirect to checkout if they came from there, else home
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
            header("Location: " . $redirect);
        }
        
        // Handle Remember Me
        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            setcookie('remember_token', $token, time() + (86400 * 30), "/");
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}

// Fetch existing user email IDs for suggestions
$stmt_emails = $pdo->query("SELECT DISTINCT email FROM users ORDER BY email ASC");
$login_emails = $stmt_emails->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden animate__animated animate__fadeIn">
                <div class="py-5 text-center position-relative" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                    <div class="bg-white d-inline-flex rounded-circle p-3 mb-3 shadow-sm position-relative" style="width: 80px; height: 80px; align-items: center; justify-content: center;">
                        <i class="bi bi-person-check text-success fs-1"></i>
                    </div>
                    <h3 class="fw-800 text-white mb-1 position-relative" style="letter-spacing: -1px;">Welcome Back</h3>
                    <p class="text-white-50 small mb-0 position-relative">Login to your Quick mart account</p>
                </div>
                <div class="card-body p-4 p-md-5">
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-success border-0 rounded-4 small animate__animated animate__fadeInDown mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($_GET['msg']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-4 small animate__animated animate__shakeX mb-4">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control bg-light border-start-0 rounded-end-3 py-2 ps-0" placeholder="name@example.com" list="loginEmailSuggestions" required>
                                <datalist id="loginEmailSuggestions">
                                    <?php foreach ($login_emails as $email_suggestion): ?>
                                        <option value="<?= htmlspecialchars($email_suggestion) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <label class="form-label fw-bold small text-muted text-uppercase mb-2" style="letter-spacing: 0.5px;">Password</label>
                                <a href="forgot_password.php" class="small text-success text-decoration-none fw-bold">Forgot?</a>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-3 px-3"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" id="password" class="form-control bg-light border-start-0 py-2 ps-0" placeholder="••••••••" required>
                                <button class="btn btn-light border-start-0 rounded-end-3 px-3" type="button" id="togglePassword">
                                    <i class="bi bi-eye text-muted"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4 form-check">
                            <input type="checkbox" name="remember_me" class="form-check-input shadow-none cursor-pointer" id="remember">
                            <label class="form-check-label small text-muted cursor-pointer" for="remember">Remember me</label>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-3 rounded-4 fw-800 shadow-sm transition-hover">
                            SIGN IN <i class="bi bi-box-arrow-in-right ms-2"></i>
                        </button>
                    </form>

                    <div class="text-center mt-5 pt-4 border-top">
                        <p class="text-muted small mb-0">Don't have an account? <a href="register.php" class="text-success fw-800 text-decoration-none ms-1">Create Account</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
