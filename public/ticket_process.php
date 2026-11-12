<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['SUPERADMIN','IT_HEAD','IT_STAFF']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT t.*,r.id request_id,r.request_no,r.program_name,r.background,r.objectives,r.requested_solution,r.urgency,d.name department_name FROM it_tickets t JOIN program_requests r ON r.id=t.request_id JOIN departments d ON d.id=r.department_id WHERE t.id=?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();
if (!$ticket) { http_response_code(404); exit('Tiket tidak ditemukan.'); }

$error = '';
$success = '';
$allowedStatuses = ['IT_REVIEW','PLANNED','IN_PROGRESS','TESTING','COMPLETED'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'assign') {
            $staffId = (int)($_POST['staff_member_id'] ?? 0);
            $check = $pdo->prepare('SELECT id FROM it_staff_members WHERE id=? AND is_active=1');
            $check->execute([$staffId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Pilih petugas IT yang aktif.');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE ticket_assignments SET is_active=0 WHERE ticket_id=? AND is_active=1')->execute([$id]);
            $pdo->prepare('INSERT INTO ticket_assignments(ticket_id,staff_id,assigned_by,staff_member_id) VALUES(?,?,?,?)')->execute([$id,current_user()['id'],current_user()['id'],$staffId]);
            $pdo->prepare("UPDATE it_tickets SET assigned_at=COALESCE(assigned_at,CURRENT_TIMESTAMP) WHERE id=?")->execute([$id]);
            $staffName = $pdo->prepare('SELECT name FROM it_staff_members WHERE id=?');
            $staffName->execute([$staffId]);
            $description = 'Petugas ditugaskan: '.$staffName->fetchColumn();
            $pdo->prepare("INSERT INTO ticket_history(ticket_id,user_id,old_status,new_status,activity,description) VALUES(?,?,?,?,'ASSIGNED',?)")->execute([$id,current_user()['id'],$ticket['status'],$ticket['status'],$description]);
            audit($pdo,(int)current_user()['id'],'TICKET_ASSIGNED','ticket',$id,$description);
            $pdo->commit();
            $success = 'Petugas berhasil ditugaskan.';
        } elseif ($action === 'status') {
            $newStatus = $_POST['status'] ?? '';
            $resolution = trim($_POST['resolution'] ?? '');
            if (!in_array($newStatus,$allowedStatuses,true)) throw new RuntimeException('Status tiket tidak valid.');
            if ($newStatus === 'COMPLETED' && $resolution === '') throw new RuntimeException('Isi hasil pekerjaan sebelum menyelesaikan tiket.');
            $active = $pdo->prepare('SELECT COUNT(*) FROM ticket_assignments WHERE ticket_id=? AND is_active=1 AND staff_member_id IS NOT NULL');
            $active->execute([$id]);
            if ((int)$active->fetchColumn() === 0 && in_array($newStatus,['IN_PROGRESS','TESTING','COMPLETED'],true)) throw new RuntimeException('Tugaskan petugas IT sebelum memproses tiket.');
            $oldStatus = $ticket['status'];
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE it_tickets SET status=?,started_at=CASE WHEN ? IN ('IN_PROGRESS','TESTING') THEN COALESCE(started_at,CURRENT_TIMESTAMP) ELSE started_at END,completed_at=CASE WHEN ?='COMPLETED' THEN CURRENT_TIMESTAMP ELSE completed_at END,resolution=CASE WHEN ?='COMPLETED' THEN ? ELSE resolution END WHERE id=?")->execute([$newStatus,$newStatus,$newStatus,$newStatus,$resolution,$id]);
            if ($newStatus === 'COMPLETED') $pdo->prepare("UPDATE program_requests SET status='COMPLETED',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$ticket['request_id']]);
            elseif ($newStatus === 'IN_PROGRESS' || $newStatus === 'TESTING' || $newStatus === 'PLANNED') $pdo->prepare('UPDATE program_requests SET status=? WHERE id=?')->execute([$newStatus,$ticket['request_id']]);
            $pdo->prepare('INSERT INTO ticket_history(ticket_id,user_id,old_status,new_status,activity,description) VALUES(?,?,?,?,?,?)')->execute([$id,current_user()['id'],$oldStatus,$newStatus,$newStatus==='COMPLETED'?'COMPLETED':'STATUS_CHANGED',$newStatus==='COMPLETED'?$resolution:trim($_POST['notes']??'')]);
            audit($pdo,(int)current_user()['id'],'TICKET_'.$newStatus,'ticket',$id,$newStatus==='COMPLETED'?$resolution:trim($_POST['notes']??''));
            $pdo->commit();
            $success = $newStatus === 'COMPLETED' ? 'Tiket berhasil diselesaikan.' : 'Status tiket berhasil diperbarui.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof PDOException ? 'Perubahan tiket gagal disimpan.' : $e->getMessage();
    }
    $stmt->execute([$id]);
    $ticket = $stmt->fetch();
}

$staff = $pdo->query('SELECT id,name,position FROM it_staff_members WHERE is_active=1 ORDER BY name')->fetchAll();
$assigned = $pdo->prepare('SELECT sm.name,sm.position,ta.assigned_at FROM ticket_assignments ta JOIN it_staff_members sm ON sm.id=ta.staff_member_id WHERE ta.ticket_id=? AND ta.is_active=1 ORDER BY ta.assigned_at DESC');
$assigned->execute([$id]);
$assignedStaff = $assigned->fetchAll();
$history = $pdo->prepare('SELECT h.*,u.name actor_name FROM ticket_history h JOIN users u ON u.id=h.user_id WHERE h.ticket_id=? ORDER BY h.created_at DESC LIMIT 20');
$history->execute([$id]);
$events = $history->fetchAll();

page_header('Proses Tiket');
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="mb-1">Proses Tiket</h3><div class="text-muted"><?= e($ticket['ticket_no']) ?> · <?= e($ticket['program_name']) ?></div></div><a class="btn btn-outline-secondary" href="tickets.php">Kembali</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-8"><div class="card mb-4"><div class="card-header d-flex justify-content-between"><strong>Informasi Permohonan</strong><span class="badge text-bg-light border"><?= e($ticket['status']) ?></span></div><div class="card-body"><div class="row g-3"><div class="col-md-6"><div class="small text-muted">Unit</div><strong><?= e($ticket['department_name']) ?></strong></div><div class="col-md-6"><div class="small text-muted">Prioritas</div><strong><?= e($ticket['priority']) ?></strong></div><div class="col-12"><div class="small text-muted">Latar belakang</div><?= nl2br(e($ticket['background'])) ?></div><div class="col-12"><div class="small text-muted">Tujuan</div><?= nl2br(e($ticket['objectives'])) ?></div><div class="col-12"><div class="small text-muted">Kebutuhan</div><?= nl2br(e($ticket['requested_solution'])) ?></div></div></div></div>
<div class="card"><div class="card-header fw-semibold">Riwayat Tiket</div><div class="card-body"><div class="list-group list-group-flush"><?php foreach ($events as $event): ?><div class="list-group-item px-0"><div class="d-flex justify-content-between gap-2"><strong><?= e($event['activity']) ?> · <?= e($event['new_status'] ?? '') ?></strong><small class="text-muted"><?= e($event['created_at']) ?></small></div><div class="small text-muted">Oleh <?= e($event['actor_name']) ?></div><?php if ($event['description']): ?><div><?= nl2br(e($event['description'])) ?></div><?php endif; ?></div><?php endforeach; ?><?php if (!$events): ?><div class="text-muted">Belum ada riwayat proses.</div><?php endif; ?></div></div></div></div>
<div class="col-lg-4"><div class="card mb-4"><div class="card-header fw-semibold">Petugas yang Mengerjakan</div><div class="card-body"><?php if ($assignedStaff): ?><ul class="list-group list-group-flush mb-3"><?php foreach ($assignedStaff as $member): ?><li class="list-group-item px-0"><strong><?= e($member['name']) ?></strong><div class="small text-muted"><?= e($member['position'] ?: 'Petugas IT') ?></div></li><?php endforeach; ?></ul><?php else: ?><p class="text-muted">Belum ada petugas yang ditugaskan.</p><?php endif; ?>
<?php if ($ticket['status'] !== 'COMPLETED' && $ticket['status'] !== 'CLOSED'): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="assign"><label class="form-label">Pilih petugas aktif</label><select class="form-select mb-3" name="staff_member_id" required><option value="">Pilih petugas</option><?php foreach ($staff as $member): ?><option value="<?= (int)$member['id'] ?>"><?= e($member['name']) ?><?= $member['position'] ? ' — '.e($member['position']) : '' ?></option><?php endforeach; ?></select><button class="btn btn-outline-primary w-100" <?= !$staff ? 'disabled' : '' ?>>Tetapkan Petugas</button><?php if (!$staff): ?><div class="form-text mt-2">Tambahkan petugas terlebih dahulu melalui menu Kelola Petugas IT.</div><?php endif; ?></form><?php endif; ?></div></div>
<div class="card"><div class="card-header fw-semibold">Perbarui Proses</div><div class="card-body"><?php if (in_array($ticket['status'],['COMPLETED','CLOSED'],true)): ?><div class="alert alert-success mb-0"><strong>Tiket selesai.</strong><?php if ($ticket['resolution']): ?><div class="mt-2"><?= nl2br(e($ticket['resolution'])) ?></div><?php endif; ?></div><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status" required><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>" <?= $ticket['status']===$status?'selected':'' ?>><?= e($status) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Catatan proses / hasil pekerjaan</label><textarea class="form-control" name="resolution" rows="4" placeholder="Wajib diisi saat status diselesaikan"><?= e($ticket['resolution'] ?? '') ?></textarea></div><button class="btn btn-primary w-100">Simpan Proses</button></form><?php endif; ?></div></div></div></div>
<?php page_footer(); ?>