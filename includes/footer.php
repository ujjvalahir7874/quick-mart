    <footer class="footer mt-5 py-5 pb-5 mb-5 mb-lg-0">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand mb-4">
                        <a class="navbar-brand fw-bold fs-3 text-success d-flex align-items-center" href="index.php">
                            <div class="bg-success text-white rounded-3 p-1 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-basket2-fill"></i>
                            </div>
                            Quick mart
                        </a>
                    </div>
                    <p class="text-muted mb-4">Your one-stop destination for fresh, organic, and quality groceries delivered to your doorstep. Experience the farm-to-table freshness every day.</p>

                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="fw-bold mb-4">Quick Links</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="index.php" class="text-muted text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="products.php" class="text-muted text-decoration-none">Shop</a></li>

                        <li class="mb-2"><a href="about.php" class="text-muted text-decoration-none">About Us</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h6 class="fw-bold mb-4">Customer Support</h6>
                    <ul class="list-unstyled footer-links">
                        <li class="mb-2"><a href="contact.php" class="text-muted text-decoration-none">Contact Us</a></li>
                        <li class="mb-2"><a href="mailto:QuickMart@gmail.com" class="text-muted text-decoration-none">Email Support</a></li>
                    </ul>
                </div>

            </div>
            <hr class="my-5 opacity-10">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Quick mart. Crafted with <i class="bi bi-heart-fill text-danger"></i> for fresh food lovers.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-muted small mb-0">Designed & Developed by <span class="text-success fw-bold">Quick mart Team</span></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav d-lg-none bg-white border-top fixed-bottom shadow-lg py-2 px-3">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="nav-item text-center text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-success' : 'text-muted'; ?>">
                <i class="bi bi-house-door fs-5 d-block"></i>
                <span class="smaller fw-bold">Home</span>
            </a>
            <a href="products.php" class="nav-item text-center text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'text-success' : 'text-muted'; ?>">
                <i class="bi bi-grid fs-5 d-block"></i>
                <span class="smaller fw-bold">Shop</span>
            </a>
            <a href="cart.php" class="nav-item text-center text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'text-success' : 'text-muted'; ?> position-relative">
                <i class="bi bi-cart3 fs-5 d-block"></i>
                <span class="smaller fw-bold">Cart</span>
                <span class="position-absolute top-0 start-50 translate-middle-x badge rounded-pill bg-danger" style="font-size: 0.6rem; margin-top: -5px; margin-left: 10px;">
                    <?php echo isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?>
                </span>
            </a>
            <a href="wishlist.php" class="nav-item text-center text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'wishlist.php' ? 'text-success' : 'text-muted'; ?>">
                <i class="bi bi-heart fs-5 d-block"></i>
                <span class="smaller fw-bold">Wishlist</span>
            </a>
            <a href="account-settings.php" class="nav-item text-center text-decoration-none <?php echo basename($_SERVER['PHP_SELF']) == 'account-settings.php' ? 'text-success' : 'text-muted'; ?>">
                <i class="bi bi-person fs-5 d-block"></i>
                <span class="smaller fw-bold">Profile</span>
            </a>
        </div>
    </div>

    <style>
    .footer {
        background-color: #fff;
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    .footer-links a:hover {
        color: var(--primary-color) !important;
        padding-left: 5px;
        transition: var(--transition);
    }
    .mobile-bottom-nav {
        z-index: 1030;
        border-radius: 20px 20px 0 0;
    }
    .mobile-bottom-nav .nav-item {
        flex: 1;
        transition: var(--transition);
    }
    .mobile-bottom-nav .nav-item:active {
        transform: scale(0.9);
    }
    .smaller { font-size: 0.7rem; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="assets/js/cart.js"></script>
    <script src="assets/js/4d-effects.js"></script>
</body>
</html>
