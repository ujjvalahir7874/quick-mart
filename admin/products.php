<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

// AJAX Stock Update Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    isset($_POST['update_stock_ajax']) || 
    isset($_POST['update_variant_stock_ajax']) || 
    isset($_POST['fetch_variants_ajax']) || 
    isset($_POST['add_variant_ajax']) || 
    isset($_POST['delete_variant_ajax']) || 
    isset($_POST['disable_variants_ajax']) || 
    isset($_POST['update_variant_ajax']) ||
    isset($_POST['update_all_variants_ajax'])
)) {
    header('Content-Type: application/json');
    try {
        if (isset($_POST['fetch_variants_ajax'])) {
            $product_id = (int)$_POST['product_id'];
            $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC");
            $stmt->execute([$product_id]);
            echo json_encode(['success' => true, 'variants' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if (isset($_POST['add_variant_ajax'])) {
            $product_id = (int)$_POST['product_id'];
            $size_name = $_POST['size_name'];
            $price = (float)$_POST['price'];
            $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
            $stock_quantity = (int)$_POST['stock_quantity'];
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price, discount_price, stock_quantity, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $size_name, $price, $discount_price, $stock_quantity, $expiry_date]);
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['delete_variant_ajax'])) {
            $variant_id = (int)$_POST['variant_id'];
            $stmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
            $stmt->execute([$variant_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['disable_variants_ajax'])) {
            $product_id = (int)$_POST['product_id'];
            $stmt = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?");
            $stmt->execute([$product_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['update_variant_ajax'])) {
            $variant_id = (int)$_POST['variant_id'];
            $size_name = $_POST['size_name'];
            $price = (float)$_POST['price'];
            $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
            $stock_quantity = (int)$_POST['stock_quantity'];
            $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

            $stmt = $pdo->prepare("UPDATE product_variants SET size_name = ?, price = ?, discount_price = ?, stock_quantity = ?, expiry_date = ? WHERE id = ?");
            $stmt->execute([$size_name, $price, $discount_price, $stock_quantity, $expiry_date, $variant_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['update_all_variants_ajax'])) {
            $variants = json_decode($_POST['variants_data'], true);
            $stmt = $pdo->prepare("UPDATE product_variants SET size_name = ?, price = ?, discount_price = ?, stock_quantity = ?, expiry_date = ? WHERE id = ?");
            foreach ($variants as $v) {
                $discount = !empty($v['discount_price']) ? (float)$v['discount_price'] : null;
                $expiry = !empty($v['expiry_date']) ? $v['expiry_date'] : null;
                $stmt->execute([$v['size_name'], (float)$v['price'], $discount, (int)$v['stock_quantity'], $expiry, (int)$v['id']]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if (isset($_POST['update_variant_stock_ajax'])) {
            $variant_id = (int)$_POST['variant_id'];
            $new_stock = (int)$_POST['new_stock'];
            
            // Update variant stock
            $stmt = $pdo->prepare("UPDATE product_variants SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$new_stock, $variant_id]);
            
            // Get product_id to update parent status
            $stmt = $pdo->prepare("SELECT product_id FROM product_variants WHERE id = ?");
            $stmt->execute([$variant_id]);
            $product_id = $stmt->fetchColumn();
            
            if ($product_id) {
                // Check all variants for this product
                $stmt = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $all_variants_stock = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Calculate total stock and determine status
                $total_stock = 0;
                foreach ($all_variants_stock as $qty) {
                    $total_stock += (int)$qty;
                }
                
                $new_status = $total_stock > 0 ? 'In Stock' : 'Out of Stock';
                
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, availability_status = ? WHERE id = ?");
                $stmt->execute([$total_stock, $new_status, $product_id]);
            }
            
            echo json_encode(['success' => true]);
            exit;
        }

        $product_id = (int)$_POST['product_id'];
        $new_stock = (int)$_POST['new_stock'];
        
        // Determine availability status based on stock
        $status = ($new_stock > 0) ? 'In Stock' : 'Out of Stock';
        
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, availability_status = ? WHERE id = ?");
        $stmt->execute([$new_stock, $status, $product_id]);
        
        // Fetch updated stats
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 1 ELSE 0 END) as low_stock
            FROM products");
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'status' => $status,
            'stats' => $stats
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

// Delete/Archive Logic
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    // Check if product exists in any orders
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $check_stmt->execute([$product_id]);
    $order_count = $check_stmt->fetchColumn();

    if ($order_count > 0) {
        // Soft delete (Archive) if orders exist
        $stmt = $pdo->prepare("UPDATE products SET status = 'Archived' WHERE id = ?");
        $stmt->execute([$product_id]);
        logActivity($pdo, "Archived product ID: " . $product_id . " (has orders)");
        $_SESSION['success_msg'] = "Product has orders, so it was <strong>Archived</strong> instead of deleted. It will no longer appear in the shop.";
    } else {
        // Permanent delete if no orders exist
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            logActivity($pdo, "Deleted product ID: " . $product_id);
            $_SESSION['success_msg'] = "Product deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Failed to delete product: " . $e->getMessage();
        }
    }
    header("Location: products.php");
    exit;
}

// Restore Logic
if (isset($_GET['restore'])) {
    $product_id = (int)$_GET['restore'];
    $stmt = $pdo->prepare("UPDATE products SET status = 'Active' WHERE id = ?");
    $stmt->execute([$product_id]);
    logActivity($pdo, "Restored product ID: " . $product_id);
    $_SESSION['success_msg'] = "Product restored to Active status.";
    header("Location: products.php");
    exit;
}

$products = $pdo->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.status ASC, p.id ASC")->fetchAll();
$variants = $pdo->query("SELECT product_id, id, size_name, price, discount_price, stock_quantity, expiry_date FROM product_variants ORDER BY product_id, price ASC")->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

// Stats for Inventory Management
$total_products = count($products);
$low_stock = 0;
$out_of_stock = 0;
foreach ($products as $p) {
    if ($p['stock_quantity'] == 0) $out_of_stock++;
    elseif ($p['stock_quantity'] <= 10) $low_stock++;
}

// Helper for Product Image Upload with error reporting
function handleImageUpload($file, &$error_msg = null) {
    if (isset($file) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
            ];
            $error_msg = $errors[$file['error']] ?? 'Unknown upload error.';
            return false;
        }

        $target_dir = "../uploads/products/";
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                $error_msg = "Failed to create directory: $target_dir";
                return false;
            }
        }
        
        if (!is_writable($target_dir)) {
            $error_msg = "Directory is not writable: $target_dir";
            return false;
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'jfif'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_msg = "Invalid file type: $file_extension. Allowed: " . implode(', ', $allowed_extensions);
            return false;
        }

        // Validate that it's actually an image
        $check = @getimagesize($file['tmp_name']);
        if ($check === false) {
            $error_msg = "The file is not a valid image (corrupt or invalid format).";
            return false;
        }

        $new_filename = uniqid('prod_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return 'uploads/products/' . $new_filename;
        } else {
            $error_msg = "Failed to move uploaded file to $target_file";
            return false;
        }
    }
    return null;
}

// Add Product Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = $_POST['name'];
    $cat = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $price = $_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : null;
    $stock = $_POST['stock'];
    $status = $_POST['availability_status'] ?? 'In Stock';
    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    $error_msg = null;
    $img = handleImageUpload($_FILES['product_image'], $error_msg);
    
    if ($img === false) {
        $_SESSION['error_msg'] = "Upload Error: " . $error_msg;
        header("Location: products.php");
        exit;
    }
    
    if (!$img) {
        $img = $_POST['image_url'];
    }

    // If no image provided, try to find a relevant placeholder using the helper
    if (empty($img)) {
        $img = getProductImage('', $name);
    }
    
    $is_exclusive = isset($_POST['is_exclusive']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, discount_price, stock_quantity, availability_status, expiry_date, image_url, is_exclusive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $cat, $price, $discount_price, $stock, $status, $expiry, $img, $is_exclusive]);
    logActivity($pdo, "Added new product: " . $name);
    $_SESSION['success_msg'] = "Product <strong>$name</strong> added successfully!";
    header("Location: products.php");
    exit;
}

