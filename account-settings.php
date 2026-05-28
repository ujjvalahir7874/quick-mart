<?php 
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $submitted_latitude = isset($_POST['user_latitude']) && $_POST['user_latitude'] !== '' ? round((float)$_POST['user_latitude'], 8) : null;
    $submitted_longitude = isset($_POST['user_longitude']) && $_POST['user_longitude'] !== '' ? round((float)$_POST['user_longitude'], 8) : null;
    $submitted_accuracy = isset($_POST['location_accuracy']) && $_POST['location_accuracy'] !== '' ? round((float)$_POST['location_accuracy'], 2) : null;
    $submitted_location_source = trim($_POST['location_source'] ?? '');
    
    if (empty($full_name) || empty($email)) {
        $error = "Name and Email are required.";
    } else {
        try {
            $profile_photo = $user['profile_photo']; // Default to current
            $location_latitude = $user['user_latitude'] ?? null;
            $location_longitude = $user['user_longitude'] ?? null;
            $location_accuracy = $user['location_accuracy'] ?? null;
            $location_source = $user['location_source'] ?? null;
            $location_updated_at = $user['location_updated_at'] ?? null;
            $address_changed = $address !== trim((string)($user['address'] ?? ''));

            // Handle Profile Photo Upload
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_photo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($ext, $allowed)) {
                    $filename = "user_" . $user_id . "_" . time() . "." . $ext;
                    $target = "uploads/profile_photos/" . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $profile_photo = $target;
                    }
                } else {
                    $error = "Invalid image format. Allowed: " . implode(', ', $allowed);
                }
            }

            if (!$error) {
                if ($submitted_latitude !== null && $submitted_longitude !== null) {
                    $location_latitude = $submitted_latitude;
                    $location_longitude = $submitted_longitude;
                    $location_accuracy = $submitted_accuracy;
                    $location_source = $submitted_location_source !== '' ? $submitted_location_source : 'browser_gps';
                    $location_updated_at = date('Y-m-d H:i:s');
                } elseif ($address_changed) {
                    $location_latitude = null;
                    $location_longitude = null;
                    $location_accuracy = null;
                    $location_source = $address !== '' ? 'manual' : null;
                    $location_updated_at = $address !== '' ? date('Y-m-d H:i:s') : null;
                }

                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ?, user_latitude = ?, user_longitude = ?, location_accuracy = ?, location_source = ?, location_updated_at = ?, profile_photo = ? WHERE id = ?");
                $stmt->execute([
                    $full_name,
                    $email,
                    $phone_number,
                    $address,
                    $location_latitude,
                    $location_longitude,
                    $location_accuracy,
                    $location_source,
                    $location_updated_at,
                    $profile_photo,
                    $user_id
                ]);
                $_SESSION['user_name'] = $full_name;
                $message = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = "Email already exists or error updating profile.";
        }
    }
}

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Password strength validation
        $has_upper = preg_match('@[A-Z]@', $new_password);
        $has_lower = preg_match('@[a-z]@', $new_password);
        $has_special = preg_match('@[^\w]@', $new_password);
        $has_digit = preg_match('@[0-9]@', $new_password);

        if (!$has_upper || !$has_lower || !$has_special || !$has_digit || strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters long and include capital letters, lowercase letters, numbers, and special characters.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();
            
            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $message = "Password updated successfully!";
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}

// Handle Account Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    session_destroy();
    header("Location: index.php?msg=account_deleted");
    exit();
}

$has_saved_location = isset($user['user_latitude'], $user['user_longitude']) && $user['user_latitude'] !== null && $user['user_longitude'] !== null;
$saved_latitude = $has_saved_location ? number_format((float)$user['user_latitude'], 6, '.', '') : '';
$saved_longitude = $has_saved_location ? number_format((float)$user['user_longitude'], 6, '.', '') : '';
$saved_accuracy = isset($user['location_accuracy']) && $user['location_accuracy'] !== null ? round((float)$user['location_accuracy']) : null;
$saved_location_time = !empty($user['location_updated_at']) ? date('d M Y, h:i A', strtotime($user['location_updated_at'])) : null;
$saved_location_source = $user['location_source'] ?? null;

