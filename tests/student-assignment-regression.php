<?php
// php tests/student-assignment-regression.php -- isolated SQLite memory database.
declare(strict_types=1);
class Database {
    public static PDO $db;
    public static function getInstance(): PDO { return self::$db; }
}
require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/models/StudentReportSync.php';
require __DIR__ . '/../app/models/Kelas.php';
require __DIR__ . '/../app/models/Murid.php';
require __DIR__ . '/../app/models/KelasGuruMurid.php';
function verify(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function rejects(callable $action): void {
    try { $action(); } catch (DomainException $e) { return; }
    throw new RuntimeException('Expected invalid assignment rejection');
}
$db = Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, nama TEXT)');
$db->exec('CREATE TABLE jabatan (id INTEGER PRIMARY KEY, role_id INTEGER, nama TEXT)');
$db->exec('CREATE TABLE karyawan (id INTEGER PRIMARY KEY, jabatan_id INTEGER, is_active INTEGER)');
$db->exec('CREATE TABLE kelas (id INTEGER PRIMARY KEY, tahun_ajaran_id INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE periode_penilaian (id INTEGER PRIMARY KEY, tahun_ajaran_id INTEGER, semester TEXT)');
$db->exec('CREATE TABLE sesi_pembagian_rapor (id INTEGER PRIMARY KEY, periode_id INTEGER, template_id INTEGER, tanggal_selesai TEXT)');
$db->exec('CREATE TABLE rapor (id INTEGER PRIMARY KEY, murid_id INTEGER, guru_id INTEGER, status TEXT, sesi_pembagian_id INTEGER, template_id INTEGER, UNIQUE(murid_id,sesi_pembagian_id))');
$db->exec("CREATE TABLE murid (id INTEGER PRIMARY KEY, nama_lengkap TEXT, kelas_id INTEGER REFERENCES kelas(id) ON DELETE SET NULL, status TEXT DEFAULT 'bersekolah')");
$db->exec('CREATE TABLE kelas_guru_murid (id INTEGER PRIMARY KEY, kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE, guru_id INTEGER REFERENCES karyawan(id), murid_id INTEGER UNIQUE REFERENCES murid(id) ON DELETE CASCADE)');
$db->exec("INSERT INTO roles VALUES (1,'Guru'),(2,'Admin'); INSERT INTO jabatan VALUES (1,1,'Guru Kelas'),(2,2,'Admin'),(3,1,'Guru Shadow'); INSERT INTO karyawan VALUES (1,1,1),(2,3,1),(3,1,1),(4,1,0),(5,2,1); INSERT INTO kelas(id) VALUES (1),(2)");
for ($id = 1; $id <= 8; $id++) {
    $db->prepare('INSERT INTO murid(id,nama_lengkap,kelas_id) VALUES (?, ?, ?)')->execute([$id, 'Murid '.$id, $id <= 5 ? 1 : 2]);
}
$assign = new KelasGuruMurid();
$assign->replaceForGuru(1, [1,2,6,6,'']);
$assign->replaceForGuru(2, [3,4,7]);
$assign->replaceForGuru(3, [8]);
verify((int) $db->query('SELECT COUNT(*) FROM kelas_guru_murid')->fetchColumn() === 7, 'Cross-class assignment/deduplication');
rejects(fn() => $assign->replaceForGuru(2, [1,3]));
verify((int) $db->query('SELECT COUNT(*) FROM kelas_guru_murid WHERE guru_id=2')->fetchColumn() === 3, 'Conflicts must roll back whole replacement');
rejects(fn() => $assign->replaceForGuruInKelas(1, 1, [6]));
rejects(fn() => $assign->replaceForGuru(4, [5]));
rejects(fn() => $assign->replaceForGuru(5, [5]));
rejects(fn() => $assign->replaceForGuru(1, [999]));
$assign->replaceForGuruInKelas(1, 1, [1,2,5]);
verify((int) $db->query('SELECT COUNT(*) FROM kelas_guru_murid WHERE guru_id=1 AND kelas_id=2')->fetchColumn() === 1, 'Scoped assignment preserves other classes');
$students = new Murid();
$students->update(1, ['kelas_id'=>2]);
verify((int) $db->query('SELECT kelas_id FROM kelas_guru_murid WHERE murid_id=1')->fetchColumn() === 2, 'Class move synchronizes assignment');
verify((int) $db->query('SELECT guru_id FROM kelas_guru_murid WHERE murid_id=1')->fetchColumn() === 1, 'Class move retains teacher');
$students->update(1, ['kelas_id'=>null]);
verify((int) $db->query('SELECT COUNT(*) FROM kelas_guru_murid WHERE murid_id=1')->fetchColumn() === 0, 'No class clears assignment');
rejects(fn() => $assign->replaceForGuru(1, [1]));
$assign->replaceForGuruInKelas(1, 1, []);
$assign->replaceForGuru(2, [2,3,4,7]);
verify((int) $db->query('SELECT guru_id FROM kelas_guru_murid WHERE murid_id=2')->fetchColumn() === 2, 'Explicit release permits reassignment');
try {
    $db->exec('INSERT INTO kelas_guru_murid (kelas_id,guru_id,murid_id) VALUES (1,3,2)');
    throw new RuntimeException('Unique constraint must reject a second teacher');
} catch (PDOException $e) {}
(new Kelas())->delete(2);
verify((int) $db->query('SELECT COUNT(*) FROM murid')->fetchColumn() === 8, 'Deleting class preserves students');
verify((int) $db->query('SELECT COUNT(*) FROM murid WHERE id IN (6,7,8) AND kelas_id IS NULL')->fetchColumn() === 3, 'Deleting class clears class links');
verify((int) $db->query('SELECT COUNT(*) FROM kelas_guru_murid WHERE kelas_id=2')->fetchColumn() === 0, 'Deleting class clears teacher assignments');
echo "PASS: cross-class teachers, uniqueness, conflict rollback, wrong class, teacher eligibility, moves, release/reassign and class deletion.\n";
