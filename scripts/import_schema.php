<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'railway';
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (!$host || !$user || $password === false) {
    fwrite(STDERR, "DB_HOST, DB_USER, and DB_PASSWORD are required.\n");
    exit(1);
}

$schemaPath = __DIR__ . '/../database/schema.sql';
$sql = file_get_contents($schemaPath);
if ($sql === false) {
    fwrite(STDERR, "Unable to read schema: {$schemaPath}\n");
    exit(1);
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec($sql);
echo "Schema imported\n";
