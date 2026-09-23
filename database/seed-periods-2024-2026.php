<?php
/** One-off, explicitly authorized local reset. Snapshot first; never run from HTTP. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
require CONFIG_PATH . '/config.php';
require ROOT_PATH . '/app/core/Database.php';
require ROOT_PATH . '/app/core/Model.php';
foreach (['PeriodePenilaian', 'SesiPembagianRapor', 'Rapor', 'ReportWorkflow'] as $model) {
    require ROOT_PATH . '/app/models/' . $model . '.php';
}
$db = Database::getInstance();
if (($argv[1] ?? '') === '--verify') {
    $rows = $db->query("SELECT p.nama,p.semester,p.tipe,p.awal_periode,p.akhir_periode,COUNT(DISTINCT s.id) AS sessions,COUNT(r.id) AS reports FROM periode_penilaian p JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id LEFT JOIN sesi_pembagian_rapor s ON s.periode_id=p.id LEFT JOIN rapor r ON r.sesi_pembagian_id=s.id WHERE (t.tahun_awal=2024 AND t.tahun_akhir=2025) OR (t.tahun_awal=2025 AND t.tahun_akhir=2026) GROUP BY p.id ORDER BY p.awal_periode")->fetchAll();
    if (count($rows) !== 8 || (int)$db->query('SELECT COUNT(*) FROM periode_penilaian WHERE id IN (2,4)')->fetchColumn() !== 0) {
        throw new RuntimeException('Period reset verification failed.');
    }
    foreach ($rows as $row) {
        if ((int)$row['sessions'] !== 1 || !isset(ReportWorkflow::SEMESTERS[$row['semester']])) throw new RuntimeException('Invalid period/session structure.');
    }
    echo json_encode(['periods'=>$rows, 'active_year'=>$db->query('SELECT tahun_awal,tahun_akhir FROM tahun_ajaran WHERE is_active=1')->fetchAll()], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}
function periodSnapshot(PDO $db): array
{
    $queries = [
        'periode_penilaian' => 'SELECT * FROM periode_penilaian WHERE id IN (2,4) ORDER BY id',
        'sesi_pembagian_rapor' => 'SELECT * FROM sesi_pembagian_rapor WHERE periode_id IN (2,4) ORDER BY id',
        'rapor' => 'SELECT r.* FROM rapor r JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id WHERE s.periode_id IN (2,4) ORDER BY r.id',
        'rapor_nilai' => 'SELECT n.* FROM rapor_nilai n JOIN rapor r ON r.id=n.rapor_id JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id WHERE s.periode_id IN (2,4) ORDER BY n.id',
        'rapor_catatan_guru' => 'SELECT n.* FROM rapor_catatan_guru n JOIN rapor r ON r.id=n.rapor_id JOIN sesi_pembagian_rapor s ON s.id=r.sesi_pembagian_id WHERE s.periode_id IN (2,4) ORDER BY n.id',
    ];
    $snapshot = [];
    foreach ($queries as $table => $sql) $snapshot[$table] = $db->query($sql)->fetchAll();
    return $snapshot;
}
if (($argv[1] ?? '') === '--snapshot') {
    $db->beginTransaction();
    $snapshot = periodSnapshot($db);
    $db->commit();
    echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
if (($argv[1] ?? '') !== '--apply') {
    exit("Use --snapshot, save its JSON privately as database/backups/periods-before-2024-2026.json, then --apply.\n");
}
$backup = json_decode(file_get_contents(__DIR__ . '/backups/periods-before-2024-2026.json'), true, 512, JSON_THROW_ON_ERROR);
$db->beginTransaction();
try {
    $old = $db->query('SELECT * FROM periode_penilaian WHERE id IN (2,4) ORDER BY id FOR UPDATE')->fetchAll();
    if (count($old) !== 2 || $old[0]['nama'] !== 'Penilaian Rapor Tengah Semester 2025/2026'
        || $old[1]['nama'] !== 'Periode test' || $old[0]['semester'] !== null || $old[1]['semester'] !== null) {
        throw new RuntimeException('Target periods changed or reset already applied. No data changed.');
    }
    if (periodSnapshot($db) !== $backup) throw new RuntimeException('Backup does not match current dependent data. Take a new snapshot.');
    // Refuse an unexpected cascade into tables not included in the backup.
    $rules = $db->query("SELECT TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('periode_penilaian','sesi_pembagian_rapor','rapor','rapor_nilai','rapor_catatan_guru')")->fetchAll();
    $expected = ['sesi_pembagian_rapor'=>'periode_penilaian', 'rapor'=>'sesi_pembagian_rapor', 'rapor_nilai'=>'rapor', 'rapor_catatan_guru'=>'rapor'];
    foreach ($rules as $rule) {
        if (($expected[$rule['TABLE_NAME']] ?? null) !== $rule['REFERENCED_TABLE_NAME'] || $rule['DELETE_RULE'] !== 'CASCADE') {
            throw new RuntimeException('Unexpected dependent table or deletion rule; inspect before proceeding.');
        }
    }
    if (count($rules) !== count($expected)) throw new RuntimeException('Missing expected cascade constraints.');
    $db->exec('DELETE FROM periode_penilaian WHERE id IN (2,4)');
    $model = new PeriodePenilaian();
    $created = [];
    foreach ([2024, 2025] as $start) {
        $end = $start + 1;
        $year = $db->prepare('SELECT id FROM tahun_ajaran WHERE tahun_awal=? AND tahun_akhir=? FOR UPDATE');
        $year->execute([$start, $end]);
        $yearId = $year->fetchColumn();
        if (!$yearId) {
            $db->prepare('INSERT INTO tahun_ajaran(tahun_awal,tahun_akhir,is_active) VALUES(?,?,0)')->execute([$start,$end]);
            $yearId = $db->lastInsertId();
        }
        // Example dates only: editable in Periode Rapor, not an official school schedule.
        foreach ([['ganjil','Tengah Semester', "$start-09-01", "$start-09-30"],
                  ['ganjil','Akhir Semester', "$start-12-01", "$start-12-20"],
                  ['genap','Tengah Semester', "$end-03-01", "$end-03-31"],
                  ['genap','Akhir Semester', "$end-06-01", "$end-06-20"]] as [$semester,$type,$from,$to]) {
            $id = (int) $model->create(['tahun_ajaran_id'=>$yearId, 'nama'=>"Rapor $type " . ucfirst($semester) . " $start/$end",
                'semester'=>$semester, 'tipe'=>$type, 'kategori'=>'Rapor Murid', 'awal_periode'=>$from, 'akhir_periode'=>$to]);
            ReportWorkflow::syncPeriod($id);
            $created[] = $id;
        }
    }
    $db->commit();
    echo json_encode(['deleted'=>array_map('count',$backup), 'created_period_ids'=>$created], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
