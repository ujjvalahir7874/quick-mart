<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    $allow_regs = isset($_POST['allow_registrations']) ? '1' : '0';
    $founder_name = trim($_POST['founder_name'] ?? 'Founding Team');
    $ceo_name = trim($_POST['ceo_name'] ?? 'Leadership Team');
    $masked_call_enabled = isset($_POST['masked_call_enabled']) ? '1' : '0';
    $masked_call_relay_label = trim($_POST['masked_call_relay_label'] ?? 'Quick mart Secure Call Desk');
    $masked_call_relay_number = trim($_POST['masked_call_relay_number'] ?? '1800123456');
    
    set_setting('maintenance_mode', $maintenance);
    set_setting('allow_registrations', $allow_regs);
    set_setting('founder_name', $founder_name);
    set_setting('ceo_name', $ceo_name);
    set_setting('masked_call_enabled', $masked_call_enabled);
    set_setting('masked_call_relay_label', $masked_call_relay_label !== '' ? $masked_call_relay_label : 'Quick mart Secure Call Desk');
    set_setting('masked_call_relay_number', $masked_call_relay_number !== '' ? $masked_call_relay_number : '1800123456');
    set_setting('app_base_url', trim($_POST['app_base_url'] ?? 'http://localhost/major/'));
    
    // SMS Settings
    set_setting('sms_enabled', isset($_POST['sms_enabled']) ? '1' : '0');
    set_setting('sms_provider', $_POST['sms_provider'] ?? 'simulation');
    set_setting('sms_fast2sms_key', $_POST['sms_fast2sms_key'] ?? '');
    set_setting('sms_twilio_sid', $_POST['sms_twilio_sid'] ?? '');
    set_setting('sms_twilio_token', $_POST['sms_twilio_token'] ?? '');
    set_setting('sms_twilio_from', $_POST['sms_twilio_from'] ?? '');
    
    // Email Settings
    set_setting('email_enabled', isset($_POST['email_enabled']) ? '1' : '0');
    set_setting('email_provider', $_POST['email_provider'] ?? 'simulation');
    set_setting('email_from_address', $_POST['email_from_address'] ?? 'noreply@quickmart.com');
    set_setting('email_from_name', $_POST['email_from_name'] ?? 'Quick mart');
    set_setting('email_smtp_host', $_POST['email_smtp_host'] ?? 'smtp.gmail.com');
    set_setting('email_smtp_port', $_POST['email_smtp_port'] ?? '587');
    set_setting('email_smtp_user', $_POST['email_smtp_user'] ?? '');
    set_setting('email_smtp_pass', $_POST['email_smtp_pass'] ?? '');
    
    // Save Fallbacks
    if (isset($_POST['fallback_prod_default'])) set_setting('fallback_prod_default', $_POST['fallback_prod_default']);
    if (isset($_POST['fallback_cat_default'])) set_setting('fallback_cat_default', $_POST['fallback_cat_default']);
    if (isset($_POST['fallback_recipe_default'])) set_setting('fallback_recipe_default', $_POST['fallback_recipe_default']);
    
    // Handle File Uploads
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if (isset($_FILES['founder_image']) && $_FILES['founder_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['founder_image']['name'], PATHINFO_EXTENSION);
        $filename = 'founder_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['founder_image']['tmp_name'], $upload_dir . $filename)) {
            set_setting('founder_image', 'uploads/' . $filename);
        }
    }
    
    if (isset($_FILES['ceo_image']) && $_FILES['ceo_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['ceo_image']['name'], PATHINFO_EXTENSION);
        $filename = 'ceo_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['ceo_image']['tmp_name'], $upload_dir . $filename)) {
            set_setting('ceo_image', 'uploads/' . $filename);
        }
    }

    $message = 'Settings saved successfully';
}