// Edit Product Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $cat = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $price = $_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : null;
    $stock = $_POST['stock'];
    
    // Auto-update availability status if stock is changed
    $status = ($stock > 0) ? 'In Stock' : 'Out of Stock';
    if (isset($_POST['availability_status']) && $stock > 0) {
        $status = $_POST['availability_status'];
    }

    $expiry = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    $error_msg = null;
    $img = handleImageUpload($_FILES['product_image'], $error_msg);
    
    if ($img === false) {
        $_SESSION['error_msg'] = "Upload Error: " . $error_msg;
        header("Location: products.php");
        exit;
    }
    
    if (!$img) {
        $img = $_POST['image_url'];
    }

    if (empty($img)) {
        $img = getProductImage('', $name);
    }
    
    $is_exclusive = isset($_POST['is_exclusive']) ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ?, discount_price = ?, stock_quantity = ?, availability_status = ?, expiry_date = ?, image_url = ?, is_exclusive = ? WHERE id = ?");
    $stmt->execute([$name, $cat, $price, $discount_price, $stock, $status, $expiry, $img, $is_exclusive, $id]);
    logActivity($pdo, "Updated product ID: " . $id . " ($name)");
    $_SESSION['success_msg'] = "Product <strong>$name</strong> updated successfully!";
    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-bg: #1e293b;
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            overflow-x: hidden;
        }
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh; overflow-y: auto;
            position: fixed;
            left: 0;
            top: 0;
            background-color: var(--sidebar-bg);
            color: #fff;
            z-index: 1050;
            transition: var(--transition);
        }
        .sidebar-brand {
            padding: 2rem 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .sidebar-brand:hover { color: var(--primary-color); }
        .nav-link-admin {
            padding: 0.85rem 1.5rem;
            color: #94a3b8;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
            border-left: 4px solid transparent;
            font-weight: 500;
        }
        .nav-link-admin:hover, .nav-link-admin.active {
            background-color: #334155;
            color: #fff;
            border-left-color: var(--primary-color);
        }
        .nav-link-admin i {
            margin-right: 0.85rem;
            font-size: 1.25rem;
        }
        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
        }
        .stat-card {
            border: none;
            border-radius: 1.25rem;
            transition: var(--transition);
            box-shadow: var(--card-shadow);
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .icon-box {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }
        .btn-white {
            background-color: #fff;
            color: var(--text-main);
            border: none;
        }
        .btn-white:hover {
            background-color: #f8fafc;
        }
        .table thead th {
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--text-muted);
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            color: var(--text-main);
        }
        .badge {
            padding: 0.6em 1em;
            font-weight: 600;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 0.75rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stock-control {
            max-width: 120px;
        }
        .search-bar {
            background-color: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            padding: 0.6rem 1.5rem;
            padding-left: 3rem;
            transition: var(--transition);
        }
        .search-bar:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }
        @media (max-width: 992px) {
            .sidebar { 
                left: -var(--sidebar-width); 
                box-shadow: none;
            }
            .sidebar.active { 
                left: 0; 
                box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            }
            .main-content { 
                margin-left: 0; 
                padding: 1.25rem; 
            }
            .stat-card { margin-bottom: 0; }
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(4px);
                z-index: 1040;
                display: none;
                opacity: 0;
                transition: var(--transition);
            }
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
            }
            .top-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem;
            }
            .top-header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
            .search-container {
                width: 100%;
                order: 2;
            }
            .search-bar {
                width: 100%;
            }
            .add-btn-container {
                width: 100%;
                order: 1;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
        }
        .edit-v-input:hover {
            background-color: rgba(0,0,0,0.05) !important;
            cursor: pointer;
        }
        .edit-v-input:focus {
            background-color: #fff !important;
            border: 1px solid #dee2e6 !important;
            cursor: text;
        }
        .quick-size-btn {
            border-radius: 50px;
            border: 1px solid #dee2e6;
            background: #fff;
            color: #6c757d;
            transition: all 0.2s;
            font-weight: 500;
        }
        .quick-size-btn:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
            color: #212529;
            transform: translateY(-1px);
        }
        .quick-size-btn:active {
            transform: translateY(0);
        }
        .variant-row {
            transition: background-color 0.2s;
        }
        .variant-row:hover {
            background-color: #f8f9fa;
        }
        .variant-action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .variant-action-btn:hover {
            transform: scale(1.1);
        }
        .variant-card {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }
        .variant-header {
            background: #f8fafc;
            padding: 12px 20px;
            border-bottom: 1px solid #edf2f7;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.025em;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
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
            <a href="products.php" class="nav-link-admin active"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin"><i class="bi bi-tags"></i>Categories</a>
            <a href="orders.php" class="nav-link-admin"><i class="bi bi-cart-check"></i>Orders</a>
            <a href="users.php" class="nav-link-admin"><i class="bi bi-people"></i>Customers</a>
            <a href="delivery-persons.php" class="nav-link-admin"><i class="bi bi-truck"></i>Delivery Staff</a>
            <a href="coupons.php" class="nav-link-admin"><i class="bi bi-ticket-perforated"></i>Coupons</a>
            <a href="offers.php" class="nav-link-admin"><i class="bi bi-megaphone"></i>Offers</a>
            
            <p class="px-4 text-muted small text-uppercase fw-bold mt-4 mb-2 opacity-50">Support</p>
            <a href="contact-messages.php" class="nav-link-admin"><i class="bi bi-chat-left-dots"></i>Messages</a>
            <a href="activity_logs.php" class="nav-link-admin"><i class="bi bi-journal-text"></i>Activity Logs</a>
            
            <hr class="mx-3 my-4 opacity-10">
            <a href="../logout.php?from=admin" class="nav-link-admin text-danger"><i class="bi bi-box-arrow-left"></i>Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4 mb-5 top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h4 class="fw-bold mb-0">Inventory Management</h4>
                    <p class="text-muted small mb-0">Manage your products and stock levels</p>
                </div>
            </div>
            <div class="d-flex gap-3 top-header-actions">
                <div class="position-relative search-container">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" class="search-bar" id="productSearch" placeholder="Search products...">
                </div>
                <div class="add-btn-container">
                    <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm w-100" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-lg me-2"></i>Add Product
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Inventory Stats -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card stat-card border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-primary-subtle text-primary me-3">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-0 fw-bold text-uppercase">Total Products</p>
                                <h3 class="fw-bold mb-0" id="stat-total"><?php echo $total_products; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-warning-subtle text-warning me-3">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-0 fw-bold text-uppercase">Low Stock (≤10)</p>
                                <h3 class="fw-bold mb-0" id="stat-low"><?php echo $low_stock; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-danger-subtle text-danger me-3">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div>
                                <p class="text-muted small mb-0 fw-bold text-uppercase">Out of Stock</p>
                                <h3 class="fw-bold mb-0" id="stat-out"><?php echo $out_of_stock; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="productsTable">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Product Details</th>
                                <th>Category</th>

                                <th style="width: 180px;">Stock Control</th>
                                <th>Status</th>
                                <th>Expiry</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($products as $p): ?>
                            <tr class="<?php echo $p['status'] === 'Archived' ? 'opacity-50 bg-light' : ''; ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo getProductImage($p['image_url'], $p['name']); ?>" class="product-img me-3">
                                        <div>
                                            <div class="fw-bold text-dark product-name">
                                                <?php echo htmlspecialchars($p['name']); ?>
                                                <?php if($p['status'] === 'Archived'): ?>
                                                    <span class="badge bg-secondary extra-small ms-1">ARCHIVED</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted smaller">ID: #<?php echo $counter++; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted border rounded-pill px-3">
                                        <?php echo htmlspecialchars($p['cat_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <?php if (isset($variants[$p['id']])): ?>
                                            <div class="d-flex flex-column gap-1">

                                                <?php foreach ($variants[$p['id']] as $v): 
                                                    $isOOS = $v['stock_quantity'] <= 0;
                                                ?>
                                                    <div class="d-flex align-items-center justify-content-between gap-2 variant-row-item">
                                                        <span class="smaller <?php echo $isOOS ? 'text-danger fw-bold' : 'text-muted'; ?> variant-label" style="font-size: 0.65rem;">
                                                            <?php echo htmlspecialchars($v['size_name']); ?>:
                                                        </span>
                                                        <div class="input-group input-group-sm rounded-pill overflow-hidden border <?php echo $isOOS ? 'border-danger shadow-sm' : ''; ?>" style="width: 100px;">
                                                            <button class="btn <?php echo $isOOS ? 'btn-danger-subtle text-danger' : 'btn-light'; ?> btn-xs border-0 variant-stock-update-btn px-1" type="button" data-action="minus" data-variant-id="<?php echo $v['id']; ?>"><i class="bi bi-dash" style="font-size: 0.7rem;"></i></button>
                                                            <input type="number" class="form-control form-control-sm border-0 text-center fw-bold variant-stock-input p-0 <?php echo $isOOS ? 'bg-danger-subtle text-danger' : 'bg-white'; ?>" 
                                                                   value="<?php echo $v['stock_quantity']; ?>" 
                                                                   data-variant-id="<?php echo $v['id']; ?>" 
                                                                   id="variant-stock-input-<?php echo $v['id']; ?>"
                                                                   min="0" style="font-size: 0.75rem;">
                                                            <button class="btn <?php echo $isOOS ? 'btn-danger-subtle text-danger' : 'btn-light'; ?> btn-xs border-0 variant-stock-update-btn px-1" type="button" data-action="plus" data-variant-id="<?php echo $v['id']; ?>"><i class="bi bi-plus" style="font-size: 0.7rem;"></i></button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="input-group input-group-sm rounded-pill overflow-hidden border">
                                                <button class="btn btn-light border-0 stock-update-btn" type="button" data-action="minus" data-id="<?php echo $p['id']; ?>"><i class="bi bi-dash"></i></button>
                                                <input type="number" class="form-control border-0 text-center stock-input fw-bold bg-white" 
                                                       value="<?php echo $p['stock_quantity']; ?>" 
                                                       data-id="<?php echo $p['id']; ?>"
                                                       id="stock-input-<?php echo $p['id']; ?>">
                                                <button class="btn btn-light border-0 stock-update-btn" type="button" data-action="plus" data-id="<?php echo $p['id']; ?>"><i class="bi bi-plus"></i></button>
                                            </div>
                                            <div id="stock-status-<?php echo $p['id']; ?>">
                                                <?php 
                                                $stockClass = 'bg-success-subtle text-success';
                                                $stockLabel = 'IN STOCK';
                                                if ($p['stock_quantity'] <= 0) {
                                                    $stockClass = 'bg-danger-subtle text-danger';
                                                    $stockLabel = 'OUT OF STOCK';
                                                } elseif ($p['stock_quantity'] <= 10) {
                                                    $stockClass = 'bg-warning-subtle text-warning';
                                                    $stockLabel = 'LOW STOCK';
                                                }
                                                ?>
                                                <span class="badge rounded-pill <?php echo $stockClass; ?> w-100 py-1" style="font-size: 0.65rem;">
                                                    <?php echo $stockLabel; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $statusLabel = strtoupper($p['availability_status']);
                                    $statusClass = $p['availability_status'] === 'In Stock' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                    
                                    if (isset($variants[$p['id']])) {
                                        $oos_variants = [];
                                        $total_variants = count($variants[$p['id']]);
                                        foreach ($variants[$p['id']] as $v) {
                                            if ($v['stock_quantity'] <= 0) {
                                                $oos_variants[] = $v['size_name'];
                                            }
                                        }
                                        
                                        if (count($oos_variants) === $total_variants) {
                                            // All variants are out of stock
                                            $statusLabel = 'OUT OF STOCK';
                                            $statusClass = 'bg-danger-subtle text-danger';
                                        } elseif (!empty($oos_variants)) {
                                            // Some variants are out of stock
                                            $statusLabel = 'FILL ' . implode(', ', $oos_variants) . ' STOCK';
                                            $statusClass = 'bg-warning-subtle text-warning border border-warning-subtle fw-bold';
                                        } else {
                                            // All variants have stock
                                            $statusLabel = 'IN STOCK';
                                            $statusClass = 'bg-success-subtle text-success';
                                        }
                                    }
                                    ?>
                                    <span id="status-badge-<?php echo $p['id']; ?>" class="badge rounded-pill <?php echo $statusClass; ?> px-3 text-wrap" style="font-size: 0.7rem; max-width: 150px; line-height: 1.2;">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $has_variant_expiry = false;
                                    if (isset($variants[$p['id']])) {
                                        foreach ($variants[$p['id']] as $v) {
                                            if (!empty($v['expiry_date'])) {
                                                $has_variant_expiry = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($has_variant_expiry): ?>
                                        <div class="d-flex flex-column gap-1">
                                        <?php foreach ($variants[$p['id']] as $v): ?>
                                            <?php if (!empty($v['expiry_date'])): 
                                                $expiry_date = new DateTime($v['expiry_date']);
                                                $today = new DateTime();
                                                $diff = $today->diff($expiry_date);
                                                $days = (int)$diff->format("%r%a");
                                                
                                                $badgeClass = 'bg-info-subtle text-info';
                                                if ($days < 0) $badgeClass = 'bg-dark-subtle text-dark';
                                                elseif ($days <= 2) $badgeClass = 'bg-danger-subtle text-danger';
                                                elseif ($days <= 14) $badgeClass = 'bg-warning-subtle text-warning';
                                            ?>
                                                <div class="badge rounded-pill <?php echo $badgeClass; ?> px-2 py-1 text-start d-flex justify-content-between align-items-center" style="font-size: 0.65rem;">
                                                    <span class="me-2 fw-normal opacity-75"><?php echo htmlspecialchars($v['size_name']); ?>:</span>
                                                    <span><i class="bi bi-clock me-1"></i><?php echo date('M d, y', strtotime($v['expiry_date'])); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="badge rounded-pill bg-light text-muted border px-2 py-1 text-start d-flex justify-content-between align-items-center" style="font-size: 0.65rem;">
                                                    <span class="me-2 fw-normal opacity-75"><?php echo htmlspecialchars($v['size_name']); ?>:</span>
                                                    <span>-</span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($p['expiry_date'])): ?>
                                        <?php 
                                        $expiry_date = new DateTime($p['expiry_date']);
                                        $today = new DateTime();
                                        $diff = $today->diff($expiry_date);
                                        $days = (int)$diff->format("%r%a");
                                        
                                        $badgeClass = 'bg-info-subtle text-info';
                                        if ($days < 0) $badgeClass = 'bg-dark-subtle text-dark';
                                        elseif ($days <= 2) $badgeClass = 'bg-danger-subtle text-danger';
                                        elseif ($days <= 14) $badgeClass = 'bg-warning-subtle text-warning';
                                        ?>
                                        <div class="badge rounded-pill <?php echo $badgeClass; ?> px-2 py-1" style="font-size: 0.65rem;">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('M d, Y', strtotime($p['expiry_date'])); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted smaller">No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <?php if($p['status'] === 'Archived'): ?>
                                            <a href="?restore=<?php echo $p['id']; ?>" 
                                               class="btn btn-sm btn-white shadow-sm rounded-circle border p-2" 
                                               title="Restore Product">
                                                <i class="bi bi-arrow-counterclockwise text-success"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-white shadow-sm rounded-circle border p-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#variantModal" 
                                                    data-product-id="<?php echo $p['id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($p['name']); ?>"
                                                    title="Manage Variants">
                                                <i class="bi bi-layers text-info"></i>
                                            </button>
                                            <button class="btn btn-sm btn-white shadow-sm rounded-circle border p-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal" 
                                                    data-product='<?php echo json_encode($p); ?>'
                                                    data-variant-count="<?php echo isset($variants[$p['id']]) ? count($variants[$p['id']]) : 0; ?>"
                                                    data-variants='<?php echo json_encode($variants[$p['id']] ?? []); ?>'
                                                    title="Edit Product">
                                                <i class="bi bi-pencil text-primary"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $p['id']; ?>" 
                                           class="btn btn-sm btn-white shadow-sm rounded-circle border p-2"
                                           onclick="return confirm('<?php echo $p['status'] === 'Archived' ? 'Are you sure you want to permanently delete this archived product?' : 'Are you sure? If this product has orders, it will be archived instead of deleted.'; ?>')">
                                            <i class="bi bi-trash text-danger"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content border-0 shadow-lg rounded-4" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_product" value="1">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Product Name</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="e.g. Organic Tomatoes" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Category</label>
                            <select name="category_id" class="form-select rounded-3">
                                <option value="">Uncategorized</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Hidden fields with default values for initial creation -->
                        <input type="hidden" name="price" value="0">
                        <input type="hidden" name="discount_price" value="">
                        <input type="hidden" name="stock" value="0">
                        <input type="hidden" name="availability_status" value="Out of Stock">
                        <input type="hidden" name="expiry_date" value="">
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_exclusive" id="add_is_exclusive">
                                <label class="form-check-label fw-bold ms-2" for="add_is_exclusive" style="cursor:pointer;">Exclusive Offer Only (Hide from main shop)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Product Image</label>
                            <div class="input-group">
                                <input type="file" name="product_image" class="form-control rounded-start-3" accept="image/*">
                                <span class="input-group-text">OR</span>
                                <input type="text" name="image_url" class="form-control rounded-end-3" placeholder="Image URL">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content border-0 shadow-lg rounded-4" method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="edit_product" value="1">
                    <input type="hidden" name="product_id" id="edit_id">
                    <div class="row g-3">
                        <div id="edit_variant_notice" class="col-12 d-none">
                            <div class="alert alert-info py-2 px-3 small mb-2 rounded-3 border-info-subtle">
                                <i class="bi bi-info-circle me-2"></i> This product has variants (sizes). Price and Stock should be managed via the <strong>Manage Variants</strong> tool.
                            </div>
                            <div id="edit_variant_stock_display" class="p-3 bg-light rounded-3 border mb-3">
                                <h6 class="fw-bold mb-2 small text-uppercase ls-1">Variant Stock Levels</h6>
                                <div id="variant_stock_list" class="row g-2">
                                    <!-- Variants will be injected here -->
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Product Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Category</label>
                            <select name="category_id" id="edit_cat" class="form-select rounded-3">
                                <option value="">Uncategorized</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 standard-field">
                            <label class="form-label small fw-bold">Price (₹)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4 standard-field">
                            <label class="form-label small fw-bold">Discount Price (₹)</label>
                            <input type="number" step="0.01" name="discount_price" id="edit_discount" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4 standard-field">
                            <label class="form-label small fw-bold">Stock Quantity</label>
                            <input type="number" name="stock" id="edit_stock" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6 standard-field">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="availability_status" id="edit_status" class="form-select rounded-3">
                                <option value="In Stock">In Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-md-6 standard-field">
                            <label class="form-label small fw-bold">Expiry Date</label>
                            <input type="date" name="expiry_date" id="edit_expiry" class="form-control rounded-3">
                        </div>
                        <div class="col-12 mt-3">
                            <div class="form-check form-switch fs-6">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_exclusive" id="edit_is_exclusive">
                                <label class="form-check-label fw-bold ms-2" for="edit_is_exclusive" style="cursor:pointer;">Exclusive Offer Only (Hide from main shop)</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <img id="edit_img_preview" src="" class="rounded-3 shadow-sm border" style="width: 60px; height: 60px; object-fit: cover; display: none;">
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-bold">Product Image</label>
                                    <div class="input-group">
                                        <input type="file" name="product_image" class="form-control rounded-start-3" accept="image/*">
                                        <span class="input-group-text">OR</span>
                                        <input type="text" name="image_url" id="edit_img" class="form-control rounded-end-3" placeholder="Image URL">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Variant Management Modal -->
    <div class="modal fade" id="variantModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="modal-title fw-bold" id="variantsModalLabel">Manage Variants</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <p class="text-muted small mb-0" id="variantModalProductName"></p>
                    </div>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="variant_product_id">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark">Current Variants</h6>
                        <div>
                            <button type="button" id="saveAllVariantsBtn" class="btn btn-outline-success btn-sm rounded-pill px-3 me-2">
                                <i class="bi bi-save me-1"></i> Save All
                            </button>
                            <button type="button" id="disableVariantsBtn" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-x-circle me-1"></i> Disable Variant
                            </button>
                        </div>
                    </div>
                    
                    <div class="variant-card mb-4">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <thead class="variant-header">
                                    <tr>
                                        <th class="ps-4">Size/Option</th>
                                        <th>Price (₹)</th>
                                        <th>Discount (₹)</th>
                                        <th>Stock</th>
                                        <th>Expiry</th>
                                        <th class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="variantsList">
                                    <!-- Loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-light rounded-4 p-4 border">
                        <h6 class="fw-bold mb-3 text-dark">create a Variant option</h6>
                        <form id="addVariantForm" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label smaller fw-bold text-muted mb-1">Size (e.g. 500g)</label>
                                <input type="text" name="size_name" id="new_variant_size" class="form-control rounded-3 border-0 shadow-sm" placeholder="Enter size" required>
                                    <div class="mt-2 text-muted fw-bold" style="font-size: 0.7rem;">WEIGHT</div>
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="50g">50g</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="100g">100g</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="250g">250g</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="500g">500g</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="1kg">1kg</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="2kg">2kg</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="5kg">5kg</button>
                                    </div>
                                    <div class="text-muted fw-bold" style="font-size: 0.7rem;">VOLUME</div>
                                    <div class="d-flex flex-wrap gap-1 mb-2">
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="50ml">50ml</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="100ml">100ml</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="200ml">200ml</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="250ml">250ml</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="500ml">500ml</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="1L">1L</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="2L">2L</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="5L">5L</button>
                                    </div>
                                    <div class="text-muted fw-bold" style="font-size: 0.7rem;">COUNT / OTHER</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="1 pc">1 pc</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="6 pcs">6 pcs</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="12 pcs">12 pcs</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="1 Unit">1 Unit</button>
                                        <button type="button" class="btn btn-xs quick-size-btn px-2" data-size="1 Pack">1 Pack</button>
                                    </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label smaller fw-bold text-muted mb-1">Price (₹)</label>
                                <input type="number" step="0.01" name="price" class="form-control rounded-3 border-0 shadow-sm" placeholder="0.00" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label smaller fw-bold text-muted mb-1">Discount (₹)</label>
                                <input type="number" step="0.01" name="discount_price" class="form-control rounded-3 border-0 shadow-sm" placeholder="0.00">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label smaller fw-bold text-muted mb-1">Stock</label>
                                <input type="number" name="stock_quantity" class="form-control rounded-3 border-0 shadow-sm text-center" value="0" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label smaller fw-bold text-muted mb-1">Expiry Date</label>
                                <input type="date" name="expiry_date" class="form-control rounded-3 border-0 shadow-sm">
                            </div>
                            
                            <div class="col-12 text-end mt-4">
                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                                    <i class="bi bi-plus-lg me-1"></i> Save Variant
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Sidebar Toggle
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', toggleSidebar);
            }

            // Close sidebar on window resize if open
            window.addEventListener('resize', () => {
                if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Product Search
            const productSearch = document.getElementById('productSearch');
            const productsTable = document.getElementById('productsTable');
            if (productSearch) {
                productSearch.addEventListener('input', (e) => {
                    const term = e.target.value.toLowerCase();
                    const rows = productsTable.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const name = row.querySelector('.product-name').textContent.toLowerCase();
                        row.style.display = name.includes(term) ? '' : 'none';
                    });
                });
            }

            // Edit Modal Handler
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const p = JSON.parse(button.getAttribute('data-product'));
                    const variantCount = parseInt(button.dataset.variantCount || 0);
                    const variants = JSON.parse(button.getAttribute('data-variants') || '[]');
                    
                    document.getElementById('edit_id').value = p.id;
                    document.getElementById('edit_name').value = p.name;
                    document.getElementById('edit_cat').value = p.category_id || "";
                    document.getElementById('edit_price').value = p.price;
                    document.getElementById('edit_discount').value = p.discount_price || "";
                    document.getElementById('edit_stock').value = p.stock_quantity;
                    document.getElementById('edit_status').value = p.availability_status;
                    document.getElementById('edit_expiry').value = p.expiry_date || "";
                    document.getElementById('edit_img').value = p.image_url || "";
                    document.getElementById('edit_is_exclusive').checked = (p.is_exclusive == 1);

                    const variantNotice = document.getElementById('edit_variant_notice');
                    const variantStockList = document.getElementById('variant_stock_list');
                    
                    if (variantNotice) {
                        if (variantCount > 0) {
                            variantNotice.classList.remove('d-none');
                            
                            // Populate variant stock list
                            if (variantStockList) {
                                variantStockList.innerHTML = variants.map(v => `
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded-2 border border-light-subtle shadow-sm">
                                            <span class="fw-bold small text-muted">${v.size_name}</span>
                                            <span class="badge ${v.stock_quantity > 0 ? 'bg-success' : 'bg-danger'} rounded-pill">${v.stock_quantity}</span>
                                        </div>
                                    </div>
                                `).join('');
                            }
                        } else {
                            variantNotice.classList.add('d-none');
                        }
                    }
                    
                    const preview = document.getElementById('edit_img_preview');
                    
                    // Toggle standard fields based on variants
                    const standardFields = document.querySelectorAll('.standard-field');
                    standardFields.forEach(field => {
                        if (variantCount > 0) {
                            field.classList.add('d-none');
                        } else {
                            field.classList.remove('d-none');
                        }
                    });
                    if (p.image_url) {
                        preview.src = p.image_url.startsWith('http') ? p.image_url : '../' + p.image_url;
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }

            // Handle New/Edit Image Preview for file inputs
            document.querySelectorAll('input[type="file"][name="product_image"]').forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        const preview = this.closest('.modal-body').querySelector('img[id$="_img_preview"]') || 
                                      this.closest('.modal-body').querySelector('.img-preview-add');
                        
                        reader.onload = function(e) {
                            if (preview) {
                                preview.src = e.target.result;
                                preview.style.display = 'block';
                            } else {
                                const previewContainer = input.parentElement.parentElement;
                                let newPreview = previewContainer.querySelector('.img-preview-add');
                                if (!newPreview) {
                                    newPreview = document.createElement('img');
                                    newPreview.className = 'img-preview-add rounded-3 shadow-sm border mb-2';
                                    newPreview.style.cssText = 'width: 60px; height: 60px; object-fit: cover;';
                                    previewContainer.prepend(newPreview);
                                }
                                newPreview.src = e.target.result;
                            }
                        }
                        reader.readAsDataURL(file);
                    }
                });
            });

            // AJAX Stock Updates
            const updateStock = async (productId, newStock) => {
                const stockStatus = document.getElementById(`stock-status-${productId}`);
                const originalContent = stockStatus.innerHTML;
                stockStatus.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status"></span>';

                const formData = new FormData();
                formData.append('update_stock_ajax', '1');
                formData.append('product_id', productId);
                formData.append('new_stock', newStock);

                try {
                    const response = await fetch('products.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        // Update status badge
                        const statusBadge = document.getElementById(`status-badge-${productId}`);
                        statusBadge.textContent = data.status.toUpperCase();
                        statusBadge.className = `badge rounded-pill ${data.status === 'In Stock' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'} px-3`;

                        // Update stock badge
                        let badgeClass = 'bg-success-subtle text-success';
                        let badgeText = 'IN STOCK';
                        if (newStock <= 0) {
                            badgeClass = 'bg-danger-subtle text-danger';
                            badgeText = 'OUT OF STOCK';
                        } else if (newStock <= 10) {
                            badgeClass = 'bg-warning-subtle text-warning';
                            badgeText = 'LOW STOCK';
                        }
                        stockStatus.innerHTML = `<span class="badge rounded-pill ${badgeClass} w-100 py-1" style="font-size: 0.65rem;">${badgeText}</span>`;

                        // Update global stats
                        document.getElementById('stat-total').textContent = data.stats.total;
                        document.getElementById('stat-low').textContent = data.stats.low_stock || 0;
                        document.getElementById('stat-out').textContent = data.stats.out_of_stock || 0;
                    }
                } catch (error) {
                    console.error('Error updating stock:', error);
                    stockStatus.innerHTML = originalContent;
                }
            };

            // Stock Button Listeners
            document.querySelectorAll('.stock-update-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const input = document.getElementById(`stock-input-${id}`);
                    let val = parseInt(input.value);
                    val = btn.dataset.action === 'plus' ? val + 1 : Math.max(0, val - 1);
                    input.value = val;
                    updateStock(id, val);
                });
            });

            // Variant Stock Button Listeners
            document.querySelectorAll('.variant-stock-update-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const variantId = btn.dataset.variantId;
                    const input = document.getElementById(`variant-stock-input-${variantId}`);
                    let val = parseInt(input.value);
                    val = btn.dataset.action === 'plus' ? val + 1 : Math.max(0, val - 1);
                    input.value = val;
                    
                    // Trigger the existing variant stock update logic
                    input.dispatchEvent(new Event('change'));
                });
            });

            // Stock Input Listener
            document.querySelectorAll('.stock-input').forEach(input => {
                input.addEventListener('change', () => {
                    const id = input.dataset.id;
                    const val = Math.max(0, parseInt(input.value) || 0);
                    input.value = val;
                    updateStock(id, val);
                });
            });

            // Variant Stock Input Listener
            document.querySelectorAll('.variant-stock-input').forEach(input => {
                input.addEventListener('change', async () => {
                    const variantId = input.dataset.variantId;
                    const val = Math.max(0, parseInt(input.value) || 0);
                    input.value = val;

                    const formData = new FormData();
                    formData.append('update_variant_stock_ajax', '1');
                    formData.append('variant_id', variantId);
                    formData.append('new_stock', val);

                    try {
                        const response = await fetch('products.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        if (data.success) {
                            input.classList.add('is-valid', 'border-success');
                            setTimeout(() => input.classList.remove('is-valid', 'border-success'), 2000);
                        }
                    } catch (error) {
                        console.error('Error updating variant stock:', error);
                        input.classList.add('is-invalid', 'border-danger');
                        setTimeout(() => input.classList.remove('is-invalid', 'border-danger'), 2000);
                    }
                });
            });

            // Variant Management Modal Handlers
            const variantModal = document.getElementById('variantModal');
            const variantsList = document.getElementById('variantsList');
            const addVariantForm = document.getElementById('addVariantForm');
            const variantProductIdInput = document.getElementById('variant_product_id');
            const disableVariantsBtn = document.getElementById('disableVariantsBtn');
            const newVariantSizeInput = document.getElementById('new_variant_size');

            // Quick Size Selection
            document.querySelectorAll('.quick-size-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (newVariantSizeInput) {
                        newVariantSizeInput.value = btn.dataset.size;
                        newVariantSizeInput.focus();
                    }
                });
            });

            const fetchVariants = async (productId) => {
                const formData = new FormData();
                formData.append('fetch_variants_ajax', '1');
                formData.append('product_id', productId);

                try {
                    const response = await fetch('products.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        variantsList.innerHTML = data.variants.map(v => `
                            <tr class="variant-row">
                                <td class="ps-4">
                                    <input type="text" class="form-control form-control-sm border-0 bg-transparent edit-v-input edit-v-size fw-semibold" value="${v.size_name}" data-id="${v.id}">
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control form-control-sm border-0 bg-transparent edit-v-input edit-v-price" value="${v.price}" data-id="${v.id}">
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control form-control-sm border-0 bg-transparent edit-v-input edit-v-discount" value="${v.discount_price || ''}" data-id="${v.id}">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm border-0 bg-transparent edit-v-input edit-v-stock" value="${v.stock_quantity}" data-id="${v.id}">
                                </td>
                                <td>
                                    <input type="date" class="form-control form-control-sm border-0 bg-transparent edit-v-input edit-v-expiry" value="${v.expiry_date || ''}" data-id="${v.id}">
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-light variant-action-btn text-primary update-v-btn" data-id="${v.id}" title="Update">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light variant-action-btn text-danger delete-v-btn" data-id="${v.id}" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('') || '<tr><td colspan="5" class="text-center text-muted py-5">No variants found for this product.</td></tr>';
                    }
                } catch (error) {
                    console.error('Error fetching variants:', error);
                }
            };

            if (variantModal) {
                variantModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    const productId = button.dataset.productId;
                    const productName = button.dataset.productName;
                    
                    variantProductIdInput.value = productId;
                    document.getElementById('variantModalProductName').textContent = productName;
                    fetchVariants(productId);
                });
            }

            if (addVariantForm) {
                addVariantForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(addVariantForm);
                    formData.append('add_variant_ajax', '1');
                    formData.append('product_id', variantProductIdInput.value);

                    try {
                        const response = await fetch('products.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            addVariantForm.reset();
                            fetchVariants(variantProductIdInput.value);
                            // Refresh page to update main table after adding first variant
                            location.reload(); 
                        }
                    } catch (error) {
                        console.error('Error adding variant:', error);
                    }
                });
            }

            if (disableVariantsBtn) {
                disableVariantsBtn.addEventListener('click', async () => {
                    if (!confirm('Are you sure you want to disable all variants for this product?')) return;
                    
                    const formData = new FormData();
                    formData.append('disable_variants_ajax', '1');
                    formData.append('product_id', variantProductIdInput.value);

                    try {
                        const response = await fetch('products.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            fetchVariants(variantProductIdInput.value);
                            location.reload();
                        }
                    } catch (error) {
                        console.error('Error disabling variants:', error);
                    }
                });
            }

            const saveAllVariantsBtn = document.getElementById('saveAllVariantsBtn');
            if (saveAllVariantsBtn) {
                saveAllVariantsBtn.addEventListener('click', async () => {
                    const variantRows = variantsList.querySelectorAll('tr.variant-row');
                    if (variantRows.length === 0) return;
                    
                    const originalText = saveAllVariantsBtn.innerHTML;
                    saveAllVariantsBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
                    saveAllVariantsBtn.disabled = true;

                    const variations = Array.from(variantRows).map(row => {
                        return {
                            id: row.querySelector('.edit-v-size').dataset.id,
                            size_name: row.querySelector('.edit-v-size').value,
                            price: row.querySelector('.edit-v-price').value,
                            discount_price: row.querySelector('.edit-v-discount').value,
                            stock_quantity: row.querySelector('.edit-v-stock').value,
                            expiry_date: row.querySelector('.edit-v-expiry').value
                        };
                    });

                    const formData = new FormData();
                    formData.append('update_all_variants_ajax', '1');
                    formData.append('variants_data', JSON.stringify(variations));

                    try {
                        const response = await fetch('products.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            saveAllVariantsBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Saved!';
                            saveAllVariantsBtn.classList.replace('btn-outline-success', 'btn-success');
                            setTimeout(() => {
                                saveAllVariantsBtn.innerHTML = originalText;
                                saveAllVariantsBtn.classList.replace('btn-success', 'btn-outline-success');
                                saveAllVariantsBtn.disabled = false;
                                location.reload();
                            }, 1000);
                        }
                    } catch (error) {
                        console.error('Error saving all variants:', error);
                        saveAllVariantsBtn.innerHTML = originalText;
                        saveAllVariantsBtn.disabled = false;
                    }
                });
            }

            // Delegate events for dynamically created buttons
            variantsList.addEventListener('click', async (e) => {
                const updateBtn = e.target.closest('.update-v-btn');
                const deleteBtn = e.target.closest('.delete-v-btn');

                if (updateBtn) {
                    const id = updateBtn.dataset.id;
                    const row = updateBtn.closest('tr');
                    const size = row.querySelector('.edit-v-size').value;
                    const price = row.querySelector('.edit-v-price').value;
                    const discount = row.querySelector('.edit-v-discount').value;
                    const stock = row.querySelector('.edit-v-stock').value;
                    const expiry = row.querySelector('.edit-v-expiry').value;

                    const formData = new FormData();
                    formData.append('update_variant_ajax', '1');
                    formData.append('variant_id', id);
                    formData.append('size_name', size);
                    formData.append('price', price);
                    formData.append('discount_price', discount);
                    formData.append('stock_quantity', stock);
                    formData.append('expiry_date', expiry);

                    try {
                        const response = await fetch('products.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            updateBtn.classList.replace('btn-outline-primary', 'btn-success');
                            setTimeout(() => updateBtn.classList.replace('btn-success', 'btn-outline-primary'), 2000);
                            location.reload();
                        }
                    } catch (error) {
                        console.error('Error updating variant:', error);
                    }
                }

                if (deleteBtn) {
                    if (!confirm('Delete this variant?')) return;
                    const id = deleteBtn.dataset.id;
                    const formData = new FormData();
                    formData.append('delete_variant_ajax', '1');
                    formData.append('variant_id', id);

                    try {
                        const response = await fetch('products.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        if (data.success) {
                            fetchVariants(variantProductIdInput.value);
                            location.reload();
                        }
                    } catch (error) {
                        console.error('Error deleting variant:', error);
                    }
                }
            });
        });
    </script>
</body>
</html>
