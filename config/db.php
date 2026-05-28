<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'grocery_store';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: ''; // Default XAMPP password is empty
$port = getenv('DB_PORT') ?: null;
$usingEnvDatabase = (bool) getenv('DB_HOST');

try {
    $dsnHost = "mysql:host=$host" . ($port ? ";port=$port" : "") . ";charset=utf8mb4";
    $dsnDatabase = $dsnHost . ";dbname=$dbname";

    // Local XAMPP can create/select the database automatically. Hosted providers
    // usually require connecting directly to an existing database.
    $pdo = new PDO($usingEnvDatabase ? $dsnDatabase : $dsnHost, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Check if PDO drivers are available (should have failed already if not, but let's be sure)
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        throw new Exception("PDO MySQL driver is not enabled in your PHP configuration.");
    }
    
    if (!$usingEnvDatabase) {
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` ");
        $pdo->exec("USE `$dbname` ");
    }

    // Ensure database is selected and tables are ready
    $pdo->query("SET NAMES utf8mb4");

    // Check if tables exist, if not create them
    $stmt = $pdo->query("SHOW TABLES");
    $tablesExist = $stmt->fetchAll();
    
    if (empty($tablesExist)) {
        $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
        // Execute schema.sql content
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            $queries = explode(';', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
        }
    } elseif (!$usingEnvDatabase || getenv('RUN_DB_MAINTENANCE') === '1') {
        // Ensure status column is VARCHAR and has all necessary values
        // We use MODIFY to change the ENUM from schema.sql to VARCHAR
        try {
            $pdo->exec("ALTER TABLE orders MODIFY COLUMN status VARCHAR(20) DEFAULT 'Pending'");
        } catch (Exception $e) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(20) DEFAULT 'Pending' AFTER total_amount");
        }

        // Add payment_method, shipping_address, and contact_number
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash on Delivery' AFTER status"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_address TEXT AFTER payment_method"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN contact_number VARCHAR(20) AFTER shipping_address"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_date TIMESTAMP NULL AFTER order_date"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) AFTER password"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT AFTER phone_number"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_house_no VARCHAR(120) DEFAULT NULL AFTER address"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_society_name VARCHAR(180) DEFAULT NULL AFTER address_house_no"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_area_name VARCHAR(180) DEFAULT NULL AFTER address_society_name"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_road_name VARCHAR(180) DEFAULT NULL AFTER address_area_name"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_landmark VARCHAR(180) DEFAULT NULL AFTER address_road_name"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_city VARCHAR(120) DEFAULT NULL AFTER address_landmark"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_state VARCHAR(120) DEFAULT NULL AFTER address_city"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_pincode VARCHAR(20) DEFAULT NULL AFTER address_state"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_country VARCHAR(120) DEFAULT NULL AFTER address_pincode"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN user_latitude DECIMAL(10, 8) DEFAULT NULL AFTER address"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN user_longitude DECIMAL(11, 8) DEFAULT NULL AFTER user_latitude"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN location_accuracy DECIMAL(8, 2) DEFAULT NULL AFTER user_longitude"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN location_source VARCHAR(30) DEFAULT NULL AFTER location_accuracy"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN location_updated_at DATETIME DEFAULT NULL AFTER location_source"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL AFTER role"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expiry DATETIME DEFAULT NULL AFTER reset_token"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL AFTER reset_token_expiry"); } catch (Exception $e) {}

        try { $pdo->exec("ALTER TABLE products ADD COLUMN discount_price DECIMAL(10, 2) DEFAULT NULL AFTER price"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE products ADD COLUMN tax_percentage DECIMAL(5, 2) DEFAULT 0.00 AFTER discount_price"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE products ADD COLUMN availability_status ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock' AFTER stock_quantity"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE products ADD COLUMN expiry_date DATE DEFAULT NULL AFTER availability_status"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE products ADD COLUMN status ENUM('Active', 'Archived') DEFAULT 'Active' AFTER availability_status"); } catch (Exception $e) {}

        // Add parent_id and status to categories
        try { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL AFTER name"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE categories ADD COLUMN status ENUM('Enabled', 'Disabled') DEFAULT 'Enabled' AFTER parent_id"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE categories ADD CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE"); } catch (Exception $e) {}

        // Add is_exclusive to products
        try { $pdo->exec("ALTER TABLE products ADD COLUMN is_exclusive TINYINT(1) DEFAULT 0 AFTER availability_status"); } catch (Exception $e) {}

        // Create wallets table
        $pdo->exec("CREATE TABLE IF NOT EXISTS wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            balance DECIMAL(10, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Create wallet_transactions table
        $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wallet_id INT NOT NULL,
            type ENUM('Credit', 'Debit') NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            description VARCHAR(255) NOT NULL,
            order_id INT DEFAULT NULL,
            status ENUM('Completed', 'Pending', 'Failed') DEFAULT 'Completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
        )");

        // Create scratch_cards table
        $pdo->exec("CREATE TABLE IF NOT EXISTS scratch_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) DEFAULT 0.00,
            order_id INT DEFAULT NULL,
            is_scratched TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Add delivery_otp to orders
        try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_otp VARCHAR(6) DEFAULT NULL"); } catch (Exception $e) {}

        // Create coupons table
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            discount_type ENUM('percentage', 'fixed') NOT NULL,
            discount_value DECIMAL(10, 2) NOT NULL,
            min_purchase DECIMAL(10, 2) DEFAULT 0.00,
            expiry_date DATE NOT NULL,
            usage_limit INT DEFAULT 1,
            used_count INT DEFAULT 0,
            status ENUM('Enabled', 'Disabled', 'Expired') DEFAULT 'Enabled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Add usage_limit and used_count to coupons if they don't exist
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN usage_limit INT DEFAULT 1 AFTER expiry_date"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons ADD COLUMN used_count INT DEFAULT 0 AFTER usage_limit"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE coupons MODIFY COLUMN status ENUM('Enabled', 'Disabled', 'Expired') DEFAULT 'Enabled'"); } catch (Exception $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NOT NULL,
            subtitle VARCHAR(255) NOT NULL,
            discount_text VARCHAR(50) NOT NULL,
            link_url VARCHAR(255) NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            bg_gradient VARCHAR(100) DEFAULT 'linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)',
            bg_img_url VARCHAR(255) NULL,
            badge_text VARCHAR(50) DEFAULT 'Limited Time Offer',
            badge_color VARCHAR(20) DEFAULT 'danger',
            start_date DATE NULL,
            end_date DATE NULL,
            offer_type VARCHAR(30) DEFAULT 'BANNER',
            buy_quantity INT DEFAULT 0,
            get_quantity INT DEFAULT 0,
            offer_scope VARCHAR(30) DEFAULT 'same_product',
            applicable_product_id INT DEFAULT NULL,
            free_product_id INT DEFAULT NULL,
            max_free_items INT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        try { $pdo->exec("ALTER TABLE offers ADD COLUMN start_date DATE NULL AFTER badge_color"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN end_date DATE NULL AFTER start_date"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN offer_type VARCHAR(30) DEFAULT 'BANNER' AFTER end_date"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN buy_quantity INT DEFAULT 0 AFTER offer_type"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN get_quantity INT DEFAULT 0 AFTER buy_quantity"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN offer_scope VARCHAR(30) DEFAULT 'same_product' AFTER get_quantity"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN applicable_product_id INT DEFAULT NULL AFTER offer_scope"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN free_product_id INT DEFAULT NULL AFTER applicable_product_id"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE offers ADD COLUMN max_free_items INT DEFAULT NULL AFTER free_product_id"); } catch (Exception $e) {}
        
        // Seed default offers if empty
        $count = $pdo->query("SELECT COUNT(*) FROM offers")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("INSERT INTO offers (title, subtitle, discount_text, link_url, image_url, bg_gradient, badge_text, badge_color) VALUES 
                ('Get <span class=\"text-success\">20% Off</span>', 'On your first organic fruit order.', 'Limited Time Offer', 'products.php', 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=400&q=80', 'linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%)', 'Limited Time Offer', 'danger'),
                ('Fresh <span class=\"text-warning\">Bakery</span>', 'Buy 1 Get 1 Free on all breads.', 'Weekend Special', 'products.php', 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=400&q=80', 'linear-gradient(135deg, #ffd3a5 0%, #fd6585 100%)', 'Weekend Special', 'warning')
            ");
        }

        // Create activity logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // Create order_messages table
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            sender_type ENUM('customer', 'delivery') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");

        // Create app_settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(255) NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Seed default app settings if not exists
        $default_settings = [
            'maintenance_mode' => '0',
            'allow_registrations' => '1',
            'masked_call_enabled' => '1',
            'masked_call_relay_label' => 'Quick mart Secure Call Desk',
            'masked_call_relay_number' => '1800123456',
            'founder_name' => 'Founding Team',
            'ceo_name' => 'Leadership Team',
            'sms_enabled' => '1',
            'sms_provider' => 'simulation',
            'email_enabled' => '1',
            'email_provider' => 'smtp',
            'email_from_name' => 'Quick mart',
            'email_from_address' => 'ujjvalahir7874@gmail.com',
            'email_smtp_host' => 'smtp.gmail.com',
            'email_smtp_port' => '587',
            'email_smtp_user' => 'ujjvalahir7874@gmail.com',
            'fallback_prod_default' => 'https://images.unsplash.com/photo-1506617564039-2f3b650ad701?auto=format&fit=crop&w=800&q=80',
            'fallback_recipe_default' => 'https://images.unsplash.com/photo-1495521821757-a1efb6729352?auto=format&fit=crop&w=800&q=80',
            'fallback_cat_default' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=800&q=80',
            // Product Fallbacks
            'fallback_prod_apple' => 'https://images.unsplash.com/photo-1560806887-1e4cd0b6bcd6?auto=format&fit=crop&w=800&q=80',
            'fallback_prod_banana' => 'https://images.unsplash.com/photo-1571771894821-ad9b5886479b?auto=format&fit=crop&w=800&q=80',
            'fallback_prod_milk' => 'https://images.unsplash.com/photo-1563636619-e910f0185962?auto=format&fit=crop&w=800&q=80',
            'fallback_prod_bread' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=800&q=80',
            'fallback_prod_egg' => 'https://images.unsplash.com/photo-1582722872445-44ad5c78a9dd?auto=format&fit=crop&w=800&q=80',
            'fallback_prod_chicken' => 'https://images.unsplash.com/photo-1587593817642-5999c1a6ce40?auto=format&fit=crop&w=800&q=80',
            // Category Fallbacks
            'fallback_cat_fruit' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=800&q=80',
            'fallback_cat_vegetable' => 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=800&q=80',
            'fallback_cat_dairy' => 'https://images.unsplash.com/photo-1528750955925-53f5a3ea2d4b?auto=format&fit=crop&w=800&q=80',
            'fallback_cat_bakery' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=800&q=80'
        ];

        foreach ($default_settings as $key => $val) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            if ($stmt->fetchColumn() == 0) {
                set_setting($key, $val);
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS call_bridge_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            requester_type VARCHAR(20) NOT NULL,
            requester_id INT NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            relay_number VARCHAR(30) DEFAULT NULL,
            reference_code VARCHAR(40) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'Requested',
            notes VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");

        // Add variant_id to order_items
        try { $pdo->exec("ALTER TABLE order_items ADD COLUMN variant_id INT DEFAULT 0 AFTER product_id"); } catch (Exception $e) {}

        // Create feedback table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            service_rating INT NOT NULL CHECK (service_rating >= 1 AND service_rating <= 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY (order_id)
        )");

        try { $pdo->exec("ALTER TABLE order_feedback ADD COLUMN service_rating INT NOT NULL DEFAULT 5 CHECK (service_rating >= 1 AND service_rating <= 5) AFTER comment"); } catch (Exception $e) {}

        // Create wishlist table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            UNIQUE KEY (user_id, product_id)
        )");

        // Create product reviews table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        // Create recipes table
        $pdo->exec("CREATE TABLE IF NOT EXISTS recipes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            instructions TEXT,
            image_url VARCHAR(255),
            prep_time VARCHAR(20),
            servings INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Create recipe ingredients table
        $pdo->exec("CREATE TABLE IF NOT EXISTS recipe_ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipe_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity_text VARCHAR(50),
            FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");

         // Create contact_messages table
         $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
             id INT AUTO_INCREMENT PRIMARY KEY,
             user_id INT NULL,
             parent_id INT NULL,
             name VARCHAR(100) NOT NULL,
             email VARCHAR(100) NOT NULL,
             subject VARCHAR(255),
             message TEXT NOT NULL,
             status ENUM('Unread', 'Read', 'Replied') DEFAULT 'Unread',
             admin_reply TEXT,
             replied_at TIMESTAMP NULL,
             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
             FOREIGN KEY (parent_id) REFERENCES contact_messages(id) ON DELETE CASCADE
         )");

         // Create delivery_persons table
         $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_persons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            mobile_no VARCHAR(20) NOT NULL,
            password_hash VARCHAR(255) NULL,
            bike_number VARCHAR(20) NOT NULL,
            status ENUM('Available', 'Busy', 'Offline') DEFAULT 'Available',
            otp VARCHAR(10) NULL,
            documents TEXT NULL,
            current_lat DECIMAL(10, 8) NULL,
            current_lng DECIMAL(11, 8) NULL,
            wallet_balance DECIMAL(10, 2) DEFAULT 0.00,
            is_verified TINYINT(1) DEFAULT 0,
            rating DECIMAL(3, 2) DEFAULT 0.00,
            fcm_token TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Create product_variants table
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            size_name VARCHAR(50) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            discount_price DECIMAL(10, 2) DEFAULT NULL,
            stock_quantity INT DEFAULT 0,
            expiry_date DATE DEFAULT NULL,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");

        // Add expiry_date to product_variants if it doesn't exist
        try { $pdo->exec("ALTER TABLE product_variants ADD COLUMN expiry_date DATE DEFAULT NULL AFTER stock_quantity"); } catch (Exception $e) {}

        // Seed product_variants if empty
        $count = $pdo->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
        if ($count == 0) {
            $products = $pdo->query("SELECT id, price, discount_price, stock_quantity FROM products")->fetchAll();
            $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price, discount_price, stock_quantity) VALUES (?, ?, ?, ?, ?)");
            foreach ($products as $p) {
                $variants = [
                    ['size' => '250g', 'price' => $p['price'] * 0.3, 'discount' => $p['discount_price'] ? $p['discount_price'] * 0.3 : null],
                    ['size' => '500g', 'price' => $p['price'] * 0.6, 'discount' => $p['discount_price'] ? $p['discount_price'] * 0.6 : null],
                    ['size' => '1kg', 'price' => $p['price'], 'discount' => $p['discount_price']],
                ];
                foreach ($variants as $v) {
                    $stmt->execute([$p['id'], $v['size'], $v['price'], $v['discount'], $p['stock_quantity']]);
                }
            }
        }

         // Create delivery_earnings table
         $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_earnings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            delivery_person_id INT NOT NULL,
            order_id INT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            type ENUM('Credit', 'Debit') NOT NULL,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
        )");

         // Create delivery_location_logs table
         $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_location_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            delivery_person_id INT NOT NULL,
            lat DECIMAL(10, 8) NOT NULL,
            lng DECIMAL(11, 8) NOT NULL,
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
        )");

         // Ensure columns exist if table was already created
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rating DECIMAL(3, 2) DEFAULT 0.00 AFTER is_verified"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN password_hash VARCHAR(255) NULL AFTER mobile_no"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN otp VARCHAR(10) NULL AFTER status"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN documents TEXT NULL AFTER otp"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN current_lat DECIMAL(10, 8) NULL AFTER documents"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN current_lng DECIMAL(11, 8) NULL AFTER current_lat"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN wallet_balance DECIMAL(10, 2) DEFAULT 0.00 AFTER current_lng"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER wallet_balance"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN fcm_token TEXT NULL AFTER is_verified"); } catch (Exception $e) {}
          
          // Add Document Columns
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN doc_aadhaar VARCHAR(255) NULL AFTER fcm_token"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN doc_license VARCHAR(255) NULL AFTER doc_aadhaar"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN doc_rc VARCHAR(255) NULL AFTER doc_license"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN doc_photo VARCHAR(255) NULL AFTER doc_rc"); } catch (Exception $e) {}
          
          // Add Suspension Columns
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN is_suspended TINYINT(1) DEFAULT 0 AFTER doc_photo"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN suspension_reason TEXT NULL AFTER is_suspended"); } catch (Exception $e) {}
          
          // Add Document Rejection Columns
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rejected_docs VARCHAR(255) NULL AFTER suspension_reason"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rejection_reason_aadhaar TEXT NULL AFTER rejected_docs"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rejection_reason_license TEXT NULL AFTER rejection_reason_aadhaar"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rejection_reason_rc TEXT NULL AFTER rejection_reason_license"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN rejection_reason_photo TEXT NULL AFTER rejection_reason_rc"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE delivery_persons ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL AFTER rejection_reason_photo"); } catch (Exception $e) {}
          
          // Add Wallet Column to Users
          try { $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10, 2) DEFAULT 0.00 AFTER profile_photo"); } catch (Exception $e) {}
          
          try { $pdo->exec("ALTER TABLE contact_messages ADD COLUMN user_id INT NULL AFTER id"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE contact_messages ADD COLUMN delivery_person_id INT NULL AFTER user_id"); } catch (Exception $e) {}
          try { $pdo->exec("ALTER TABLE contact_messages ADD CONSTRAINT fk_message_delivery_person FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE SET NULL"); } catch (Exception $e) {}
         try { $pdo->exec("ALTER TABLE contact_messages ADD COLUMN parent_id INT NULL AFTER user_id"); } catch (Exception $e) {}
         try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_person_id INT DEFAULT NULL AFTER user_id"); } catch (Exception $e) {}
         try { $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_order_delivery_person FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE SET NULL"); } catch (Exception $e) {}

          // Add User Profile Photo Column
          try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role"); } catch (Exception $e) {}

          // Add Order Notification Columns
          try { $pdo->exec("ALTER TABLE orders ADD COLUMN pickup_notification_sent TINYINT(1) DEFAULT 0 AFTER delivery_otp"); } catch (Exception $e) {}

        // Even if tables exist, ensure admin user exists and password is 'admin123'
        // We use a specific hash for 'admin123' to ensure it's always correct
        $adminEmail = 'admin@grocery.com';
        $adminPass = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // This is 'password' in the schema, let's use a fresh hash for 'admin123'
        $adminPassCorrect = password_hash('admin123', PASSWORD_DEFAULT);
        
        $adminCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $adminCheck->execute([$adminEmail]);
        $admin = $adminCheck->fetch();

        if (!$admin) {
            $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)")
                ->execute(['Admin', $adminEmail, $adminPassCorrect, 'admin']);
        } else {
            // Update password to ensure it's 'admin123' and name to 'Admin'
            $pdo->prepare("UPDATE users SET full_name = 'Admin', password = ?, role = 'admin' WHERE email = ?")
                ->execute([$adminPassCorrect, $adminEmail]);
        }
    }
    
} catch (PDOException $e) {
    // If it's a CLI request, just echo. If it's a web request, try to be helpful but minimal.
    if (php_sapi_name() === 'cli') {
        die("Database Error: " . $e->getMessage());
    }
    $isVercel = getenv('VERCEL') || getenv('VERCEL_ENV');
    $helpMessage = $isVercel
        ? 'This Vercel deployment needs a hosted MySQL database. Add DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, and optional DB_PORT in Vercel Project Settings, then redeploy.'
        : 'Please ensure your MySQL server is running in XAMPP.';
    ?>
    <div style="padding: 20px; font-family: sans-serif; border: 1px solid #cc0000; background: #fff5f5; color: #cc0000; border-radius: 5px; margin: 20px;">
        <h3 style="margin-top: 0;">Database Connection Error</h3>
        <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
        <p><?php echo htmlspecialchars($helpMessage); ?></p>
    </div>
    <?php
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle "Remember Me" auto-login
if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['delivery_partner_id'])) {
    $token = $_COOKIE['remember_token'];
    
    // Check users table (Admin or Customer)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['role'] === 'admin') {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_role'] = $user['role'];
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
        }
    } else {
        // Check delivery_persons table
        $stmt = $pdo->prepare("SELECT * FROM delivery_persons WHERE remember_token = ?");
        $stmt->execute([$token]);
        $partner = $stmt->fetch();
        
        if ($partner) {
            $_SESSION['delivery_partner_id'] = $partner['id'];
            $_SESSION['delivery_partner_name'] = $partner['name'];
        }
    }
}

require_once __DIR__ . '/../includes/sms_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

// Helper to check if user is admin
function isAdmin() {
    return isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin';
}

// Helper to check if logged in as customer (defined below to avoid redeclaration)

function get_setting($key, $default = null) {
    global $pdo;
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    $cache[$key] = ($val === false) ? $default : $val;
    return $cache[$key];
}

function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

if (!isAdmin()) {
    $maint = (int)(get_setting('maintenance_mode', '0') ?? 0);
    if ($maint === 1) {
        $path = $_SERVER['PHP_SELF'] ?? '';
        if (strpos($path, '/admin/') === false) {
            http_response_code(503);
            ?>
            <!DOCTYPE html>
            <html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Maintenance - Quick mart</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
            <body class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#f8fafc;">
            <div class="text-center p-4">
                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;"><i class="bi bi-tools fs-3"></i></div>
                <h2 class="fw-bold mb-2">We’ll be right back</h2>
                <p class="text-muted">Quick mart is undergoing maintenance. Please check again soon.</p>
            </div>
            </body></html>
            <?php
            exit;
        }
    }
}
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isExpiredDateValue($dateValue) {
    if (empty($dateValue)) {
        return false;
    }

    $today = date('Y-m-d');
    return date('Y-m-d', strtotime((string)$dateValue)) < $today;
}

function getExpiredPurchaseMessage() {
    return 'Expired product - purchase not allowed.';
}

// Add coupon columns to orders table if not exist
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_id INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_order_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL");
} catch (Exception $e) {
    // Columns might already exist or DB doesn't support IF NOT EXISTS in ALTER
}

// Activity Logger
function logActivity($pdo, $action) {
    if (isset($_SESSION['admin_id'])) {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action]);
    }
}

// Helper to get product image with fallback to relevant category image
function getProductImage($image_url, $product_name) {
    if (!empty($image_url)) {
        if (strpos($image_url, 'http') === 0) return $image_url;
        $current_dir = basename(getcwd());
        if ($current_dir === 'admin' && strpos($image_url, 'uploads/') === 0) return '../' . $image_url;
        return $image_url;
    }

    $name = strtolower(trim($product_name));
    $keywords = ['apple', 'banana', 'milk', 'bread', 'egg', 'chicken', 'rice', 'tomato', 'potato', 'onion', 'orange', 'mango', 'butter', 'cheese', 'yogurt', 'pasta', 'flour', 'sugar', 'salt', 'oil', 'coffee', 'tea', 'juice', 'water', 'fish', 'beef', 'mutton', 'carrot', 'cucumber', 'spinach', 'broccoli', 'garlic', 'ginger', 'honey', 'chocolate', 'biscuit', 'chips', 'coke', 'pepsi', 'soda', 'lemon', 'grapes', 'strawberry', 'watermelon', 'pineapple', 'papaya', 'kiwi', 'pepper', 'chili', 'soap', 'shampoo', 'shrimp', 'eggplant', 'cabbage', 'cauliflower', 'peas', 'corn', 'mushroom'];

    foreach ($keywords as $kw) {
        if (strpos($name, $kw) !== false) {
            $db_val = get_setting('fallback_prod_' . $kw);
            if ($db_val) return $db_val;
        }
    }

    return get_setting('fallback_prod_default', 'https://images.unsplash.com/photo-1506617564039-2f3b650ad701?auto=format&fit=crop&w=800&q=80');
}

// Helper to get recipe image with fallback
function getRecipeImage($image_url, $recipe_name) {
    if (!empty($image_url)) {
        if (strpos($image_url, 'http') === 0) return $image_url;
        $current_dir = basename(getcwd());
        if ($current_dir === 'admin' && strpos($image_url, 'uploads/') === 0) return '../' . $image_url;
        return $image_url;
    }

    $name = strtolower(trim($recipe_name));
    $keywords = ['salad', 'pasta', 'soup', 'chicken', 'curry', 'breakfast', 'smoothie', 'dessert', 'sandwich', 'pizza'];

    foreach ($keywords as $kw) {
        if (strpos($name, $kw) !== false) {
            $db_val = get_setting('fallback_recipe_' . $kw);
            if ($db_val) return $db_val;
        }
    }

    return get_setting('fallback_recipe_default', 'https://images.unsplash.com/photo-1495521821757-a1efb6729352?auto=format&fit=crop&w=800&q=80');
}

// Helper to get category image with robust path resolution
function getCategoryImage($image_url, $category_name) {
    $category_name = trim($category_name);
    
    // If it's a full URL, return it as is
    if (!empty($image_url) && preg_match('/^https?:\/\//i', $image_url)) {
        return $image_url;
    }

    // Check if the image_url is a valid path or filename
    if (!empty($image_url) && $image_url !== 'null' && $image_url !== 'undefined' && 
        $image_url !== $category_name && (strpos($image_url, '.') !== false)) {
        
        $path = $image_url;
        // If the path doesn't start with uploads/, it might be just a filename
        if (strpos($path, 'uploads/') === false && strpos($path, '/') === false) {
            $path = 'uploads/categories/' . $path;
        }

        // Adjust path for admin panel
        $current_dir = basename(getcwd());
        if ($current_dir === 'admin') {
            return '../' . $path;
        }
        return $path;
    }

    // Fallback logic
    $name = strtolower($category_name);
    $keywords = ['fruit', 'vegetable', 'dairy', 'bakery', 'meat', 'beverage', 'snack', 'frozen', 'personal', 'household', 'grain', 'spice', 'baby'];

    foreach ($keywords as $kw) {
        if (strpos($name, $kw) !== false) {
            $db_val = get_setting('fallback_cat_' . $kw);
            if ($db_val) return $db_val;
        }
    }

    return get_setting('fallback_cat_default', 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=800&q=80');
}
