<?php 
require_once 'includes/header.php'; 

// Fetch Featured Categories in specific order
$stmt = $pdo->query("SELECT * FROM categories 
    WHERE status = 'Enabled' 
    ORDER BY CASE name 
        WHEN 'Fruits' THEN 1 
        WHEN 'Vegetables' THEN 2 
        WHEN 'Dairy' THEN 3 
        WHEN 'Bakery' THEN 4 
        WHEN 'Meat & Seafood' THEN 5 
        WHEN 'Beverages' THEN 6 
        WHEN 'Snacks & Sweets' THEN 7 
        ELSE 8 
    END 
    LIMIT 12");
$categories = $stmt->fetchAll();

// Fetch Latest Products
$stmt = $pdo->query("SELECT p.*, c.name as cat_name, 
        v.price as v_price, v.discount_price as v_discount
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_variants v ON v.id = (
            SELECT id FROM product_variants WHERE product_id = p.id ORDER BY price ASC LIMIT 1
        )
        WHERE p.status = 'Active' ORDER BY p.id DESC LIMIT 10");
$latest_products = $stmt->fetchAll();

// Fetch Featured Products for the section below
$stmt = $pdo->query("SELECT p.*, c.name as cat_name,
        v.price as v_price, v.discount_price as v_discount
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN product_variants v ON v.id = (
            SELECT id FROM product_variants WHERE product_id = p.id ORDER BY price ASC LIMIT 1
        )
        WHERE p.status = 'Active' LIMIT 8");
$products = $stmt->fetchAll();

// Check for active 'Out for Delivery' orders
$active_order = null;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT o.*, dp.name as agent_name 
                           FROM orders o 
                           LEFT JOIN delivery_persons dp ON o.delivery_person_id = dp.id 
                           WHERE o.user_id = ? AND o.status = 'Out for Delivery' 
                           ORDER BY o.order_date DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $active_order = $stmt->fetch();
}
?>

<header class="position-relative overflow-hidden mb-5" style="background-color: #2F6146; border-radius: 0 0 30px 30px;">
    <div class="container py-5 py-lg-0">
        <div class="row align-items-center" style="min-height: 550px;">
            <div class="col-lg-6 py-5 z-index-1">

                
                <h1 class="display-3 fw-bold text-white lh-sm mb-4" style="font-family: inherit;">
                    Make healthy<br>
                    life with <span style="color: #D4ED6D; position: relative;">
                        fresh
                        <svg class="position-absolute w-100 start-0" style="bottom: -15px; height: 18px;" viewBox="0 0 100 20" preserveAspectRatio="none">
                            <path d="M0,15 Q50,0 100,15" fill="none" stroke="#D4ED6D" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </span><br>
                    grocery
                </h1>
                
                <p class="text-white opacity-75 mb-5 fs-6 lh-lg" style="max-width: 480px;">
                    Get the best quality and most delicious grocery food in the world, you can get them all use our website.
                </p>
                
                <a href="products.php" class="btn btn-lg fw-bold rounded-3 shadow-sm d-inline-block transition-hover" style="background-color: #FF6B35; color: white; padding: 14px 40px; border: none;">
                    Shop Now
                </a>
            </div>
            
            <div class="col-lg-6 position-relative d-none d-lg-block h-100">
                <div class="position-absolute bottom-0 end-0 d-flex justify-content-end align-items-end" style="width: 100%; height: 100%; top: 50px;">
                    <img src="https://images.unsplash.com/photo-1628102491629-778571d893a3?q=80&w=800&auto=format&fit=crop" 
                         alt="Delivery Person" 
                         class="img-fluid" 
                         style="max-height: 100%; object-fit: contain; transform: scaleX(-1);">
                </div>
                

            </div>
        </div>
    </div>
</header>

<?php if ($active_order): ?>

<!-- Active Delivery Alert -->
<div class="container mb-5 mt-n4 position-relative" style="z-index: 10;">
    <div class="card border-0 shadow-2xl rounded-5 overflow-hidden animate__animated animate__fadeInUp animate__delay-1s bg-black">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-md text-center text-md-start">
                    <h6 class="fw-bold text-warning mb-2 text-uppercase ls-1">Arriving at location</h6>
                    <h2 class="fw-800 text-white mb-2 display-5">Get ready to collect<br>your order</h2>
                    <p class="text-white-50 fs-5 mb-0">
                        Order <span class="text-white fw-bold">#<?php echo $active_order['id']; ?></span> is on the way. 
                        <?php if ($active_order['agent_name']): ?>
                            <span class="text-success fw-bold"><?php echo htmlspecialchars($active_order['agent_name']); ?></span> is nearby.
                        <?php endif; ?>
                    </p>
                    <button onclick="openLiveTracking(<?php echo $active_order['id']; ?>, '<?php echo addslashes($active_order['agent_name']); ?>')" class="btn btn-light rounded-pill px-4 py-2 mt-4 fw-bold shadow-sm transition-hover">
                        <i class="bi bi-geo-alt-fill me-2 text-danger"></i> Track Live
                    </button>
                </div>
                <div class="col-md-auto">
                    <div class="delivery-bag-container mx-auto">
                        <div class="delivery-bag">
                            <div class="bag-handle"></div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <div class="bg-white bg-opacity-10 rounded-pill px-3 py-1 d-inline-flex align-items-center">
                            <div class="spinner-grow spinner-grow-sm text-warning me-2" role="status"></div>
                            <span class="text-white small fw-bold">Live Updates</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Tracking Script and Modal Requirements -->
<?php require_once 'includes/tracking-modal.php'; ?>
<?php endif; ?>

<main class="container">
    <!-- Smooth Slide Banner (Swiper.js) -->
    <section class="mb-5 pb-4">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-800 display-6 mb-1">New <span class="text-success">Arrivals</span></h2>
                <p class="text-muted mb-0">Fresh from the farm to your doorstep</p>
            </div>
            <a href="products.php" class="btn btn-outline-success rounded-pill px-4 fw-bold">View All</a>
        </div>
        
        <div class="swiper latestProductsSwiper rounded-4 overflow-hidden shadow-sm">
            <div class="swiper-wrapper">
                <?php foreach ($latest_products as $product): ?>
                <div class="swiper-slide">
                    <div class="product-banner-card tilt-card position-relative overflow-hidden h-100" style="background: #f8f9fa;">
                        <div class="row g-0 align-items-center h-100">
                            <div class="col-md-6 p-4 p-md-5">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">Just In</span>
                                    <?php 
                                        $display_price = $product['v_price'] ?? $product['price'];
                                        $display_discount = $product['v_discount'] ?? $product['discount_price'];
                                        if (!empty($display_discount) && $display_discount > 0): 
                                    ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-2 fw-bold animate__animated animate__pulse animate__infinite">
                                            <?php 
                                                $discount_pct = round((($display_price - $display_discount) / $display_price) * 100);
                                                echo $discount_pct; 
                                            ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="display-5 fw-800 mb-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="text-muted mb-4 fs-5"><?php echo htmlspecialchars($product['cat_name']); ?> • Fresh & Organic</p>
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <?php if (!empty($display_discount) && $display_discount > 0): ?>
                                        <span class="fs-1 fw-800 text-success">₹<?php echo number_format($display_discount, 2); ?></span>
                                        <span class="text-muted text-decoration-line-through fs-4">₹<?php echo number_format($display_price, 2); ?></span>
                                    <?php else: ?>
                                        <span class="fs-1 fw-800 text-success">₹<?php echo number_format($display_price, 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-success btn-lg px-5 py-3 rounded-pill fw-bold shadow-lg transition-hover add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                    Add to Cart <i class="bi bi-cart-plus ms-2"></i>
                                </button>
                            </div>
                            <div class="col-md-6 h-100">
                                <div class="h-100 position-relative" style="min-height: 400px;">
                                    <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" class="img-fluid w-100 h-100" style="object-fit: cover;" alt="<?php echo $product['name']; ?>">
                                    <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(to right, #f8f9fa 0%, transparent 30%);"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Swiper Navigation -->
            <div class="swiper-button-next text-success"></div>
            <div class="swiper-button-prev text-success"></div>
            <!-- Swiper Pagination -->
            <div class="swiper-pagination"></div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="mb-5 pb-4">
        <div class="text-center mb-5">
            <h2 class="fw-800 display-5 mb-2">Shop by <span class="text-success">Categories</span></h2>
            <p class="text-muted lead">Browse our wide range of fresh products</p>
            <div class="bg-success mx-auto rounded-pill" style="width: 80px; height: 5px;"></div>
        </div>
        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="products.php?category=<?php echo $cat['id']; ?>" class="text-decoration-none group">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 category-card-modern transition-hover tilt-card">
                        <div class="overflow-hidden position-relative" style="height: 200px;">
                            <img src="<?php echo getCategoryImage($cat['image_url'], $cat['name']); ?>" class="img-fluid w-100 h-100 transition-zoom" style="object-fit: cover;" alt="<?php echo $cat['name']; ?>">
                            <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark bg-opacity-10 opacity-0 group-hover-opacity-100 transition-all"></div>
                        </div>
                        <div class="card-body text-center py-4">
                            <h5 class="fw-800 mb-1 text-dark"><?php echo $cat['name']; ?></h5>
                            <span class="text-success small fw-bold">Explore Items <i class="bi bi-arrow-right ms-1"></i></span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Offer Banner Section -->
    <?php
    $offers = $pdo->query("SELECT * FROM offers WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURRENT_DATE) AND (end_date IS NULL OR end_date >= CURRENT_DATE) ORDER BY id DESC")->fetchAll();
    if (!empty($offers)):
    ?>
    <section class="mb-5 pb-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-800 display-6 mb-1">Special <span class="text-success">Offers</span></h2>
                <p class="text-muted mb-0">Discover amazing deals and seasonal specials</p>
            </div>
        </div>
        
        <div class="swiper offerBannerSwiper">
            <div class="swiper-wrapper">
                <?php foreach ($offers as $offer): ?>
                <div class="swiper-slide py-2">
                    <div class="card h-100 border-0 rounded-4 overflow-hidden shadow-sm hover-shadow transition-all position-relative" style="background: <?php echo htmlspecialchars($offer['bg_gradient']); ?>; min-height: 250px;">
                        <div class="position-absolute h-100 w-50 end-0 top-0 d-none d-sm-block">
                            <img src="<?php echo htmlspecialchars($offer['image_url']); ?>" alt="Banner Image" class="w-100 h-100 object-fit-cover" style="clip-path: polygon(25% 0, 100% 0, 100% 100%, 0% 100%); mix-blend-mode: multiply; opacity: 0.9;">
                        </div>
                        <div class="card-body p-4 p-md-5 position-relative z-index-1 w-75">
                            <?php if(!empty($offer['discount_text'])): ?>
                                <span class="badge bg-danger rounded-pill px-3 py-2 mb-3 fw-bold shadow-sm animate__animated animate__pulse animate__infinite"><?php echo htmlspecialchars($offer['discount_text']); ?></span>
                            <?php endif; ?>
                            <h2 class="display-6 fw-800 text-dark mb-4 lh-sm"><?php echo $offer['title']; ?></h2>
                            <a href="<?php echo htmlspecialchars(!empty($offer['link_url']) ? $offer['link_url'] : 'products.php'); ?>" class="btn btn-dark rounded-pill px-4 py-2 fw-bold shadow-sm transition-hover d-inline-flex align-items-center">
                                Shop Now <i class="bi bi-arrow-right ms-2 fs-5"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Swiper Navigation -->
            <div class="swiper-button-next text-dark fw-bold pe-2 drop-shadow-md" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
            <div class="swiper-button-prev text-dark fw-bold ps-2 drop-shadow-md" style="text-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Products -->
    <section class="mb-5 pb-5">
        <div class="d-flex justify-content-between align-items-end mb-5">
            <div>
                <h2 class="fw-800 display-5 mb-2">Featured <span class="text-success">Products</span></h2>
                <p class="text-muted lead mb-0">Selected quality products for you</p>
            </div>
            <a href="products.php" class="btn btn-outline-success rounded-4 px-4 py-2 fw-800 border-2 transition-hover">
                VIEW ALL <i class="bi bi-grid-3x3-gap-fill ms-2"></i>
            </a>
        </div>
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden product-card-modern transition-hover tilt-card">
                    <div class="position-relative overflow-hidden" style="height: 240px;">
                        <?php 
                            $display_price = $product['v_price'] ?? $product['price'];
                            $display_discount = $product['v_discount'] ?? $product['discount_price'];
                            if (!empty($display_discount) && $display_discount > 0): 
                        ?>
                            <div class="badge bg-danger position-absolute top-0 start-0 m-3 rounded-4 px-3 py-2 fw-800 shadow-sm" style="z-index: 2;">
                                <?php 
                                    $discount_pct = round((($display_price - $display_discount) / $display_price) * 100);
                                    echo $discount_pct; 
                                ?>% OFF
                            </div>
                        <?php endif; ?>
                        
                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="d-block h-100">
                            <img src="<?php echo getProductImage($product['image_url'], $product['name']); ?>" class="w-100 h-100 transition-zoom" style="object-fit: cover;" alt="<?php echo $product['name']; ?>">
                        </a>
                        
                        <div class="product-actions position-absolute bottom-0 start-0 w-100 p-3 translate-y-100 transition-all">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <button class="btn btn-success w-100 rounded-4 py-2 shadow-lg add-to-cart fw-800" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="bi bi-cart-plus-fill me-2"></i>ADD TO CART
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary w-100 rounded-4 py-2 shadow-lg fw-800" disabled>
                                    OUT OF STOCK
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-success fw-800 text-uppercase ls-1" style="font-size: 0.7rem;">
                                <?php echo htmlspecialchars($product['cat_name'] ?? 'Uncategorized'); ?>
                            </small>
                            <div class="text-warning small">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-half"></i>
                            </div>
                        </div>
                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark d-block">
                            <h5 class="fw-800 mb-3 text-truncate"><?php echo htmlspecialchars($product['name']); ?></h5>
                        </a>
                        <div class="d-flex align-items-center gap-2 mb-0">
                            <?php 
                                $display_price = $product['v_price'] ?? $product['price'];
                                $display_discount = $product['v_discount'] ?? $product['discount_price'];
                                if (!empty($display_discount) && $display_discount > 0): 
                            ?>
                                <span class="text-success fw-800 h4 mb-0">₹<?php echo number_format($display_discount, 2); ?></span>
                                <span class="text-muted text-decoration-line-through small">₹<?php echo number_format($display_price, 2); ?></span>
                            <?php else: ?>
                                <span class="text-success fw-800 h4 mb-0">₹<?php echo number_format($display_price, 2); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>


</main>

<?php require_once 'includes/footer.php'; ?>

<style>
.mainBannerSwiper {
    height: 450px;
}
.mainBannerSwiper .swiper-pagination-bullet-active {
    background: #ffffff;
}
.mainBannerSwiper .swiper-button-next:after, 
.mainBannerSwiper .swiper-button-prev:after {
    font-size: 24px;
    font-weight: bold;
}
.banner-content {
    cursor: pointer;
    transition: all 0.3s ease;
}
@media (max-width: 768px) {
    .mainBannerSwiper {
        height: auto;
    }
    .banner-content {
        padding: 2rem !important;
        min-height: 350px !important;
    }
    .banner-content h1 {
        font-size: 2rem !important;
    }
}

.latestProductsSwiper {
    height: 450px;
}
.latestProductsSwiper .swiper-pagination-bullet-active {
    background: #198754;
}
.latestProductsSwiper .swiper-button-next:after, 
.latestProductsSwiper .swiper-button-prev:after {
    font-size: 20px;
    font-weight: bold;
}
.product-banner-card {
    transition: all 0.3s ease;
}
@media (max-width: 768px) {
    .latestProductsSwiper {
        height: auto;
    }
    .product-banner-card .row {
        flex-direction: column-reverse;
    }
    .product-banner-card img {
        height: 250px !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Main Banner Swiper
    const mainSwiper = new Swiper('.mainBannerSwiper', {
        loop: true,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        effect: 'creative',
        creativeEffect: {
            prev: {
                shadow: true,
                translate: ['-20%', 0, -1],
            },
            next: {
                translate: ['100%', 0, 0],
            },
        },
    });

    const swiper = new Swiper('.latestProductsSwiper', {
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        effect: 'fade',
        fadeEffect: {
            crossFade: true
        },
    });

    const offerSwiper = new Swiper('.offerBannerSwiper', {
        loop: false,
        slidesPerView: 1,
        spaceBetween: 20,
        autoplay: {
            delay: 4500,
            disableOnInteraction: false,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        breakpoints: {
            768: {
                slidesPerView: 2,
                spaceBetween: 30
            }
        }
    });
});
</script>
