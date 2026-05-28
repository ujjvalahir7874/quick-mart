<?php 
require_once 'includes/header.php'; 

$category_id = $_GET['category'] ?? null;
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;
$rating = $_GET['rating'] ?? null;
$sort = $_GET['sort'] ?? 'newest';

$effectivePriceExpr = "IFNULL((SELECT MIN(IFNULL(NULLIF(pv.discount_price,0), pv.price)) FROM product_variants pv WHERE pv.product_id = p.id), IFNULL(NULLIF(p.discount_price,0), p.price))";

$sql = "SELECT p.*, c.name as cat_name, 
        (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) as avg_rating,
        (SELECT COUNT(*) FROM product_reviews WHERE product_id = p.id) as review_count,
        $effectivePriceExpr AS effective_price
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'Active' AND p.is_exclusive = 0";
$params = [];

if ($category_id) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($min_price !== null && $min_price !== '') {
    $sql .= " AND $effectivePriceExpr >= ?";
    $params[] = (float)$min_price;
}

if ($max_price !== null && $max_price !== '') {
    $sql .= " AND $effectivePriceExpr <= ?";
    $params[] = (float)$max_price;
}

if ($rating) {
    $sql .= " AND (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.id) >= ?";
    $params[] = $rating;
}

// Sorting
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY $effectivePriceExpr ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY $effectivePriceExpr DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.id DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch variants for all products on this page
$product_ids = array_map(function($p) { return $p['id']; }, $products);
$product_variants = [];
if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $v_stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id IN ($placeholders) ORDER BY price ASC");
    $v_stmt->execute($product_ids);
    $all_variants = $v_stmt->fetchAll();
    foreach ($all_variants as $v) {
        $product_variants[$v['product_id']][] = $v;
    }
}

// Get current user's wishlist IDs
$wishlist_ids = [];
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>

<style>
    .variant-dropdown-btn {
        transition: all 0.2s ease;
        background-color: #f8f9fa;
        border-color: #eee !important;
    }
    .variant-dropdown-btn:hover, .variant-dropdown-btn:focus {
        background-color: #fff;
        border-color: #198754 !important;
        color: #198754 !important;
    }
    .variant-option {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f8f9fa;
    }
    .variant-option:last-child {
        border-bottom: none;
    }
    .variant-option:hover {
        background-color: #f0fdf4 !important;
    }
    .dropdown-menu {
        min-width: 100%;
        border: 1px solid rgba(0,0,0,.05) !important;
    }
</style>

