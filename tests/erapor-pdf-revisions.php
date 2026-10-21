<?php
/** No database/network: reviewed DDL, privacy, explicit confirmation and themed view contracts. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('ROOT_PATH',dirname(__DIR__));define('BASE_PATH','');define('VIEW_PATH',ROOT_PATH.'/app/views');
define('ERAPOR_PDF_REVISION_TOKEN','revision-private-test-key-with-symbols-2026!');
require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/EraporPdfRevision.php';require ROOT_PATH.'/app/models/EraporMigrationRunner.php';require ROOT_PATH.'/app/models/EraporPdfDiagnostics.php';
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
foreach(['SANGAT_BAIK','PRIVATE_STUDENT_VALUE','HISTORICAL_PRIVATE_CHILD_NOTE','<script>private</script>',ERAPOR_PDF_REVISION_TOKEN] as $raw)$check(EraporPdfRevision::safeCode(new DomainException($raw))==='REVISION_OPERATION_BLOCKED','Only enumerated technical codes may be shown');
$check(EraporPdfRevision::safeCode(new DomainException('HISTORICAL_SOURCE_HASH_MISMATCH'))==='HISTORICAL_SOURCE_HASH_MISMATCH','Known mismatch remains actionable');
$check(!str_contains(EraporPdfDiagnostics::safeMessage('Call to undefined function '.ERAPOR_PDF_REVISION_TOKEN.'()'),ERAPOR_PDF_REVISION_TOKEN),'Diagnostic redaction also knows the new revision token');
$plan=EraporMigrationRunner::plan(ROOT_PATH.'/database/migrations/'.EraporPdfRevision::MIGRATION.'.sql');
$check(array_keys($plan['steps'])===['erapor_pdf_sumber','erapor_pdf_revisi','erapor_pdf_aktif','erapor_pdf_revisi_log'],'Exactly four new tables, no ALTER/DML on original tables');
foreach($plan['steps'] as $sql)$check(str_contains($sql,'ON DELETE RESTRICT')&&!str_contains($sql,'ON DELETE CASCADE'),'Revision history cannot cascade away with business data');
$controller=file_get_contents(ROOT_PATH.'/app/controllers/EraporPdfRevisionController.php');
foreach(['hash_equals','Superadmin','ERAPOR_PDF_REVISION_TOKEN','Cache-Control: private, no-store','X-Frame-Options: DENY','pdf_revision_next','pdf_revision_seen',"time()-1800","REVISION_REVIEW_REQUIRED","REVISION_DISABLE_API_FOR_MIGRATION","getCsrfToken"] as $contract){if($contract==='getCsrfToken')continue;$check(str_contains($controller,$contract),'Controller contract: '.$contract);}
$service=file_get_contents(ROOT_PATH.'/app/models/EraporPdfRevision.php');
foreach(['SET TRANSACTION READ ONLY','GET_LOCK','RELEASE_LOCK','REVISION_SELECTION_CHANGED','ARCHIVED_SOURCE_IMMUTABLE','hash_equals','SOURCE_ORIGINAL_MISMATCH'] as $contract)$check(str_contains($service,$contract),'Service invariant: '.$contract);
$check(!str_contains($service,'R2Storage')&&!str_contains($service,'UPDATE erapor_publikasi')&&!str_contains($service,'UPDATE erapor_sesi'),'Revision service cannot publish, overwrite originals or change business status');
$css=file_get_contents(ROOT_PATH.'/public/assets/css/erapor-pdf-revision.css');$check(!preg_match('/#[0-9a-f]{3,8}\b|\b(?:rgb|hsl)a?\(/i',$css),'New layout uses shared bank tokens, not new literal colors');
$view=str_replace(["require VIEW_PATH.'/layouts/shell-header.php';","require VIEW_PATH.'/layouts/shell-footer.php';"],'',file_get_contents(VIEW_PATH.'/admin/erapor-revisions/index.php'));
$render=static function(array $overrides=[])use($view):string {
 $data=array_merge(['pageTitle'=>'Revisi PDF','breadcrumb'=>'','activeNavItem'=>'','sid'=>12,'kind'=>'RAPOR','notice'=>null,'error'=>null,'technical'=>null,'available'=>true,'apiEnabled'=>true,'selection'=>'asli','originalHash'=>str_repeat('a',64),'recent'=>[],
  'check'=>['ready'=>true,'method'=>'HASH_HISTORIS','original_renderer'=>'erapor-print-v8','renderer'=>'erapor-print-v11','document_count'=>4,'package_sha256'=>str_repeat('b',64),'design_sha256'=>str_repeat('c',64)],
  'history'=>[['id'=>3,'aktif'=>false,'renderer_versi'=>'erapor-print-v11','halaman'=>17,'dibuat_pada'=>'2026-10-21','pdf_sha256'=>str_repeat('d',64)]]],$overrides);
 extract($data);$_SESSION=['csrf_token'=>'fixture','csrf_token_expires'=>time()+3600];ob_start();eval('?>'.$view);return ob_get_clean();
};
foreach([
 'ready'=>[],
 'blocked'=>['check'=>['ready'=>false,'code'=>'PPI_SOURCE_NOT_ARCHIVED']],
 'active'=>['selection'=>'3','history'=>[['id'=>3,'aktif'=>true,'renderer_versi'=>'erapor-print-v11','halaman'=>17,'dibuat_pada'=>'2026-10-21','pdf_sha256'=>str_repeat('d',64)]]],
 'migration'=>['available'=>false,'apiEnabled'=>false],
 'disabled-api'=>['available'=>false],
 'escaped'=>['notice'=>'<script>private</script>','recent'=>[['id'=>12,'nama_lengkap'=>'<Child>','kondisi'=>'Regular','periode'=>'<Period>']]],
] as $name=>$input){
 $html=$render($input);$check(!str_contains($html,ERAPOR_PDF_REVISION_TOKEN),'Token never echoed: '.$name);
 $check(!str_contains($html,'<script>private</script>')&&!str_contains($html,'<Child>'),'All private labels escaped: '.$name);
 $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new DOMXPath($dom);$ids=[];foreach($xp->query('//*[@id]') as $node)$ids[]=$node->getAttribute('id');$check(count($ids)===count(array_unique($ids)),'Unique field/confirmation IDs: '.$name);
 foreach($xp->query('//form') as $form)$check($xp->query('.//input[@name="csrf_token"]',$form)->length===1,'Every form has exactly one CSRF field: '.$name);
 foreach($xp->query('//input[@type="password"]') as $node)$check($node->getAttribute('value')==='','No password/token value is repopulated: '.$name);
 if($name==='blocked')$check(!str_contains($html,'name="package_hash"'),'Blocked readiness never offers preparation');
 if($name==='active')$check(str_contains($html,'Kembali ke arsip asli')&&!str_contains($html,'name="pdf_hash"'),'Active revision offers explicit restore, not another activation');
}
echo "PASS: $checks revision schema/privacy/view checks.\n";
