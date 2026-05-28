<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once '../config/db.php'; 

if (!isAdmin()) {
    $login_path = dirname($_SERVER['PHP_SELF']) . '/login.php';
    header("Location: " . $login_path);
    exit;
}

// Delete Logic
if (isset($_GET['delete'])) {
    try {
        // Prevent deletion if it has products
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmtCheck->execute([$_GET['delete']]);
        if ($stmtCheck->fetchColumn() > 0) {
            header("Location: categories.php?error=" . urlencode("Cannot delete category because it contains products. Please move or delete them first."));
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        logActivity($pdo, "Deleted category ID: " . $_GET['delete']);
        header("Location: categories.php?success=" . urlencode("Category deleted successfully."));
    } catch (PDOException $e) {
        header("Location: categories.php?error=" . urlencode("Database Error: " . $e->getMessage()));
    }
    exit;
}

// Bulk Update GST Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_gst'])) {
    $target_category = $_POST['target_category'];
    $gst_percentage = (float)$_POST['gst_percentage'];
    
    if ($target_category === 'all') {
        $stmt = $pdo->prepare("UPDATE products SET tax_percentage = ?");
        $stmt->execute([$gst_percentage]);
        $message = "GST updated to $gst_percentage% for all products.";
    } else {
        // Get this category and all its subcategories
        $cat_ids = [(int)$target_category];
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
        $stmt->execute([$target_category]);
        $subs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($subs) {
            $cat_ids = array_merge($cat_ids, array_map('intval', $subs));
        }
        
        $placeholders = implode(',', array_fill(0, count($cat_ids), '?'));
        $stmt = $pdo->prepare("UPDATE products SET tax_percentage = ? WHERE category_id IN ($placeholders)");
        $params = array_merge([$gst_percentage], $cat_ids);
        $stmt->execute($params);
        
        // Get category name for message
        $cat_stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $cat_stmt->execute([$target_category]);
        $cat_name = $cat_stmt->fetchColumn();
        $message = "GST updated to $gst_percentage% for category: $cat_name (including subcategories).";
    }
    
    logActivity($pdo, $message);
    header("Location: categories.php?success=" . urlencode($message));
    exit;
}

// Helper for Category Image Upload with error reporting
function handleCategoryImageUpload($file, &$error_msg = null) {
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

        $target_dir = "../uploads/categories/";
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
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'jfif', 'svg'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_msg = "Invalid file type: $file_extension. Allowed: " . implode(', ', $allowed_extensions);
            return false;
        }

        // Optional: Check if it's actually an image
        if ($file_extension !== 'svg') {
            $check = @getimagesize($file['tmp_name']);
            if ($check === false) {
                $error_msg = "File is not a valid image.";
                return false;
            }
        }

        $new_filename = uniqid('cat_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            return 'uploads/categories/' . $new_filename;
        } else {
            $error_msg = "Failed to move uploaded file to $target_file";
            return false;
        }
    }
    return null; // No file uploaded, which is fine
}

// Add Category Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $status = $_POST['status'] ?? 'Enabled';
    
    $error_msg = null;
    $img = handleCategoryImageUpload($_FILES['category_image'], $error_msg);
    
    if ($img === false) {
        header("Location: categories.php?error=" . urlencode("Upload Error: " . $error_msg));
        exit;
    }
    
    if (!$img) {
        $img = $_POST['image_url'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, status, image_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $parent_id, $status, $img]);
    header("Location: categories.php?success=" . urlencode("Category added successfully."));
    exit;
}

// Edit Category Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = $_POST['category_id'];
    $name = $_POST['name'];
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $status = $_POST['status'] ?? 'Enabled';
    
    $error_msg = null;
    $img = handleCategoryImageUpload($_FILES['category_image'], $error_msg);
    
    if ($img === false) {
        header("Location: categories.php?error=" . urlencode("Upload Error: " . $error_msg));
        exit;
    }
    
    if (!$img) {
        $img = $_POST['image_url'];
    }
    
    $stmt = $pdo->prepare("UPDATE categories SET name = ?, parent_id = ?, status = ?, image_url = ? WHERE id = ?");
    $stmt->execute([$name, $parent_id, $status, $img, $id]);
    header("Location: categories.php?success=" . urlencode("Category updated successfully."));
    exit;
}

// Fetch all categories with product counts

