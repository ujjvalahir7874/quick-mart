<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'grocery_store';
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (!$host || !$user || $password === false) {
    fwrite(STDERR, "DB_HOST, DB_USER, and DB_PASSWORD are required.\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$categories = [
    'Fruits & Vegetables' => 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?auto=format&fit=crop&w=900&q=80',
    'Dairy & Bakery' => 'https://images.unsplash.com/photo-1550583724-12770d98a633?auto=format&fit=crop&w=900&q=80',
    'Beverages' => 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=900&q=80',
    'Meat & Seafood' => 'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?auto=format&fit=crop&w=900&q=80',
    'Snacks & Sweets' => 'https://images.unsplash.com/photo-1582298538104-fe2e74c27f59?auto=format&fit=crop&w=900&q=80',
    'Grains & Pulses' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=900&q=80',
    'Condiments & Spices' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=900&q=80',
    'Baby Care' => 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4?auto=format&fit=crop&w=900&q=80',
];

$products = [
    ['Fruits & Vegetables', 'Fresh Apples', 'Crisp red apples packed with natural sweetness.', 180, 150, 80, 'https://images.unsplash.com/photo-1560806887-1e4cd0b6bcd6?auto=format&fit=crop&w=900&q=80'],
    ['Fruits & Vegetables', 'Organic Bananas', 'Naturally ripened bananas for everyday energy.', 70, 60, 120, 'https://images.unsplash.com/photo-1571771894821-ad9b5886479b?auto=format&fit=crop&w=900&q=80'],
    ['Fruits & Vegetables', 'Farm Tomatoes', 'Juicy tomatoes for salads and cooking.', 55, 45, 90, 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=900&q=80'],
    ['Fruits & Vegetables', 'Green Broccoli', 'Fresh broccoli florets, rich in nutrients.', 140, 120, 45, 'https://images.unsplash.com/photo-1459411621453-7b03977f4bfc?auto=format&fit=crop&w=900&q=80'],

    ['Dairy & Bakery', 'Fresh Milk', 'Pure full-cream milk for daily use.', 75, 65, 100, 'https://images.unsplash.com/photo-1563636619-e9143da7973b?auto=format&fit=crop&w=900&q=80'],
    ['Dairy & Bakery', 'Whole Wheat Bread', 'Soft bakery bread made with whole wheat.', 50, 42, 70, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=900&q=80'],
    ['Dairy & Bakery', 'Greek Yogurt', 'Thick and creamy probiotic yogurt.', 95, 85, 60, 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=900&q=80'],
    ['Dairy & Bakery', 'Cheddar Cheese', 'Rich cheddar cheese for sandwiches and snacks.', 220, 199, 35, 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?auto=format&fit=crop&w=900&q=80'],

    ['Beverages', 'Orange Juice', 'Refreshing orange juice with bright citrus flavor.', 120, 99, 55, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?auto=format&fit=crop&w=900&q=80'],
    ['Beverages', 'Mineral Water Pack', 'Clean drinking water for home and travel.', 90, 75, 100, 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=900&q=80'],
    ['Beverages', 'Cold Coffee', 'Ready-to-drink chilled coffee.', 110, 95, 65, 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=900&q=80'],

    ['Snacks & Sweets', 'Potato Chips', 'Crispy salted potato chips.', 40, 35, 150, 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=900&q=80'],
    ['Snacks & Sweets', 'Chocolate Cookies', 'Crunchy cookies with chocolate chips.', 85, 75, 80, 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=900&q=80'],
    ['Snacks & Sweets', 'Mixed Dry Fruits', 'Premium nuts and dry fruits mix.', 420, 379, 40, 'https://images.unsplash.com/photo-1599599810694-b5b37304c041?auto=format&fit=crop&w=900&q=80'],

    ['Grains & Pulses', 'Basmati Rice', 'Long grain aromatic basmati rice.', 160, 139, 90, 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=900&q=80'],
    ['Grains & Pulses', 'Red Lentils', 'Protein-rich split red lentils.', 110, 96, 75, 'https://images.unsplash.com/photo-1515543904379-3d757afe72e4?auto=format&fit=crop&w=900&q=80'],

    ['Condiments & Spices', 'Turmeric Powder', 'Pure turmeric powder for everyday cooking.', 65, 55, 85, 'https://images.unsplash.com/photo-1615485500704-8e990f9900f7?auto=format&fit=crop&w=900&q=80'],
    ['Baby Care', 'Baby Wipes', 'Gentle alcohol-free baby wipes.', 180, 159, 50, 'https://images.unsplash.com/photo-1584464491033-06628f3a6b7b?auto=format&fit=crop&w=900&q=80'],
];

$categoryIds = [];
$selectCategory = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
$insertCategory = $pdo->prepare("INSERT INTO categories (name, image_url, status) VALUES (?, ?, 'Enabled')");
$updateCategory = $pdo->prepare("UPDATE categories SET image_url = ?, status = 'Enabled' WHERE id = ?");

foreach ($categories as $name => $image) {
    $selectCategory->execute([$name]);
    $id = $selectCategory->fetchColumn();
    if (!$id) {
        $insertCategory->execute([$name, $image]);
        $id = $pdo->lastInsertId();
    } else {
        $updateCategory->execute([$image, $id]);
    }
    $categoryIds[$name] = $id;
}

$selectProduct = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
$insertProduct = $pdo->prepare("
    INSERT INTO products (category_id, name, description, price, discount_price, stock_quantity, availability_status, status, image_url)
    VALUES (?, ?, ?, ?, ?, ?, 'In Stock', 'Active', ?)
");
$updateProduct = $pdo->prepare("
    UPDATE products
    SET category_id = ?, description = ?, price = ?, discount_price = ?, stock_quantity = ?, availability_status = 'In Stock', status = 'Active', image_url = ?
    WHERE id = ?
");
$variantCount = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ?");
$insertVariant = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price, discount_price, stock_quantity) VALUES (?, ?, ?, ?, ?)");

foreach ($products as [$category, $name, $description, $price, $discount, $stock, $image]) {
    $categoryId = $categoryIds[$category];
    $selectProduct->execute([$name]);
    $productId = $selectProduct->fetchColumn();

    if (!$productId) {
        $insertProduct->execute([$categoryId, $name, $description, $price, $discount, $stock, $image]);
        $productId = $pdo->lastInsertId();
    } else {
        $updateProduct->execute([$categoryId, $description, $price, $discount, $stock, $image, $productId]);
    }

    $variantCount->execute([$productId]);
    if ((int)$variantCount->fetchColumn() === 0) {
        foreach ([['250g', 0.30], ['500g', 0.55], ['1kg', 1.00]] as [$size, $multiplier]) {
            if (stripos($name, 'milk') !== false || stripos($name, 'juice') !== false || stripos($name, 'water') !== false || stripos($name, 'coffee') !== false) {
                $size = $size === '250g' ? '250ml' : ($size === '500g' ? '500ml' : '1L');
            }
            $insertVariant->execute([
                $productId,
                $size,
                round($price * $multiplier, 2),
                $discount ? round($discount * $multiplier, 2) : null,
                $stock,
            ]);
        }
    }
}

echo "Seeded " . count($products) . " products\n";
