<?php 
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

require_once 'includes/header.php'; 

$user_id = $_SESSION['user_id'];

// Fetch all products purchased by this user, grouped by product and variant to show unique items
$sql = "SELECT p.id, p.name, p.image_url, c.name as cat_name, 
        pv.id as variant_id, pv.size_name,
        COALESCE(pv.price, p.price) as display_price,
        COALESCE(pv.discount_price, p.discount_price) as display_discount,
        COALESCE(pv.stock_quantity, p.stock_quantity) as display_stock,
        MAX(o.order_date) as last_purchased_date,
        SUM(oi.quantity) as total_quantity_bought
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_variants pv ON oi.variant_id = pv.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE o.user_id = ? AND o.status != 'Cancelled' AND p.status = 'Active'
        GROUP BY p.id, oi.variant_id
        ORDER BY last_purchased_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$purchased_products = $stmt->fetchAll();
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 animate__animated animate__fadeIn">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                        <li class="breadcrumb-item"><a href="account-settings.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Account</a></li>
                        <li class="breadcrumb-item active text-white small fw-600" aria-current="page">Purchase History</li>
                    </ol>
                </nav>
                <h1 class="text-white fw-800 mb-1 display-5">Purchase History</h1>
                <p class="text-white text-opacity-75 mb-0 fw-600">Reorder your favorite items instantly</p>
            </div>
            <div class="d-flex gap-2">
                <a href="my-orders.php" class="btn btn-white rounded-4 px-4 py-3 fw-800 shadow-lg transition-hover border-0">
                    <i class="bi bi-box-seam-fill me-2 text-success fs-5"></i> TRACK ORDERS
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <?php if (empty($purchased_products)): ?>
        <div class="text-center py-5 animate__animated animate__fadeInUp">
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm animate__animated animate__pulse animate__infinite" style="width: 140px; height: 140px;">
                <i class="bi bi-bag-x-fill text-success display-1"></i>
            </div>
            <h2 class="fw-800 mb-2 display-6">No purchase history</h2>
            <p class="text-muted mb-5 fs-5 fw-600">You haven't purchased any products yet. Start your organic journey today!</p>
            <a href="products.php" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover btn-lg tracking-wider">
                START SHOPPING <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($purchased_products as $index => $product): ?>
                <div class="col-12 col-lg-6 animate__animated animate__fadeInUp" style="animation-delay: <?php echo $index * 0.05; ?>s">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden product-history-card transition-hover h-100 bg-white">
                        <div class="row g-0 h-100">
                            <div class="col-4 col-sm-3 bg-light d-flex align-items-center justify-content-center p-3 position-relative overflow-hidden">
                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-success opacity-0 transition-hover" style="z-index: 1;"></div>
                                <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" 
                                     class="img-fluid rounded-4 h-100 position-relative" 
                                     style="object-fit: contain; max-height: 120px; z-index: 2;" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="col-8 col-sm-9 border-start border-light">
                                <div class="card-body p-4 d-flex flex-column h-100">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-success bg-opacity-10 text-success fw-800 text-uppercase smaller px-2 py-1 tracking-wider mb-2 d-inline-block"><?php echo htmlspecialchars($product['cat_name'] ?? 'General'); ?></span>
                                            <h5 class="card-title fw-800 text-dark mb-1 lh-base">
                                                <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark hover-success">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                    <?php if (!empty($product['size_name'])): ?>
                                                        <span class="badge bg-light text-success border ms-1 small fw-600" style="font-size: 0.65rem;"><?php echo htmlspecialchars($product['size_name']); ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </h5>
                                        </div>
                                        <div class="text-end bg-light p-2 rounded-4 px-3 border">
                                            <div class="smaller text-muted fw-800 text-uppercase tracking-wider mb-1" style="font-size: 0.65rem;">Last Bought</div>
                                            <div class="small fw-800 text-success"><?php echo date('M d, Y', strtotime($product['last_purchased_date'])); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-auto d-flex flex-wrap justify-content-between align-items-center pt-3 border-top border-light gap-3">
                                        <div class="d-flex flex-column">
                                            <div class="d-flex align-items-baseline gap-2 mb-1">
                                                <?php if (!empty($product['display_discount']) && $product['display_discount'] > 0): ?>
                                                    <span class="fw-800 text-success fs-5">₹<?php echo number_format($product['display_discount'], 2); ?></span>
                                                    <span class="text-muted text-decoration-line-through small fw-600">₹<?php echo number_format($product['display_price'], 2); ?></span>
                                                <?php else: ?>
                                                    <span class="fw-800 text-success fs-5">₹<?php echo number_format($product['display_price'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center text-muted smaller fw-800 bg-light rounded-pill px-3 py-1">
                                                <i class="bi bi-check2-circle me-2 text-success"></i> <?php echo $product['total_quantity_bought']; ?> units bought total
                                            </div>
                                        </div>
                                        <?php if ($product['display_stock'] > 0): ?>
                                            <button class="btn btn-success rounded-4 px-4 py-3 fw-800 small add-to-cart shadow-sm transition-hover tracking-wider" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    data-variant-id="<?php echo $product['variant_id'] ?? 0; ?>">
                                                <i class="bi bi-arrow-repeat me-2 fs-5"></i> BUY AGAIN
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-light rounded-4 px-4 py-3 fw-800 small text-muted border-0 shadow-none tracking-wider" disabled>
                                                <i class="bi bi-exclamation-triangle me-2 fs-5"></i> OUT OF STOCK
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
:root {
    --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.tracking-wider { letter-spacing: 0.8px; }

.transition-hover {
    transition: var(--transition-base);
}

.transition-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 1rem 3rem rgba(0,0,0,0.12)!important;
}

.product-history-card {
    border-left: 0 solid #198754;
    transition: var(--transition-base);
}

.product-history-card:hover {
    border-left: 6px solid #198754;
}

.hover-success:hover {
    color: #198754 !important;
}

.smaller { font-size: 0.7rem; }

.btn-white {
    background: #fff;
    color: #198754;
}

.btn-white:hover {
    background: #f8f9fa;
    color: #157347;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: #198754;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb:hover {
    background: #157347;
}
</style>

<script src="assets/js/cart.js"></script>

<?php require_once 'includes/footer.php'; ?>
