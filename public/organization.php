<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_role(['SUPERADMIN']);
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$action=$_POST['action']??'';
 try{
  if($action==='directorate'){
   $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');
   if($name==='') throw new RuntimeException('Nama direktorat wajib diisi.');
   if($id){$st=$pdo->prepare('UPDATE directorates SET name=? WHERE id=?');$st->execute([$name,$id]);audit($pdo,(int)current_user()['id'],'UPDATE_DIRECTORATE','directorate',$id,$name);}
   else{$st=$pdo->prepare('INSERT INTO directorates(name) VALUES(?)');$st->execute([$name]);audit($pdo,(int)current_user()['id'],'CREATE_DIRECTORATE','directorate',(int)$pdo->lastInsertId(),$name);}
   $success='Direktorat disimpan.';
  }elseif($action==='department'){
   $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$did=(int)($_POST['directorate_id']??0);
   if($name===''||$did<1) throw new RuntimeException('Nama unit dan direktorat wajib diisi.');
   if($id){$st=$pdo->prepare('UPDATE departments SET name=?,directorate_id=? WHERE id=?');$st->execute([$name,$did,$id]);audit($pdo,(int)current_user()['id'],'UPDATE_DEPARTMENT','department',$id,$name);}
   else{$st=$pdo->prepare('INSERT INTO departments(name,directorate_id) VALUES(?,?)');$st->execute([$name,$did]);audit($pdo,(int)current_user()['id'],'CREATE_DEPARTMENT','department',(int)$pdo->lastInsertId(),$name);}
   $success='Unit disimpan.';
  }elseif($action==='toggle_dir'||$action==='toggle_dept'){
   $table=$action==='toggle_dir'?'directorates':'departments';$id=(int)($_POST['id']??0);
   $st=$pdo->prepare("UPDATE $table SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?");$st->execute([$id]);
   audit($pdo,(int)current_user()['id'],'TOGGLE_'.strtoupper($table),$table,$id,'Mengubah status aktif');
   $success='Status diperbarui.';
  }
 }catch(Throwable $ex){$error=$ex->getMessage();}
}
$dirs=$pdo->query('SELECT * FROM directorates ORDER BY name')->fetchAll();
$depts=$pdo->query('SELECT d.*,di.name directorate_name FROM departments d JOIN directorates di ON di.id=d.directorate_id ORDER BY di.name,d.name')->fetchAll();
$editDept=null;if(isset($_GET['edit_dept'])){$st=$pdo->prepare('SELECT * FROM departments WHERE id=?');$st->execute([(int)$_GET['edit_dept']]);$editDept=$st->fetch()?:null;}
$editDir=null;if(isset($_GET['edit_dir'])){$st=$pdo->prepare('SELECT * FROM directorates WHERE id=?');$st->execute([(int)$_GET['edit_dir']]);$editDir=$st->fetch()?:null;}
page_header('Direktorat dan Unit');
echo '<div class="mb-3"><h3>Direktorat & Unit Kerja</h3><div class="text-muted">Kelola struktur organisasi yang digunakan untuk akun dan pembatasan akses permohonan.</div></div>';
if($error)echo '<div class="alert alert-danger">'.e($error).'</div>';if($success)echo '<div class="alert alert-success">'.e($success).'</div>';
echo '<div class="row g-3"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">'.($editDir?'Edit':'Tambah').' Direktorat</div><div class="card-body"><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="directorate"><input type="hidden" name="id" value="'.(int)($editDir['id']??0).'"><label class="form-label">Nama direktorat</label><input class="form-control mb-3" name="name" required value="'.e($editDir['name']??'').'"><button class="btn btn-primary">Simpan</button> <a class="btn btn-light" href="organization.php">Reset</a></form></div></div>';
echo '<div class="card border-0 shadow-sm mt-3"><div class="card-header bg-white fw-semibold">Daftar Direktorat</div><div class="list-group list-group-flush">';
foreach($dirs as $d){echo '<div class="list-group-item d-flex justify-content-between align-items-center"><div>'.e($d['name']).' '.($d['is_active']?'':'<span class="badge text-bg-secondary">Nonaktif</span>').'</div><div><a class="btn btn-sm btn-outline-primary" href="organization.php?edit_dir='.(int)$d['id'].'">Edit</a> <form class="d-inline" method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="toggle_dir"><input type="hidden" name="id" value="'.(int)$d['id'].'"><button class="btn btn-sm btn-outline-warning">'.($d['is_active']?'Nonaktifkan':'Aktifkan').'</button></form></div></div>';}
echo '</div></div></div><div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">'.($editDept?'Edit':'Tambah').' Unit Kerja</div><div class="card-body"><form method="post" class="row g-2"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="department"><input type="hidden" name="id" value="'.(int)($editDept['id']??0).'"><div class="col-md-7"><label class="form-label">Nama unit</label><input class="form-control" name="name" required value="'.e($editDept['name']??'').'"></div><div class="col-md-5"><label class="form-label">Direktorat</label><select class="form-select" name="directorate_id" required><option value="">Pilih</option>';
foreach($dirs as $d)if($d['is_active'])echo '<option value="'.(int)$d['id'].'" '.((int)($editDept['directorate_id']??0)===(int)$d['id']?'selected':'').'>'.e($d['name']).'</option>';
echo '</select></div><div class="col-12 mt-3"><button class="btn btn-primary">Simpan Unit</button> <a class="btn btn-light" href="organization.php">Reset</a></div></form></div></div><div class="card border-0 shadow-sm mt-3"><div class="card-header bg-white fw-semibold">Daftar Unit Kerja</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Unit</th><th>Direktorat</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
foreach($depts as $d)echo '<tr><td>'.e($d['name']).'</td><td>'.e($d['directorate_name']).'</td><td>'.($d['is_active']?'Aktif':'Nonaktif').'</td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="organization.php?edit_dept='.(int)$d['id'].'">Edit</a> <form class="d-inline" method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="toggle_dept"><input type="hidden" name="id" value="'.(int)$d['id'].'"><button class="btn btn-sm btn-outline-warning">'.($d['is_active']?'Nonaktifkan':'Aktifkan').'</button></form></td></tr>';
if(!$depts)echo '<tr><td colspan="4" class="text-center text-muted p-3">Belum ada unit.</td></tr>';
echo '</tbody></table></div></div></div></div>';page_footer();