require_once 'includes/header.php';
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 animate__animated animate__fadeIn">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                        <li class="breadcrumb-item active text-white small fw-600" aria-current="page">Account Settings</li>
                    </ol>
                </nav>
                <h1 class="text-white fw-800 mb-1 display-5">Account Settings</h1>
                <p class="text-white text-opacity-75 mb-0 fw-600">Manage your profile and account security</p>
            </div>
            <div class="d-flex gap-2">
                <a href="my-orders.php" class="btn btn-white rounded-4 px-4 py-3 fw-800 shadow-lg transition-hover border-0">
                    <i class="bi bi-box-seam-fill me-2 text-success"></i> MY ORDERS
                </a>
                <a href="wishlist.php" class="btn btn-white rounded-4 px-4 py-3 fw-800 shadow-lg transition-hover border-0">
                    <i class="bi bi-heart-fill me-2 text-danger"></i> WISHLIST
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5 animate__animated animate__fadeInUp">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-4 mb-5 pb-4 border-bottom">
                        <div class="position-relative profile-photo-container" onclick="triggerPhotoUpload()" style="cursor: pointer;">
                            <?php if ($user['profile_photo']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" class="rounded-4 shadow-lg object-fit-cover profile-img" style="width: 100px; height: 100px;">
                            <?php else: ?>
                                <div class="bg-success rounded-4 d-flex align-items-center justify-content-center shadow-lg transition-hover profile-img" style="width: 100px; height: 100px; background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                                    <span class="text-white fw-800 display-4 mb-0"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="position-absolute bottom-0 end-0 bg-success text-white rounded-circle p-2 shadow-sm border border-2 border-white edit-overlay" style="transform: translate(25%, 25%);">
                                <i class="bi bi-camera-fill" style="font-size: 1rem;"></i>
                            </div>
                        </div>
                        <div>
                            <h2 class="fw-800 mb-1 text-dark"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-800 smaller">VERIFIED MEMBER <i class="bi bi-patch-check-fill ms-1"></i></span>
                                <span class="text-muted fw-600 small"><i class="bi bi-calendar3 me-1"></i> Since <?php echo date('M Y', strtotime($user['created_at'] ?? '2023-01-01')); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 animate__animated animate__headShake" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                                <div class="fw-800"><?php echo $message; ?></div>
                            </div>
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-4 shadow-sm mb-4 animate__animated animate__headShake" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                                <div class="fw-800"><?php echo $error; ?></div>
                            </div>
                            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Profile Photo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-image text-success"></i></span>
                                    <input type="file" name="profile_photo" id="profile_photo_input" class="form-control bg-light border-0 rounded-end-4 py-3 fw-600" accept="image/*" onchange="previewImage(this)">
                                </div>
                                <div class="form-text small text-muted">Allowed: JPG, PNG, WebP. Best size: 500x500px.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-person text-success"></i></span>
                                    <input type="text" name="full_name" class="form-control bg-light border-0 rounded-end-4 py-3 fw-600" value="<?php echo htmlspecialchars($user['full_name']); ?>" required placeholder="Your full name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-envelope text-success"></i></span>
                                    <input type="email" name="email" class="form-control bg-light border-0 rounded-end-4 py-3 fw-600" value="<?php echo htmlspecialchars($user['email']); ?>" required placeholder="Email for login and updates">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-telephone text-success"></i></span>
                                    <input type="text" name="phone_number" class="form-control bg-light border-0 rounded-end-4 py-3 fw-600" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="Enter your contact number">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-2">
                                    <label class="form-label small fw-800 text-muted text-uppercase tracking-wider mb-0">Default Shipping Address</label>
                                    <button type="button" id="use-current-location" class="btn btn-outline-success rounded-4 px-4 py-2 fw-800 shadow-sm transition-hover location-action-btn">
                                        <span class="location-btn-default"><i class="bi bi-crosshair me-2"></i> USE CURRENT LOCATION</span>
                                        <span class="location-btn-loading d-none"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>FETCHING GPS...</span>
                                    </button>
                                </div>
                                <input type="hidden" name="user_latitude" id="user_latitude" value="<?php echo htmlspecialchars($user['user_latitude'] ?? ''); ?>">
                                <input type="hidden" name="user_longitude" id="user_longitude" value="<?php echo htmlspecialchars($user['user_longitude'] ?? ''); ?>">
                                <input type="hidden" name="location_accuracy" id="location_accuracy" value="<?php echo htmlspecialchars($user['location_accuracy'] ?? ''); ?>">
                                <input type="hidden" name="location_source" id="location_source" value="<?php echo htmlspecialchars($user['location_source'] ?? ''); ?>">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-geo-alt text-success"></i></span>
                                    <textarea name="address" id="address_input" class="form-control bg-light border-0 rounded-end-4 py-3 fw-600" rows="4" placeholder="Enter your full shipping address for faster checkout"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                <div id="location_status" class="location-status-card mt-3 <?php echo $has_saved_location ? 'has-location' : ''; ?>">
                                    <?php if ($has_saved_location): ?>
                                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                            <div>
                                                <div class="fw-800 text-dark mb-1">
                                                    <i class="bi bi-check-circle-fill text-success me-2"></i>Saved current location available
                                                </div>
                                                <div class="small text-muted">
                                                    GPS: <?php echo htmlspecialchars($saved_latitude); ?>, <?php echo htmlspecialchars($saved_longitude); ?>
                                                    <?php if ($saved_accuracy !== null): ?>
                                                        • Accuracy ~<?php echo (int)$saved_accuracy; ?>m
                                                    <?php endif; ?>
                                                    <?php if ($saved_location_time): ?>
                                                        • Updated <?php echo htmlspecialchars($saved_location_time); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($saved_location_source): ?>
                                                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><?php echo strtoupper(htmlspecialchars($saved_location_source)); ?></span>
                                                <?php endif; ?>
                                                <a id="location_map_link" href="https://www.google.com/maps?q=<?php echo rawurlencode(($user['user_latitude'] ?? '') . ',' . ($user['user_longitude'] ?? '')); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light rounded-pill px-3">
                                                    <i class="bi bi-map me-1"></i>View Map
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="fw-800 text-dark mb-1">
                                            <i class="bi bi-info-circle-fill text-success me-2"></i>No verified GPS location saved yet
                                        </div>
                                        <div class="small text-muted">
                                            Tap <strong>Use Current Location</strong> to capture live GPS, fill the nearest mapped area automatically, and save exact coordinates for faster delivery.
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-text small text-muted mt-2">
                                    Best accuracy usually comes from mobile devices with Location/GPS enabled. If house or flat details are missing, you can add them manually and the saved GPS pin will still remain attached.
                                </div>
                            </div>
                            <div class="col-12 mt-5">
                                <button type="submit" name="update_profile" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover">
                                    <i class="bi bi-shield-check me-2 fs-5"></i> SAVE PROFILE CHANGES
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-5 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px;">
                            <i class="bi bi-shield-lock-fill text-success fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-800 text-dark mb-1 uppercase tracking-wider">Security & Password</h5>
                            <p class="text-muted small mb-0">Update your password to keep your account secure</p>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-lock text-success"></i></span>
                                    <input type="password" name="current_password" class="form-control bg-light border-0 py-3 fw-600" required placeholder="••••••••">
                                    <button class="btn btn-light border-0 rounded-end-4 px-3 toggle-password" type="button">
                                        <i class="bi bi-eye text-muted"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-key text-success"></i></span>
                                    <input type="password" name="new_password" class="form-control bg-light border-0 py-3 fw-600" required placeholder="••••••••" minlength="8">
                                    <button class="btn btn-light border-0 rounded-end-4 px-3 toggle-password" type="button">
                                        <i class="bi bi-eye text-muted"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-muted mt-2 px-1">At least 8 chars with capital, small, digit, and special char.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-800 text-muted text-uppercase tracking-wider">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-0 rounded-start-4 px-3"><i class="bi bi-shield-check text-success"></i></span>
                                    <input type="password" name="confirm_password" class="form-control bg-light border-0 py-3 fw-600" required placeholder="••••••••">
                                    <button class="btn btn-light border-0 rounded-end-4 px-3 toggle-password" type="button">
                                        <i class="bi bi-eye text-muted"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" name="update_password" class="btn btn-outline-success rounded-4 px-4 py-2 fw-800 shadow-sm transition-hover">
                                    <i class="bi bi-arrow-repeat me-2"></i> UPDATE PASSWORD
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden border-start border-danger border-5 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px;">
                            <i class="bi bi-exclamation-octagon-fill text-danger fs-3"></i>
                        </div>
                        <div>
                            <h5 class="fw-800 text-danger mb-1 uppercase tracking-wider">Danger Zone</h5>
                            <p class="text-muted small mb-0">Manage account deletion and security risks</p>
                        </div>
                    </div>
                    <p class="text-muted mb-4 fs-6">Once you delete your account, there is no going back. All your data, order history, saved addresses, and feedback will be <strong class="text-danger">permanently removed</strong> from our servers.</p>
                    
                    <button type="button" class="btn btn-outline-danger rounded-4 px-4 py-2 fw-800 shadow-sm transition-hover" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-person-x-fill me-2 fs-5"></i> DELETE MY ACCOUNT
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close shadow-none m-2" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-md-5 text-center">
                <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm animate__animated animate__pulse animate__infinite" style="width: 90px; height: 90px;">
                    <i class="bi bi-trash3-fill text-danger display-4"></i>
                </div>
                <h3 class="fw-800 mb-3 text-dark">Are you sure?</h3>
                <p class="text-muted mb-5 px-3">This action will permanently delete your profile, order history, and reviews. <br><strong>This cannot be undone.</strong></p>
                
                <div class="d-flex flex-column flex-md-row gap-3">
                    <button type="button" class="btn btn-light rounded-4 flex-grow-1 py-3 fw-800 border-0 transition-hover" data-bs-dismiss="modal">NO, KEEP IT</button>
                    <form method="POST" class="flex-grow-1">
                        <button type="submit" name="delete_account" class="btn btn-danger rounded-4 w-100 py-3 fw-800 shadow-lg transition-hover">YES, DELETE ACCOUNT</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
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
.input-group-text { 
    border-radius: 1rem 0 0 1rem!important; 
    border: 1px solid transparent;
    transition: all 0.3s ease;
}
.form-control {
    border-radius: 0 1rem 1rem 0!important;
    border: 1px solid transparent;
    transition: all 0.3s ease;
}
.form-control:focus {
    box-shadow: 0 0.5rem 1.5rem rgba(25, 135, 84, 0.1);
    background-color: #fff!important;
    border-color: #198754!important;
}
.form-control:focus + .input-group-text,
.input-group:focus-within .input-group-text {
    background-color: #fff!important;
    border-color: #198754!important;
    color: #198754!important;
}
.smaller { font-size: 0.75rem; }
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

/* Profile Photo Enhancements */
.profile-photo-container {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}
.profile-photo-container:hover {
    transform: scale(1.05);
}
.profile-photo-container::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.2);
    border-radius: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 2;
}
.profile-photo-container:hover::after {
    opacity: 1;
}
.profile-img {
    transition: all 0.3s ease;
    z-index: 1;
}
.edit-overlay {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 3;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.profile-photo-container:hover .edit-overlay {
    background-color: #157347 !important;
    transform: translate(25%, 25%) scale(1.2);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}
.location-status-card {
    border: 1px solid rgba(25, 135, 84, 0.12);
    background: rgba(25, 135, 84, 0.04);
    border-radius: 1rem;
    padding: 1rem 1.1rem;
}
.location-status-card.has-location {
    background: rgba(25, 135, 84, 0.08);
}
.location-status-card.status-loading {
    border-color: rgba(13, 110, 253, 0.18);
    background: rgba(13, 110, 253, 0.05);
}
.location-status-card.status-warning {
    border-color: rgba(255, 193, 7, 0.25);
    background: rgba(255, 193, 7, 0.08);
}
.location-status-card.status-error {
    border-color: rgba(220, 53, 69, 0.2);
    background: rgba(220, 53, 69, 0.06);
}
.location-action-btn {
    min-width: 235px;
}
</style>

<script>
function triggerPhotoUpload() {
    document.getElementById('profile_photo_input').click();
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const profileImgs = document.querySelectorAll('.profile-img');
            profileImgs.forEach(img => {
                if (img.tagName.toLowerCase() === 'img') {
                    img.src = e.target.result;
                } else {
                    // If it's the avatar div, replace it with an image
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.className = img.className;
                    newImg.style.cssText = img.style.cssText;
                    img.parentNode.replaceChild(newImg, img);
                }
            });
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
});

