<?php
session_start();
require_once '../config/db.php';

function normalize_delivery_mobile($mobile) {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    return $digits;
}

if (isset($_SESSION['delivery_partner_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_input = trim($_POST['mobile']);
    $normalized_mobile = normalize_delivery_mobile($login_input);
    $password = $_POST['password'];

    $user = null;
    if (strlen($normalized_mobile) !== 10) {
        $error = "Enter a valid mobile number.";
    } else {
        $stmt = $pdo->query("SELECT * FROM delivery_persons ORDER BY id DESC");
        $partners = $stmt->fetchAll();

        foreach ($partners as $candidate) {
            if (normalize_delivery_mobile($candidate['mobile_no'] ?? '') === $normalized_mobile) {
                $user = $candidate;
                break;
            }
        }
    }

    if (!$error && $user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
        if (($user['mobile_no'] ?? '') !== $normalized_mobile) {
            $pdo->prepare("UPDATE delivery_persons SET mobile_no = ? WHERE id = ?")->execute([$normalized_mobile, $user['id']]);
        }
        $_SESSION['delivery_partner_id'] = $user['id'];
        $_SESSION['delivery_partner_name'] = $user['name'];

        // Handle Remember Me
        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("UPDATE delivery_persons SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 days
        }

        header("Location: index.php");
        exit;
    } elseif (!$error) {
        $error = "Invalid credentials";
    }
}

// Fetch existing delivery partner mobile numbers for suggestions
$stmt_suggestions = $pdo->query("SELECT mobile_no FROM delivery_persons ORDER BY name ASC");
$partners = $stmt_suggestions->fetchAll();
$login_suggestions = [];
foreach ($partners as $p) {
    $normalized_suggestion = normalize_delivery_mobile($p['mobile_no'] ?? '');
    if (strlen($normalized_suggestion) === 10) {
        $login_suggestions[] = $normalized_suggestion;
    }
}
$login_suggestions = array_unique($login_suggestions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Partner Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8fafc; 
            font-family: 'Outfit', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0; 
            padding: 20px;
        }
        .login-card { 
            width: 100%; 
            max-width: 420px; 
            padding: 2.5rem; 
            background: #fff; 
            border-radius: 30px; 
            box-shadow: 0 15px 35px -5px rgba(0,0,0,0.05); 
            border: 1px solid #f0f0f0;
        }
        .app-brand { 
            color: #10b981; 
            font-weight: 800; 
            font-size: 1.75rem; 
            text-align: center; 
            margin-bottom: 0.5rem; 
        }
        .brand-subtitle {
            text-align: center;
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .form-control { 
            border-radius: 15px; 
            padding: 12px 18px; 
            border: 1px solid #e2e8f0; 
            background: #f8fafc; 
            font-weight: 500;
            transition: all 0.2s;
        }
        .form-control:focus { 
            background: #fff;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        .btn-primary { 
            background-color: #10b981; 
            border: none; 
            border-radius: 15px; 
            padding: 14px; 
            font-weight: 700; 
            width: 100%; 
            margin-top: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            transition: all 0.2s;
        }
        .btn-primary:hover { 
            background-color: #059669; 
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.25);
        }
        .footer-link { 
            text-align: center; 
            margin-top: 2rem; 
            color: #64748b; 
            font-size: 0.95rem; 
        }
        .footer-link a { 
            color: #10b981; 
            text-decoration: none; 
            font-weight: 700; 
        }
        .alert {
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="app-brand">
        <i class="bi bi-rocket-takeoff-fill me-2"></i>Flash Delivery
    </div>
    <div class="brand-subtitle">Partner Portal Access</div>

    <?php if($error): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Mobile Number</label>
            <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile or +91 number" pattern="(?:\+91|0)?[6-9][0-9]{9}" list="deliveryLoginSuggestions" title="Enter a valid 10-digit mobile number. Leading 0 or +91 is also allowed." required>
            <datalist id="deliveryLoginSuggestions">
                <?php foreach ($login_suggestions as $suggestion): ?>
                    <option value="<?= htmlspecialchars($suggestion) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
        </div>
        <div class="mb-4 form-check">
            <input type="checkbox" name="remember_me" class="form-check-input" id="rememberMe">
            <label class="form-check-label small text-muted fw-500" for="rememberMe">Remember me on this device</label>
        </div>
        <button type="submit" class="btn btn-primary">Sign In</button>
    </form>

    <div class="footer-link">
        New Partner? <a href="register.php">Register Here</a>
    </div>
</div>

</body>
</html>
