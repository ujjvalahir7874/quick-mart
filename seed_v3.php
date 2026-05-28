<?php
// require_once 'config/db.php'; // This might fail in CLI if drivers aren't in path

$host = 'localhost';
$dbname = 'grocery_store';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$new_categories = [
    ['name' => 'Grains & Pulses', 'parent_id' => null, 'status' => 'Enabled'],
    ['name' => 'Condiments & Spices', 'parent_id' => null, 'status' => 'Enabled'],
    ['name' => 'Baby Care', 'parent_id' => null, 'status' => 'Enabled']
];

$products_data = [
    'Grains & Pulses' => [
        ['name' => 'Basmati Rice', 'description' => 'Long-grain aromatic rice.', 'price' => 120.00, 'stock' => 50],
        ['name' => 'Red Lentils', 'description' => 'High-protein split red lentils.', 'price' => 90.00, 'stock' => 40],
        ['name' => 'Chickpeas', 'description' => 'Organic dried chickpeas.', 'price' => 85.00, 'stock' => 30]
    ],
    'Condiments & Spices' => [
        ['name' => 'Turmeric Powder', 'description' => 'Pure organic turmeric powder.', 'price' => 45.00, 'stock' => 100],
        ['name' => 'Black Pepper', 'description' => 'Whole black peppercorns.', 'price' => 60.00, 'stock' => 80],
        ['name' => 'Tomato Ketchup', 'description' => 'Classic tangy tomato ketchup.', 'price' => 55.00, 'stock' => 60]
    ],
    'Baby Care' => [
        ['name' => 'Baby Wipes', 'description' => 'Gentle alcohol-free baby wipes.', 'price' => 150.00, 'stock' => 40],
        ['name' => 'Baby Shampoo', 'description' => 'No-tears gentle baby shampoo.', 'price' => 200.00, 'stock' => 30],
        ['name' => 'Diapers Pack', 'description' => 'Soft and absorbent diapers.', 'price' => 450.00, 'stock' => 25]
    ]
];

foreach ($new_categories as $cat) {
    // Check if category exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$cat['name']]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, status) VALUES (?, ?, ?)");
        $stmt->execute([$cat['name'], $cat['parent_id'], $cat['status']]);
        $cat_id = $pdo->lastInsertId();
        echo "Created category: " . $cat['name'] . "<br>";
    } else {
        $cat_id = $existing['id'];
        echo "Category already exists: " . $cat['name'] . "<br>";
    }
    
    // Add products for this category
    if (isset($products_data[$cat['name']])) {
        foreach ($products_data[$cat['name']] as $p) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $stmt->execute([$p['name']]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO products (category_id, name, description, price, stock_quantity) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$cat_id, $p['name'], $p['description'], $p['price'], $p['stock']]);
                echo "Added product: " . $p['name'] . "<br>";
            }
        }
    }
}
echo "Done!";