const useCurrentLocationBtn = document.getElementById('use-current-location');
const addressInput = document.getElementById('address_input');
const latitudeInput = document.getElementById('user_latitude');
const longitudeInput = document.getElementById('user_longitude');
const accuracyInput = document.getElementById('location_accuracy');
const sourceInput = document.getElementById('location_source');
const locationStatus = document.getElementById('location_status');
let fillingAddressFromGps = false;
let currentLocationMapUrl = <?php echo json_encode($has_saved_location ? 'https://www.google.com/maps?q=' . rawurlencode(($user['user_latitude'] ?? '') . ',' . ($user['user_longitude'] ?? '')) : ''); ?>;

function setLocationButtonLoading(isLoading) {
    if (!useCurrentLocationBtn) return;
    useCurrentLocationBtn.disabled = isLoading;
    useCurrentLocationBtn.querySelector('.location-btn-default')?.classList.toggle('d-none', isLoading);
    useCurrentLocationBtn.querySelector('.location-btn-loading')?.classList.toggle('d-none', !isLoading);
}

function setLocationStatus(type, title, details, extraHtml = '') {
    if (!locationStatus) return;

    locationStatus.className = 'location-status-card mt-3';
    if (type === 'loading') locationStatus.classList.add('status-loading');
    if (type === 'warning') locationStatus.classList.add('status-warning');
    if (type === 'error') locationStatus.classList.add('status-error');
    if (type === 'success') locationStatus.classList.add('has-location');

    const mapHtml = currentLocationMapUrl
        ? `<div class="mt-3"><a href="${currentLocationMapUrl}" target="_blank" rel="noopener" class="btn btn-sm btn-light rounded-pill px-3"><i class="bi bi-map me-1"></i>View Map</a></div>`
        : '';

    locationStatus.innerHTML = `
        <div class="fw-800 text-dark mb-1">${title}</div>
        <div class="small text-muted">${details}</div>
        ${extraHtml}
        ${mapHtml}
    `;
}

