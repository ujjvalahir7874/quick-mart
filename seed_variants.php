<?php
require_once 'config/db.php';

$products = $pdo->query("SELECT id, name, price, discount_price, stock_quantity FROM products")->fetchAll();

function getVariantTemplate(string $productName): array {
    $name = strtolower($productName);

    $volumeKeywords = ['milk', 'juice', 'drink', 'water', 'oil', 'shampoo', 'ketchup', 'syrup'];
    foreach ($volumeKeywords as $keyword) {
        if (strpos($name, $keyword) !== false) {
            return [
                ['size' => '250ml', 'multiplier' => 0.31],
                ['size' => '500ml', 'multiplier' => 0.55],
                ['size' => '1L', 'multiplier' => 1.00],
            ];
        }
    }

    $countKeywords = ['egg', 'eggs', 'diaper', 'diapers', 'wipe', 'wipes', 'toothbrush', 'battery', 'bulb', 'soap bar', 'mask'];
    foreach ($countKeywords as $keyword) {
        if (strpos($name, $keyword) !== false) {
            return [
                ['size' => '1 pc', 'multiplier' => 0.12],
                ['size' => '6 pcs', 'multiplier' => 0.58],
                ['size' => '12 pcs', 'multiplier' => 1.00],
            ];
        }
    }

    return [
        ['size' => '250g', 'multiplier' => 0.30],
        ['size' => '500g', 'multiplier' => 0.55],
        ['size' => '1kg', 'multiplier' => 1.00],
    ];
}

foreach ($products as $p) {
    // Check if variants already exist for this product
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_variants WHERE product_id = ?");
    $stmt->execute([$p['id']]);
    if ($stmt->fetchColumn() == 0) {
        $variants = [];
        foreach (getVariantTemplate($p['name']) as $template) {
            $variants[] = [
                'size' => $template['size'],
                'price' => round($p['price'] * $template['multiplier'], 2),
                'discount' => $p['discount_price'] ? round($p['discount_price'] * $template['multiplier'], 2) : null,
            ];
        }

        $stmt = $pdo->prepare("INSERT INTO product_variants (product_id, size_name, price, discount_price, stock_quantity) VALUES (?, ?, ?, ?, ?)");
        foreach ($variants as $v) {
            $stmt->execute([$p['id'], $v['size'], $v['price'], $v['discount'], $p['stock_quantity']]);
        }
        echo "Added variants for product: {$p['name']}\n";
    }
}
echo "Seeding completed!";
?>
