<?php
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', ROOT_PATH . '/app/views');
define('BASE_PATH', '');
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';
$_SESSION = ['csrf_token' => 'fixture'];
$kelas = ['id'=>3];
$group = ['guru_id'=>7,'nama_guru'=>'Guru A','nama_jabatan'=>'Guru Kelas','murid'=>[['id'=>1]]];
$muridDiKelasIni = [['id'=>1,'nama_lengkap'=>'Murid A'],['id'=>2,'nama_lengkap'=>'Murid B'],['id'=>3,'nama_lengkap'=>'Murid C']];
$assignedElsewhere = [1=>7,2=>8];
ob_start(); require VIEW_PATH . '/admin/kelas/_assignment-modal.php'; $classHtml=ob_get_clean();
$guru = ['id'=>7,'nama'=>'Guru A','nama_jabatan'=>'Guru Kelas','murid'=>[['id'=>1]]];
$muridOptions = array_map(static fn($s)=>$s+['level_kelas'=>'Ranting','nama_kelas'=>'Akasia','assigned_guru_id'=>$assignedElsewhere[$s['id']] ?? null], $muridDiKelasIni);
ob_start(); require VIEW_PATH . '/admin/manajemen-guru/_assignment-modal.php'; $teacherHtml=ob_get_clean();
foreach ([$classHtml, $teacherHtml] as $html) {
    $dom=new DOMDocument(); @$dom->loadHTML($html); $x=new DOMXPath($dom);
    foreach (['//*[contains(@class,"ui-modal--assignment")]','//*[@data-assign-template]','//input[@name="guru_id" and @value="7"]','//input[@name="csrf_token"]'] as $query) {
        if($x->query($query)->length!==1) throw new Exception($query);
    }
    if($x->query('//option[@value="2"]')->length) throw new Exception('Student assigned to another teacher offered');
    if($x->query('//*[contains(@class,"ui-field--compact")]')->length!==2) throw new Exception('Compact existing/template rows');
    if(!str_contains($html,'value="1" selected')) throw new Exception('Existing selection');
}
if(!str_contains($classHtml,'action="/kelas/3/guru-murid"') || !str_contains($teacherHtml,'action="/manajemen-guru/7/murid"')) throw new Exception('Form scope');
$source=file_get_contents(VIEW_PATH.'/admin/kelas/show.php');
if(str_contains($source,'modal-tambah-guru')) throw new Exception('Obsolete add teacher modal');
echo "PASS: shared modal, compact rows, correct form scopes, selected students, exclusion of other assignments and removal of add-teacher modal.\n";
