-- Quick mart Complete Database Schema
-- Last Updated: 2026-04-18

CREATE DATABASE IF NOT EXISTS grocery_store;
USE grocery_store;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20),
    address TEXT,
    address_house_no VARCHAR(120) DEFAULT NULL,
    address_society_name VARCHAR(180) DEFAULT NULL,
    address_area_name VARCHAR(180) DEFAULT NULL,
    address_road_name VARCHAR(180) DEFAULT NULL,
    address_landmark VARCHAR(180) DEFAULT NULL,
    address_city VARCHAR(120) DEFAULT NULL,
    address_state VARCHAR(120) DEFAULT NULL,
    address_pincode VARCHAR(20) DEFAULT NULL,
    address_country VARCHAR(120) DEFAULT NULL,
    user_latitude DECIMAL(10, 8) DEFAULT NULL,
    user_longitude DECIMAL(11, 8) DEFAULT NULL,
    location_accuracy DECIMAL(8, 2) DEFAULT NULL,
    location_source VARCHAR(30) DEFAULT NULL,
    location_updated_at DATETIME DEFAULT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    profile_photo VARCHAR(255) NULL,
    wallet_balance DECIMAL(10, 2) DEFAULT 0.00,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    parent_id INT DEFAULT NULL,
    status ENUM('Enabled', 'Disabled') DEFAULT 'Enabled',
    image_url VARCHAR(255),
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- 3. Products Table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2) DEFAULT NULL,
    tax_percentage DECIMAL(5, 2) DEFAULT 0.00,
    stock_quantity INT DEFAULT 0,
    availability_status ENUM('In Stock', 'Out of Stock') DEFAULT 'In Stock',
    status ENUM('Active', 'Archived') DEFAULT 'Active',
    is_exclusive TINYINT(1) DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    image_url VARCHAR(255),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 4. Product Variants Table
CREATE TABLE IF NOT EXISTS product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_name VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    discount_price DECIMAL(10, 2) DEFAULT NULL,
    stock_quantity INT DEFAULT 0,
    expiry_date DATE DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 5. Delivery Persons Table
CREATE TABLE IF NOT EXISTS delivery_persons (
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
    doc_aadhaar VARCHAR(255) NULL,
    doc_license VARCHAR(255) NULL,
    doc_rc VARCHAR(255) NULL,
    doc_photo VARCHAR(255) NULL,
    is_suspended TINYINT(1) DEFAULT 0,
    suspension_reason TEXT NULL,
    rejected_docs VARCHAR(255) NULL,
    rejection_reason_aadhaar TEXT NULL,
    rejection_reason_license TEXT NULL,
    rejection_reason_rc TEXT NULL,
    rejection_reason_photo TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Coupons Table
CREATE TABLE IF NOT EXISTS coupons (
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
);

-- 7. Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    delivery_person_id INT DEFAULT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) DEFAULT 'Pending',
    payment_method VARCHAR(50) DEFAULT 'Cash on Delivery',
    shipping_address TEXT,
    contact_number VARCHAR(20),
    delivery_otp VARCHAR(6) DEFAULT NULL,
    pickup_notification_sent TINYINT(1) DEFAULT 0,
    coupon_id INT DEFAULT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE SET NULL,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
);

-- 8. Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    variant_id INT DEFAULT 0,
    quantity INT NOT NULL,
    price_at_time DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- 9. Wallets Table
CREATE TABLE IF NOT EXISTS wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 10. Wallet Transactions Table
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    type ENUM('Credit', 'Debit') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    order_id INT DEFAULT NULL,
    status ENUM('Completed', 'Pending', 'Failed') DEFAULT 'Completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE
);

-- 11. Scratch Cards Table
CREATE TABLE IF NOT EXISTS scratch_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    order_id INT DEFAULT NULL,
    is_scratched TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 12. Offers Table
CREATE TABLE IF NOT EXISTS offers (
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
);

-- 13. App Settings Table
CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 14. Order Messages (Chat) Table
CREATE TABLE IF NOT EXISTS order_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    sender_type ENUM('customer', 'delivery') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- 15. Call Bridge Requests Table
CREATE TABLE IF NOT EXISTS call_bridge_requests (
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
);

-- 15. Order Feedback Table
CREATE TABLE IF NOT EXISTS order_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    service_rating INT NOT NULL DEFAULT 5 CHECK (service_rating >= 1 AND service_rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (order_id)
);

-- 16. Wishlist Table
CREATE TABLE IF NOT EXISTS wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, product_id)
);

-- 17. Product Reviews Table
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 18. Recipes Table
CREATE TABLE IF NOT EXISTS recipes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    instructions TEXT,
    image_url VARCHAR(255),
    prep_time VARCHAR(20),
    servings INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 19. Recipe Ingredients Table
CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_text VARCHAR(50),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 20. Contact Messages Table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    delivery_person_id INT NULL,
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
    FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES contact_messages(id) ON DELETE CASCADE
);

-- 21. Delivery Earnings Table
CREATE TABLE IF NOT EXISTS delivery_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_person_id INT NOT NULL,
    order_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('Credit', 'Debit') NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
);

-- 22. Delivery Location Logs Table
CREATE TABLE IF NOT EXISTS delivery_location_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_person_id INT NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_person_id) REFERENCES delivery_persons(id) ON DELETE CASCADE
);

-- 23. Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- SEED DATA
-- Default Admin (Password: admin123)
INSERT INTO users (full_name, email, password, role) VALUES 
('Admin', 'admin@grocery.com', '$2y$10$tZ/n07fFq3X.Gz/u6v0Lbe/v4N7m5N7hW4P0Z8R1Wv.9i8y7y6x54', 'admin')
ON DUPLICATE KEY UPDATE full_name='Admin', role='admin';

-- Sample Categories
INSERT INTO categories (name, image_url) VALUES 
('Fruits & Vegetables', 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=800&q=80'),
('Dairy & Bakery', 'https://images.unsplash.com/photo-1550583724-12770d98a633?auto=format&fit=crop&w=800&q=80'),
('Beverages', 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=800&q=80'),
('Meat & Seafood', 'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?auto=format&fit=crop&w=800&q=80'),
('Snacks & Sweets', 'https://images.unsplash.com/photo-1582298538104-fe2e74c27f59?auto=format&fit=crop&w=800&q=80');

-- Default App Settings
INSERT INTO app_settings (setting_key, setting_value) VALUES 
('maintenance_mode', '0'),
('allow_registrations', '1'),
('masked_call_enabled', '1'),
('masked_call_relay_label', 'Quick mart Secure Call Desk'),
('masked_call_relay_number', '1800123456'),
('founder_name', 'Founding Team'),
('ceo_name', 'Leadership Team')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
