<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT r.*,d.name department_name,di.name directorate_name,c.name category_name,u.name requester,t.id ticket_id,t.ticket_no,t.status ticket_status FROM program_requests r JOIN departments d ON d.id=r.department_id JOIN directorates di ON di.id=r.directorate_id JOIN program_categories c ON c.id=r.category_id JOIN users u ON u.id=r.user_id LEFT JOIN it_tickets t ON t.request_id=r.id WHERE r.id=?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r || !can_access_request($r)) {
    http_response_code(404);
    exit('Permohonan tidak ditemukan.');
}
page_header('Detail Permohonan');
echo '<div class="d-flex justify-content-between"><h3>Detail Permohonan</h3><a class="btn btn-outline-secondary" href="requests.php">Kembali</a></div><div class="card border-0 shadow-sm mt-3"><div class="card-body"><h5>' . e($r['program_name']) . '</h5><p class="text-muted">' . e($r['request_no']) . ' · ' . e($r['ticket_no']) . '</p><dl class="row">';
foreach (['requester' => 'Pemohon', 'department_name' => 'Unit', 'directorate_name' => 'Direktorat', 'category_name' => 'Kategori', 'status' => 'Status Permohonan', 'ticket_status' => 'Status Tiket', 'urgency' => 'Urgensi'] as $key => $label) echo '<dt class="col-sm-3">' . e($label) . '</dt><dd class="col-sm-9">' . e((string)$r[$key]) . '</dd>';
echo '</dl><h6>Latar Belakang</h6><p>' . nl2br(e($r['background'])) . '</p><h6>Tujuan</h6><p>' . nl2br(e($r['objectives'])) . '</p><h6>Solusi yang Dimohonkan</h6><p>' . nl2br(e($r['requested_solution'])) . '</p><div class="d-flex gap-2"><a class="btn btn-outline-primary" href="request_pdf.php?id=' . (int)$r['id'] . '&amp;preview=1" target="_blank" rel="noopener">Preview PDF</a><a class="btn btn-primary" href="request_pdf.php?id=' . (int)$r['id'] . '">Unduh PDF</a>';
if (in_array(current_user()['role'], ['APPROVER', 'SUPERADMIN'], true)) echo '<a class="btn btn-outline-success" href="approval.php?id=' . (int)$r['id'] . '">Proses Persetujuan</a>';
echo '</div></div></div>';
page_footer();
