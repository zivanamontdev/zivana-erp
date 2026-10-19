<?php
/** Pure render regression: PPI reminder supplements the main report status, without extra actions. */
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
$_SESSION=['csrf_token'=>'synthetic-token','csrf_token_expires'=>time()+3600];
class RoleMiddleware {public function check(...$args):bool{return true;}}
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/EraporOverview.php';require ROOT_PATH.'/app/models/EraporPackagePlan.php';
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$render=static function(string $file,array $vars):DOMXPath{
    extract($vars);$source=file_get_contents(VIEW_PATH.'/'.$file);
    $source=preg_replace("~require VIEW_PATH\\s*\\.\\s*'/layouts/(?:shell-header|shell-footer)\\.php';~",'',$source);
    ob_start();eval('?>'.$source);$html=ob_get_clean();$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);return new DOMXPath($dom);
};
$students=[];foreach(['Belum diajukan','PPI disertakan','Sudah diajukan','Regular','Belum Finalisasi'] as $i=>$name){$students[]=['id'=>$i+1,'sesi_id'=>$i+101,'nama_lengkap'=>$name.' <aman>','level_kelas'=>'Ranting','nama_kelas'=>'Uji','sesi_status'=>$i===4?'BELUM_DIISI':'SELESAI','action'=>'open','action_label'=>$i===4?'Isi Rapor':'Lihat Rapor','ppi_pending_submission'=>$i===0];}
$period=['id'=>1,'nama'=>'Periode Uji','tahun_label'=>'2026/2027'];
$xp=$render('portal-guru/erapor-dashboard.php',['students'=>$students,'selectedPeriod'=>$period,'periodOptions'=>[$period+['label'=>'Periode Uji']],'agendaCurrent'=>null,'agendaNext'=>null]);
$check($xp->query('//*[@data-erapor-ppi-pending]')->length===1,'Teacher list renders only the explicit pending reminder');
$row=$xp->query('//tr[.//a[@href="/portal-guru/sesi/101"]]')->item(0);
$check($row&&str_contains($row->textContent,'PPI belum diajukan')&&str_contains($row->textContent,'Lihat Rapor'),'Published main report keeps its own action beside the PPI reminder');
$check($row&&str_contains($row->textContent,'<aman>')&&$xp->query('//aman')->length===0,'Names remain escaped in reminder rows');
$check($xp->query('//*[@data-erapor-ppi-pending]//a|//*[@data-erapor-ppi-pending]//button')->length===0,'Reminder is a badge, not another action button');
$rows=[];foreach($students as $i=>$s)$rows[]=$s+['status_kondisi'=>$i===3?'Regular':'ABK','status'=>'TERBIT','filled'=>1,'required'=>1,'tahap'=>'4/4','guru'=>'Guru Uji','guru_sesi'=>'Guru Uji','has_official_pdf'=>true];
$xp=$render('admin/rapor-murid/erapor-index.php',['tahunOptions'=>[['id'=>1,'tahun_awal'=>2026,'tahun_akhir'=>2027]],'tahunId'=>1,'periodeList'=>[['id'=>1,'nama'=>'Periode Uji','awal_periode'=>'2026-09-01','akhir_periode'=>'2026-10-31','opened'=>5,'rows'=>$rows]]]);
$check($xp->query('//*[@data-erapor-ppi-pending]')->length===1,'Superadmin list also exposes the pending PPI reminder');
$row=$xp->query('//tr[.//a[@href="/erapor/sesi/101/pdf"]]')->item(0);
$check($row&&str_contains($row->textContent,'Rapor telah terbit')&&str_contains($row->textContent,'PPI belum diajukan'),'Main publication label is not replaced by the separate PPI status');
$check($row&&str_contains($row->textContent,'PDF Resmi'),'Existing official PDF action is preserved');
echo "PASS: $checks PPI list badge UI checks (teacher/Superadmin, main status preserved, escaping, no extra actions).\n";
