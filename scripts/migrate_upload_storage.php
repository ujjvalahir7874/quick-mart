<?php
require_once __DIR__ . '/../config/db.php';

$alterStatements = [
    'users.profile_photo' => "ALTER TABLE users MODIFY profile_photo LONGTEXT NULL",
    'categories.image_url' => "ALTER TABLE categories MODIFY image_url LONGTEXT NULL",
    'products.image_url' => "ALTER TABLE products MODIFY image_url LONGTEXT NULL",
    'recipes.image_url' => "ALTER TABLE recipes MODIFY image_url LONGTEXT NULL",
    'offers.image_url' => "ALTER TABLE offers MODIFY image_url LONGTEXT NOT NULL",
    'offers.bg_img_url' => "ALTER TABLE offers MODIFY bg_img_url LONGTEXT NULL",
    'app_settings.setting_value' => "ALTER TABLE app_settings MODIFY setting_value LONGTEXT NULL",
    'delivery_persons.doc_aadhaar' => "ALTER TABLE delivery_persons MODIFY doc_aadhaar LONGTEXT NULL",
    'delivery_persons.doc_license' => "ALTER TABLE delivery_persons MODIFY doc_license LONGTEXT NULL",
    'delivery_persons.doc_rc' => "ALTER TABLE delivery_persons MODIFY doc_rc LONGTEXT NULL",
    'delivery_persons.doc_photo' => "ALTER TABLE delivery_persons MODIFY doc_photo LONGTEXT NULL",
];

foreach ($alterStatements as $label => $sql) {
    try {
        $pdo->exec($sql);
        echo "[OK] $label\n";
    } catch (Throwable $e) {
        echo "[SKIP] $label - " . $e->getMessage() . "\n";
    }
}

echo "Upload storage migration finished.\n";
