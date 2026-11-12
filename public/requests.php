<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();
$u = current_user();
$sql = "SELECT r.*, d.name department_name, c.name category_name, t.ticket_no FROM program_requests r JOIN departments d ON d.id=r.department_id JOIN program_categories c ON c.id=r.category_id LEFT JOIN it_tickets t ON t.request_id=r.id";
$where = [];
$params = [];
if ($u['role'] === 'UNIT') {
    $where[] = 'r.department_id=?';
    $params[] = $u['department_id'];
} elseif ($u['role'] === 'APPROVER') {
    $where[] = 'r.directorate_id=?';
    $params[] = $u['directorate_id'];
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY r.created_at DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
page_header('Daftar Permohonan');
echo '<div class="d-flex justify-content-between align-items-center mb-3"><h3>Daftar Permohonan</h3>';
if ($u['role'] === 'UNIT') echo '<a class="btn btn-primary" href="request_create.php">Buat Permohonan</a>';
echo '</div><div class="table-responsive card border-0 shadow-sm"><table class="table table-hover mb-0"><thead><tr><th>Nomor</th><th>Program</th><th>Unit</th><th>Tiket</th><th>Status</th><th></th></tr></thead><tbody>';
foreach ($rows as $r) echo '<tr><td>' . e($r['request_no']) . '</td><td>' . e($r['program_name']) . '</td><td>' . e($r['department_name']) . '</td><td>' . e($r['ticket_no']) . '</td><td>' . e($r['status']) . '</td><td><a href="request_view.php?id=' . (int)$r['id'] . '">Detail</a></td></tr>';
if (!$rows) echo '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada permohonan.</td></tr>';
echo '</tbody></table></div>';
page_footer();
