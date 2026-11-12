<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['SUPERADMIN', 'IT_HEAD', 'IT_STAFF']);
$q = $pdo->query(
    "SELECT t.*,r.request_no,r.program_name,d.name department_name,(SELECT GROUP_CONCAT(sm.name, ', ') FROM ticket_assignments ta JOIN it_staff_members sm ON sm.id=ta.staff_member_id WHERE ta.ticket_id=t.id AND ta.is_active=1) staff_names FROM it_tickets t JOIN program_requests r ON r.id=t.request_id JOIN departments d ON d.id=r.department_id ORDER BY t.created_at DESC LIMIT 300",
);
$rows = $q->fetchAll();
page_header('IT Ticketing');
echo
    '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h3>IT Ticketing</h3><div class="text-muted">Kelola penugasan, proses, dan penyelesaian tiket.</div></div>'
;
if (in_array(current_user()['role'], ['SUPERADMIN', 'IT_HEAD'], true))
    echo
        '<a class="btn btn-outline-primary" href="it_staff.php"><i class="bi bi-people me-1"></i>Kelola Petugas IT</a>'
    ;
echo
    '</div><div class="table-responsive card mt-3"><table class="table table-hover align-middle mb-0"><thead><tr><th>Tiket</th><th>Program</th><th>Unit</th><th>Prioritas</th><th>Petugas</th><th>Status</th><th></th></tr></thead><tbody>'
;
foreach ($rows as $r)
    echo
        '<tr><td>'
            . e($r['ticket_no'])
            . '</td><td>'
            . e($r['program_name'])
            . '</td><td>'
            . e($r['department_name'])
            . '</td><td>'
            . e($r['priority'])
            . '</td><td>'
            . e($r['staff_names'] ?: 'Belum ditugaskan')
            . '</td><td><span class="badge text-bg-light border">'
            . e($r['status'])
            . '</span></td><td><a class="btn btn-sm btn-primary" href="ticket_process.php?id='
            . (int) $r['id']
            . '">Proses</a></td></tr>'
    ;
if (!$rows)
    echo '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada tiket.</td></tr>';
echo '</tbody></table></div>';
page_footer();
