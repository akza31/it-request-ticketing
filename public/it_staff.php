<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['SUPERADMIN','IT_HEAD']);

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'save') {
            $name = trim($_POST['name'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if ($name === '') throw new RuntimeException('Nama petugas wajib diisi.');
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE it_staff_members SET name=?,position=?,phone=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $stmt->execute([$name, $position ?: null, $phone ?: null, $id]);
                audit($pdo, (int)current_user()['id'], 'UPDATE_IT_STAFF', 'it_staff', $id, $name);
            } else {
                $stmt = $pdo->prepare('INSERT INTO it_staff_members(name,position,phone) VALUES(?,?,?)');
                $stmt->execute([$name, $position ?: null, $phone ?: null]);
                $id = (int)$pdo->lastInsertId();
                audit($pdo, (int)current_user()['id'], 'CREATE_IT_STAFF', 'it_staff', $id, $name);
            }
            $success = 'Data petugas IT berhasil disimpan.';
        } elseif ($action === 'toggle') {
            $stmt = $pdo->prepare('UPDATE it_staff_members SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->execute([$id]);
            audit($pdo, (int)current_user()['id'], 'TOGGLE_IT_STAFF', 'it_staff', $id, 'Ubah status petugas');
            $success = 'Status petugas berhasil diperbarui.';
        }
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Data petugas gagal disimpan.' : $e->getMessage();
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM it_staff_members WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch() ?: null;
}
$staff = $pdo->query('SELECT * FROM it_staff_members ORDER BY is_active DESC,name')->fetchAll();
page_header('Petugas IT');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Daftar Petugas IT</h3><div class="text-muted">Kelola direktori petugas yang dapat ditugaskan ke tiket.</div></div><a class="btn btn-outline-secondary" href="tickets.php">Kembali ke tiket</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-4"><div class="card"><div class="card-header fw-semibold"><?= $edit ? 'Edit petugas' : 'Tambah petugas' ?></div><div class="card-body"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><div class="mb-3"><label class="form-label">Nama petugas *</label><input class="form-control" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">Jabatan / keahlian</label><input class="form-control" name="position" value="<?= e($edit['position'] ?? '') ?>"></div><div class="mb-3"><label class="form-label">Nomor telepon</label><input class="form-control" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div><button class="btn btn-primary">Simpan Petugas</button><?php if ($edit): ?> <a class="btn btn-light" href="it_staff.php">Batal</a><?php endif; ?></form></div></div></div>
<div class="col-lg-8"><div class="card"><div class="card-header fw-semibold">Petugas terdaftar</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nama</th><th>Jabatan / keahlian</th><th>Telepon</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($staff as $member): ?><tr><td><?= e($member['name']) ?></td><td><?= e($member['position'] ?: '—') ?></td><td><?= e($member['phone'] ?: '—') ?></td><td><span class="badge text-bg-<?= $member['is_active'] ? 'success' : 'secondary' ?>"><?= $member['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="it_staff.php?edit=<?= (int)$member['id'] ?>">Edit</a> <form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$member['id'] ?>"><button class="btn btn-sm btn-outline-warning"><?= $member['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></td></tr><?php endforeach; ?>
<?php if (!$staff): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada petugas IT. Tambahkan petugas melalui formulir ini.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div>
<?php page_footer(); ?>