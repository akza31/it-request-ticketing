<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();

$u = current_user();
$where = '';
$params = [];
if ($u['role'] === 'UNIT') {
    $where = ' WHERE r.department_id=?';
    $params[] = $u['department_id'];
} elseif ($u['role'] === 'APPROVER') {
    $where = ' WHERE r.directorate_id=?';
    $params[] = $u['directorate_id'];
}

$q = $pdo->prepare('SELECT COUNT(*) FROM program_requests r' . $where);
$q->execute($params);
$requestCount = (int)$q->fetchColumn();
$q = $pdo->prepare('SELECT COUNT(*) FROM it_tickets t JOIN program_requests r ON r.id=t.request_id' . $where);
$q->execute($params);
$ticketCount = (int)$q->fetchColumn();

$cards = [
    ['Permohonan', $requestCount, 'bi-file-earmark-text', 'primary', 'requests.php'],
    ['Total Tiket IT', $ticketCount, 'bi-ticket-detailed', 'info', 'tickets.php'],
];
if (in_array($u['role'], ['SUPERADMIN', 'IT_HEAD', 'IT_STAFF'], true)) {
    foreach ([
        ['WAITING_APPROVAL', 'Menunggu Persetujuan', 'warning'],
        ['IN_PROGRESS', 'Sedang Dikerjakan', 'success'],
        ['COMPLETED', 'Selesai', 'secondary'],
    ] as [$status, $label, $color]) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM it_tickets WHERE status=?');
        $q->execute([$status]);
        $cards[] = [$label, (int)$q->fetchColumn(), 'bi-bar-chart-line', $color, 'tickets.php'];
    }
}

$recent = $pdo->prepare('SELECT r.id,r.request_no,r.program_name,r.status,r.created_at,d.name department_name FROM program_requests r JOIN departments d ON d.id=r.department_id' . $where . ' ORDER BY r.created_at DESC LIMIT 6');
$recent->execute($params);
$recentRequests = $recent->fetchAll();

page_header('Dashboard');
?>
<style>
    .dashboard-wrap { max-width: 1440px; margin: 0 auto; }
    .dashboard-title { color: #263445; font-weight: 700; }
    .welcome-panel { background: linear-gradient(120deg,#17365d,#245b91); border-radius: 14px; color: #fff; padding: 1.5rem 1.75rem; box-shadow: 0 8px 24px rgba(23,54,93,.15); }
    .welcome-panel p { color: rgba(255,255,255,.78); }
    .stat-card { border: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 3px 14px rgba(29,45,65,.08); transition: transform .18s ease,box-shadow .18s ease; }
    .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(29,45,65,.13); }
    .stat-card .stat-icon { font-size: 2rem; opacity: .75; }
    .stat-card .stat-value { font-size: 1.9rem; font-weight: 700; }
    .dashboard-section { border: 0; border-radius: 12px; box-shadow: 0 3px 14px rgba(29,45,65,.07); }
    .dashboard-section .card-header { background: #fff; border-bottom: 1px solid #edf0f4; padding: 1rem 1.25rem; }
    .quick-link { border: 1px solid #e7ebf0; border-radius: 10px; padding: 1rem; height: 100%; color: #263445; text-decoration: none; display: block; transition: .18s ease; }
    .quick-link:hover { border-color: #8ab3df; background: #f7faff; transform: translateY(-2px); color: #17365d; }
    .quick-link i { font-size: 1.35rem; color: #245b91; }
    @media (max-width:575.98px) { .welcome-panel { padding: 1.25rem; } }
</style>
<div class="dashboard-wrap">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div><h2 class="dashboard-title mb-1">Dashboard</h2><div class="text-muted">Ringkasan aktivitas permohonan dan tiket IT</div></div>
        <div class="small text-muted"><i class="bi bi-person-circle me-1"></i><?= e($u['name'] ?? '') ?> <span class="badge text-bg-light border ms-1"><?= e($u['role'] ?? '') ?></span></div>
    </div>
    <section class="welcome-panel mb-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h4 class="fw-semibold mb-1">Halo, <?= e($u['name'] ?? 'Pengguna') ?>!</h4><p class="mb-0">Selamat datang di sistem Permohonan Program Teknologi Informasi.</p></div>
        <?php if ($u['role'] === 'UNIT'): ?><a class="btn btn-light text-primary fw-semibold" href="request_create.php"><i class="bi bi-plus-lg me-1"></i>Buat Permohonan</a><?php endif; ?>
    </section>
    <div class="row g-3 mb-4">
        <?php foreach ($cards as [$label, $value, $icon, $color, $url]): ?>
            <div class="col-6 col-xl-3"><a class="text-decoration-none text-reset" href="<?= e($url) ?>"><div class="card stat-card border-start border-4 border-<?= e($color) ?>"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small mb-1"><?= e($label) ?></div><div class="stat-value"><?= (int)$value ?></div></div><i class="bi <?= e($icon) ?> stat-icon text-<?= e($color) ?>"></i></div></div></a></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-4">
        <div class="col-lg-8"><section class="card dashboard-section h-100"><div class="card-header d-flex justify-content-between align-items-center"><strong>Permohonan Terbaru</strong><a class="small text-decoration-none" href="requests.php">Lihat semua <i class="bi bi-arrow-right"></i></a></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nomor</th><th>Program</th><th>Unit</th><th>Status</th><th></th></tr></thead><tbody>
        <?php if (!$recentRequests): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada permohonan.</td></tr><?php else: foreach ($recentRequests as $request): ?><tr><td class="text-nowrap small"><?= e($request['request_no']) ?></td><td><?= e($request['program_name']) ?></td><td><?= e($request['department_name']) ?></td><td><span class="badge text-bg-light border"><?= e($request['status']) ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="request_view.php?id=<?= (int)$request['id'] ?>">Detail</a></td></tr><?php endforeach; endif; ?>
        </tbody></table></div></section></div>
        <div class="col-lg-4"><section class="card dashboard-section h-100"><div class="card-header"><strong>Akses Cepat</strong></div><div class="card-body"><div class="row g-2">
            <?php if ($u['role'] === 'UNIT'): ?><div class="col-12"><a class="quick-link" href="request_create.php"><i class="bi bi-file-earmark-plus me-2"></i><strong>Buat Permohonan</strong><div class="small text-muted mt-1">Ajukan kebutuhan program IT baru</div></a></div><?php endif; ?>
            <div class="col-12"><a class="quick-link" href="requests.php"><i class="bi bi-list-ul me-2"></i><strong>Daftar Permohonan</strong><div class="small text-muted mt-1">Lihat dan kelola permohonan</div></a></div>
            <?php if (in_array($u['role'], ['SUPERADMIN','IT_HEAD','IT_STAFF'], true)): ?><div class="col-12"><a class="quick-link" href="tickets.php"><i class="bi bi-ticket-detailed me-2"></i><strong>IT Ticketing</strong><div class="small text-muted mt-1">Pantau tindak lanjut tiket IT</div></a></div><?php endif; ?>
        </div></div></section></div>
    </div>
</div>
<?php page_footer(); ?>