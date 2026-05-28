<?php require_once 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick mart - Online Grocery Store</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/delivery-animation.css">
    <link rel="stylesheet" href="assets/css/4d-animation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3 text-success d-flex align-items-center" href="index.php">
                <div class="bg-success text-white rounded-3 p-1 me-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-basket2-fill"></i>
                </div>
                Quick mart
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link px-3 fw-medium" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link px-3 fw-medium" href="products.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link px-3 fw-medium" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link px-3 fw-medium" href="contact.php">Contact</a></li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="cart.php" class="btn btn-light position-relative rounded-circle p-0 d-flex align-items-center justify-content-center shadow-sm transition-hover" style="width: 45px; height: 45px;">
                        <i class="bi bi-cart3 fs-5"></i>
                        <span id="cart-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-white" style="font-size: 0.7rem;">
                            <?php echo isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?>
                        </span>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <?php
                            // Count unread messages
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE user_id = ? AND status = 'Unread'");
                            $stmt->execute([$_SESSION['user_id']]);
                            $unread_msg_count = $stmt->fetchColumn();

                            // Count active delivery notifications (Shipped or Out for Delivery)
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('Shipped', 'Out for Delivery') AND delivery_otp IS NOT NULL");
                            $stmt->execute([$_SESSION['user_id']]);
                            $active_delivery_count = $stmt->fetchColumn();

                            $total_notifications = $unread_msg_count + $active_delivery_count;

                            // Fetch user profile photo
                            $stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $user_profile_photo = $stmt->fetchColumn();
                        ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-success dropdown-toggle rounded-pill p-1 pe-3 fw-medium shadow-sm transition-hover d-flex align-items-center gap-2 position-relative" type="button" data-bs-toggle="dropdown">
                                <?php if ($user_profile_photo): ?>
                                    <img src="<?php echo htmlspecialchars($user_profile_photo); ?>" class="rounded-circle object-fit-cover" width="35" height="35">
                                <?php else: ?>
                                    <i class="bi bi-person-circle fs-4 ms-2"></i> 
                                <?php endif; ?>
                                <span class="d-none d-sm-inline"><?php echo explode(' ', $_SESSION['user_name'])[0]; ?></span>
                                <?php if ($total_notifications > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                                        <span class="visually-hidden">New notifications</span>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-3 p-2 animate-fade-in" style="min-width: 220px; border-radius: 1rem;">
                                <li><div class="dropdown-header text-uppercase small fw-bold text-muted px-3 py-2">Account</div></li>
                                <li>
                                    <a class="dropdown-item rounded-3 py-2 px-3 d-flex justify-content-between align-items-center" href="my-orders.php">
                                        <span><i class="bi bi-clock-history me-2"></i>Order History</span>
                                        <?php if ($active_delivery_count > 0): ?>
                                            <span class="badge rounded-pill bg-warning text-dark small"><?php echo $active_delivery_count; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3" href="wallet.php"><i class="bi bi-wallet2 me-2"></i>My Wallet</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3" href="wishlist.php"><i class="bi bi-heart me-2"></i>My Wishlist</a></li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3" href="purchase-history.php"><i class="bi bi-bag-check me-2"></i>Product History</a></li>
                                <li>
                                    <a class="dropdown-item rounded-3 py-2 px-3 d-flex justify-content-between align-items-center" href="my-messages.php">
                                        <span><i class="bi bi-chat-dots me-2"></i>My Messages</span>
                                        <?php if ($total_notifications > 0): ?>
                                            <span class="badge rounded-pill bg-danger small"><?php echo $total_notifications; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3" href="account-settings.php"><i class="bi bi-gear me-2"></i>Account Settings</a></li>
                                <li><hr class="dropdown-divider mx-2"></li>
                                <li><a class="dropdown-item rounded-3 py-2 px-3 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="d-flex gap-2">
                            <a href="login.php" class="btn btn-outline-success rounded-pill px-4 fw-medium shadow-sm transition-hover">Login</a>
                            <a href="register.php" class="btn btn-success rounded-pill px-4 fw-medium shadow-sm transition-hover">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
