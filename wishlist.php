<?php
require_once 'config/db.php';

if (!isLoggedIn()) {
    header("Location: login.php?msg=Please login to view wishlist");
    exit;
}

require_once 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Fetch Wishlist Items
$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name FROM wishlist w JOIN products p ON w.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll();
?>

<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 animate__animated animate__fadeIn">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                        <li class="breadcrumb-item active text-white small fw-600" aria-current="page">Wishlist</li>
                    </ol>
                </nav>
                <h1 class="text-white fw-800 mb-1 display-5">My Wishlist</h1>
                <p class="text-white text-opacity-75 mb-0 fw-600">Items you've saved for later</p>
            </div>
            <div class="d-flex gap-2">
                <a href="products.php" class="btn btn-white rounded-4 px-4 py-3 fw-800 shadow-lg transition-hover border-0">
                    <i class="bi bi-cart-plus me-2 fs-5 text-success"></i> CONTINUE SHOPPING
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container mb-5">
    <?php if (empty($wishlist_items)): ?>
        <div class="text-center py-5 animate__animated animate__fadeInUp">
            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4 shadow-sm animate__animated animate__pulse animate__infinite" style="width: 140px; height: 140px;">
                <i class="bi bi-heart-fill text-success display-1"></i>
            </div>
            <h2 class="fw-800 mb-2 display-6">Your wishlist is empty</h2>
            <p class="text-muted mb-5 fs-5 fw-600">Save items you like to buy them later and they'll appear here.</p>
            <a href="products.php" class="btn btn-success rounded-4 px-5 py-3 fw-800 shadow-lg transition-hover btn-lg tracking-wider">
                BROWSE PRODUCTS <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($wishlist_items as $index => $product): ?>
            <div class="col-6 col-md-4 col-lg-3 animate__animated animate__fadeInUp" 
                 style="animation-delay: <?php echo $index * 0.1; ?>s"
                 id="wishlist-item-<?php echo $product['id']; ?>">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative product-card transition-hover bg-white">
                    <!-- Discount Badge -->
                    <?php if (!empty($product['discount_price']) && $product['discount_price'] > 0): ?>
                        <div class="position-absolute top-0 start-0 m-3" style="z-index: 2;">
                            <span class="badge bg-danger rounded-4 px-3 py-2 shadow-sm small fw-800 tracking-wider">
                                <?php 
                                    $discount_pct = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                                    echo $discount_pct; 
                                ?>% OFF
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Remove Button -->
                    <button class="btn btn-white btn-sm rounded-circle position-absolute top-0 end-0 m-3 shadow-sm remove-wishlist transition-hover border-0" 
                            style="z-index: 2; width: 42px; height: 42px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);"
                            data-product-id="<?php echo $product['id']; ?>"
                            title="Remove from wishlist">
                        <i class="bi bi-trash3-fill text-danger fs-5"></i>
                    </button>

                    <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                        <div class="bg-light d-flex align-items-center justify-content-center p-4 position-relative overflow-hidden" style="height: 240px;">
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-success opacity-0 transition-hover" style="z-index: 1;"></div>
                            <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" 
                                 class="img-fluid rounded-4 h-100 product-image position-relative" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 style="object-fit: contain; z-index: 2;">
                        </div>
                    </a>

                    <div class="card-body p-4">
                        <div class="mb-2">
                            <span class="badge bg-success bg-opacity-10 text-success fw-800 text-uppercase smaller px-2 py-1 tracking-wider"><?php echo htmlspecialchars($product['cat_name'] ?? 'General'); ?></span>
                        </div>
                        <h6 class="card-title text-dark fw-800 mb-2 text-truncate-2 lh-base" style="height: 2.8rem;"><?php echo htmlspecialchars($product['name']); ?></h6>
                        
                        <div class="d-flex align-items-baseline gap-2 mb-4">
                            <?php if (!empty($product['discount_price']) && $product['discount_price'] > 0): ?>
                                <span class="text-success fw-800 fs-5">₹<?php echo number_format($product['discount_price'], 2); ?></span>
                                <span class="text-muted text-decoration-line-through small fw-600">₹<?php echo number_format($product['price'], 2); ?></span>
                            <?php else: ?>
                                <span class="text-success fw-800 fs-5">₹<?php echo number_format($product['price'], 2); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($product['stock_quantity'] > 0): ?>
                            <button class="btn btn-success w-100 rounded-4 py-3 fw-800 small add-to-cart shadow-sm transition-hover tracking-wider" data-product-id="<?php echo $product['id']; ?>">
                                <i class="bi bi-bag-plus-fill me-2 fs-5"></i> ADD TO CART
                            </button>
                        <?php else: ?>
                            <button class="btn btn-light w-100 rounded-4 py-3 fw-800 small text-muted border-0 shadow-none tracking-wider" disabled>
                                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> OUT OF STOCK
                            </button>
                        <?php endif; ?>
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
    transform: translateY(-8px);
    box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.12)!important;
}

.product-image {
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover .product-image {
    transform: scale(1.1) rotate(2deg);
}

.remove-wishlist {
    transition: var(--transition-base);
}

.remove-wishlist:hover {
    transform: scale(1.1) rotate(8deg);
    background: #fff!important;
    box-shadow: 0 0.5rem 1.5rem rgba(220, 53, 69, 0.2)!important;
}

.smaller { font-size: 0.7rem; }

.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

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

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
    display: inline-block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add to Cart with Toast-like Feedback
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const originalText = this.innerHTML;
            
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split animate-spin me-2"></i> ADDING...';
            
            fetch('manage_cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&quantity=1&action=add`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.classList.replace('btn-success', 'btn-dark');
                    this.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> ADDED!';
                    
                    // Trigger a custom event or reload after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    this.disabled = false;
                    this.innerHTML = originalText;
                    alert(data.message || 'Error adding to cart');
                }
            })
            .catch(err => {
                this.disabled = false;
                this.innerHTML = originalText;
                console.error(err);
            });
        });
    });

    // Remove from Wishlist
    document.querySelectorAll('.remove-wishlist').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const itemElement = document.getElementById(`wishlist-item-${productId}`);
            
            if (confirm('Remove this item from your wishlist?')) {
                itemElement.style.opacity = '0.5';
                itemElement.style.pointerEvents = 'none';
                
                fetch('manage_wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${productId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        itemElement.classList.add('animate__animated', 'animate__fadeOutScale');
                        setTimeout(() => {
                            itemElement.remove();
                            if (document.querySelectorAll('[id^="wishlist-item-"]').length === 0) {
                                window.location.reload();
                            }
                        }, 500);
                    } else {
                        itemElement.style.opacity = '1';
                        itemElement.style.pointerEvents = 'auto';
                    }
                });
            }
        });
    });
});
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.animate-spin {
    animation: spin 1s linear infinite;
    display: inline-block;
}
.animate__fadeOutScale {
    animation: fadeOutScale 0.5s forwards;
}
@keyframes fadeOutScale {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
