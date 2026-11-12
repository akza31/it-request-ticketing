<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['SUPERADMIN']);
$error = '';
$success = '';
$roles = [
    'UNIT' => 'Unit Pemohon',
    'APPROVER' => 'Approver Direktorat',
    'IT_HEAD' => 'Kepala TI',
    'IT_STAFF' => 'Staf TI',
    'SUPERADMIN' => 'Superadmin',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? '';
            $departmentId = (int) ($_POST['department_id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if ($name === '' || $username === '' || !isset($roles[$role]))
                throw new RuntimeException('Nama, username, dan role wajib diisi.');
            if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username))
                throw new RuntimeException(
                    'Username 3-50 karakter; gunakan huruf, angka, titik, garis bawah, atau strip.',
                );
            if (in_array($role, ['UNIT', 'APPROVER'], true) && $departmentId < 1)
                throw new RuntimeException('Akun UNIT dan APPROVER wajib terhubung ke unit/direktorat.');
            if ($departmentId > 0) {
                $q = $pdo->prepare('SELECT id FROM departments WHERE id=? AND is_active=1');
                $q->execute([$departmentId]);
                if (!$q->fetchColumn())
                    throw new RuntimeException('Unit tidak ditemukan atau tidak aktif.');
            } else
                $departmentId = null;
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new RuntimeException('Format email tidak valid.');
            if ($id > 0) {
                $q = $pdo->prepare('SELECT id FROM users WHERE id=?');
                $q->execute([$id]);
                if (!$q->fetchColumn())
                    throw new RuntimeException('Akun tidak ditemukan.');
                if ($id === (int) current_user()['id'] && $role !== 'SUPERADMIN')
                    throw new RuntimeException('Role akun yang sedang digunakan tidak boleh diturunkan.');
                if ($password !== '') {
                    if (strlen($password) < 12)
                        throw new RuntimeException('Password minimal 12 karakter.');
                    $st = $pdo->prepare(
                        'UPDATE users SET name=?,username=?,email=?,role=?,department_id=?,password=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',
                    );
                    $st->execute([
                        $name,
                        $username,
                        $email ?: null,
                        $role,
                        $departmentId,
                        password_hash($password, PASSWORD_DEFAULT),
                        $id,
                    ]);
                } else {
                    $st = $pdo->prepare(
                        'UPDATE users SET name=?,username=?,email=?,role=?,department_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',
                    );
                    $st->execute([$name, $username, $email ?: null, $role, $departmentId, $id]);
                }
                audit($pdo, (int) current_user()['id'], 'UPDATE_USER', 'user', $id, 'Memperbarui akun ' . $username);
            } else {
                if (strlen($password) < 12)
                    throw new RuntimeException('Password awal minimal 12 karakter.');
                $st = $pdo->prepare(
                    'INSERT INTO users(department_id,name,username,email,password,role) VALUES(?,?,?,?,?,?)',
                );
                $st->execute([
                    $departmentId,
                    $name,
                    $username,
                    $email ?: null,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                ]);
                $id = (int) $pdo->lastInsertId();
                audit($pdo, (int) current_user()['id'], 'CREATE_USER', 'user', $id, 'Membuat akun ' . $username);
            }
            $success = 'Akun berhasil disimpan.';
        } elseif ($action === 'toggle') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) current_user()['id'])
                throw new RuntimeException('Akun yang sedang digunakan tidak dapat dinonaktifkan.');
            $st = $pdo->prepare(
                'UPDATE users SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END,updated_at=CURRENT_TIMESTAMP WHERE id=?',
            );
            $st->execute([$id]);
            audit($pdo, (int) current_user()['id'], 'TOGGLE_USER', 'user', $id, 'Mengubah status aktif akun');
            $success = 'Status akun diperbarui.';
        }
    } catch (Throwable $ex) {
        $error = $ex instanceof PDOException && str_contains($ex->getMessage(), 'UNIQUE')
            ? 'Username sudah digunakan.'
            : $ex->getMessage();
    }
}
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$departments = $pdo->query(
    'SELECT d.id,d.name department_name,di.name directorate_name FROM departments d JOIN directorates di ON di.id=d.directorate_id WHERE d.is_active=1 AND di.is_active=1 ORDER BY di.name,d.name',
)->fetchAll();
$users = $pdo->query(
    'SELECT u.*,d.name department_name,di.name directorate_name FROM users u LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN directorates di ON di.id=d.directorate_id ORDER BY u.created_at DESC,u.id DESC',
)->fetchAll();
page_header('Manajemen Pengguna');
echo
    '<div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Manajemen Pengguna</h3><div class="text-muted">Buat akun unit, approver, dan tim TI.</div></div><a class="btn btn-outline-secondary" href="index.php">Kembali</a></div>'
