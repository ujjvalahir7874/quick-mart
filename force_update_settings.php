<?php
require_once 'config/db.php';

$updates = [
    'email_enabled' => '1',
    'email_provider' => 'smtp',
    'email_from_name' => 'Quick mart',
    'email_from_address' => 'ujjvalahir7874@gmail.com',
    'email_smtp_host' => 'smtp.gmail.com',
    'email_smtp_port' => '587',
    'email_smtp_user' => 'ujjvalahir7874@gmail.com'
];

foreach ($updates as $key => $val) {
    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$val, $key]);
    
    // If update affected 0 rows, it might not exist yet
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $val]);
    }
}

echo "Settings updated successfully to ujjvalahir7874@gmail.com\n";
?>