function updateMapLink(lat, lng, visible = true) {
    if (visible) {
        currentLocationMapUrl = `https://www.google.com/maps?q=${encodeURIComponent(`${lat},${lng}`)}`;
    } else {
        currentLocationMapUrl = '';
    }
}

function markGpsAddressRefined(reasonText) {
    if (!(latitudeInput.value && longitudeInput.value)) return;

    if ((sourceInput.value || '').startsWith('browser_gps')) {
        sourceInput.value = 'browser_gps_refined';
    }

    setLocationStatus(
        'warning',
        '<i class="bi bi-pencil-square text-warning me-2"></i>Address refined manually',
        reasonText || 'You updated the written address manually. The exact GPS pin is still saved. If this text belongs to a different place, tap Use Current Location again.',
        `<div class="small text-muted mt-2">Latitude: ${latitudeInput.value} • Longitude: ${longitudeInput.value}</div>`
    );
}

function getUniqueAddressParts(parts) {
    return parts
        .map((value) => String(value || '').trim())
        .filter(Boolean)
        .filter((value, index, array) => array.indexOf(value) === index);
}

function createGeocodeResult(formatted, precision = 'area', provider = '') {
    return {
        address: String(formatted || '').trim(),
        precision,
        provider
    };
}

function getAddressPrecisionRank(precision) {
    if (precision === 'exact') return 3;
    if (precision === 'street') return 2;
    if (precision === 'area') return 1;
    return 0;
}

