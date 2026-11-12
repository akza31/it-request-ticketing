<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit("Script hanya boleh dijalankan melalui CLI.\n");
if ($argc < 4) exit("Usage: php scripts/create_admin.php username \"Nama Admin\" password\n");
[, $username, $name, $password] = $argv;
$username = trim($username);
$name = trim($name);
if ($username === '' || $name === '') exit("Username dan nama wajib diisi.\n");
if (strlen($password) < 8) exit("Password minimal 12 karakter.\n");
require_once dirname(__DIR__) . '/app/bootstrap.php';
try {
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $check->execute([$username]);
    if ($check->fetch()) exit("Username sudah digunakan.\n");
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users(department_id,name,username,password,role,is_active) VALUES(NULL,?,?,?,'SUPERADMIN',1)");
    $stmt->execute([$name, $username, $hash]);
    echo "Administrator berhasil dibuat.\nUsername: {$username}\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Gagal membuat administrator: " . $e->getMessage() . "\n");
    exit(1);
}
