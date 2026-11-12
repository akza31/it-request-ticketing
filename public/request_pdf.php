<?php

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_login();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT r.*,d.name department_name,di.name directorate_name,u.name requester,c.name category_name FROM program_requests r JOIN departments d ON d.id=r.department_id JOIN directorates di ON di.id=r.directorate_id JOIN users u ON u.id=r.user_id JOIN program_categories c ON c.id=r.category_id WHERE r.id=?',
);
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r || !can_access_request($r)) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}
if (!class_exists('Mpdf\\Mpdf')) {
    http_response_code(500);
    exit('mPDF belum terpasang. Jalankan composer install dari folder aplikasi.');
}
$mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'margin_top' => 15, 'margin_bottom' => 15]);
$html = '<div style="text-align:center;border-bottom:2px solid #17365d;padding-bottom:10px"><h2>RSU ISLAM KLATEN</h2><strong>FORMULIR PERMOHONAN PROGRAM DIGITALISASI</strong></div>';
$html .= '<p style="text-align:center">Nomor: ' . htmlspecialchars($r['request_no']) . '</p>';
$html .=
    '<h4>I. IDENTITAS PEMOHON</h4><table width="100%"><tr><td>Nama</td><td>: '
    . htmlspecialchars($r['requester'])
    . '</td></tr><tr><td>Unit</td><td>: '
    . htmlspecialchars($r['department_name'])
    . '</td></tr><tr><td>Direktorat</td><td>: '
    . htmlspecialchars($r['directorate_name'])
    . '</td></tr></table>';
$html .=
    '<h4>II. DETAIL PERMOHONAN</h4><p><b>Program:</b> '
    . htmlspecialchars($r['program_name'])
    . '<br><b>Kategori:</b> '
    . htmlspecialchars($r['category_name'])
    . '<br><b>Urgensi:</b> '
    . htmlspecialchars($r['urgency'])
    . '</p>';
foreach ([
    'background' => 'III. LATAR BELAKANG',
    'objectives' => 'IV. TUJUAN',
    'requested_solution' => 'V. KEBUTUHAN / SOLUSI',
] as $key => $label)
    $html .= '<h4>' . $label . '</h4><p>' . nl2br(htmlspecialchars($r[$key])) . '</p>';
$html .= '<br><table width="100%" style="text-align:center"><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>Pemohon</td></tr><tr><td height="90"></td><td></td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>(___________________)</td></tr></table>';
$mpdf->WriteHTML($html);
$outputMode = ($_GET['preview'] ?? '') === '1' ? 'I' : 'D';
$mpdf->Output('Permohonan-' . $r['request_no'] . '.pdf', $outputMode);