function getAddressTextScore(addressText) {
    const text = String(addressText || '').trim().toLowerCase();
    if (!text) return 0;

    const segments = text.split(',').map((part) => part.trim()).filter(Boolean);
    const detailHints = ['road', 'street', 'society', 'nagar', 'colony', 'sector', 'apartment', 'tower', 'building', 'market', 'lane', 'taluka', 'floor', 'flat', 'plot'];
    let score = segments.length * 2;

    if (/\b\d{5,6}\b/.test(text)) score += 2;
    if (/\b\d+\b/.test(text)) score += 1;
    if (detailHints.some((hint) => text.includes(hint))) score += 4;

    return score;
}

function pickBetterGeocodeResult(currentResult, candidateResult) {
    if (!candidateResult?.address) return currentResult;
    if (!currentResult?.address) return candidateResult;

    const currentRank = getAddressPrecisionRank(currentResult.precision);
    const candidateRank = getAddressPrecisionRank(candidateResult.precision);

    if (candidateRank !== currentRank) {
        return candidateRank > currentRank ? candidateResult : currentResult;
    }

    const currentScore = getAddressTextScore(currentResult.address);
    const candidateScore = getAddressTextScore(candidateResult.address);

    if (candidateScore !== currentScore) {
        return candidateScore > currentScore ? candidateResult : currentResult;
    }

    return candidateResult.address.length > currentResult.address.length ? candidateResult : currentResult;
}

