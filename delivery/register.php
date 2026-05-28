<?php
// session_start();
require_once '../config/db.php';

function normalize_delivery_mobile($mobile) {
    $digits = preg_replace('/\D+/', '', (string)$mobile);
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    return $digits;
}

$error = '';
$allow_regs = (int)(get_setting('allow_registrations', '1') ?? 1);
if ($allow_regs !== 1) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Registrations Closed</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-6"><div class="alert alert-warning rounded-4 shadow-sm">New partner registrations are currently disabled. Please try again later.</div><a href="login.php" class="btn btn-success rounded-pill">Back to Login</a></div></div></div></body></html>';
    exit;
}

$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $mobile = normalize_delivery_mobile(trim($_POST['mobile']));

    $password = $_POST['password'];
    $bike_number = trim($_POST['bike_number']);

    if (strlen($mobile) !== 10) {
        $error = "Enter a valid 10-digit mobile number.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $pdo->query("SELECT id, mobile_no FROM delivery_persons");
        $existing_partners = $stmt->fetchAll();
        $mobile_exists = false;

        foreach ($existing_partners as $existing_partner) {
            if (normalize_delivery_mobile($existing_partner['mobile_no'] ?? '') === $mobile) {
                $mobile_exists = true;
                break;
            }
        }
    }

    if (!$error && $mobile_exists) {
        $error = "Mobile number already registered.";
    } elseif (!$error) {
        $upload_dir = realpath(__DIR__ . '/../uploads/documents') . DIRECTORY_SEPARATOR;
        if (!$upload_dir) {
            $target_dir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $upload_dir = realpath($target_dir) . DIRECTORY_SEPARATOR;
        }

        $doc_aadhaar = '';
        $doc_license = '';
        $doc_rc = '';
        $doc_photo = '';

        $files_to_upload = [
            'aadhaar' => &$doc_aadhaar,
            'license' => &$doc_license,
            'rc' => &$doc_rc,
            'photo' => &$doc_photo
        ];

        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        $upload_errors_list = [];

        foreach ($files_to_upload as $key => &$var) {
            if (isset($_FILES[$key]) && $_FILES[$key]['error'] == UPLOAD_ERR_OK) {
                    $ext = strtolower(trim(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION)));
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
                    if (in_array($ext, $allowed_exts)) {
                    $filename = $key . '_' . preg_replace('/[^0-9]/', '', $mobile) . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES[$key]['tmp_name'], $upload_dir . $filename)) {
                        $var = 'uploads/documents/' . $filename;
                    } else {
                        $upload_errors_list[] = "Failed to move $key";
                    }
                } else {
                    $upload_errors_list[] = "Invalid type for $key";
                }
            } elseif (isset($_FILES[$key]) && $_FILES[$key]['error'] != UPLOAD_ERR_NO_FILE) {
                $upload_errors_list[] = "Error uploading $key (Code: ".$_FILES[$key]['error'].")";
            }
        }

        if (!empty($upload_errors_list) && empty($doc_aadhaar) && empty($doc_license) && empty($doc_rc) && empty($doc_photo)) {
            $error = "Document upload failed: " . implode(", ", $upload_errors_list);
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO delivery_persons (name, mobile_no, password_hash, bike_number, status, is_verified, doc_aadhaar, doc_license, doc_rc, doc_photo) VALUES (?, ?, ?, ?, 'Offline', 0, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $mobile, $hash, $bike_number, $doc_aadhaar, $doc_license, $doc_rc, $doc_photo])) {
                $success = "Registration successful! You can login now.";
            } else {
                $error = "Registration failed. Database error.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Partner Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8fafc; 
            font-family: 'Outfit', sans-serif; 
            padding: 20px; 
            color: #1f2937; 
        }
        .login-card { 
            width: 100%; 
            max-width: 500px; 
            padding: 2.5rem; 
            background: #fff; 
            border-radius: 30px; 
            box-shadow: 0 15px 35px -5px rgba(0,0,0,0.05); 
            margin: 2rem auto; 
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
        .x-small { font-size: 0.75rem; }
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
        .doc-section-title {
            font-weight: 800;
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
        }
        .doc-section-title::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
            margin-left: 1rem;
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
    <div class="brand-subtitle">Become a Delivery Partner</div>

    <?php if($error): ?>
        <div class="alert alert-danger d-flex align-items-center">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                <h6 class="mb-0 fw-bold">Registration Successful!</h6>
            </div>
            <p class="small mb-2">Your application has been submitted for verification.</p>
            <a href="login.php" class="btn btn-success btn-sm w-100 rounded-3 fw-bold py-2">Sign In Now</a>
        </div>
    <?php else: ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Mobile Number</label>
            <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile or +91 number" pattern="(?:\+91|0)?[6-9][0-9]{9}" title="Enter a valid 10-digit mobile number. Leading 0 or +91 is also allowed." required>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Vehicle Number</label>
            <input type="text" name="bike_number" class="form-control" placeholder="e.g. GJ-01-XX-1234" required>
        </div>
        
        <div class="doc-section-title">Upload Documents</div>
        
        <div class="row g-3 mb-3">
            <div class="col-6">
                <label class="form-label x-small">Aadhaar Card</label>
                <input type="file" name="aadhaar" class="form-control form-control-sm" accept="image/*,.pdf" required>
            </div>
            <div class="col-6">
                <label class="form-label x-small">Driving License</label>
                <input type="file" name="license" class="form-control form-control-sm" accept="image/*,.pdf" required>
            </div>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-6">
                <label class="form-label x-small">RC Book</label>
                <input type="file" name="rc" class="form-control form-control-sm" accept="image/*,.pdf" required>
            </div>
            <div class="col-6">
                <label class="form-label x-small">Profile Photo</label>
                <input type="file" name="photo" class="form-control form-control-sm" accept="image/*" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Create Password</label>
            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
        </div>
        <button type="submit" class="btn btn-primary">Submit Application</button>
    </form>
    <?php endif; ?>

    <div class="footer-link">
        Already a partner? <a href="login.php">Login Here</a>
    </div>
</div>

</body>
</html>

