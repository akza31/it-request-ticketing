<?php
declare(strict_types=1);
$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}
$configFile = __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Konfigurasi belum dibuat. Salin config.example.php menjadi config.php.');
}
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('it_request_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$dbPath = $config['db']['path'] ?? (dirname(__DIR__) . '/storage/database/it_request_ticketing.sqlite');
$dbDir = dirname($dbPath);
if (!is_dir($dbDir) && !mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
    throw new RuntimeException('Folder database tidak dapat dibuat: ' . $dbDir);
}
if (!is_file($dbPath)) {
    throw new RuntimeException('File SQLite tidak ditemukan: ' . $dbPath . '. Jalankan scripts/init_db.php.');
}
$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA busy_timeout = 5000');
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA synchronous = NORMAL');
require_once __DIR__ . '/helpers.php';
