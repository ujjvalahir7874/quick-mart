<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=grocery_store', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Clear image_url for categories that are currently broken or have weird values
    $stmt = $pdo->prepare("UPDATE categories SET image_url = '' WHERE name IN ('Vegetables', 'Snacks & Sweets')");
    $stmt->execute();
    
    echo "Categories updated successfully.\n";
    
    $stmt = $pdo->query("SELECT name, image_url FROM categories");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Category: {$row['name']}, Image URL: '{$row['image_url']}'\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