;
if ($error)
    echo '<div class="alert alert-danger">' . e($error) . '</div>';
if ($success)
    echo '<div class="alert alert-success">' . e($success) . '</div>';
echo
    '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white fw-semibold">'
        . ($edit ? 'Edit Akun' : 'Tambah Akun')
        . '</div><div class="card-body"><form method="post" class="row g-3"><input type="hidden" name="csrf" value="'
        . e(csrf_token())
        . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'
        . (int) ($edit['id'] ?? 0)
        . '">'
;
echo
    '<div class="col-md-4"><label class="form-label">Nama lengkap</label><input class="form-control" name="name" required value="'
        . e($edit['name'] ?? '')
        . '"></div><div class="col-md-4"><label class="form-label">Username</label><input class="form-control" name="username" required value="'
        . e($edit['username'] ?? '')
        . '"></div><div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="'
        . e($edit['email'] ?? '')
        . '"></div>'
;
echo '<div class="col-md-4"><label class="form-label">Role</label><select class="form-select" name="role" required>';
foreach ($roles as $k => $v)
    echo
        '<option value="'
            . e($k)
            . '" '
            . (($edit['role'] ?? 'UNIT') === $k ? 'selected' : '')
            . '>'
            . e($v)
            . '</option>'
    ;
echo
    '</select></div><div class="col-md-4"><label class="form-label">Unit / Direktorat</label><select class="form-select" name="department_id"><option value="0">— Tidak terikat unit (Tim TI) —</option>'
;
foreach ($departments as $d)
    echo
        '<option value="'
            . (int) $d['id']
            . '" '
            . ((int) ($edit['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '')
            . '>'
            . e($d['directorate_name'] . ' / ' . $d['department_name'])
            . '</option>'
    ;
echo
    '</select><div class="form-text">Wajib untuk role UNIT dan APPROVER. Approver mengikuti direktorat dari unit yang dipilih.</div></div><div class="col-md-4"><label class="form-label">Password '
        . ($edit ? '(kosongkan jika tidak diubah)' : 'awal')
        . '</label><input class="form-control" type="password" name="password" '
        . ($edit ? '' : 'required')
        . ' minlength="12" autocomplete="new-password"><div class="form-text">Minimal 12 karakter.</div></div><div class="col-12"><button class="btn btn-primary">Simpan Akun</button> '
        . ($edit ? '<a class="btn btn-light" href="users.php">Batal Edit</a>' : '')
        . '</div></form></div></div>'
;
echo
    '<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Daftar Akun</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nama / Username</th><th>Role</th><th>Unit / Direktorat</th><th>Status</th><th>Aksi</th></tr></thead><tbody>'
;
foreach ($users as $u) {
    echo
        '<tr><td><strong>'
            . e($u['name'])
            . '</strong><div class="small text-muted">'
            . e($u['username'])
            . ' · '
            . e($u['email'] ?? '')
            . '</div></td><td>'
            . e($roles[$u['role']] ?? $u['role'])
            . '</td><td>'
            . e(($u['directorate_name'] ?? '—') . ' / ' . ($u['department_name'] ?? '—'))
            . '</td><td>'
            . (
                $u['is_active']
                    ? '<span class="badge text-bg-success">Aktif</span>'
                    : '<span class="badge text-bg-secondary">Nonaktif</span>'
            )
            . '</td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="users.php?edit='
            . (int) $u['id']
            . '">Edit</a> <form method="post" class="d-inline" onsubmit="return confirm(\'Ubah status akun ini?\')"><input type="hidden" name="csrf" value="'
            . e(csrf_token())
            . '"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="'
            . (int) $u['id']
            . '"><button class="btn btn-sm btn-outline-warning" '
            . ((int) $u['id'] === (int) current_user()['id'] ? 'disabled' : '')
            . '>'
            . ($u['is_active'] ? 'Nonaktifkan' : 'Aktifkan')
            . '</button></form></td></tr>'
    ;
}
if (!$users)
    echo '<tr><td colspan="5" class="text-center text-muted p-4">Belum ada akun.</td></tr>';
echo '</tbody></table></div></div>';
page_footer();