<div class="container py-5">
    <div class="row gy-4">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="collapse d-lg-block" id="filterContent">
                <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden sticky-top" style="top: 100px;">
                    <div class="card-header bg-white py-3 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-800 mb-0">Filters</h5>
                            <a href="products.php" class="text-success small text-decoration-none fw-bold">Reset All</a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 text-muted small text-uppercase ls-1">Categories</h6>
                            <div class="d-flex flex-column gap-2">
                                <a href="products.php" class="btn btn-sm text-start py-2 px-3 rounded-3 fw-bold transition-all <?php echo !$category_id ? 'btn-success shadow-sm' : 'btn-light text-muted'; ?>">
                                    <i class="bi bi-grid-fill me-2"></i>All Categories
                                </a>
                                <?php foreach ($categories as $cat): ?>
                                    <a href="products.php?category=<?php echo $cat['id']; ?>" 
                                       class="btn btn-sm text-start py-2 px-3 rounded-3 fw-bold transition-all <?php echo $category_id == $cat['id'] ? 'btn-success shadow-sm' : 'btn-light text-muted'; ?>">
                                        <i class="bi bi-tag-fill me-2 opacity-50"></i><?php echo $cat['name']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <hr class="my-4 opacity-10">

                        <div class="mb-4">
                            <h6 class="fw-800 mb-3 text-muted small text-uppercase ls-1">Price Range</h6>
                            <form action="products.php" method="GET">
                                <?php if($category_id): ?><input type="hidden" name="category" value="<?php echo $category_id; ?>"><?php endif; ?>
                                <?php if($search): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
                                <?php if($sort): ?><input type="hidden" name="sort" value="<?php echo $sort; ?>"><?php endif; ?>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="number" name="min_price" class="form-control border-0 bg-light rounded-3 fw-bold" id="minPrice" placeholder="Min" value="<?php echo $min_price; ?>">
                                            <label for="minPrice" class="small text-muted fw-bold">Min ₹</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="number" name="max_price" class="form-control border-0 bg-light rounded-3 fw-bold" id="maxPrice" placeholder="Max" value="<?php echo $max_price; ?>">
                                            <label for="maxPrice" class="small text-muted fw-bold">Max ₹</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr class="my-4 opacity-10">
                                
                                <h6 class="fw-800 mb-3 text-muted small text-uppercase ls-1">Review</h6>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php for($i=5; $i>=1; $i--): ?>
                                    <div class="form-check custom-rating-check">
                                        <input class="form-check-input" type="radio" name="rating" value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" <?php echo ($rating == $i) ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-bold d-flex align-items-center justify-content-between w-100" for="rating<?php echo $i; ?>">
                                            <div class="text-warning">
                                                <?php for($j=1; $j<=5; $j++): ?>
                                                    <i class="bi bi-star<?php echo $j <= $i ? '-fill' : ($j - 0.5 <= $i ? '-half' : ''); ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="text-dark ms-2"><?php echo $i; ?> Star</span>
                                        </label>
                                    </div>
                                    <?php endfor; ?>
                                </div>

                                <button type="submit" class="btn btn-success w-100 rounded-4 py-2 fw-800 shadow-sm transition-hover">
                                    APPLY FILTERS <i class="bi bi-funnel-fill ms-1 small"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Promotion Card -->
                <div class="card border-0 rounded-4 overflow-hidden text-white shadow-lg mb-4" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <div class="card-body p-4 text-center position-relative">
                        <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                        <i class="bi bi-lightning-charge-fill display-4 mb-3 d-block text-warning animate__animated animate__pulse animate__infinite"></i>
                        <h5 class="fw-800">Fast Delivery</h5>
                        <p class="small opacity-90 mb-0">Get your groceries delivered in 60 minutes.</p>
                    </div>
                </div>
            </div>

            <!-- Mobile Filter Toggle -->
            <button class="btn btn-success w-100 rounded-4 py-3 fw-800 d-lg-none mb-4 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterContent" aria-controls="filterContent" aria-expanded="false">
                <i class="bi bi-filter-left me-1"></i> SHOW FILTERS
            </button>
        </div>

        <!-- Product List -->
        <div class="col-lg-9">
            <div class="d-md-flex justify-content-between align-items-center mb-4 gap-3">
                <form action="products.php" method="GET" class="flex-grow-1 mb-3 mb-md-0">
                    <div class="input-group shadow-sm rounded-4 overflow-hidden bg-white">
                        <span class="input-group-text bg-white border-0 ps-4"><i class="bi bi-search text-success"></i></span>
                        <input type="text" name="search" class="form-control border-0 py-3 fw-bold" placeholder="Search for fresh groceries..." value="<?php echo htmlspecialchars($search); ?>" style="box-shadow: none;">
                        <button class="btn btn-success px-4 fw-800" type="submit">SEARCH</button>
                    </div>
                </form>
                
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-white bg-white shadow-sm border-0 rounded-4 py-2 px-4 dropdown-toggle fw-800" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-sort-down me-2 text-success"></i> 
                            <span class="small">SORT BY</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 p-2">
                            <li><a class="dropdown-item rounded-3 py-2 fw-bold <?php echo $sort == 'newest' ? 'active bg-success' : ''; ?>" href="products.php?sort=newest<?php echo $category_id ? '&category='.$category_id : ''; ?><?php echo $search ? '&search='.$search : ''; ?>">Newest First</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-bold <?php echo $sort == 'price_low' ? 'active bg-success' : ''; ?>" href="products.php?sort=price_low<?php echo $category_id ? '&category='.$category_id : ''; ?><?php echo $search ? '&search='.$search : ''; ?>">Price: Low to High</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-bold <?php echo $sort == 'price_high' ? 'active bg-success' : ''; ?>" href="products.php?sort=price_high<?php echo $category_id ? '&category='.$category_id : ''; ?><?php echo $search ? '&search='.$search : ''; ?>">Price: High to Low</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-search display-1 text-muted opacity-25"></i>
                        </div>
                        <h3 class="fw-800">No products found</h3>
                        <p class="text-muted">Try adjusting your filters or search terms</p>
                        <a href="products.php" class="btn btn-success rounded-4 px-4 py-2 mt-3 fw-bold">Clear All Filters</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <?php $product_is_expired = isExpiredDateValue($product['expiry_date'] ?? null); ?>
                    <div class="col-sm-6 col-xl-4">
                        <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden product-card transition-all hover-shadow">
                            <?php 
                                $badge_price = $product['price'];
                                $badge_discount = $product['discount_price'];
                                if (!empty($product_variants[$product['id']])) {
                                    $badge_price = $product_variants[$product['id']][0]['price'];
                                    $badge_discount = $product_variants[$product['id']][0]['discount_price'];
                                }
                                
                                if (!empty($badge_discount) && $badge_discount > 0): 
                            ?>
                                <span class="badge bg-danger position-absolute top-0 start-0 m-3 shadow-sm rounded-4 px-3 py-2 fw-800 discount-badge" style="z-index: 2;">
                                    <?php 
                                        $discount_pct = round((($badge_price - $badge_discount) / $badge_price) * 100);
                                        echo $discount_pct; 
                                    ?>% OFF
                                </span>
                            <?php endif; ?>
                            
                            <div class="position-absolute top-0 end-0 m-2" style="z-index: 2;">
                                <button class="btn btn-white shadow-sm toggle-wishlist rounded-circle d-flex align-items-center justify-content-center transition-hover" 
                                        data-product-id="<?php echo $product['id']; ?>"
                                        style="width: 42px; height: 42px; border: none; background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);">
                                    <i class="bi bi-heart<?php echo in_array($product['id'], $wishlist_ids) ? '-fill text-danger' : ''; ?> fs-5"></i>
                                </button>
                            </div>
                            
                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                <div class="card-img-container overflow-hidden position-relative" style="height: 240px; background: #fdfdfd;">
                                    <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" class="card-img-top h-100 w-100 product-img transition-zoom" alt="<?php echo $product['name']; ?>" style="object-fit: contain; padding: 1.5rem;">
                                    <div class="position-absolute bottom-0 start-0 w-100 h-100 bg-dark bg-opacity-10 opacity-0 transition-all hover-overlay"></div>
                                </div>
                            </a>
                            
                            <div class="card-body p-4">
                                <div class="mb-3 d-flex justify-content-between align-items-center">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-4 px-3 py-2 small fw-800 ls-1">
                                        <?php echo htmlspecialchars($product['cat_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                    <div class="text-warning small">
                                        <i class="bi bi-star-fill"></i>
                                        <span class="ms-1 fw-bold text-dark"><?php echo number_format($product['avg_rating'] ?: 0, 1); ?></span>
                                        <span class="text-muted small ms-1">(<?php echo $product['review_count']; ?>)</span>
                                    </div>
                                </div>
                                <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                    <h6 class="card-title text-truncate fw-800 mb-3 fs-5"><?php echo htmlspecialchars($product['name']); ?></h6>
                                </a>
                                
                                <?php if (!empty($product_variants[$product['id']])): ?>
                                    <div class="mb-4">
                                        <div class="dropdown w-100">
                                            <button class="btn btn-outline-light border-2 text-dark w-100 d-flex justify-content-between align-items-center py-2 px-3 rounded-4 variant-dropdown-btn" type="button" data-bs-toggle="dropdown">
                                                <span class="selected-variant-label fw-bold">
                                                    <?php 
                                                        $first_v = $product_variants[$product['id']][0];
                                                        echo $first_v['size_name']; 
                                                    ?>
                                                </span>
                                                <i class="bi bi-chevron-down small opacity-50"></i>
                                            </button>
                                            <ul class="dropdown-menu w-100 border-0 shadow-lg rounded-4 p-2 mt-2">
                                                <?php foreach ($product_variants[$product['id']] as $v): ?>
                                                    <li>
                                                        <a class="dropdown-item rounded-3 py-2 px-3 variant-option" href="#" 
                                                           data-variant-id="<?php echo $v['id']; ?>"
                                                           data-size="<?php echo htmlspecialchars($v['size_name']); ?>"
                                                           data-price="<?php echo $v['price']; ?>"
                                                           data-discount-price="<?php echo $v['discount_price']; ?>"
                                                           data-stock="<?php echo $v['stock_quantity']; ?>"
                                                           data-is-expired="<?php echo isExpiredDateValue($v['expiry_date'] ?? null) ? '1' : '0'; ?>">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($v['size_name']); ?></div>
                                                                    <?php if (isExpiredDateValue($v['expiry_date'] ?? null)): ?>
                                                                        <span class="text-danger small" style="font-size: 0.65rem;">Expired</span>
                                                                    <?php elseif ($v['stock_quantity'] <= 0): ?>
                                                                        <span class="text-danger small" style="font-size: 0.65rem;">Out of Stock</span>
                                                                    <?php elseif ($v['discount_price'] > 0): ?>
                                                                        <?php 
                                                                            $v_discount_pct = round((($v['price'] - $v['discount_price']) / $v['price']) * 100);
                                                                        ?>
                                                                        <span class="badge bg-success-subtle text-success border-0 small px-2 py-1" style="font-size: 0.65rem;"><?php echo $v_discount_pct; ?>% OFF</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-end">
                                                                    <div class="fw-bold text-success">₹<?php echo number_format($v['discount_price'] ?: $v['price'], 2); ?></div>
                                                                    <?php if ($v['discount_price'] > 0): ?>
                                                                        <div class="text-muted text-decoration-line-through small" style="font-size: 0.7rem;">₹<?php echo number_format($v['price'], 2); ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex align-items-baseline gap-2 mb-4 price-display">
                                    <?php 
                                        $display_price = $product['price'];
                                        $display_discount = $product['discount_price'];
                                        if (!empty($product_variants[$product['id']])) {
                                            $display_price = $product_variants[$product['id']][0]['price'];
                                            $display_discount = $product_variants[$product['id']][0]['discount_price'];
                                        }
                                    ?>
                                    <?php if (!empty($display_discount) && $display_discount > 0): ?>
                                        <span class="text-success fw-800 fs-3 current-price">₹<?php echo number_format($display_discount, 2); ?></span>
                                        <span class="text-muted text-decoration-line-through small fw-bold original-price">₹<?php echo number_format($display_price, 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-800 fs-3 current-price">₹<?php echo number_format($display_price, 2); ?></span>
                                        <span class="text-muted text-decoration-line-through small fw-bold original-price d-none"></span>
                                    <?php endif; ?>
                                </div>

                                <div class="product-actions mt-auto">
                                    <?php 
                                        $initial_stock = $product['stock_quantity'];
                                        $initial_variant_id = 0;
                                        $initial_is_expired = $product_is_expired;
                                        if (!empty($product_variants[$product['id']])) {
                                            $initial_stock = $product_variants[$product['id']][0]['stock_quantity'];
                                            $initial_variant_id = $product_variants[$product['id']][0]['id'];
                                            $initial_is_expired = isExpiredDateValue($product_variants[$product['id']][0]['expiry_date'] ?? null) || $product_is_expired;
                                        }
                                    ?>
                                    
                                    <div class="add-to-cart-ui <?php echo ($initial_stock <= 0 || $initial_is_expired) ? 'd-none' : ''; ?>">
                                        <div class="d-flex flex-column gap-3">
                                            <div class="input-group rounded-4 overflow-hidden border border-2 border-light shadow-sm">
                                                <button class="btn btn-light border-0 px-3 qty-minus transition-hover" type="button"><i class="bi bi-dash-lg"></i></button>
                                                <input type="number" class="form-control border-0 bg-white text-center qty-select p-0 fw-800" value="1" min="1" max="<?php echo $initial_stock; ?>" readonly>
                                                <button class="btn btn-light border-0 px-3 qty-plus transition-hover" type="button"><i class="bi bi-plus-lg"></i></button>
                                            </div>
                                            <button class="btn btn-success w-100 rounded-4 py-3 fw-800 add-to-cart shadow-sm transition-hover" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    data-variant-id="<?php echo $initial_variant_id; ?>">
                                                <i class="bi bi-cart-plus-fill me-2"></i> ADD TO CART
                                            </button>
                                        </div>
                                    </div>

                                    <div class="out-of-stock-ui <?php echo ($initial_stock <= 0 && !$initial_is_expired) ? '' : 'd-none'; ?>">
                                        <div class="text-center py-3 px-3 bg-light rounded-4 text-muted border border-2 border-dashed">
                                            <i class="bi bi-exclamation-circle-fill me-2 small"></i> <span class="small fw-800 text-uppercase">Out of Stock</span>
                                        </div>
                                    </div>
                                    <div class="expired-ui <?php echo $initial_is_expired ? '' : 'd-none'; ?>">
                                        <div class="text-center py-3 px-3 bg-light rounded-4 text-danger border border-2 border-dashed">
                                            <i class="bi bi-exclamation-octagon-fill me-2 small"></i> <span class="small fw-800 text-uppercase">Expired product - purchase not allowed.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Qty plus/minus
    document.querySelectorAll('.qty-plus').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('.qty-select');
            const max = parseInt(input.getAttribute('max'));
            let val = parseInt(input.value);
            if(val < max) input.value = val + 1;
        });
    });

    document.querySelectorAll('.qty-minus').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('.qty-select');
            let val = parseInt(input.value);
            if(val > 1) input.value = val - 1;
        });
    });

    // Variant Selection Handling
    document.querySelectorAll('.variant-option').forEach(option => {
        option.addEventListener('click', function(e) {
            e.preventDefault();
            const card = this.closest('.product-card');
            const variantId = this.dataset.variantId;
            const size = this.dataset.size;
            const price = parseFloat(this.dataset.price);
            const discountPrice = parseFloat(this.dataset.discountPrice) || 0;
            const stock = parseInt(this.dataset.stock);
            const isExpired = this.dataset.isExpired === '1';

            // Update dropdown label
            card.querySelector('.selected-variant-label').textContent = size;

            // Update price display
            const currentPriceElem = card.querySelector('.current-price');
            const originalPriceElem = card.querySelector('.original-price');
            const badgeElem = card.querySelector('.discount-badge');
            
            if (discountPrice > 0) {
                currentPriceElem.textContent = '₹' + discountPrice.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                originalPriceElem.textContent = '₹' + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                originalPriceElem.classList.remove('d-none');
                
                // Update or create badge
                const discountPct = Math.round(((price - discountPrice) / price) * 100);
                if (badgeElem) {
                    badgeElem.textContent = discountPct + '% OFF';
                    badgeElem.style.display = 'block';
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge bg-danger position-absolute top-0 start-0 m-3 shadow-sm rounded-4 px-3 py-2 fw-800 discount-badge';
                    newBadge.style.zIndex = '2';
                    newBadge.textContent = discountPct + '% OFF';
                    card.prepend(newBadge);
                }
            } else {
                currentPriceElem.textContent = '₹' + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                originalPriceElem.classList.add('d-none');
                if (badgeElem) badgeElem.style.display = 'none';
            }

            // Update Add to Cart button
            const addToCartBtn = card.querySelector('.add-to-cart');
            const qtyInput = card.querySelector('.qty-select');
            const addToCartUI = card.querySelector('.add-to-cart-ui');
            const outOfStockUI = card.querySelector('.out-of-stock-ui');
            const expiredUI = card.querySelector('.expired-ui');

            if (isExpired) {
                addToCartUI.classList.add('d-none');
                outOfStockUI.classList.add('d-none');
                expiredUI.classList.remove('d-none');
            } else if (stock > 0) {
                addToCartUI.classList.remove('d-none');
                outOfStockUI.classList.add('d-none');
                expiredUI.classList.add('d-none');
                if (addToCartBtn) {
                    addToCartBtn.dataset.variantId = variantId;
                    qtyInput.setAttribute('max', stock);
                    if (parseInt(qtyInput.value) > stock) qtyInput.value = stock;
                }
            } else {
                addToCartUI.classList.add('d-none');
                outOfStockUI.classList.remove('d-none');
                expiredUI.classList.add('d-none');
            }
        });
    });

    // Wishlist Toggle
    document.querySelectorAll('.toggle-wishlist').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            fetch('manage_wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const icon = this.querySelector('i');
                    if (data.action === 'added') {
                        icon.classList.replace('bi-heart', 'bi-heart-fill');
                        icon.classList.add('text-danger');
                    } else {
                        icon.classList.replace('bi-heart-fill', 'bi-heart');
                        icon.classList.remove('text-danger');
                    }
                } else if (data.status === 'error' && data.message === 'Please login') {
                    window.location.href = 'login.php?msg=Please login to use wishlist';
                }
            });
        });
    });
});
</script>

<style>
.product-card {
    transition: var(--transition);
}
.product-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover) !important;
}
.product-img {
    transition: transform 0.5s ease;
}
.product-card:hover .product-img {
    transform: scale(1.08);
}
.bg-success-subtle {
    background-color: #d1fae5;
}
.tracking-wider {
    letter-spacing: 0.05em;
}
.qty-select::-webkit-inner-spin-button,
.qty-select::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.qty-select {
    -moz-appearance: textfield;
    appearance: textfield;
}
.custom-rating-check .form-check-input:checked {
    background-color: #198754;
    border-color: #198754;
}
.custom-rating-check .form-check-input {
    width: 1.2em;
    height: 1.2em;
    margin-top: 0.15em;
    cursor: pointer;
    border: 2px solid #dee2e6;
}
.custom-rating-check .form-check-label {
    cursor: pointer;
    padding-left: 0.5rem;
}
</style>

<?php require_once 'includes/footer.php'; ?>