$categories = $pdo->query("SELECT c.*, p.name as parent_name, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY c.id ASC")->fetchAll();
$parentCategories = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Quick mart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #1e293b;
            --primary-color: #10b981;
            --bg-light: #f8fafc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
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
            z-index: 1000;
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
            padding: 2.5rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: var(--card-shadow);
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
        @media (max-width: 992px) {
            .sidebar { left: -var(--sidebar-width); }
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 5px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>
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
            <a href="products.php" class="nav-link-admin"><i class="bi bi-box-seam"></i>Products</a>
            <a href="categories.php" class="nav-link-admin active"><i class="bi bi-tags"></i>Categories</a>
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
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div class="d-flex align-items-center">
                <button class="btn btn-white shadow-sm d-lg-none me-3" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h4 class="fw-bold mb-0">Category Management</h4>
                    <p class="text-muted small mb-0">Organize and manage your product categories</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-white shadow-sm rounded-pill px-4 py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#bulkGstModal">
                    <i class="bi bi-percent me-2"></i>Bulk Set GST
                </button>
                <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg me-2"></i>Add New Category
                </button>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Category</th>
                                <th>Parent</th>
                                <th>Products</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-folder-x display-4 text-muted opacity-25 d-block mb-3"></i>
                                    <p class="text-muted mb-0">No categories found.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php $counter = 1; ?>
                                <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo getCategoryImage($c['image_url'], $c['name']); ?>" 
                                                 class="rounded-3 shadow-sm me-3" 
                                                 style="width: 45px; height: 45px; object-fit: cover;"
                                                 onerror="this.src='https://via.placeholder.com/45?text=Error'; console.log('Broken image ID #<?php echo $c['id']; ?>: ' + this.src);">
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($c['name']); ?></div>
                                                <small class="text-muted">ID: #<?php echo $c['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-3">
                                            <?php echo htmlspecialchars($c['parent_name'] ?? 'Main Category'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary-subtle text-primary rounded-pill px-3 py-1 small fw-bold">
                                                <?php echo $c['product_count']; ?> Products
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'Enabled'): ?>
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">
                                                <i class="bi bi-check2-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-3">
                                                <i class="bi bi-x-circle me-1"></i>Disabled
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light rounded-circle shadow-sm me-1 edit-btn" 
                                                data-id="<?php echo $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-parent="<?php echo $c['parent_id']; ?>"
                                                data-status="<?php echo $c['status']; ?>"
                                                data-img="<?php echo htmlspecialchars($c['image_url']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?delete=<?php echo $c['id']; ?>" 
                                           class="btn btn-sm btn-light text-danger rounded-circle shadow-sm" 
                                           onclick="return confirm('Are you sure? This will affect products in this category.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_category" value="1">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Parent Category (Optional)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">None (Main Category)</option>
                            <?php foreach ($parentCategories as $pCat): ?>
                                <option value="<?php echo $pCat['id']; ?>"><?php echo $pCat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Enabled">Enabled</option>
                            <option value="Disabled">Disabled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Category Photo</label>
                        <input type="file" name="category_image" class="form-control" accept="image/*">
                        <div class="form-text">Or provide an image URL below</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="text" name="image_url" class="form-control" placeholder="https://unsplash.com/...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-plus-circle me-2"></i>Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" name="category_id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Parent Category (Optional)</label>
                        <select name="parent_id" id="edit_parent" class="form-select">
                            <option value="">None (Main Category)</option>
                            <?php foreach ($parentCategories as $pCat): ?>
                                <option value="<?php echo $pCat['id']; ?>"><?php echo $pCat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Enabled">Enabled</option>
                            <option value="Disabled">Disabled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload New Photo</label>
                        <div class="mb-2">
                            <img id="edit_img_preview" src="" style="width: 80px; height: 80px; object-fit: cover; display: none;" class="rounded shadow-sm border">
                        </div>
                        <input type="file" name="category_image" class="form-control" accept="image/*">
                        <div class="form-text">Leave empty to keep current or update URL below</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="text" name="image_url" id="edit_img" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-circle me-2"></i>Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk GST Modal -->
    <div class="modal fade" id="bulkGstModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Apply GST</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="bulk_update_gst" value="1">
                    
                    <div class="alert alert-warning small border-0 shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Selecting "All Products" will overwrite GST for every item in your store.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Category</label>
                        <select name="target_category" class="form-select" required>
                            <option value="all">⚠️ All Products (Global Update)</option>
                            <optgroup label="Specific Categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">GST Percentage (%)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="gst_percentage" id="bulk_gst_input" class="form-control" placeholder="e.g. 18" required>
                            <span class="input-group-text bg-primary text-white">%</span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted me-2">Common rates:</small>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary gst-shortcut" data-value="0">0%</button>
                                <button type="button" class="btn btn-outline-secondary gst-shortcut" data-value="5">5%</button>
                                <button type="button" class="btn btn-outline-secondary gst-shortcut" data-value="12">12%</button>
                                <button type="button" class="btn btn-outline-secondary gst-shortcut" data-value="18">18%</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary shadow-sm">
                        <i class="bi bi-check2-all me-2"></i>Apply Bulk Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Sidebar Toggle
            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
            });

            // Bulk GST shortcut rates
            document.querySelectorAll('.gst-shortcut').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById('bulk_gst_input').value = btn.dataset.value;
                });
            });

            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const name = btn.dataset.name;
                    const parent = btn.dataset.parent || "";
                    const status = btn.dataset.status;
                    const img = btn.dataset.img;
                    
                    document.getElementById('edit_id').value = id;
                    document.getElementById('edit_name').value = name;
                    document.getElementById('edit_parent').value = parent;
                    document.getElementById('edit_status').value = status;
                    document.getElementById('edit_img').value = img;
                    
                    // Update preview
                    const preview = document.getElementById('edit_img_preview');
                    if (img) {
                        // Use a simple version of getCategoryImage logic for preview
                        let previewSrc = img;
                        if (!img.startsWith('http')) {
                            previewSrc = '../' + img;
                        }
                        preview.src = previewSrc;
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            });

            // Handle New/Edit Image Preview for file inputs
            document.querySelectorAll('input[type="file"][name="category_image"]').forEach(input => {
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
                                // For Add modal, we might need to create a preview element
                                const previewContainer = input.parentElement;
                                let newPreview = previewContainer.querySelector('.img-preview-add');
                                if (!newPreview) {
                                    newPreview = document.createElement('img');
                                    newPreview.className = 'img-preview-add rounded shadow-sm border mb-2';
                                    newPreview.style.cssText = 'width: 80px; height: 80px; object-fit: cover;';
                                    previewContainer.insertBefore(newPreview, input);
                                }
                                newPreview.src = e.target.result;
                            }
                        }
                        reader.readAsDataURL(file);
                    }
                });
            });
        });
    </script>
</body>
</html>
