<?php
// Isolated fatal-path fixture. In-memory SQLite for the guard; never loads project .env or a real DB.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');
define('ROOT_PATH',dirname(__DIR__,2));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
define('ERAPOR_API_ENABLED',true);define('ERAPOR_PDF_DIAGNOSTIC_TOKEN',str_repeat('test-maintenance-token-',3));define('DB_PASS','fixture-private-password');
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/core/Controller.php';
// Keep the production error sanitizer; replace only the probe to deterministically trigger an uncatchable E_USER_ERROR.
$source=file_get_contents(ROOT_PATH.'/app/models/EraporPdfDiagnostics.php');
eval('?>'.str_replace('final class EraporPdfDiagnostics','final class DiagnosticErrorSanitizer',$source));
final class EraporPdfDiagnostics {
    public static function run(...$args): array {trigger_error('Private child note in fatal test fixture-private-password',E_USER_ERROR);return [];}
    public static function safeError(Throwable $e): array {return DiagnosticErrorSanitizer::safeError($e);}
}
final class Database {
    public static function getInstance(): PDO {
        static $db;if(!$db){$db=new PDO('sqlite::memory:');$db->exec("CREATE TABLE roles(id INTEGER,nama TEXT);CREATE TABLE users(id INTEGER,role_id INTEGER,is_active INTEGER);INSERT INTO roles VALUES(1,'Superadmin');INSERT INTO users VALUES(1,1,1)");}return $db;
    }
}
final class AuthMiddleware {public function handle(): void {}}
$_SERVER['REQUEST_METHOD']='POST';$_SESSION=['user_id'=>1,'csrf_token'=>'fixture-csrf','csrf_token_expires'=>time()+100];
$_POST=['csrf_token'=>'fixture-csrf','token'=>ERAPOR_PDF_DIAGNOSTIC_TOKEN,'mode'=>'render','session_id'=>'1'];
require ROOT_PATH.'/app/controllers/EraporPdfDiagnosticController.php';
(new EraporPdfDiagnosticController())->run();