function isGenericResolvedAddress(addressText) {
    const segments = String(addressText || '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

    if (!segments.length) return true;
    if (segments.length <= 3) return true;

    const joined = segments.join(' ').toLowerCase();
    const detailedHints = ['road', 'street', 'society', 'nagar', 'colony', 'sector', 'apartment', 'tower', 'building', 'market', 'lane'];
    return !detailedHints.some((hint) => joined.includes(hint));
}

function buildDetailedNominatimAddress(data) {
    const address = data?.address || {};
    const precision = address.house_number || address.building
        ? 'exact'
        : ((address.amenity || address.road || address.pedestrian || address.residential) ? 'street' : 'area');
    const primaryLine = getUniqueAddressParts([
        [address.house_number, address.road].filter(Boolean).join(' ').trim(),
        address.building,
        address.amenity,
        address.road,
        address.pedestrian,
        address.residential
    ])[0] || '';

    const localityLine = getUniqueAddressParts([
        address.neighbourhood,
        address.suburb,
        address.city_district,
        address.hamlet,
        address.village,
        address.town,
        address.city
    ]);

    const regionLine = getUniqueAddressParts([
        address.county,
        address.state_district,
        address.state,
        address.postcode,
        address.country
    ]);

    const detailedAddress = getUniqueAddressParts([
        primaryLine,
        ...localityLine,
        ...regionLine
    ]).join(', ');

    if (detailedAddress && !isGenericResolvedAddress(detailedAddress)) {
        return createGeocodeResult(detailedAddress, precision, 'nominatim');
    }

    if (data?.display_name && !isGenericResolvedAddress(data.display_name)) {
        return createGeocodeResult(data.display_name, precision, 'nominatim');
    }

    return createGeocodeResult(detailedAddress || data?.display_name || '', precision, 'nominatim');
}

async function reverseGeocode(lat, lng) {
    const providers = [
        async () => {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
            if (!response.ok) throw new Error('Nominatim failed');
            const data = await response.json();
            return buildDetailedNominatimAddress(data);
        },
        async () => {
            const response = await fetch(`https://photon.komoot.io/reverse?lat=${lat}&lon=${lng}&lang=en`);
            if (!response.ok) throw new Error('Photon failed');
            const data = await response.json();
            const properties = data?.features?.[0]?.properties || {};
            const precision = properties.housenumber
                ? 'exact'
                : ((properties.street || properties.name) ? 'street' : 'area');
            const formatted = getUniqueAddressParts([
                [properties.housenumber, properties.street].filter(Boolean).join(' ').trim(),
                properties.name,
                properties.district,
                properties.suburb,
                properties.city,
                properties.county,
                properties.state,
                properties.postcode,
                properties.country
            ]).join(', ');

            return createGeocodeResult(formatted, precision, 'photon');
        },
        async () => {
            const response = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=en`);
            if (!response.ok) throw new Error('BigDataCloud failed');
            const data = await response.json();
            return createGeocodeResult(getUniqueAddressParts([
                data.locality,
                data.city || data.localityInfo?.administrative?.find((item) => item.order === 5)?.name,
                data.principalSubdivision,
                data.postcode,
                data.countryName
            ]).join(', '), 'area', 'bigdatacloud');
        }
    ];

    let bestResult = createGeocodeResult('', 'unknown', '');

    for (const provider of providers) {
        try {
            const result = provider ? await provider() : null;
            if (!result?.address) continue;
            bestResult = pickBetterGeocodeResult(bestResult, result);
            if (result.precision === 'exact') return result;
        } catch (error) {
            // Try next provider quietly
        }
    }

    return bestResult;
}

function getAccuracyMessage(accuracy) {
    const rounded = Math.round(accuracy);
    if (rounded <= 30) return { tone: 'success', text: `Excellent GPS lock (~${rounded}m accuracy).` };
    if (rounded <= 100) return { tone: 'success', text: `Good GPS lock (~${rounded}m accuracy).` };
    if (rounded <= 300) return { tone: 'warning', text: `Usable but not perfect (~${rounded}m accuracy). For better precision, try again near a window or on mobile GPS.` };
    return { tone: 'warning', text: `Weak location lock (~${rounded}m accuracy). Enable device GPS/location services and retry for a more correct address.` };
}

useCurrentLocationBtn?.addEventListener('click', () => {
    if (!('geolocation' in navigator)) {
        setLocationStatus(
            'error',
            '<i class="bi bi-x-circle-fill text-danger me-2"></i>Current location not supported',
            'Your browser does not support live geolocation. Please enter the address manually.'
        );
        return;
    }

    setLocationButtonLoading(true);
    setLocationStatus(
        'loading',
        '<i class="bi bi-crosshair text-primary me-2"></i>Fetching your live location',
        'Please allow location permission. We are requesting high-accuracy GPS from your device.'
    );

    navigator.geolocation.getCurrentPosition(async (position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy;
        const lat_fixed = Number(lat).toFixed(8);
        const lng_fixed = Number(lng).toFixed(8);
        const accuracy_fixed = Number(accuracy || 0).toFixed(2);
        const accuracyInfo = getAccuracyMessage(Number(accuracy));

        latitudeInput.value = lat_fixed;
        longitudeInput.value = lng_fixed;
        accuracyInput.value = accuracy_fixed;
        sourceInput.value = 'browser_gps';
        updateMapLink(lat_fixed, lng_fixed, true);

        let geocodeResult = { address: '', precision: 'unknown', provider: '' };
        try {
            // Nominatim (OpenStreetMap)
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                headers: { 'Accept-Language': 'en' }
            });
            if (response.ok) {
                const data = await response.json();
                const addr = data.address || {};
                const parts = [];
                
                // Building/House
                if (addr.house_number || addr.building) parts.push(addr.house_number || addr.building);
                // Road/Street
                if (addr.road || addr.pedestrian || addr.suburb) parts.push(addr.road || addr.pedestrian || addr.suburb);
                // Locality
                if (addr.neighbourhood || addr.city_district) parts.push(addr.neighbourhood || addr.city_district);
                // City
                if (addr.city || addr.town || addr.village) parts.push(addr.city || addr.town || addr.village);
                // State & Postcode
                if (addr.state) parts.push(addr.state);
                if (addr.postcode) parts.push(addr.postcode);
                // Country
                if (addr.country) parts.push(addr.country);

                geocodeResult.address = parts.filter((v, i, a) => v && a.indexOf(v) === i).join(', ');
                geocodeResult.precision = (addr.house_number || addr.building) ? 'exact' : 'street';
            }
        } catch (e) { console.error("Geocoding failed", e); }

        const resolvedAddress = geocodeResult.address || '';
        const isExactAddress = geocodeResult.precision === 'exact';
        const hasStreetLevelAddress = geocodeResult.precision === 'exact' || geocodeResult.precision === 'street';

        sourceInput.value = isExactAddress ? 'browser_gps_exact' : (hasStreetLevelAddress ? 'browser_gps_street' : 'browser_gps_area');

        if (resolvedAddress) {
            fillingAddressFromGps = true;
            addressInput.value = hasStreetLevelAddress
                ? resolvedAddress
                : `Nearby mapped area: ${resolvedAddress}\nExact GPS pin: ${lat}, ${lng}\nAdd house / flat / floor manually if needed`;
            fillingAddressFromGps = false;
        } else if (!addressInput.value.trim()) {
            fillingAddressFromGps = true;
            addressInput.value = `Exact GPS pin: ${lat}, ${lng}\nAdd house / flat / floor manually if needed`;
            fillingAddressFromGps = false;
        }

        const statusMessage = resolvedAddress
            ? (isExactAddress
                ? `${accuracyInfo.text} House or street-level address was found automatically.`
                : (hasStreetLevelAddress
                    ? `${accuracyInfo.text} Nearby street or locality has been filled automatically. If your flat or house number is missing, you can add it manually and the GPS pin will still stay saved.`
                    : `${accuracyInfo.text} Exact GPS pin was captured, but public maps only found the nearby area. Add house or flat details manually and the GPS pin will still stay saved.`))
            : 'Address lookup failed, but exact GPS coordinates have been captured. Add your house or flat details manually and save.';

        setLocationStatus(
            accuracyInfo.tone,
            '<i class="bi bi-check-circle-fill text-success me-2"></i>Current location captured',
            statusMessage,
            `<div class="small text-muted mt-2">Latitude: ${lat} • Longitude: ${lng}</div>`
        );
        setLocationButtonLoading(false);
    }, (error) => {
        const errorMessages = {
            1: 'Location permission was denied. Please allow browser location access and try again.',
            2: 'We could not determine your current location. Make sure device location/GPS is turned on.',
            3: 'Location request timed out. Please retry in a place with better signal.'
        };

        setLocationStatus(
            'error',
            '<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Could not fetch current location',
            errorMessages[error.code] || 'An unexpected geolocation error occurred. Please try again.'
        );
        setLocationButtonLoading(false);
    }, {
        enableHighAccuracy: true,
        timeout: 20000,
        maximumAge: 0
    });
});

addressInput?.addEventListener('input', () => {
    if (fillingAddressFromGps) return;

    if (latitudeInput.value || longitudeInput.value || (sourceInput.value || '').startsWith('browser_gps')) {
        markGpsAddressRefined('You changed the written address manually. The exact GPS pin is still attached. If you moved to a different place, tap Use Current Location again before saving.');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
