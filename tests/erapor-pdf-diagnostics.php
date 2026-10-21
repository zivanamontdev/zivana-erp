<?php
// No database/network. Guard contracts, private error export and shared themed view.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('ROOT_PATH',dirname(__DIR__));define('BASE_PATH','');define('VIEW_PATH',ROOT_PATH.'/app/views');
define('DB_PASS','diagnostic-test-private-password');define('ERAPOR_PDF_DIAGNOSTIC_TOKEN',str_repeat('secret-token-',4));
require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/EraporPdfDiagnostics.php';
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
foreach([
 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'Nama Anak Rahasia\' in diagnostic-test-private-password',
 'Isi rahasia tanpa tanda petik milik anak dan catatan guru',
 'data:image/png;base64,'.str_repeat('a',90).' private@example.invalid https://example.invalid/private?token=123',
 "file_get_contents('/home/customer/private/user.png'): permission denied",
 'Runtime error diagnostic-test-private-password '.ERAPOR_PDF_DIAGNOSTIC_TOKEN,
] as $raw){$safe=EraporPdfDiagnostics::safeMessage($raw);foreach(['Nama Anak Rahasia','catatan guru','private@example.invalid','/home/customer','diagnostic-test-private-password',ERAPOR_PDF_DIAGNOSTIC_TOKEN,str_repeat('a',60),'token=123'] as $secret)$check(!str_contains($safe,$secret),'Sensitive content is absent from the error export');}
$check(str_contains(EraporPdfDiagnostics::safeMessage('SQLSTATE[42S22]: Column not found: 1054 Unknown column \'private\''),'42S22'),'SQLSTATE remains useful without raw driver text');
$check(EraporPdfDiagnostics::safeMessage('GOOD')!=='GOOD','Bare assessment codes are not treated as technical error codes');
$check(EraporPdfDiagnostics::safeMessage('3')!=='3','Bare numeric assessment values are hidden too');
$check(!str_contains(EraporPdfDiagnostics::safeMessage('Undefined array key PrivateChildValue'),'PrivateChildValue'),'Undefined-key warnings never disclose field values');
$check(str_contains(EraporPdfDiagnostics::safeMessage('Call to undefined function imagecreatefrompng()'),'imagecreatefrompng'),'Undefined function is diagnostically useful');
$check(str_contains(EraporPdfDiagnostics::safeMessage('EraporPublication::age(): Argument #1 ($birthDate) must be of type string, null given, called in '.ROOT_PATH.'/app/models/EraporPublication.php on line 103'),'must be of type'),'Type errors retain expected type information');
$error=EraporPdfDiagnostics::safeError(new RuntimeException('Unquoted private child note'));
$json=json_encode($error);$check(!str_contains($json,'Unquoted private child note')&&!str_contains($json,'args'),'No raw message or trace arguments');
$controller=file_get_contents(ROOT_PATH.'/app/controllers/EraporPdfDiagnosticController.php');
foreach(['hash_equals','Superadmin','ERAPOR_PDF_DIAGNOSTIC_TOKEN','Cache-Control: private, no-store','X-Frame-Options: DENY','pdf_diag_next','expires','register_shutdown_function'] as $contract)$check(str_contains($controller,$contract),'Controller guard: '.$contract);
$routes=file_get_contents(ROOT_PATH.'/routes/web.php');$check(str_contains($routes,"post('/sistem/diagnosis-pdf/unduh'"),'Report download requires POST and automatic CSRF');
$report=['id'=>str_repeat('a',24),'ok'=>false,'read_only'=>true,'error'=>['stage'=>'render_layout','message'=>'<script>unsafe</script>']];
$actors=[''=>'Pilih akun','1'=>'<Akun>'];$notice=null;$pageTitle='Diagnosis PDF';$activeNavItem='';$breadcrumb='';$headerActions='';
$_SESSION=['csrf_token'=>'fixture','csrf_token_expires'=>time()+3600];
$view=str_replace(["require VIEW_PATH.'/layouts/shell-header.php';","require VIEW_PATH.'/layouts/shell-footer.php';"],'',file_get_contents(VIEW_PATH.'/admin/erapor-diagnostics/index.php'));
ob_start();eval('?>'.$view);$html=ob_get_clean();
$check(str_contains($html,'&lt;script&gt;unsafe&lt;/script&gt;')&&!str_contains($html,'<script>unsafe</script>'),'Error view escapes diagnostics');
$check(str_contains($html,'data-pdf-diagnostic-form')&&str_contains($html,'name="csrf_token"'),'Form uses existing UI and CSRF');
$check(!str_contains($html,'name="token" value="'.ERAPOR_PDF_DIAGNOSTIC_TOKEN),'Maintenance token is never echoed');
$check(!str_contains($html,'Terbitkan PDF')&&!str_contains($html,'Jalankan Migrasi'),'No destructive/reissue action exists');
$process=proc_open([PHP_BINARY,__DIR__.'/fixtures/erapor-diagnostic-fatal.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
$fatalOutput=stream_get_contents($pipes[1]);$fatalStderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
$fatal=json_decode($fatalOutput,true);
$check($exit!==0 && ($fatal['fatal']??false) && ($fatal['read_only']??false),'Fatal shutdown returns a private structured JSON diagnosis');
$check(!str_contains($fatalOutput,'Private child note')&&!str_contains($fatalOutput,'fixture-private-password')&&!str_contains($fatalOutput,'test-maintenance-token'),'Fatal export is sanitized too');
$check(str_contains($controller,'X-CSRF-Token: '),'JSON download issues a fresh single-use CSRF token');
echo "PASS: $checks private PDF diagnostic checks.\n";
