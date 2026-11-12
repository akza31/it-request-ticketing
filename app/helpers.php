<?php
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $path): never {
    global $config;
    header('Location: ' . rtrim($config['base_url'], '/') . '/' . ltrim($path, '/'));
    exit;
}
function require_login(): void {
    if (empty($_SESSION['user'])) redirect('login.php');
}
function require_role(array $roles): void {
    require_login();
    if (!in_array($_SESSION['user']['role'], $roles, true)) {
        http_response_code(403);
        exit('Akses tidak diizinkan.');
    }
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Muat ulang halaman.');
    }
}
function audit(PDO $pdo, int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $detail = null): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs(user_id, action, entity_type, entity_id, detail, ip_address, user_agent) VALUES(?,?,?,?,?,?,?)');
    $stmt->execute([$userId, $action, $entityType, $entityId, $detail, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
}
function current_user(): array {
    return $_SESSION['user'] ?? [];
}
function send_telegram_message(string $chatId, string $message): bool {
    global $config;
    $token = trim((string)($config['telegram']['bot_token'] ?? ''));
    if ($token === '' || trim($chatId) === '') return false;
    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $payload = http_build_query(['chat_id' => $chatId, 'text' => $message]);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5]);
        $result = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $result !== false && $status >= 200 && $status < 300;
    }
    $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => 5, 'ignore_errors' => true]]);
    $result = @file_get_contents($url, false, $context);
    return $result !== false && isset($http_response_header[0]) && str_contains($http_response_header[0], '200');
}
function can_access_request(array $request): bool {
    $u = current_user();
    if (in_array($u['role'], ['SUPERADMIN','IT_HEAD','IT_STAFF'], true)) return true;
    if ($u['role'] === 'UNIT') return (int)$u['department_id'] === (int)$request['department_id'];
    if ($u['role'] === 'APPROVER') return (int)$u['directorate_id'] === (int)$request['directorate_id'];
    return false;
}
function page_header(string $title): void {
    global $config;
    $name = e($config['app_name']);
    $u = current_user();
    $authenticated = !empty($u['role']);
    $activePage = basename($_SERVER['SCRIPT_NAME'] ?? '');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).' - '.$name.'</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>
    :root{--app-navy:#17365d;--app-blue:#245b91;--app-bg:#f3f6fa;--app-ink:#263445}body{background:var(--app-bg)!important;color:var(--app-ink);font-family:system-ui,-apple-system,"Segoe UI",sans-serif}.app-topbar{height:64px;background:#fff;box-shadow:0 2px 12px rgba(28,43,61,.07);position:sticky;top:0;z-index:1030}.app-brand{color:var(--app-navy);font-weight:700;text-decoration:none}.app-shell{display:flex;min-height:calc(100vh - 64px)}.app-sidebar{width:250px;flex:0 0 250px;background:linear-gradient(180deg,#17365d,#122b4b);padding:1.25rem .85rem;color:#fff}.sidebar-caption{font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;color:#9db3cd;padding:.7rem .8rem .45rem}.app-sidebar .nav-link{color:#d6e0ec;border-radius:8px;padding:.7rem .8rem;margin:.15rem 0}.app-sidebar .nav-link:hover,.app-sidebar .nav-link.active{background:rgba(255,255,255,.14);color:#fff}.app-sidebar .nav-link i{width:1.5rem;display:inline-block}.app-content{min-width:0;flex:1;padding:1.6rem clamp(1rem,2.5vw,2.25rem)}.app-content h1,.app-content h2,.app-content h3{font-weight:700;color:#263445}.app-content .card{border:0;border-radius:12px;box-shadow:0 3px 14px rgba(29,45,65,.07);overflow:hidden}.app-content .card-header{background:#fff;border-bottom:1px solid #edf0f4}.app-content .form-control,.app-content .form-select{border-color:#dce3eb;border-radius:8px;padding:.6rem .75rem}.app-content .form-control:focus,.app-content .form-select:focus{border-color:#82acd5;box-shadow:0 0 0 .2rem rgba(36,91,145,.12)}.app-content .btn{border-radius:8px;font-weight:500}.app-content .table{--bs-table-bg:transparent}.app-content .table thead th{font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:#68788a;white-space:nowrap}.app-content .alert{border:0;border-radius:10px}.app-user{color:#5b697a}.login-shell{min-height:calc(100vh - 64px);display:grid;place-items:center}.login-shell>.container{max-width:1100px}@media(max-width:767.98px){.app-shell{display:block}.app-sidebar{width:100%;padding:.65rem .8rem}.app-sidebar .nav{display:flex;flex-direction:row;overflow-x:auto;white-space:nowrap}.sidebar-caption{display:none}.app-content{padding:1.2rem .85rem}}
    </style></head><body>';
    echo '<header class="app-topbar d-flex align-items-center"><div class="container-fluid px-3 px-lg-4 d-flex justify-content-between align-items-center"><a class="app-brand" href="'.($authenticated?'index.php':'login.php').'"><i class="bi bi-grid-1x2-fill me-2"></i>'.$name.'</a>';
    if ($authenticated) echo '<div class="app-user small"><i class="bi bi-person-circle me-1"></i>'.e($u['name'] ?? '').' <span class="badge text-bg-light border ms-1">'.e($u['role'] ?? '').'</span> <a class="btn btn-sm btn-outline-secondary ms-2" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Keluar</a></div>';
    echo '</div></header>';
    if ($authenticated) {
        echo '<div class="app-shell"><aside class="app-sidebar"><div class="sidebar-caption">Menu utama</div><nav class="nav flex-column">';
        $links = [['index.php','bi-speedometer2','Dashboard'],['requests.php','bi-file-earmark-text','Permohonan']];
        if (in_array($u['role'], ['SUPERADMIN','IT_HEAD','IT_STAFF'], true)) $links[] = ['tickets.php','bi-ticket-detailed','IT Ticketing'];
        if (in_array($u['role'], ['SUPERADMIN','IT_HEAD'], true)) $links[] = ['it_staff.php','bi-people','Petugas IT'];
        if ($u['role'] === 'SUPERADMIN') { $links[] = ['users.php','bi-people','Pengguna']; $links[] = ['organization.php','bi-diagram-3','Direktorat & Unit']; }
        $links[] = ['profile.php','bi-person-gear','Profil Saya'];
        foreach ($links as [$href,$icon,$label]) echo '<a class="nav-link'.($activePage===$href?' active':'').'" href="'.$href.'"><i class="bi '.$icon.'"></i>'.e($label).'</a>';
        echo '</nav></aside><main class="app-content">';
    } else {
        echo '<div class="login-shell"><div class="container">';
    }
}
function page_footer(): void {
    if (!empty(current_user()['role'])) echo '</main></div>';
    else echo '</div></div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>';
}
