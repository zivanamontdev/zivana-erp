<?php
/** CLI-only local backup, ready to import into an EMPTY database in phpMyAdmin.
 * php database/export-local.php --mysqldump="path/to/mysqldump.exe" --verify
 * Uses .env (never .env.example). Does not print credentials or modify local data.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ROOT_PATH',dirname(__DIR__));define('CONFIG_PATH',ROOT_PATH.'/config');
require CONFIG_PATH.'/config.php';require ROOT_PATH.'/app/core/Database.php';
session_write_close();
$args=getopt('', ['mysqldump:','verify']);
if(empty($args['mysqldump'])) { echo "Specify --mysqldump=/path/to/mysqldump; optionally --verify\n";exit(1); }
if (!in_array(DB_HOST,['127.0.0.1','localhost','::1'],true)) throw new RuntimeException('Export is restricted to local databases.');
$db=Database::getInstance();
$backup=ROOT_PATH.'/database/backups';
if(!is_dir($backup) && !mkdir($backup,0700,true)) throw new RuntimeException('Cannot create backup directory.');
$target=$backup.'/zivana-local-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
$config=tempnam(sys_get_temp_dir(),'zivana-dump-');
$escape=fn($value)=>str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','\n','\r'],(string)$value);
try {
    chmod($config,0600);
    file_put_contents($config,"[client]\nhost=\"".$escape(DB_HOST)."\"\nport=".(int)DB_PORT."\nuser=\"".$escape(DB_USER)."\"\npassword=\"".$escape(DB_PASS)."\"\n");
    $command=[$args['mysqldump'],'--defaults-extra-file='.$config,'--single-transaction','--skip-lock-tables',
        '--no-tablespaces','--set-gtid-purged=OFF','--column-statistics=0','--default-character-set=utf8mb4',
        '--hex-blob','--skip-add-drop-table','--result-file='.$target,DB_NAME];
    $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($process)) throw new RuntimeException('Cannot start mysqldump.');
    fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);
    $error=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($process);
    if($code!==0) throw new RuntimeException('mysqldump failed (exit '.$code.'). Backup is incomplete. '.str_replace([DB_PASS],['[redacted]'],$error));
} finally { if(is_file($config)) unlink($config); }
$sql=file_get_contents($target);
if(!$sql || !str_contains($sql,'Dump completed')) throw new RuntimeException('Dump completion marker missing.');
if(preg_match('/^(?:CREATE DATABASE|USE |DROP TABLE)/mi',$sql)) throw new RuntimeException('Unexpected database selection or destructive table statement.');
$counts=[];
foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table){
    $name=$table[0];$quoted='`'.str_replace('`','``',$name).'`';
    $counts[$name]=(int)$db->query("SELECT COUNT(*) FROM $quoted")->fetchColumn();
}
$verified=false;
if(array_key_exists('verify',$args)){
    $testName='zivana_restore_'.bin2hex(random_bytes(8));$created=false;
    $restore=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    try {
        $restore->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;
        $restore->exec("USE `$testName`");$restore->exec($sql);
        foreach($counts as $name=>$count){
            $quoted='`'.str_replace('`','``',$name).'`';
            if((int)$restore->query("SELECT COUNT(*) FROM $quoted")->fetchColumn()!==$count) throw new RuntimeException('Restore count mismatch: '.$name);
        }
        $verified=true;
    } finally {
        if($created && preg_match('/^zivana_restore_[a-f0-9]{16}$/D',$testName) && $testName!==DB_NAME) $restore->exec("DROP DATABASE `$testName`");
    }
}
$gzip=$target.'.gz';file_put_contents($gzip,gzencode($sql,9));
$report=['sql'=>basename($target),'gzip'=>basename($gzip),'sql_bytes'=>strlen($sql),'gzip_bytes'=>filesize($gzip),
    'sha256'=>hash_file('sha256',$target),'restore_verified'=>$verified,'row_counts'=>$counts,
    'warning'=>'Contains user data, password hashes and demo accounts. Import into empty database; keep private; disable demo accounts before production.'];
file_put_contents($target.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