$maintenance_mode = (int)(get_setting('maintenance_mode', '0') ?? 0);
$allow_registrations = (int)(get_setting('allow_registrations', '1') ?? 1);
$founder_name = get_setting('founder_name', 'Founding Team') ?? 'Founding Team';
$ceo_name = get_setting('ceo_name', 'Leadership Team') ?? 'Leadership Team';
$masked_call_enabled = (int)(get_setting('masked_call_enabled', '1') ?? 1);
$masked_call_relay_label = get_setting('masked_call_relay_label', 'Quick mart Secure Call Desk') ?? 'Quick mart Secure Call Desk';
$masked_call_relay_number = get_setting('masked_call_relay_number', '1800123456') ?? '1800123456';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-width: 260px; --sidebar-bg: #1e293b; --primary-color: #10b981; --bg-light: #f8fafc; --text-main: #1e293b; --text-muted: #64748b; --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); color: var(--text-main); }
        .sidebar { width: var(--sidebar-width); height: 100vh; overflow-y: auto; position: fixed; left: 0; top: 0; background-color: var(--sidebar-bg); color: #fff; z-index: 1000; transition: var(--transition); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
        .sidebar-brand { padding: 2rem 1.5rem; font-size: 1.5rem; font-weight: 700; color: #fff; text-decoration: none; display: flex; align-items: center; }
        .nav-link-admin { padding: 0.85rem 1.5rem; color: #94a3b8; text-decoration: none; display: flex; align-items: center; transition: var(--transition); border-left: 4px solid transparent; }
        .nav-link-admin:hover, .nav-link-admin.active { background-color: #334155; color: #fff; border-left-color: var(--primary-color); }
        .nav-link-admin i { margin-right: 0.85rem; font-size: 1.25rem; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; transition: var(--transition); }
        .card { border: none; border-radius: 1rem; box-shadow: var(--card-shadow); }
        .form-switch .form-check-input { width: 3em; height: 1.5em; }
        @media (max-width: 992px) { .sidebar { left: -var(--sidebar-width); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; } .sidebar.active { left: 0; } .main-content { margin-left: 0; padding: 1.5rem; } }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <a href="../index.php" class="sidebar-brand">
            <div class="bg-success text-white rounded-3 p-1 me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                <i class="bi bi-basket2-fill fs-5"></i>
            </div>
            Quick mart
        </a>
        <div class="mt-3">
            <p class="px-4 text-muted small text-uppercase fw-bold mb-2 opacity-50">Menu</p>
            <a href="dashboard.php" class="nav-link-admin"><i class="bi bi-speedometer2"></i>Dashboard</a>
            <a href="products.php" class="nav-link-admin"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin"><i class="bi bi-tags"></i>Categories</a>
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">System</p>
            <a href="settings.php" class="nav-link-admin active"><i class="bi bi-gear"></i>Settings</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">System Settings</h2>
                <p class="text-muted small mb-0">Configure application preferences</p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success border-0 rounded-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">General Settings</h5>
                <div class="alert alert-info rounded-4 small">Settings are stored in the database and applied globally.</div>
                <form method="POST" class="mt-3" enctype="multipart/form-data">
                    <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                        <div>
                            <div class="fw-bold">Maintenance Mode</div>
                            <div class="text-muted small">Temporarily disable the public store</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" <?php echo $maintenance_mode ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-3">
                        <div>
                            <div class="fw-bold">Allow New Registrations</div>
                            <div class="text-muted small">Enable/disable new user signups</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="allow_registrations" <?php echo $allow_registrations ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-3 border-top">
                        <div>
                            <div class="fw-bold">App Base URL</div>
                            <div class="text-muted small">Required for correct links in Emails (e.g. http://localhost/major/)</div>
                        </div>
                        <div style="width: 300px;">
                            <input type="text" name="app_base_url" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('app_base_url', 'http://localhost/major/')); ?>" placeholder="http://localhost/major/">
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between py-3 border-top">
                        <div>
                            <div class="fw-bold">Masked Calling</div>
                            <div class="text-muted small">Hide customer and delivery partner numbers behind a secure relay line</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="masked_call_enabled" <?php echo $masked_call_enabled ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    </div>

                    <div class="row py-3 border-top">
                        <div class="col-12 mb-3">
                            <div class="fw-bold mb-2 text-primary"><i class="bi bi-envelope-at me-2"></i>Email Gateway Configuration</div>
                            <div class="alert alert-light border small rounded-4">
                                Used for sending Password Reset links and Order Receipts.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">Email Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="email_enabled" <?php echo get_setting('email_enabled') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label small ms-2">Enabled</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">Email Provider</label>
                            <select name="email_provider" class="form-select form-select-sm" id="email_provider_select">
                                <option value="simulation" <?php echo get_setting('email_provider') == 'simulation' ? 'selected' : ''; ?>>Simulation (Log to file)</option>
                                <option value="smtp" <?php echo get_setting('email_provider') == 'smtp' ? 'selected' : ''; ?>>SMTP (Gmail/Outlook)</option>
                            </select>
                        </div>
                        
                        <!-- SMTP Fields -->
                        <div class="col-12 provider-fields-email d-none" id="smtp_fields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">From Name</label>
                                    <input type="text" name="email_from_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_from_name', 'Quick mart')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">From Email</label>
                                    <input type="email" name="email_from_address" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_from_address', 'noreply@quickmart.com')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">SMTP Host</label>
                                    <input type="text" name="email_smtp_host" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_smtp_host', 'smtp.gmail.com')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">SMTP Port</label>
                                    <input type="text" name="email_smtp_port" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_smtp_port', '587')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">SMTP Username</label>
                                    <input type="text" name="email_smtp_user" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_smtp_user')); ?>" placeholder="your-email@gmail.com">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">SMTP Password (App Password)</label>
                                    <input type="password" name="email_smtp_pass" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('email_smtp_pass')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row py-3 border-top">
                        <div class="col-12 mb-3">
                            <div class="fw-bold mb-2 text-primary"><i class="bi bi-chat-left-text me-2"></i>SMS Gateway Configuration</div>
                            <div class="alert alert-light border small rounded-4">
                                Used for sending Order OTPs and notifications to customers.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">SMS Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="sms_enabled" <?php echo get_setting('sms_enabled') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label small ms-2">Enabled</label>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">SMS Provider</label>
                            <select name="sms_provider" class="form-select form-select-sm" id="sms_provider_select">
                                <option value="simulation" <?php echo get_setting('sms_provider') == 'simulation' ? 'selected' : ''; ?>>Simulation (Log to file)</option>
                                <option value="fast2sms" <?php echo get_setting('sms_provider') == 'fast2sms' ? 'selected' : ''; ?>>Fast2SMS (India)</option>
                                <option value="twilio" <?php echo get_setting('sms_provider') == 'twilio' ? 'selected' : ''; ?>>Twilio (Global)</option>
                            </select>
                        </div>
                        
                        <!-- Fast2SMS Fields -->
                        <div class="col-12 provider-fields d-none" id="fast2sms_fields">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Fast2SMS API Key</label>
                                    <input type="text" name="sms_fast2sms_key" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('sms_fast2sms_key')); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Twilio Fields -->
                        <div class="col-12 provider-fields d-none" id="twilio_fields">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Twilio SID</label>
                                    <input type="text" name="sms_twilio_sid" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('sms_twilio_sid')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Twilio Token</label>
                                    <input type="password" name="sms_twilio_token" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('sms_twilio_token')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Twilio From Number</label>
                                    <input type="text" name="sms_twilio_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('sms_twilio_from')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row py-3 border-top">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="fw-bold mb-2">Secure Call Desk Label</div>
                            <input type="text" name="masked_call_relay_label" class="form-control" value="<?php echo htmlspecialchars($masked_call_relay_label); ?>" placeholder="Quick mart Secure Call Desk">
                            <div class="form-text">Shown on the secure call page for customers and delivery partners.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-bold mb-2">Secure Relay Number</div>
                            <input type="text" name="masked_call_relay_number" class="form-control" value="<?php echo htmlspecialchars($masked_call_relay_number); ?>" placeholder="1800123456">
                            <div class="form-text">This number is dialed instead of exposing the real customer or rider mobile number.</div>
                        </div>
                    </div>
                    <div class="row py-3 border-top">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="fw-bold mb-2">Founder Name</div>
                            <input type="text" name="founder_name" class="form-control mb-2" value="<?php echo htmlspecialchars($founder_name); ?>" required>
                            <label class="form-label small text-muted">Founder Image (Optional)</label>
                            <input type="file" name="founder_image" class="form-control form-control-sm" accept="image/*">
                            <?php if ($founder_img = get_setting('founder_image')): ?>
                                <img src="../<?php echo htmlspecialchars($founder_img); ?>" width="50" height="50" class="mt-2 rounded object-fit-cover shadow-sm">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="fw-bold mb-2">CEO Name</div>
                            <input type="text" name="ceo_name" class="form-control mb-2" value="<?php echo htmlspecialchars($ceo_name); ?>" required>
                            <label class="form-label small text-muted">CEO Image (Optional)</label>
                            <input type="file" name="ceo_image" class="form-control form-control-sm" accept="image/*">
                            <?php if ($ceo_img = get_setting('ceo_image')): ?>
                                <img src="../<?php echo htmlspecialchars($ceo_img); ?>" width="50" height="50" class="mt-2 rounded object-fit-cover shadow-sm">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row py-3 border-top">
                        <div class="col-12 mb-3">
                            <div class="fw-bold mb-2 text-primary"><i class="bi bi-image me-2"></i>Global Image Fallbacks</div>
                            <div class="alert alert-light border small rounded-4">
                                These images are used automatically when a product or category doesn't have an image assigned.
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">Default Product Image</label>
                            <input type="text" name="fallback_prod_default" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('fallback_prod_default')); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">Default Category Image</label>
                            <input type="text" name="fallback_cat_default" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('fallback_cat_default')); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-bold">Default Recipe Image</label>
                            <input type="text" name="fallback_recipe_default" class="form-control form-control-sm" value="<?php echo htmlspecialchars(get_setting('fallback_recipe_default')); ?>">
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button class="btn btn-success rounded-3 px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sms_provider_select').addEventListener('change', function() {
            document.querySelectorAll('.provider-fields').forEach(el => el.classList.add('d-none'));
            const provider = this.value;
            if (provider === 'fast2sms') document.getElementById('fast2sms_fields').classList.remove('d-none');
            if (provider === 'twilio') document.getElementById('twilio_fields').classList.remove('d-none');
        });
        // Trigger on load
        document.getElementById('sms_provider_select').dispatchEvent(new Event('change'));

        document.getElementById('email_provider_select').addEventListener('change', function() {
            const smtpFields = document.getElementById('smtp_fields');
            if (this.value === 'smtp') {
                smtpFields.classList.remove('d-none');
            } else {
                smtpFields.classList.add('d-none');
            }
        });
        document.getElementById('email_provider_select').dispatchEvent(new Event('change'));
    </script>
</body>
</html>
