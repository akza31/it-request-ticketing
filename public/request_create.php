<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['UNIT']);
$categories = $pdo->query("SELECT id,name FROM program_categories WHERE is_active=1 ORDER BY name")->fetchAll();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['program_name'] ?? '');
    $background = trim($_POST['background'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');
    $solution = trim($_POST['requested_solution'] ?? '');
    $category = (int)($_POST['category_id'] ?? 0);
    if ($name === '' || $background === '' || $objectives === '' || $solution === '' || $category < 1) {
        $error = 'Lengkapi seluruh kolom wajib.';
    } else {
        $pdo->beginTransaction();
        try {
            $year = date('Y');
            $seq = $pdo->prepare("INSERT INTO document_sequences(sequence_key, sequence_year, last_number)
                VALUES('REQUEST', ?, 1)
                ON CONFLICT(sequence_key, sequence_year)
                DO UPDATE SET last_number = document_sequences.last_number + 1");
            $seq->execute([$year]);
            $number = (int)$pdo->query("SELECT last_number FROM document_sequences WHERE sequence_key='REQUEST' AND sequence_year=".(int)$year)->fetchColumn();
            $requestNo = sprintf('ITREQ/%s/%06d', $year, $number);

            $ins = $pdo->prepare("INSERT INTO program_requests(request_no,user_id,department_id,directorate_id,category_id,program_name,background,objectives,requested_solution,urgency,status,submitted_at)
                VALUES(?,?,?,?,?,?,?,?,?,?, 'WAITING_APPROVAL',?)");
            $ins->execute([$requestNo,current_user()['id'],current_user()['department_id'],current_user()['directorate_id'],$category,$name,$background,$objectives,$solution,$_POST['urgency'] ?? 'MEDIUM',date('Y-m-d H:i:s')]);
            $requestId = (int)$pdo->lastInsertId();

            $seq2 = $pdo->prepare("INSERT INTO document_sequences(sequence_key, sequence_year, last_number)
                VALUES('TICKET', ?, 1)
                ON CONFLICT(sequence_key, sequence_year)
                DO UPDATE SET last_number = document_sequences.last_number + 1");
            $seq2->execute([$year]);
            $ticketNumber = (int)$pdo->query("SELECT last_number FROM document_sequences WHERE sequence_key='TICKET' AND sequence_year=".(int)$year)->fetchColumn();
            $ticketNo = sprintf('IT/%s/%06d', $year, $ticketNumber);
            $ticket = $pdo->prepare("INSERT INTO it_tickets(ticket_no,request_id,status,priority) VALUES(?,?,'WAITING_APPROVAL',?)");
            $ticket->execute([$ticketNo,$requestId,$_POST['urgency'] ?? 'MEDIUM']);
            $ticketId = (int)$pdo->lastInsertId();

            $hist = $pdo->prepare("INSERT INTO ticket_history(ticket_id,user_id,old_status,new_status,activity,description) VALUES(?,?,'DRAFT','WAITING_APPROVAL','SUBMITTED','Permohonan dikirim oleh unit')");
            $hist->execute([$ticketId,current_user()['id']]);
            audit($pdo,current_user()['id'],'REQUEST_SUBMITTED','request',$requestId,$requestNo);
            $pdo->commit();
            $categoryName = $pdo->prepare('SELECT name FROM program_categories WHERE id=?');
            $categoryName->execute([$category]);
            $message = "📩 Permohonan IT baru\nNomor: {$requestNo}\nPemohon: " . current_user()['name'] . "\nProgram: {$name}\nKategori: " . (string)$categoryName->fetchColumn() . "\nUrgensi: " . ($_POST['urgency'] ?? 'MEDIUM') . "\nStatus: Menunggu persetujuan";
            foreach (['it_staff_chat_id', 'it_head_chat_id'] as $recipient) {
                try { send_telegram_message((string)($config['telegram'][$recipient] ?? ''), $message); } catch (Throwable $notificationError) { error_log('Telegram notification failed: ' . $notificationError->getMessage()); }
            }
            redirect('request_view.php?id='.$requestId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Permohonan gagal disimpan. Periksa konfigurasi dan log aplikasi.';
        }
    }
}
page_header('Buat Permohonan');
echo '<h3>Buat Permohonan Program IT</h3>';
if ($error) echo '<div class="alert alert-danger">'.e($error).'</div>';
echo '<div class="card border-0 shadow-sm"><div class="card-body"><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div class="mb-3"><label class="form-label">Nama Program *</label><input class="form-control" name="program_name" required></div><div class="mb-3"><label class="form-label">Kategori *</label><select class="form-select" name="category_id" required><option value="">Pilih kategori</option>';
foreach ($categories as $c) echo '<option value="'.(int)$c['id'].'">'.e($c['name']).'</option>';
echo '</select></div><div class="mb-3"><label class="form-label">Latar Belakang *</label><textarea class="form-control" name="background" rows="3" required></textarea></div><div class="mb-3"><label class="form-label">Tujuan *</label><textarea class="form-control" name="objectives" rows="3" required></textarea></div><div class="mb-3"><label class="form-label">Solusi / Kebutuhan yang Dimohonkan *</label><textarea class="form-control" name="requested_solution" rows="4" required></textarea></div><div class="mb-3"><label class="form-label">Urgensi</label><select class="form-select" name="urgency"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select></div><button class="btn btn-primary">Kirim Permohonan & Buat Tiket</button></form></div></div>';
page_footer();
