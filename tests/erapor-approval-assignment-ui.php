<?php
define('ROOT_PATH',dirname(__DIR__)); define('VIEW_PATH',ROOT_PATH.'/app/views'); define('BASE_PATH','');
$_SESSION=['csrf_token'=>'assignment-csrf','csrf_token_expires'=>time()+3600];
require ROOT_PATH.'/app/helpers/functions.php'; require ROOT_PATH.'/app/helpers/ui.php';
function assignmentUiCheck(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
function renderAssignment(array $data): string {
    extract($data); $source=file_get_contents(VIEW_PATH.'/admin/erapor-approval/assignments.php');
    $source=str_replace(["require VIEW_PATH.'/layouts/shell-header.php';","require VIEW_PATH.'/layouts/shell-footer.php';"],'',$source);
    ob_start(); eval('?>'.$source); return ob_get_clean();
}
$flow=['id'=>1,'code'=>'KOORDINATOR_QURAN','label'=>'Koordinator Al-Qur’an','rank'=>1,'scope'=>'TERBATAS','pending'=>2,'users'=>[
    ['id'=>14,'email'=>'guru@example.test','nama'=>'Guru Aktif','jabatan'=>'Guru Kelas','eligible'=>true,'assigned'=>true,'ineligible_reason'=>''],
    ['id'=>15,'email'=>'lama@example.test','nama'=>'Akun Lama','jabatan'=>'Admin','eligible'=>false,'assigned'=>true,'ineligible_reason'=>'akun/pegawai/jabatan nonaktif'],
]];
$html=renderAssignment(['pageTitle'=>'Penugasan Penyetuju','state'=>['ready'=>true,'flows'=>[$flow]],'configurationIssue'=>null,'notice'=>null]);
$dom=new DOMDocument(); @$dom->loadHTML($html); $xpath=new DOMXPath($dom);
assignmentUiCheck($xpath->query('//form[contains(@action,"/erapor/persetujuan/penugasan")]')->length===1,'Uses standard POST form and setup route');
assignmentUiCheck($xpath->query('//input[@name="csrf_token" and @value="assignment-csrf"]')->length===1,'Form includes CSRF token');
assignmentUiCheck($xpath->query('//section[contains(@class,"ui-card") and contains(@class,"ui-card--outlined")]')->length===1,'Flow is wrapped by shared outlined card');
assignmentUiCheck($xpath->query('//input[@type="checkbox" and @name="assignments[KOORDINATOR_QURAN][]" and @value="14" and @checked]')->length===1,'Active explicit assignment renders selected checkbox');
assignmentUiCheck(str_contains($html,'akun/pegawai/jabatan nonaktif') && str_contains($html,'2 rapor menunggu'),'Shows invalid legacy assignment and pending impact');
assignmentUiCheck($xpath->query('//textarea[@name="reason" and @required]')->length===1,'Audit reason is required through shared field component');
assignmentUiCheck($xpath->query('//button[@type="submit" and contains(@class,"ui-button--primary")]')->length===1,'Uses shared primary button');
assignmentUiCheck(!preg_match('/#[0-9a-f]{3,8}\b/i',$html),'Rendered assignment UI contains no hardcoded hex colors');
$blocked=renderAssignment(['pageTitle'=>'Penugasan Penyetuju','state'=>['ready'=>false,'flows'=>[]],'configurationIssue'=>'Konfigurasi belum siap.','notice'=>null]);
assignmentUiCheck(str_contains($blocked,'Konfigurasi belum siap.') && !str_contains($blocked,'Simpan Penugasan'),'Broken configuration cannot be edited');
echo "PASS: approver assignment UI uses shared components, CSRF, explicit checkboxes, audit reason and fail-closed configuration state.\n";
