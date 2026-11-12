<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script hanya boleh dijalankan melalui CLI.\n");
}

$configFile = dirname(__DIR__) . '/app/config/config.php';
if (!is_file($configFile))
    exit("Konfigurasi belum tersedia.\n");
$config = require $configFile;
$dbPath = $config['db']['path'] ?? dirname(__DIR__) . '/storage/database/it_request_ticketing.sqlite';
if (!is_file($dbPath))
    exit("Database tidak ditemukan: {$dbPath}\n");

$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->beginTransaction();
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS it_staff_members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        position TEXT NULL,
        phone TEXT NULL,
        is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN(0,1)),
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_it_staff_active ON it_staff_members(is_active,name)');
    $columns = $pdo->query('PRAGMA table_info(ticket_assignments)')->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns)
        throw new RuntimeException('Tabel ticket_assignments tidak ditemukan. Pastikan database sudah diinisialisasi.');
    if (!in_array('staff_member_id', array_column($columns, 'name'), true)) {
        $pdo->exec(
            'ALTER TABLE ticket_assignments ADD COLUMN staff_member_id INTEGER NULL REFERENCES it_staff_members(id) ON DELETE SET NULL',
        );
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_assignment_member ON ticket_assignments(staff_member_id,is_active)');
    $pdo->commit();
    echo "Migrasi alur tiket berhasil. Data yang sudah ada tetap dipertahankan.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    fwrite(STDERR, 'Migrasi gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
