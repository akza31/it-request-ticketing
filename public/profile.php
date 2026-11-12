<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();$u=current_user();$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$old=$_POST['current_password']??'';$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
 $st=$pdo->prepare('SELECT password FROM users WHERE id=? AND is_active=1');$st->execute([(int)$u['id']]);$hash=$st->fetchColumn();
 if(!$hash||!password_verify($old,$hash))$error='Password saat ini tidak sesuai.';
 elseif(strlen($new)<12)$error='Password baru minimal 12 karakter.';
 elseif($new!==$confirm)$error='Konfirmasi password baru tidak sama.';
 else{$st=$pdo->prepare('UPDATE users SET password=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$st->execute([password_hash($new,PASSWORD_DEFAULT),(int)$u['id']]);audit($pdo,(int)$u['id'],'CHANGE_PASSWORD','user',(int)$u['id'],'Pengguna mengganti password sendiri');$success='Password berhasil diperbarui.';}
}
$st=$pdo->prepare('SELECT u.name,u.username,u.email,u.role,d.name department_name,di.name directorate_name FROM users u LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN directorates di ON di.id=d.directorate_id WHERE u.id=?');$st->execute([(int)$u['id']]);$info=$st->fetch();
page_header('Profil Saya');echo '<div class="row justify-content-center"><div class="col-lg-7"><h3 class="mb-3">Profil Saya</h3>';
if($error)echo '<div class="alert alert-danger">'.e($error).'</div>';if($success)echo '<div class="alert alert-success">'.e($success).'</div>';
echo '<div class="card border-0 shadow-sm mb-4"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Nama</dt><dd class="col-sm-8">'.e($info['name']).'</dd><dt class="col-sm-4">Username</dt><dd class="col-sm-8">'.e($info['username']).'</dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8">'.e($info['email']??'—').'</dd><dt class="col-sm-4">Role</dt><dd class="col-sm-8">'.e($info['role']).'</dd><dt class="col-sm-4">Unit</dt><dd class="col-sm-8">'.e($info['department_name']??'—').'</dd><dt class="col-sm-4">Direktorat</dt><dd class="col-sm-8">'.e($info['directorate_name']??'—').'</dd></dl></div></div>';
echo '<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Ganti Password</div><div class="card-body"><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div class="mb-3"><label class="form-label">Password saat ini</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div><div class="mb-3"><label class="form-label">Password baru</label><input class="form-control" type="password" name="new_password" required minlength="12" autocomplete="new-password"></div><div class="mb-3"><label class="form-label">Ulangi password baru</label><input class="form-control" type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></div><button class="btn btn-primary">Ubah Password</button></form></div></div></div></div>';page_footer();
