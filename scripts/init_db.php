<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Script hanya boleh dijalankan melalui CLI.\n");
$configFile = dirname(__DIR__) . '/app/config/config.php';
if (!is_file($configFile)) {
    if (!copy(dirname(__DIR__) . '/app/config/config.example.php', $configFile)) exit("Gagal membuat config.php.\n");
}
$config = require $configFile;
$dbPath = $config['db']['path'] ?? (dirname(__DIR__) . '/storage/database/it_request_ticketing.sqlite');
$dir = dirname($dbPath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) exit("Folder database tidak dapat dibuat.\n");
if (is_file($dbPath) && filesize($dbPath) > 0) exit("Database sudah ada dan tidak ditimpa: {$dbPath}\n");
$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
echo "Database SQLite berhasil dibuat: {$dbPath}\n";
