<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: offers.php");
    exit;
}

$id = (int)$_GET['id'];
$products_list = $pdo->query("SELECT id, name FROM products WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$error = '';

$stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ?");
$stmt->execute([$id]);
$offer = $stmt->fetch();
if (!$offer) {
    header("Location: offers.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $discount_text = trim($_POST['discount_text'] ?? '');
    $offer_type = strtoupper(trim($_POST['offer_type'] ?? 'BANNER'));
    $buy_quantity = max(0, (int)($_POST['buy_quantity'] ?? 0));
    $get_quantity = max(0, (int)($_POST['get_quantity'] ?? 0));
    $offer_scope = $_POST['offer_scope'] ?? 'same_product';
    $applicable_product_id = (int)($_POST['applicable_product_id'] ?? 0);
    $free_product_id = (int)($_POST['free_product_id'] ?? 0);
    $max_free_items = trim($_POST['max_free_items'] ?? '');
    $max_free_items = $max_free_items === '' ? null : max(0, (int)$max_free_items);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $link_url = $applicable_product_id > 0 ? 'product-details.php?id=' . $applicable_product_id : 'products.php';
    $image_url = $_POST['existing_image_url'] ?? '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $error = $errors[$_FILES['image_file']['error']] ?? 'Unknown upload error.';
        } else {
            $upload_dir = '../uploads/offers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file type. Allowed: " . implode(', ', $allowed);
            } else {
                $filename = 'offer_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename)) {
                    $image_url = 'uploads/offers/' . $filename;
                } else {
                    $error = "Failed to move uploaded file.";
                }
            }
        }
    }

    if ($offer_type !== 'BOGO') {
        $buy_quantity = 0;
        $get_quantity = 0;
        $offer_scope = 'same_product';
        $applicable_product_id = $applicable_product_id > 0 ? $applicable_product_id : null;
        $free_product_id = null;
        $max_free_items = null;
    } else {
        if ($buy_quantity <= 0 || $get_quantity <= 0 || $applicable_product_id <= 0) {
            $error = 'Select an applicable product and enter valid buy/get quantities for the BOGO rule.';
        } elseif ($offer_scope === 'different_product' && $free_product_id <= 0) {
            $error = 'Select the free product for a different-product BOGO offer.';
        } elseif ($offer_scope !== 'different_product') {
            $offer_scope = 'same_product';
            $free_product_id = $applicable_product_id;
        }
    }

    if (empty($title) || empty($discount_text)) {
        $error = $error ?: 'Please complete the required fields.';
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("UPDATE offers SET title = ?, discount_text = ?, image_url = ?, start_date = ?, end_date = ?, offer_type = ?, buy_quantity = ?, get_quantity = ?, offer_scope = ?, applicable_product_id = ?, free_product_id = ?, max_free_items = ?, is_active = ?, link_url = ? WHERE id = ?");
            $stmt->execute([$title, $discount_text, $image_url, $start_date, $end_date, $offer_type, $buy_quantity, $get_quantity, $offer_scope, $applicable_product_id ?: null, $free_product_id ?: null, $max_free_items, $is_active, $link_url, $id]);
            header("Location: offers.php");
            exit;
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }

    $offer = array_merge($offer, [
        'title' => $title,
        'discount_text' => $discount_text,
        'image_url' => $image_url,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'offer_type' => $offer_type,
        'buy_quantity' => $buy_quantity,
        'get_quantity' => $get_quantity,
        'offer_scope' => $offer_scope,
        'applicable_product_id' => $applicable_product_id,
        'free_product_id' => $free_product_id,
        'max_free_items' => $max_free_items,
        'is_active' => $is_active
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Offer - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
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
        .preview-box { background: linear-gradient(135deg, #f8fff9 0%, #eefaf3 100%); border: 1px dashed #cde7d5; }
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
            <a href="offers.php" class="nav-link-admin active"><i class="bi bi-megaphone"></i>Offers</a>
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">System</p>
            <a href="settings.php" class="nav-link-admin"><i class="bi bi-gear"></i>Settings</a>
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Edit Offer</h2>
                <p class="text-muted small mb-0">Update banner details and the BOGO engine settings together</p>
            </div>
            <a href="offers.php" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm">Back</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 max-w-2xl">
            <div class="card-body p-4 p-md-5">
                <form method="POST" enctype="multipart/form-data" id="offer-form">
                    <input type="hidden" name="existing_image_url" value="<?php echo htmlspecialchars($offer['image_url']); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Offer Name</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($offer['title']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Display Text</label>
                            <input type="text" name="discount_text" class="form-control" required value="<?php echo htmlspecialchars($offer['discount_text']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Offer Type</label>
                            <select name="offer_type" id="offer_type" class="form-select">
                                <option value="BOGO" <?php echo (($offer['offer_type'] ?? 'BANNER') === 'BOGO') ? 'selected' : ''; ?>>BUY_X_GET_Y</option>
                                <option value="BANNER" <?php echo (($offer['offer_type'] ?? 'BANNER') === 'BANNER') ? 'selected' : ''; ?>>Banner Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Offer Scope</label>
                            <select name="offer_scope" id="offer_scope" class="form-select">
                                <option value="same_product" <?php echo (($offer['offer_scope'] ?? 'same_product') === 'same_product') ? 'selected' : ''; ?>>Same Product</option>
                                <option value="different_product" <?php echo (($offer['offer_scope'] ?? '') === 'different_product') ? 'selected' : ''; ?>>Different Product</option>
                            </select>
                        </div>

                        <div class="col-md-3 bogo-field">
                            <label class="form-label fw-bold">Buy Quantity (X)</label>
                            <input type="number" min="1" name="buy_quantity" id="buy_quantity" class="form-control" value="<?php echo htmlspecialchars((string)($offer['buy_quantity'] ?: 1)); ?>">
                        </div>
                        <div class="col-md-3 bogo-field">
                            <label class="form-label fw-bold">Get Quantity (Y)</label>
                            <input type="number" min="1" name="get_quantity" id="get_quantity" class="form-control" value="<?php echo htmlspecialchars((string)($offer['get_quantity'] ?: 1)); ?>">
                        </div>
                        <div class="col-md-6 bogo-field">
                            <label class="form-label fw-bold">Max Free Items</label>
                            <input type="number" min="1" name="max_free_items" class="form-control" placeholder="Optional limit" value="<?php echo htmlspecialchars((string)($offer['max_free_items'] ?? '')); ?>">
                        </div>

                        <div class="col-md-6 bogo-field">
                            <label class="form-label fw-bold">Applicable Product</label>
                            <select name="applicable_product_id" id="applicable_product_id" class="form-select">
                                <option value="">Select product</option>
                                <?php foreach ($products_list as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ((int)($offer['applicable_product_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 bogo-field" id="free-product-wrap">
                            <label class="form-label fw-bold">Free Product</label>
                            <select name="free_product_id" id="free_product_id" class="form-select">
                                <option value="">Select product</option>
                                <?php foreach ($products_list as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ((int)($offer['free_product_id'] ?? 0) === (int)$p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Image Upload (Leave empty to keep current)</label>
                            <input type="file" name="image_file" class="form-control" accept="image/*">
                            <div class="mt-2">
                                <small class="text-muted">Current image:</small><br>
                                <img src="../<?php echo htmlspecialchars($offer['image_url']); ?>" style="max-height: 80px;" class="rounded border mt-1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($offer['start_date']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($offer['end_date']); ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end mb-2">
                            <div class="form-check form-switch mt-2 fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveCheck" <?php echo !empty($offer['is_active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fs-6 fw-bold ms-2 mt-1" for="isActiveCheck">Enable Offer</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="preview-box rounded-4 p-4">
                                <div class="small text-muted text-uppercase fw-bold mb-2">Preview</div>
                                <div class="fw-bold fs-5 mb-1" id="preview-title">Buy 1 Get 1 Free</div>
                                <div class="text-muted" id="preview-rule">Buy 1 of the selected product and get 1 free.</div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-pill"><i class="bi bi-save me-2"></i>Update Offer</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const offerTypeEl = document.getElementById('offer_type');
        const offerScopeEl = document.getElementById('offer_scope');
        const buyQtyEl = document.getElementById('buy_quantity');
        const getQtyEl = document.getElementById('get_quantity');
        const applicableProductEl = document.getElementById('applicable_product_id');
        const freeProductEl = document.getElementById('free_product_id');
        const previewTitleEl = document.getElementById('preview-title');
        const previewRuleEl = document.getElementById('preview-rule');

        function selectedText(selectEl) {
            return selectEl && selectEl.selectedIndex >= 0 ? selectEl.options[selectEl.selectedIndex].text : 'selected product';
        }

        function toggleBogoFields() {
            const isBogo = offerTypeEl.value === 'BOGO';
            document.querySelectorAll('.bogo-field').forEach((field) => {
                field.style.display = isBogo ? '' : 'none';
            });
            document.getElementById('free-product-wrap').style.display = isBogo && offerScopeEl.value === 'different_product' ? '' : 'none';
        }

        function updatePreview() {
            const buyQty = parseInt(buyQtyEl.value || '1', 10);
            const getQty = parseInt(getQtyEl.value || '1', 10);
            const titleInput = document.querySelector('input[name="discount_text"]');
            previewTitleEl.textContent = titleInput.value || 'Buy 1 Get 1 Free';

            if (offerTypeEl.value !== 'BOGO') {
                previewRuleEl.textContent = 'Banner-only offer. No automatic free item logic will run in the cart.';
                return;
            }

            const applicableName = selectedText(applicableProductEl);
            if (offerScopeEl.value === 'different_product') {
                previewRuleEl.textContent = `Buy ${buyQty} of ${applicableName} and get ${getQty} of ${selectedText(freeProductEl)} free.`;
            } else {
                previewRuleEl.textContent = `Buy ${buyQty} of ${applicableName} and get ${getQty} of the same product free.`;
            }
        }

        [offerTypeEl, offerScopeEl, buyQtyEl, getQtyEl, applicableProductEl, freeProductEl, document.querySelector('input[name="discount_text"]')].forEach((el) => {
            el.addEventListener('input', updatePreview);
            el.addEventListener('change', () => {
                toggleBogoFields();
                updatePreview();
            });
        });

        toggleBogoFields();
        updatePreview();
    </script>
</body>
</html>
