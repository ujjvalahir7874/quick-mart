<?php 
require_once 'includes/header.php'; 

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: products.php");
    exit;
}

// Fetch Product Details
$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 'Active'");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php");
    exit;
}

// Fetch Reviews
$stmt = $pdo->prepare("SELECT r.*, u.full_name FROM product_reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Calculate Average Rating
$avg_rating = 0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $sum = 0;
    foreach ($reviews as $r) {
        $sum += $r['rating'];
    }
    $avg_rating = round($sum / $total_reviews, 1);
}

// Check if in wishlist
$is_in_wishlist = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $is_in_wishlist = (bool)$stmt->fetch();
}

// Fetch Related Products (same category)
$stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND status = 'Active' LIMIT 4");
$stmt->execute([$product['category_id'], $product_id]);
$related_products = $stmt->fetchAll();

// Fetch Product Variants
$stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY price ASC");
$stmt->execute([$product_id]);
$variants = $stmt->fetchAll();
$product_is_expired = isExpiredDateValue($product['expiry_date'] ?? null);
?>

<div class="container py-4 py-lg-5">
    <nav aria-label="breadcrumb" class="mb-5">
        <ol class="breadcrumb bg-white p-3 rounded-4 shadow-sm border-0 mb-0 animate__animated animate__fadeIn">
            <li class="breadcrumb-item"><a href="index.php" class="text-success text-decoration-none fw-800">HOME</a></li>
            <li class="breadcrumb-item"><a href="products.php" class="text-success text-decoration-none fw-800">PRODUCTS</a></li>
            <li class="breadcrumb-item active text-muted fw-800" aria-current="page"><?php echo strtoupper(htmlspecialchars($product['name'])); ?></li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <!-- Product Image -->
        <div class="col-md-6 animate__animated animate__fadeInLeft">
            <div class="product-gallery position-relative">
                <div class="card border-0 shadow-sm rounded-5 overflow-hidden bg-white p-4 tilt-card">
                    <?php if (!empty($product['discount_price']) && $product['discount_price'] > 0): ?>
                        <div class="discount-badge position-absolute top-0 start-0 m-4 shadow-sm" style="z-index: 2;">
                            <span class="badge bg-danger px-3 py-2 rounded-4 fw-800 fs-6">
                                <i class="bi bi-lightning-charge-fill me-1"></i>
                                <?php 
                                    $discount_pct = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                                    echo $discount_pct; 
                                ?>% OFF
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="wishlist-overlay position-absolute top-0 end-0 m-4" style="z-index: 2;">
                        <button class="btn btn-white shadow-lg rounded-circle p-2 border-0 toggle-wishlist transition-hover" 
                                data-product-id="<?php echo $product['id']; ?>" style="width: 50px; height: 50px; background: rgba(255,255,255,0.9); backdrop-filter: blur(8px);">
                            <i class="bi bi-heart<?php echo $is_in_wishlist ? '-fill text-danger' : ''; ?> fs-4"></i>
                        </button>
                    </div>
                    <div class="main-img-container overflow-hidden" style="height: 450px;">
                        <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" 
                             class="img-fluid w-100 h-100 transition-zoom" 
                             alt="<?php echo $product['name']; ?>" 
                             style="object-fit: contain;">
                    </div>
                </div>
                
                <!-- Trust Badges (Modern) -->
                <div class="row g-3 mt-4 text-center">
                    <div class="col-4">
                        <div class="bg-white p-3 rounded-4 shadow-sm border border-light transition-hover">
                            <i class="bi bi-truck text-success fs-3 d-block mb-1"></i>
                            <span class="smaller fw-800 text-muted text-uppercase ls-1" style="font-size: 0.65rem;">Free Delivery</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white p-3 rounded-4 shadow-sm border border-light transition-hover">
                            <i class="bi bi-shield-check text-success fs-3 d-block mb-1"></i>
                            <span class="smaller fw-800 text-muted text-uppercase ls-1" style="font-size: 0.65rem;">Quality Check</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-white p-3 rounded-4 shadow-sm border border-light transition-hover">
                            <i class="bi bi-arrow-clockwise text-success fs-3 d-block mb-1"></i>
                            <span class="smaller fw-800 text-muted text-uppercase ls-1" style="font-size: 0.65rem;">7 Day Return</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Details -->
        <div class="col-md-6 animate__animated animate__fadeInRight">
            <div class="product-info-wrapper h-100 d-flex flex-column">
                <div class="mb-2">
                    <span class="badge bg-success-subtle text-success px-3 py-2 rounded-4 smaller fw-800 mb-3 ls-1 text-uppercase">
                        <i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($product['cat_name'] ?? 'Uncategorized'); ?>
                    </span>
                    <h1 class="fw-800 mb-3 display-5"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="d-flex align-items-center mb-4 bg-light bg-opacity-50 p-2 rounded-4 d-inline-flex">
                        <div class="rating-display text-warning me-2 fs-5">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="bi bi-star<?php echo $i <= $avg_rating ? '-fill' : ($i - 0.5 <= $avg_rating ? '-half' : ''); ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-dark fw-800 small"><?php echo $avg_rating; ?> Rating <span class="text-muted fw-normal mx-2">|</span> <?php echo $total_reviews; ?> Verified Reviews</span>
                    </div>
                </div>

                <div class="price-section p-4 rounded-5 mb-4 shadow-lg border-0 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                    <div class="position-relative" style="z-index: 2;">
                        <div class="d-flex align-items-center flex-wrap gap-3">
                            <h2 class="text-white fw-800 mb-0 display-4" id="display_price">
                                ₹<?php echo number_format(!empty($product['discount_price']) ? $product['discount_price'] : $product['price'], 2); ?>
                            </h2>
                            
                            <span class="text-white text-decoration-line-through h4 mb-0 opacity-50" id="original_price" 
                                  style="<?php echo empty($product['discount_price']) ? 'display: none;' : ''; ?>">
                                ₹<?php echo number_format($product['price'], 2); ?>
                            </span>
                            
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-4 fw-800 shadow-sm animate__animated animate__pulse animate__infinite" id="save_badge"
                                  style="<?php echo empty($product['discount_price']) ? 'display: none;' : ''; ?>">
                                SAVE ₹<?php echo number_format($product['price'] - ($product['discount_price'] ?? $product['price']), 2); ?>
                            </span>
                        </div>
                        <div class="text-white opacity-75 small mt-3 fw-bold">
                            <i class="bi bi-info-circle me-1"></i> Inclusive of GST (<?php echo $product['tax_percentage'] ?? '5'; ?>%)
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-800 text-dark mb-3 text-uppercase ls-1">Product Description</h6>
                    <p class="text-muted lh-lg mb-0" style="font-size: 1rem;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>
                </div>

                <div class="mb-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-4 bg-white border-2 border-light shadow-sm h-100 transition-hover">
                                <div class="bg-success bg-opacity-10 rounded-4 p-3" id="stock_icon_bg">
                                    <i class="bi <?php echo $product['stock_quantity'] > 0 ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'; ?> fs-4" id="stock_icon"></i>
                                </div>
                                <div>
                                    <div class="small text-muted fw-800 text-uppercase ls-1">Availability</div>
                                    <div class="fw-800 <?php echo $product['stock_quantity'] > 0 ? 'text-success' : 'text-danger'; ?>" id="stock_display">
                                        <?php echo $product_is_expired ? 'Expired product - purchase not allowed.' : ($product['stock_quantity'] > 0 ? 'In Stock ('.$product['stock_quantity'].' Units)' : 'Out of Stock'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6" id="expiry_container" <?php echo !$product['expiry_date'] ? 'style="display:none;"' : ''; ?>>
                            <div class="d-flex align-items-center gap-3 p-3 rounded-4 bg-white border-2 border-light shadow-sm h-100 transition-hover">
                                <div class="bg-info bg-opacity-10 rounded-4 p-3">
                                    <i class="bi bi-calendar-check-fill text-info fs-4"></i>
                                </div>
                                <div>
                                    <div class="small text-muted fw-800 text-uppercase ls-1">Best Before</div>
                                    <div class="fw-800 text-dark" id="display_expiry"><?php echo $product['expiry_date'] ? date('M d, Y', strtotime($product['expiry_date'])) : ''; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($variants)): ?>
                <div class="mb-4">
                    <label class="fw-800 text-dark mb-2 text-uppercase ls-1 d-block">Choose Size</label>
                    <div class="btn-group w-100 flex-wrap gap-2" role="group" aria-label="Size selection">
                        <?php foreach ($variants as $index => $variant): ?>
                            <input type="radio" class="btn-check size-option" name="variant_id" 
                                   id="variant_<?php echo $variant['id']; ?>" 
                                   autocomplete="off" 
                                   value="<?php echo $variant['id']; ?>"
                                   data-price="<?php echo $variant['price']; ?>"
                                   data-discount-price="<?php echo $variant['discount_price'] ?? 0; ?>"
                                   data-stock="<?php echo $variant['stock_quantity']; ?>"
                                   data-is-expired="<?php echo isExpiredDateValue($variant['expiry_date'] ?? null) ? '1' : '0'; ?>"
                                   data-expiry="<?php echo $variant['expiry_date'] ? date('M d, Y', strtotime($variant['expiry_date'])) : ''; ?>"
                                   <?php echo $index === count($variants) - 1 ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-success py-2 px-3 rounded-4 fw-800 border-2 flex-grow-1" for="variant_<?php echo $variant['id']; ?>">
                                <?php echo htmlspecialchars($variant['size_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-auto pt-4 border-top">
                    <div class="d-flex flex-wrap gap-3">
                        <div class="quantity-wrapper rounded-4 border-2 border-light overflow-hidden d-flex align-items-center shadow-sm bg-white" style="width: 150px;">
                            <button class="btn btn-light border-0 px-3 py-3 transition-hover" type="button" onclick="changeQty(-1)"><i class="bi bi-dash-lg"></i></button>
                            <input type="number" id="qty_input" class="form-control border-0 text-center fw-800 bg-white p-0 fs-5" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>" readonly style="box-shadow: none;">
                            <button class="btn btn-light border-0 px-3 py-3 transition-hover" type="button" onclick="changeQty(1)"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        <button class="btn btn-success flex-grow-1 py-3 px-4 rounded-4 fw-800 shadow-lg add-to-cart-btn position-relative overflow-hidden transition-hover" 
                                data-product-id="<?php echo $product['id']; ?>"
                                <?php echo ($product['stock_quantity'] <= 0 || $product_is_expired) ? 'disabled' : ''; ?>>
                            <i class="bi <?php echo $product_is_expired ? 'bi-exclamation-octagon-fill' : 'bi-cart-plus-fill'; ?> me-2 fs-5"></i> <?php echo $product_is_expired ? 'EXPIRED PRODUCT' : 'ADD TO CART'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Tabs & Reviews -->
    <div class="row mt-5 pt-4 animate__animated animate__fadeIn">
        <div class="col-lg-8">
            <ul class="nav nav-pills mb-4 gap-2 bg-light p-2 rounded-4 shadow-sm" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-4 px-4 py-2 fw-800" id="reviews-tab" data-bs-toggle="pill" data-bs-target="#reviews-content" type="button" role="tab">
                        <i class="bi bi-chat-square-text me-2"></i>Reviews (<?php echo $total_reviews; ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-4 px-4 py-2 fw-800" id="info-tab" data-bs-toggle="pill" data-bs-target="#info-content" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>Additional Info
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="productTabsContent">
                <!-- Reviews Tab -->
                <div class="tab-pane fade show active" id="reviews-content" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-800 mb-0">Verified Customer Reviews</h4>
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-success rounded-4 px-4 py-2 fw-800 shadow-sm transition-hover" type="button" data-bs-toggle="collapse" data-bs-target="#reviewForm">
                                <i class="bi bi-pencil-square me-2"></i>Write a Review
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (isLoggedIn()): ?>
                    <div class="collapse mb-4" id="reviewForm">
                        <div class="card border-0 shadow-lg rounded-4 p-4" style="background: #f8fff9; border: 1px dashed #198754 !important;">
                            <h5 class="fw-800 mb-3">Rate this product</h5>
                            <form action="submit_review.php" method="POST">
                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                <div class="mb-3">
                                    <div class="star-rating-input h3 text-warning">
                                        <i class="bi bi-star rating-star" data-rating="1"></i>
                                        <i class="bi bi-star rating-star" data-rating="2"></i>
                                        <i class="bi bi-star rating-star" data-rating="3"></i>
                                        <i class="bi bi-star rating-star" data-rating="4"></i>
                                        <i class="bi bi-star rating-star" data-rating="5"></i>
                                        <input type="hidden" name="rating" id="rating_value" value="5" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <textarea name="comment" class="form-control rounded-4 border-2 border-light shadow-sm p-3" rows="4" placeholder="How was the quality? Any suggestions?" required></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success px-4 py-2 rounded-4 fw-800 shadow-sm transition-hover">Post Review</button>
                                    <button type="button" class="btn btn-light px-4 py-2 rounded-4 fw-800 border transition-hover" data-bs-toggle="collapse" data-bs-target="#reviewForm">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="reviews-container">
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-5 bg-light rounded-4 border border-dashed">
                                <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 80px; height: 80px;">
                                    <i class="bi bi-chat-left-dots display-6 text-muted"></i>
                                </div>
                                <h5 class="fw-800">No reviews yet</h5>
                                <p class="text-muted mb-0 px-4">Be the first to share your experience with this product!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="card border-0 shadow-sm rounded-4 mb-3 transition-hover overflow-hidden">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success-subtle text-success rounded-4 d-flex align-items-center justify-content-center fw-800 me-3" style="width: 50px; height: 50px; font-size: 1.2rem;">
                                                    <?php echo strtoupper(substr($review['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="fw-800 mb-0 text-dark"><?php echo htmlspecialchars($review['full_name']); ?></h6>
                                                    <div class="text-warning smaller">
                                                        <?php for($i=1; $i<=5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= $review['rating'] ? '-fill' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                        <span class="text-muted ms-2 smaller fw-normal opacity-75"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="badge bg-success-subtle text-success border border-success-subtle smaller px-3 py-2 rounded-4 fw-800">
                                                <i class="bi bi-patch-check-fill me-1"></i> Verified
                                            </div>
                                        </div>
                                        <p class="text-muted mb-0 lh-base" style="font-size: 0.95rem;"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Tab -->
                <div class="tab-pane fade" id="info-content" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 p-4 overflow-hidden">
                        <div class="position-absolute top-0 end-0 p-4 opacity-10">
                            <i class="bi bi-journal-text display-1"></i>
                        </div>
                        <h5 class="fw-800 mb-4 position-relative">Product Specifications</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <tbody>
                                    <tr class="border-light">
                                        <td class="text-muted py-3 ps-0 fw-600" style="width: 200px;"><i class="bi bi-tag me-2"></i>Category</td>
                                        <td class="fw-800 py-3 text-dark"><?php echo htmlspecialchars($product['cat_name'] ?? 'General'); ?></td>
                                    </tr>
                                    <tr class="border-light">
                                        <td class="text-muted py-3 ps-0 fw-600"><i class="bi bi-receipt me-2"></i>Tax Status</td>
                                        <td class="fw-800 py-3 text-dark">Inclusive of <?php echo $product['tax_percentage'] ?? '5'; ?>% GST</td>
                                    </tr>
                                    <tr class="border-light">
                                        <td class="text-muted py-3 ps-0 fw-600"><i class="bi bi-calendar-check me-2"></i>Shelf Life</td>
                                        <td class="fw-800 py-3 text-dark">Best Before <?php echo $product['expiry_date'] ? date('d M Y', strtotime($product['expiry_date'])) : 'N/A'; ?></td>
                                    </tr>
                                    <tr class="border-0">
                                        <td class="text-muted py-3 ps-0 fw-600"><i class="bi bi-box-seam me-2"></i>Stock Status</td>
                                        <td class="py-3">
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success rounded-4 px-3 py-2 fw-800">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Available
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger rounded-4 px-3 py-2 fw-800">
                                                    <i class="bi bi-x-circle-fill me-1"></i> Out of Stock
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <div class="col-lg-4 mt-5 mt-lg-0">
            <div class="related-products-wrapper animate__animated animate__fadeInRight">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-800 mb-0">Related Products</h4>
                    <a href="products.php?category=<?php echo $product['category_id']; ?>" class="btn btn-link text-success text-decoration-none fw-800 p-0">
                        View All <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="row g-3">
                    <?php if (empty($related_products)): ?>
                        <div class="col-12">
                            <div class="p-4 bg-light rounded-4 text-center">
                                <p class="text-muted small mb-0">No related products found in this category.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($related_products as $rp): ?>
                            <div class="col-12">
                                <a href="product-details.php?id=<?php echo $rp['id']; ?>" class="text-decoration-none">
                                    <div class="card border-0 shadow-sm rounded-4 p-2 h-100 related-card transition-all position-relative overflow-hidden">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0 bg-light rounded-4 p-2 overflow-hidden" style="width: 90px; height: 90px;">
                                                <img src="<?php echo getProductImage($rp['image_url'], $rp['name']); ?>" 
                                                     class="img-fluid rounded-3 transition-hover w-100 h-100" alt="<?php echo $rp['name']; ?>" 
                                                     style="object-fit: contain;">
                                            </div>
                                            <div class="ms-3 flex-grow-1">
                                                <h6 class="fw-800 mb-1 text-dark text-truncate-2" style="font-size: 0.95rem; line-height: 1.4;"><?php echo htmlspecialchars($rp['name']); ?></h6>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="text-success fw-800">₹<?php echo number_format($rp['discount_price'] ?: $rp['price'], 2); ?></span>
                                                    <?php if ($rp['discount_price']): ?>
                                                        <span class="text-muted text-decoration-line-through smaller opacity-50">₹<?php echo number_format($rp['price'], 2); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="ms-2 me-2">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-chevron-right text-success fw-bold"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Banner -->
                <div class="card border-0 rounded-4 overflow-hidden mt-4 shadow-lg position-relative" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png');"></div>
                    <div class="card-body p-4 text-center position-relative" style="z-index: 2;">
                        <div class="bg-white bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px;">
                            <i class="bi bi-lightning-charge-fill display-5 text-white"></i>
                        </div>
                        <h5 class="fw-800 text-white mb-2">Free Delivery</h5>
                        <p class="smaller mb-4 text-white opacity-75">On all orders above ₹250. Shop now and save more!</p>
                        <a href="products.php" class="btn btn-white btn-lg rounded-4 px-4 py-2 fw-800 text-success shadow-sm w-100 transition-hover">
                            Browse All Products
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sizeOptions = document.querySelectorAll('.size-option');
    const displayPrice = document.getElementById('display_price');
    const originalPrice = document.getElementById('original_price');
    const saveBadge = document.getElementById('save_badge');
    const displayExpiry = document.getElementById('display_expiry');
    const expiryContainer = document.getElementById('expiry_container');
    const addToCartBtn = document.querySelector('.add-to-cart-btn');
    const stockDisplay = document.getElementById('stock_display');
    const stockIcon = document.getElementById('stock_icon');
    const stockIconBg = document.getElementById('stock_icon_bg');
    const qtyInput = document.getElementById('qty_input');

    // Store main product expiry as fallback
    const mainExpiry = "<?php echo $product['expiry_date'] ? date('M d, Y', strtotime($product['expiry_date'])) : ''; ?>";
    const productExpiredMessage = 'Expired product - purchase not allowed.';
    const mainProductExpired = <?php echo $product_is_expired ? 'true' : 'false'; ?>;
    
    function updatePrice(radio) {
        const price = parseFloat(radio.dataset.price);
        const discountPrice = parseFloat(radio.dataset.discountPrice);
        const stock = parseInt(radio.dataset.stock);
        const variantExpiry = radio.dataset.expiry;
        const variantExpired = radio.dataset.isExpired === '1';
        
        if (discountPrice > 0) {
            displayPrice.innerText = '₹' + discountPrice.toFixed(2);
            if (originalPrice) {
                originalPrice.style.display = 'inline';
                originalPrice.innerText = '₹' + price.toFixed(2);
            }
            if (saveBadge) {
                saveBadge.style.display = 'inline';
                saveBadge.innerHTML = 'SAVE ₹' + (price - discountPrice).toFixed(2);
            }
        } else {
            displayPrice.innerText = '₹' + price.toFixed(2);
            if (originalPrice) originalPrice.style.display = 'none';
            if (saveBadge) saveBadge.style.display = 'none';
        }

        // Update Expiry Date
        const expiryToShow = variantExpiry || mainExpiry;
        if (displayExpiry && expiryContainer) {
            if (expiryToShow) {
                displayExpiry.innerText = expiryToShow;
                expiryContainer.style.display = 'block';
            } else {
                expiryContainer.style.display = 'none';
            }
        }

        // Update Stock Display
        if (stockDisplay && stockIcon && stockIconBg) {
            if (variantExpired || mainProductExpired) {
                stockDisplay.innerText = productExpiredMessage;
                stockDisplay.className = 'fw-800 text-danger';
                stockIcon.className = 'bi bi-exclamation-octagon-fill text-danger fs-4';
                stockIconBg.className = 'bg-danger bg-opacity-10 rounded-4 p-3';
                if (qtyInput) qtyInput.value = 1;
            } else if (stock > 0) {
                stockDisplay.innerText = 'In Stock (' + stock + ' Units)';
                stockDisplay.className = 'fw-800 text-success';
                stockIcon.className = 'bi bi-check-circle-fill text-success fs-4';
                stockIconBg.className = 'bg-success bg-opacity-10 rounded-4 p-3';
                if (qtyInput) {
                    qtyInput.max = stock;
                    if (parseInt(qtyInput.value) > stock) {
                        qtyInput.value = stock;
                    }
                }
            } else {
                stockDisplay.innerText = 'Out of Stock';
                stockDisplay.className = 'fw-800 text-danger';
                stockIcon.className = 'bi bi-x-circle-fill text-danger fs-4';
                stockIconBg.className = 'bg-danger bg-opacity-10 rounded-4 p-3';
                if (qtyInput) qtyInput.value = 1;
            }
        }

        // Update Add to Cart button state
        if (variantExpired || mainProductExpired) {
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i> EXPIRED PRODUCT';
        } else if (stock <= 0) {
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="bi bi-x-circle-fill me-2 fs-5"></i> OUT OF STOCK';
        } else {
            addToCartBtn.disabled = false;
            addToCartBtn.innerHTML = '<i class="bi bi-cart-plus-fill me-2 fs-5"></i> ADD TO CART';
        }
    }
    
    sizeOptions.forEach(radio => {
        radio.addEventListener('change', () => updatePrice(radio));
    });

    // Initialize with checked option
    const checkedOption = document.querySelector('.size-option:checked');
    if (checkedOption) updatePrice(checkedOption);

    // Handle Add to Cart
    addToCartBtn.addEventListener('click', function() {
        const productId = this.dataset.productId;
        const selectedVariant = document.querySelector('.size-option:checked');
        const variantId = selectedVariant ? selectedVariant.value : null;
        const qty = parseInt(document.getElementById('qty_input').value) || 1;

        if (!variantId) {
            alert('Please select a size first!');
            return;
        }

        // Call the global function from cart.js
        if (typeof window.addToCart === 'function') {
            window.addToCart(productId, qty, variantId);
        } else {
            console.error('addToCart function not found');
            alert('An error occurred. Please try again.');
        }
    });
});
</script>

<style>
    .bg-success-subtle { background-color: #e8f5e9 !important; }
    .bg-danger-subtle { background-color: #ffebee !important; }
    .bg-white { background-color: #ffffff !important; }
    
    .fw-600 { font-weight: 600; }
    .fw-800 { font-weight: 800; }
    
    .smaller { font-size: 0.75rem; }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .transition-hover {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .transition-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.175)!important;
    }
    
    .related-card {
        transition: all 0.3s ease;
        border: 1px solid transparent !important;
    }
    .related-card:hover {
        background: #f8fff9 !important;
        border-color: #198754 !important;
        transform: translateX(5px);
    }
    .related-card:hover img {
        transform: scale(1.1);
    }
    
    .btn-white {
        background: #fff;
        border: none;
    }
    .btn-white:hover {
        background: #f8f9fa;
        transform: scale(1.02);
    }

    .nav-pills .nav-link {
        color: #495057;
        border: 1px solid transparent;
        transition: all 0.3s ease;
    }
    .nav-pills .nav-link:hover:not(.active) {
        background: #e9ecef;
    }
    .nav-pills .nav-link.active {
        background: #198754;
        box-shadow: 0 4px 15px rgba(25, 135, 84, 0.3);
    }
    
    .star-rating-input i {
        cursor: pointer;
        transition: transform 0.2s ease;
    }
    .star-rating-input i:hover {
        transform: scale(1.2);
    }

    .table-hover tbody tr:hover {
        background-color: #f8fff9;
    }
    
    @media (max-width: 991.98px) {
        .related-products-wrapper { margin-top: 3rem; }
    }
</style>

<script>
function changeQty(amt) {
    const input = document.getElementById('qty_input');
    let val = parseInt(input.value) + amt;
    if (val < 1) val = 1;
    if (val > <?php echo $product['stock_quantity']; ?>) val = <?php echo $product['stock_quantity']; ?>;
    input.value = val;
}

document.addEventListener('DOMContentLoaded', function() {
    // Star Rating Interactivity
    const stars = document.querySelectorAll('.rating-star');
    const ratingInput = document.getElementById('rating_value');

    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const rating = this.dataset.rating;
            highlightStars(rating);
        });

        star.addEventListener('mouseleave', function() {
            highlightStars(ratingInput.value);
        });

        star.addEventListener('click', function() {
            ratingInput.value = this.dataset.rating;
            highlightStars(this.dataset.rating);
        });
    });

    function highlightStars(rating) {
        stars.forEach(s => {
            if (s.dataset.rating <= rating) {
                s.classList.replace('bi-star', 'bi-star-fill');
            } else {
                s.classList.replace('bi-star-fill', 'bi-star');
            }
        });
    }

    // Wishlist Toggle
    document.querySelector('.toggle-wishlist').addEventListener('click', function() {
        const productId = this.dataset.productId;
        
        fetch('manage_wishlist.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `product_id=${productId}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                this.classList.toggle('btn-danger');
                this.classList.toggle('btn-outline-danger');
                const icon = this.querySelector('i');
                icon.classList.toggle('bi-heart');
                icon.classList.toggle('bi-heart-fill');
            } else if (data.status === 'error' && data.message === 'Please login') {
                window.location.href = 'login.php?msg=Please login to use wishlist';
            }
        });
    });
});
</script>

<style>
.bg-success-soft {
    background-color: rgba(25, 135, 84, 0.1);
}
.star-rating i {
    cursor: pointer;
    transition: transform 0.2s;
}
.star-rating i:hover {
    transform: scale(1.2);
}
</style>

<?php require_once 'includes/footer.php'; ?>
