<?php
require_once 'config/db.php';
$settings = $pdo->query("SELECT * FROM app_settings WHERE setting_key LIKE 'email%'")->fetchAll(PDO::FETCH_ASSOC);
echo "Email Settings:\n";
print_r($settings);

if (file_exists('email_sim_messages.txt')) {
    echo "\nEmail Simulation Content:\n";
    echo file_get_contents('email_sim_messages.txt');
} else {
    echo "\nemail_sim_messages.txt does not exist.\n";
}
?>
