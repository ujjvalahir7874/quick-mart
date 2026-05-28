<?php
// Get the directory path to ensure we redirect to the correct location
$dir = dirname($_SERVER['PHP_SELF']);
// If the path ends in index.php (like in index.php/dashboard.php), clean it up
$clean_dir = preg_replace('/index\.php.*$/', '', $dir);
header("Location: " . $clean_dir . "dashboard.php");
exit;
?>
