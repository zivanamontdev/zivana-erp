<?php
/** Opt-in: restore a local backup into a random disposable DB; never migrate live DB.
 * php tests/erapor-catalog-mysql.php --run --backup=filename.sql
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args = getopt('', ['run', 'backup:']);
if (!isset($args['run'], $args['backup'])) { echo "Usage: --run --backup=filename.sql (database/backups only)\n"; exit(1); }
define('ROOT_PATH', dirname(__DIR__)); define('CONFIG_PATH', ROOT_PATH . '/config');
require CONFIG_PATH . '/config.php';
require ROOT_PATH . '/app/models/EraporMigrationRunner.php';
require ROOT_PATH . '/app/models/EraporRtsSeed.php';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!in_array(DB_HOST, ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local DB only.');
if (basename($args['backup']) !== $args['backup'] || !str_ends_with($args['backup'], '.sql')) throw new RuntimeException('Invalid backup name.');
$path = realpath(ROOT_PATH . '/database/backups/' . $args['backup']);
$root = realpath(ROOT_PATH . '/database/backups');
if (!$path || dirname($path) !== $root) throw new RuntimeException('Backup must remain in database/backups.');
$sql = file_get_contents($path);
if (preg_match('/^(?:CREATE DATABASE|USE |DROP TABLE|DROP DATABASE)/mi', $sql)) throw new RuntimeException('Backup must not select/drop databases or tables.');
$options = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
$db = new PDO($dsn, DB_USER, DB_PASS, $options);
$live = new PDO($dsn . ';dbname=' . DB_NAME, DB_USER, DB_PASS, $options);
$database = 'zivana_erapor_test_' . bin2hex(random_bytes(8));
$created = false; $checks = 0;
function catalogCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function catalogReject(callable $call, string $expected): void {
    try { $call(); } catch (Throwable $e) {
        catalogCheck(str_contains($e->getMessage(), $expected), 'Unexpected rejection: ' . $e->getMessage()); return;
    }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
/** Order independent, handles NULL and binary data; no contents printed. */
function catalogFingerprints(PDO $db, ?array $tables = null): array {
    $tables ??= $db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`','``',$table) . '`';
        $rows = [];
        foreach ($db->query('SELECT * FROM ' . $quoted) as $row) {
            $row = array_map(fn($v) => $v === null ? null : (string)$v, $row);
            $rows[] = hash('sha256', serialize($row));
        }
        sort($rows, SORT_STRING);
        $result[$table] = [count($rows), hash('sha256', implode('', $rows))];
    }
    ksort($result);
    return $result;
}
try {
    $live->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $live->exec('SET TRANSACTION READ ONLY'); $live->beginTransaction();
    $before = catalogFingerprints($live); $live->rollBack();
    $db->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true; $db->exec("USE `$database`"); $db->exec($sql);
    catalogCheck(catalogFingerprints($db) === $before, 'Restored content differs from current local DB; create a fresh backup.');
    $file = ROOT_PATH . '/database/migrations/20260924_erapor_catalog.sql';
    $plan = EraporMigrationRunner::plan($file);
    catalogCheck(count($plan['steps']) === 4, 'Four catalog tables');
    catalogCheck(EraporMigrationRunner::apply($db,$file) === 'applied', 'Initial apply');
    catalogCheck(EraporMigrationRunner::apply($db,$file) === 'already_applied', 'Idempotent retry');
    $other = new PDO($dsn . ';dbname=' . $database, DB_USER, DB_PASS, $options);
    $lock = substr('erapor_migration_' . hash('sha256',$database),0,64);
    $stmt = $other->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]);
    catalogCheck((int)$stmt->fetchColumn() === 1, 'Acquire competing migration lock');
    try { catalogReject(fn() => EraporMigrationRunner::apply($db,$file), 'Another migration'); }
    finally { $stmt = $other->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
    catalogCheck(catalogFingerprints($db, array_keys($before)) === $before, 'Legacy rows changed');
    $db->exec("INSERT INTO erapor_rubrik(kode,jenis_dokumen,nama,judul_cetak,versi,cakupan,cetak_gabung_periode,khusus_abk)
        VALUES('TEST_RTS','RTS','Fixture','Fixture',1,'TAHUNAN',1,0)");
    catalogReject(fn() => $db->exec("INSERT INTO erapor_rubrik_bagian(rubrik_id,kode,judul,jenis,wajib,urutan) VALUES(999,'X','X','rubrik',1,1)"), 'foreign key');
    $db->exec("INSERT INTO erapor_rubrik_bagian(rubrik_id,kode,judul,jenis,wajib,urutan) VALUES(1,'RTS','Semua','rubrik',1,1)");
    catalogReject(fn() => $db->exec("INSERT INTO erapor_rubrik_bagian(rubrik_id,kode,judul,jenis,wajib,urutan) VALUES(1,'RTS','Semua','rubrik',1,2)"), 'Duplicate');
    catalogReject(fn() => $db->exec('DELETE FROM erapor_rubrik WHERE id=1'), 'foreign key');
    catalogReject(fn() => $db->exec("INSERT INTO erapor_rubrik_bagian(rubrik_id,kode,judul,jenis,wajib,urutan) VALUES(1,'Z','Z','rubrik',1,0)"), 'Check constraint');
    $rtsFile=ROOT_PATH.'/database/migrations/20260924_erapor_rts.sql';
    catalogCheck(EraporMigrationRunner::apply($db,$rtsFile)==='applied','RTS schema');
    catalogCheck(EraporMigrationRunner::apply($db,$rtsFile)==='already_applied','RTS schema retry');
    $seed=EraporRtsSeed::load(ROOT_PATH.'/eRapor_Zivana_Spesifikasi/rubrik_rts_seed.json');
    // Deliberate failure halfway through import must roll back every inserted definition.
    $db->exec("CREATE TRIGGER fail_rts_import BEFORE INSERT ON erapor_rubrik_indikator FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture seed failure'");
    try { catalogReject(fn()=>EraporRtsSeed::apply($db,$seed),'fixture seed failure'); }
    finally { $db->exec('DROP TRIGGER fail_rts_import'); }
    catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_rubrik WHERE kode='RTS_MONTESSORI_V1'")->fetchColumn()===0,'Failed import rolled back');
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_rubrik_area')->fetchColumn()===0,'Child definitions rolled back');
    catalogCheck(EraporRtsSeed::apply($db,$seed)==='seeded','Official RTS seeded');
    foreach (['erapor_rubrik_area'=>8,'erapor_rubrik_sub_area'=>25,'erapor_rubrik_grup'=>2,'erapor_rubrik_indikator'=>175,'erapor_skala_nilai'=>4,'erapor_seed_history'=>1] as $table=>$count) {
        catalogCheck((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$count,$table.' count');
    }
    catalogCheck((int)$db->query('SELECT COUNT(*) FROM erapor_rubrik_sub_area WHERE implisit=1')->fetchColumn()===4,'Four implicit subareas');
    catalogCheck((int)$db->query("SELECT COUNT(*) FROM erapor_rubrik_indikator WHERE tujuan='Dua suku kata'")->fetchColumn()===2,'Duplicate labels retain distinct codes');
    foreach ($seed['area'] as $area) foreach ($area['sub_area'] as $sub) foreach ($sub['indikator'] as $item) {
        $q=$db->prepare('SELECT tujuan,aparatus FROM erapor_rubrik_indikator WHERE kode=?'); $q->execute([$item['kode']]);
        catalogCheck($q->fetch()===['tujuan'=>$item['tujuan'],'aparatus'=>$item['aparatus']],'Exact source text retained');
    }
    $rtsBefore=catalogFingerprints($db);
    catalogCheck(EraporRtsSeed::apply($db,$seed)==='already_seeded','Seed retry');
    catalogCheck(catalogFingerprints($db)===$rtsBefore,'Retry does not change content or timestamps');
    $changed=$seed; $changed['area'][0]['sub_area'][0]['indikator'][0]['tujuan']='Changed';
    catalogReject(fn()=>EraporRtsSeed::apply($db,$changed),'Seed changed');
    catalogCheck(catalogFingerprints($db)===$rtsBefore,'Changed source rejected without changes');
    $first=$seed['area'][0]['sub_area'][0]['indikator'][0];
    $q=$db->prepare('UPDATE erapor_rubrik_indikator SET tujuan=? WHERE kode=?'); $q->execute(['Drift',$first['kode']]);
    catalogReject(fn()=>EraporRtsSeed::apply($db,$seed),'Stored rubric drift');
    $q->execute([$first['tujuan'],$first['kode']]);
    $db->exec("UPDATE erapor_rubrik SET status='terkunci' WHERE kode='RTS_MONTESSORI_V1'");
    catalogCheck(EraporRtsSeed::apply($db,$seed)==='already_seeded','Locked rubric no-op');
    catalogReject(fn()=>EraporRtsSeed::apply($db,$changed),'Seed changed');
    $wrongGroup=(int)$db->query('SELECT id FROM erapor_rubrik_grup ORDER BY id LIMIT 1')->fetchColumn();
    catalogReject(fn()=>$db->exec('UPDATE erapor_rubrik_indikator SET grup_id='.$wrongGroup.' WHERE kode='.$db->quote($first['kode'])),'foreign key');
    require __DIR__.'/erapor-ummi-ppi-mysql.inc.php';
    require __DIR__.'/erapor-bing-mysql.inc.php';
    require __DIR__.'/erapor-agama-mysql.inc.php';
    require __DIR__.'/erapor-package-mysql.inc.php';
    require __DIR__.'/erapor-sessions-mysql.inc.php';
    require __DIR__.'/erapor-rts-entry-mysql.inc.php';
    require __DIR__.'/erapor-structured-entry-mysql.inc.php';
    require __DIR__.'/erapor-agama-entry-mysql.inc.php';
    require __DIR__.'/erapor-ummi-entry-mysql.inc.php';
    require __DIR__.'/erapor-completeness-mysql.inc.php';
    require __DIR__.'/erapor-approval-flow-mysql.inc.php';
    require __DIR__.'/erapor-reception-mysql.inc.php';
    require __DIR__.'/erapor-approve-mysql.inc.php';
    require __DIR__.'/erapor-extensions-mysql.inc.php';
    require __DIR__.'/erapor-teacher-form-mysql.inc.php';
    require __DIR__.'/erapor-teacher-dashboard-mysql.inc.php';
    require __DIR__.'/erapor-http-mysql.inc.php';
    require __DIR__.'/erapor-approval-assignment-mysql.inc.php';
    require __DIR__.'/erapor-signer-profile-mysql.inc.php';
    $db->exec("UPDATE erapor_migrations SET sha256=REPEAT('0',64)");
    catalogReject(fn() => EraporMigrationRunner::apply($db,$file), 'checksum');
    $db->prepare("UPDATE erapor_migrations SET sha256=?,status='applying'")->execute([$plan['sha256']]);
    catalogReject(fn() => EraporMigrationRunner::apply($db,$file), 'Partial');
    $db->exec("UPDATE erapor_migrations SET status='complete'");
    $db->exec('DROP TABLE erapor_rubrik_periode'); // Disposable test DB only.
    catalogReject(fn() => EraporMigrationRunner::apply($db,$file), 'table is missing');
    $db->exec($plan['steps']['erapor_rubrik_periode']);
    $db->exec('DELETE FROM erapor_migrations');
    catalogReject(fn() => EraporMigrationRunner::apply($db,$file), 'collision');
    $legacyAfter=catalogFingerprints($db,array_keys($before));
    $changedLegacy=array_keys(array_filter($before,fn($rows,$table)=>($legacyAfter[$table]??null)!==$rows,ARRAY_FILTER_USE_BOTH));
    catalogCheck($changedLegacy===[], 'Legacy rows changed after negative tests: '.implode(',',$changedLegacy));
    $live->exec('SET TRANSACTION READ ONLY'); $live->beginTransaction();
    catalogCheck(catalogFingerprints($live) === $before, 'Local DB changed during test'); $live->rollBack();
    echo "PASS: $checks checks; backup restored, legacy checksums unchanged, catalog/RTS/Ummi/PPI/BING/Agama constraints, atomic seeding, exact text, retry/drift/lock guards.\n";
} finally {
    if ($live->inTransaction()) $live->rollBack();
    if ($created && preg_match('/^zivana_erapor_test_[a-f0-9]{16}$/D',$database) && $database !== DB_NAME) {
        $db->exec("DROP DATABASE `$database`");
    }
}
