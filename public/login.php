<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!empty($_SESSION['user'])) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("SELECT u.*, d.directorate_id FROM users u LEFT JOIN departments d ON d.id=u.department_id WHERE u.username=? AND u.is_active=1 LIMIT 1");
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'=>(int)$user['id'], 'name'=>$user['name'], 'username'=>$user['username'],
            'role'=>$user['role'], 'department_id'=>$user['department_id'],
            'directorate_id'=>$user['directorate_id']
        ];
        audit($pdo, (int)$user['id'], 'LOGIN', 'user', (int)$user['id']);
        redirect('index.php');
    }
    $error = 'Username atau password tidak sesuai.';
}
page_header('Login');
echo '<div class="row justify-content-center"><div class="col-md-5"><div class="card border-0 shadow-sm"><div class="card-body p-4"><h4>Login</h4>';
if ($error) echo '<div class="alert alert-danger">'.e($error).'</div>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" required autofocus></div><div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div><button class="btn btn-primary w-100">Masuk</button></form></div></div></div></div>';
page_footer();
