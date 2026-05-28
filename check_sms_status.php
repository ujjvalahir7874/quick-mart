<?php
require_once 'config/db.php';
$settings = $pdo->query("SELECT * FROM app_settings WHERE setting_key LIKE 'sms%'")->fetchAll();
echo "SMS Settings:\n";
print_r($settings);

if (file_exists('sms_log.txt')) {
    echo "\nSMS Log Content:\n";
    echo file_get_contents('sms_log.txt');
} else {
    echo "\nsms_log.txt does not exist.\n";
}
?>
