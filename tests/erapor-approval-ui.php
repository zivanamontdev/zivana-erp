<?php
// Render-only contract checks for the approval inbox and scoped reviewer UI.
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', ROOT_PATH . '/app/views');
define('BASE_PATH', '');
$_SESSION = ['csrf_token' => 'approval-ui-csrf', 'csrf_token_expires' => time() + 3600];
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';

function approvalUiCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function renderApprovalView(string $file, array $data): string
{
    extract($data);
    $source = file_get_contents($file);
    $source = str_replace([
        "require VIEW_PATH . '/layouts/shell-header.php';",
        "require VIEW_PATH . '/layouts/shell-footer.php';",
    ], '', $source);
    ob_start();
    eval('?>' . $source);
    return ob_get_clean();
}

$indexFile = VIEW_PATH . '/admin/erapor-approval/index.php';
$reviewFile = VIEW_PATH . '/admin/erapor-approval/review.php';
$tasks = [
    ['student'=>'Murid Terbatas','period'=>'Tengah Semester · 2025/2026','label'=>'Koordinator Bahasa Inggris',
        'documents'=>['Rapor Bahasa Inggris'],'session_id'=>55,'approval_id'=>9,'can_approve'=>true,'integrity_error'=>false],
    ['student'=>'Murid Terbatas','period'=>'Tengah Semester · 2025/2026','label'=>'Kepala Sekolah',
        'documents'=>[],'session_id'=>55,'approval_id'=>10,'can_approve'=>false,'integrity_error'=>true],
    ['student'=>'Murid Berikutnya','period'=>'Tengah Semester · 2025/2026','label'=>'Kepala Sekolah',
        'documents'=>['Rapor Montessori','Rapor Agama'],'session_id'=>56,'approval_id'=>11,'can_approve'=>false,'integrity_error'=>false],
];
$html = renderApprovalView($indexFile, ['pageTitle'=>'Antrean Persetujuan','breadcrumb'=>'','activeNavItem'=>'erapor-approval','tasks'=>$tasks,'notice'=>null]);
$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
approvalUiCheck($xpath->query('//table[contains(@class,"data-table")]')->length === 1, 'Inbox uses shared data-table component');
approvalUiCheck($xpath->query('//a[contains(@href,"/erapor/persetujuan/55/9") and normalize-space(.)="Tinjau"]')->length === 1,
    'Assigned, valid task gets its review action');
approvalUiCheck($xpath->query('//a[contains(@href,"/erapor/persetujuan/55/10")]')->length === 0
    && str_contains($html, 'Tidak dapat ditinjau'), 'Invalid assignment/snapshot is not linked to a guaranteed 404');
approvalUiCheck(str_contains($html, 'Rapor Bahasa Inggris') && str_contains($html, 'Menunggu tahap sebelumnya'), 'Inbox shows assigned scope and readiness');
$empty = renderApprovalView($indexFile, ['pageTitle'=>'Antrean Persetujuan','breadcrumb'=>'','activeNavItem'=>'erapor-approval','tasks'=>[],'notice'=>null]);
approvalUiCheck(str_contains($empty, 'Tidak ada rapor yang menunggu persetujuan'), 'Empty inbox has a useful empty state');

$review = [
    'session'=>['id'=>55],
    'student'=>['nama_lengkap'=>'Murid Terbatas','nisn'=>'0012345678','level_kelas'=>'Ranting','nama_kelas'=>'Akasia'],
    'period'=>['nama'=>'Pembagian Rapor Tengah Semester','tahun_label'=>'2025/2026'],
    'approval'=>['id'=>9,'label'=>'Koordinator Bahasa Inggris','status'=>'MENUNGGU'],
    'all_approved'=>false,'waiting_for'=>[],
    'documents'=>[['id'=>25,'nama'=>'Rapor Bahasa Inggris','display_rows'=>[
        ['label'=>'Memahami percakapan','value'=>'Berkembang Sesuai Harapan'],
        ['label'=>'Catatan guru','value'=>'Teks <script>alert(1)</script> aman di-escape'],
    ]]],
];
$reviewHtml = renderApprovalView($reviewFile, ['pageTitle'=>'Tinjau Persetujuan','breadcrumb'=>'','activeNavItem'=>'erapor-approval',
    'review'=>$review,'canApprove'=>true,'notice'=>null]);
$reviewDom = new DOMDocument(); @$reviewDom->loadHTML($reviewHtml); $reviewXPath = new DOMXPath($reviewDom);
approvalUiCheck($reviewXPath->query('//table[contains(@class,"data-table")]')->length === 1
    && $reviewXPath->query('//section[contains(@class,"ui-card")]')->length === 1, 'Review uses shared card and table components');
approvalUiCheck($reviewXPath->query('//table//tr/td[contains(.,"Memahami percakapan")]')->length === 1
    && str_contains($reviewHtml, 'Berkembang Sesuai Harapan'), 'Only assigned document rows and values are rendered');
approvalUiCheck($reviewXPath->query('//script')->length === 0 && !str_contains($reviewHtml, '<script>alert(1)</script>'), 'Reviewer text is escaped');
approvalUiCheck($reviewXPath->query('//form[contains(@action,"/setujui")]//input[@name="csrf_token" and @value="approval-ui-csrf"]')->length === 1,
    'Approval form includes a CSRF token');
approvalUiCheck(str_contains(uiButton('Setujui', 'primary', ['attributes'=>['data-modal-open'=>'modal-setujui-erapor-55-9']] ), 'data-modal-open="modal-setujui-erapor-55-9"'),
    'Approval trigger uses the shared button component and opens the confirmation modal');
approvalUiCheck(str_contains($reviewHtml, 'tidak dapat dibatalkan'), 'Confirmation warns approval is irreversible');
$review['approval']['status'] = 'DISETUJUI';
$readOnly = renderApprovalView($reviewFile, ['pageTitle'=>'Tinjau Persetujuan','breadcrumb'=>'','activeNavItem'=>'erapor-approval',
    'review'=>$review,'canApprove'=>false,'notice'=>null]);
approvalUiCheck(!str_contains($readOnly, 'data-modal-open="modal-setujui-erapor-55-9"')
    && str_contains($readOnly, 'Persetujuan tercatat'), 'Read-only/completed review has no approval control');

echo "PASS: approval inbox/review templates use shared UI, scoped values, escaped content, and irreversible CSRF-confirmed action.\n";
