<?php 
require_once 'config/db.php';
$founder_name = get_setting('founder_name', 'Founding Team') ?? 'Founding Team';
$ceo_name = get_setting('ceo_name', 'Leadership Team') ?? 'Leadership Team';
$founder_image = get_setting('founder_image');
$ceo_image = get_setting('ceo_image');
require_once 'includes/header.php';  
?>
<style>
.fw-800 { font-weight: 800; }
.fw-600 { font-weight: 600; }
.transition-hover {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.transition-hover:hover {
    transform: translateY(-8px);
    box-shadow: 0 1.5rem 4rem rgba(0,0,0,0.15)!important;
}
.text-justify { text-align: justify; }
.feature-icon-wrapper {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-bottom: 20px;
}
</style>

<!-- Hero Section -->
<div class="bg-success py-5 mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #198754 0%, #157347 100%);">
    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-image: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.1;"></div>
    <div class="container position-relative">
        <div class="text-center animate__animated animate__fadeIn">
            <nav aria-label="breadcrumb" class="d-flex justify-content-center">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white text-opacity-75 text-decoration-none small fw-600">Home</a></li>
                    <li class="breadcrumb-item active text-white small fw-600" aria-current="page">About Us</li>
                </ol>
            </nav>
            <h1 class="text-white fw-800 mb-2 display-4">About <span class="text-white opacity-75">Quick mart</span></h1>
            <p class="text-white text-opacity-75 mb-0 fw-600 mx-auto" style="max-width: 600px;">Your one-stop destination for fresh, organic, and quality groceries delivered right to your doorstep.</p>
        </div>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6 animate__animated animate__fadeInLeft">
            <div class="rounded-4 shadow-lg p-5 d-flex flex-column justify-content-center" style="background-color: #356a49; height: 100%; min-height: 480px;">
                <h1 class="text-white fw-bold mb-4" style="font-size: 3.5rem; line-height: 1.1; letter-spacing: -1px;">
                    Make healthy<br>
                    life with <span style="position: relative; color: #c0ee75; display: inline-block;">
                        fresh
                        <svg style="position: absolute; bottom: -8px; left: 0; width: 100%; height: 12px; transform: rotate(-2deg);" viewBox="0 0 100 20" preserveAspectRatio="none">
                            <path d="M0,10 Q50,0 100,10" stroke="#c0ee75" stroke-width="3" stroke-linecap="round" fill="none" />
                        </svg>
                    </span><br>
                    grocery
                </h1>
                <p class="text-white text-opacity-75 mb-5" style="font-size: 0.95rem; line-height: 1.6; max-width: 90%;">
                    Get the best quality and most delicious grocery food in the<br>
                    world, you can get them all use our website.
                </p>
                <div>
                    <a href="products.php" class="btn shadow-sm px-4 py-2 fw-bold transition-hover" style="background-color: #ff6839; color: white; border-radius: 8px; font-size: 1.05rem;">
                        Shop Now
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-6 animate__animated animate__fadeInRight">
            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-600 mb-3">Our Story</span>
            <h2 class="fw-bold mb-4">Dedicated to Farm-to-Table Freshness</h2>
            <p class="text-muted text-justify mb-4" style="line-height: 1.8;">
                At <strong>Quick mart</strong>, we believe that high-quality, fresh food should be accessible to everyone. Founded with a mission to bridge the gap between local farmers and urban consumers, we carefully curate our inventory to include the best organic produce, dairy, bakery items, and household essentials.
            </p>
            <p class="text-muted text-justify mb-4" style="line-height: 1.8;">
                Our rapid delivery network ensures that your items arrive at peak freshness within hours. We are committed to prioritizing sustainability and supporting local vendors while offering competitive prices and unmatched convenience for you and your family.
            </p>
            <div class="d-flex gap-4 mb-4">
                <div class="d-flex align-items-center">
                    <?php $f_img_src = $founder_image ? htmlspecialchars($founder_image) : "https://ui-avatars.com/api/?name=" . urlencode($founder_name) . "&background=random"; ?>
                    <img src="<?php echo $f_img_src; ?>" alt="Founder" class="rounded-circle me-3 object-fit-cover shadow-sm" width="60" height="60">
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($founder_name); ?></h5>
                        <p class="text-success small fw-bold mb-0">Founder</p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <?php $c_img_src = $ceo_image ? htmlspecialchars($ceo_image) : "https://ui-avatars.com/api/?name=" . urlencode($ceo_name) . "&background=random"; ?>
                    <img src="<?php echo $c_img_src; ?>" alt="CEO" class="rounded-circle me-3 object-fit-cover shadow-sm" width="60" height="60">
                    <div>
                        <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($ceo_name); ?></h5>
                        <p class="text-success small fw-bold mb-0">CEO</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Core Values -->
    <div class="text-center mb-5 mt-5">
        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-600 mb-3">Why Choose Us</span>
        <h2 class="fw-bold mb-3">Our Core Values</h2>
        <p class="text-muted mx-auto" style="max-width: 600px;">We stick to these principles every single day to bring you the best online grocery shopping experience.</p>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4 animate__animated animate__fadeInUp">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="feature-icon-wrapper bg-success bg-opacity-10 text-success mx-auto">
                    <i class="bi bi-basket fs-3"></i>
                </div>
                <h5 class="fw-bold mb-3">100% Organic Products</h5>
                <p class="text-muted small mb-0">We source our products exclusively from trusted local organic farms and certified suppliers.</p>
            </div>
        </div>
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="feature-icon-wrapper bg-warning bg-opacity-10 text-warning mx-auto">
                    <i class="bi bi-truck fs-3"></i>
                </div>
                <h5 class="fw-bold mb-3">Lightning Fast Delivery</h5>
                <p class="text-muted small mb-0">Our dedicated delivery fleet ensures your groceries reach your kitchen right on time.</p>
            </div>
        </div>
        <div class="col-md-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-4 transition-hover text-center bg-white tilt-card">
                <div class="feature-icon-wrapper bg-danger bg-opacity-10 text-danger mx-auto">
                    <i class="bi bi-shield-check fs-3"></i>
                </div>
                <h5 class="fw-bold mb-3">Secure Payments</h5>
                <p class="text-muted small mb-0">We utilize bank-level security for all transactions and prioritize your data privacy.</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-light py-5">
    <div class="container py-4 text-center">
        <h3 class="fw-bold mb-4">Ready to try farm-fresh groceries?</h3>
        <a href="products.php" class="btn btn-success btn-lg px-5 py-3 rounded-pill shadow-sm fw-600">Start Shopping Now <i class="bi bi-arrow-right ms-2"></i></a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
