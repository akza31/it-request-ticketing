<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['APPROVER','SUPERADMIN']);
$id=(int)($_GET['id']??0);
$q=$pdo->prepare("SELECT * FROM program_requests WHERE id=?");$q->execute([$id]);$r=$q->fetch();
if (!$r || !can_access_request($r)) {http_response_code(404);exit('Permohonan tidak ditemukan.');}
$msg='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $decision=$_POST['decision']??'';
    if (!in_array($decision,['APPROVED','REJECTED','REVISION'],true)) exit('Keputusan tidak valid.');
    $notes=trim($_POST['notes']??'');
    if ($decision!=='APPROVED' && $notes==='') $msg='Catatan wajib diisi untuk penolakan atau revisi.';
    else {
        $pdo->beginTransaction();
        try {
            $a=$pdo->prepare("INSERT INTO request_approvals(request_id,approver_id,approval_method,decision,notes,approved_at) VALUES(?,?,'DIGITAL',?,?,?)");
            $a->execute([$id,current_user()['id'],$decision,$notes,date('Y-m-d H:i:s')]);
            $newStatus=$decision==='APPROVED'?'APPROVED':($decision==='REJECTED'?'REJECTED':'REVISION');
            $pdo->prepare("UPDATE program_requests SET status=? WHERE id=?")->execute([$newStatus,$id]);
            $pdo->prepare("UPDATE it_tickets SET status=? WHERE request_id=?")->execute([$newStatus==='APPROVED'?'IT_REVIEW':$newStatus,$id]);
            $t=$pdo->prepare("SELECT id FROM it_tickets WHERE request_id=?");$t->execute([$id]);$ticketId=(int)$t->fetchColumn();
            if ($ticketId > 0) {
                $pdo->prepare("INSERT INTO ticket_history(ticket_id,user_id,new_status,activity,description) VALUES(?,?,?,'DIRECTORATE_APPROVAL',?)")->execute([$ticketId,current_user()['id'],$newStatus,$notes]);
            }
            audit($pdo,current_user()['id'],'APPROVAL_'.$decision,'request',$id,$notes);
            $pdo->commit();
            redirect('request_view.php?id='.$id);
        } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();$msg='Persetujuan gagal diproses.';}
    }
}
page_header('Persetujuan');
echo '<h3>Persetujuan Direktorat</h3><div class="card border-0 shadow-sm"><div class="card-body"><h5>'.e($r['program_name']).'</h5>';
if($msg) echo '<div class="alert alert-warning">'.e($msg).'</div>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><label class="form-label">Keputusan</label><select class="form-select mb-3" name="decision"><option value="APPROVED">Setujui</option><option value="REVISION">Minta Revisi</option><option value="REJECTED">Tolak</option></select><label class="form-label">Catatan</label><textarea class="form-control mb-3" name="notes" rows="4"></textarea><button class="btn btn-primary">Simpan Keputusan</button></form></div></div>';
page_footer();
