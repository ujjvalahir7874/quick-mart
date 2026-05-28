<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (isAdmin()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role'];

        // Handle Remember Me
        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 days
        }

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Unauthorized access or invalid credentials.";
    }
}

// Fetch admin email IDs for suggestions
$stmt_admin_emails = $pdo->query("SELECT DISTINCT email FROM users WHERE role = 'admin' ORDER BY email ASC");
$admin_emails = $stmt_admin_emails->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Secure Login - Quick mart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --transition: all 0.3s ease;
        }
        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light); 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            background-image: radial-gradient(circle at 20% 30%, rgba(16, 185, 129, 0.05) 0%, transparent 50%),
                              radial-gradient(circle at 80% 70%, rgba(16, 185, 129, 0.05) 0%, transparent 50%);
        }
        .card { 
            border: none; 
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
        }
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            border-color: var(--primary-color);
        }
        .btn-success {
            background-color: var(--primary-color);
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
        }
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
        }
        .input-group-text {
            background: none;
            border-left: none;
            border-radius: 0 0.75rem 0.75rem 0;
            cursor: pointer;
        }
        #adminPassword {
            border-right: none;
        }
        .brand-icon {
            width: 48px;
            height: 48px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="brand-icon">
                            <i class="bi bi-basket2-fill"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-1">Quick mart Admin</h2>
                        <p class="text-muted small">Please sign in to access the control panel</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0 rounded-4 py-3 small d-flex align-items-center">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Admin Email</label>
                            <input type="email" name="email" class="form-control" required placeholder="admin@quickmart.com" list="adminEmailSuggestions">
                            <datalist id="adminEmailSuggestions">
                                <?php foreach ($admin_emails as $email_suggestion): ?>
                                    <option value="<?= htmlspecialchars($email_suggestion) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-muted">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="adminPassword" class="form-control" required placeholder="••••••••">
                                <span class="input-group-text border-start-0" id="toggleAdminPassword">
                                    <i class="bi bi-eye text-muted"></i>
                                </span>
                            </div>
                            <div class="text-end mt-2">
                                <a href="../forgot_password.php" class="text-success small fw-medium text-decoration-none">Forgot password?</a>
                            </div>
                        </div>
                        <div class="mb-4 form-check d-flex align-items-center">
                            <input type="checkbox" name="remember_me" class="form-check-input mt-0 me-2" id="remember_me">
                            <label class="form-check-label small text-muted" for="remember_me">Remember this device</label>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mb-3">Sign In</button>
                    </form>
                    
                    <div class="text-center">
                        <a href="../index.php" class="text-muted text-decoration-none small d-inline-flex align-items-center hover-opacity">
                            <i class="bi bi-arrow-left me-2"></i> Back to storefront
                        </a>
                    </div>
                </div>
                <p class="text-center text-muted small mt-4">&copy; <?php echo date('Y'); ?> Quick mart Admin Dashboard</p>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('toggleAdminPassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('adminPassword');
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
</body>
</html>
