<?php
// Read-only listing controller against an isolated in-memory database.
class Database {
    public static PDO $db;
    public static function getInstance(): PDO { return self::$db; }
}
class Controller {
    public array $data;
    public array $inputs = [];
    protected function middleware(...$args) {}
    protected function input($name, $default = null) { return $this->inputs[$name] ?? $default; }
    protected function view($name, $data) { $this->data = $data; }
}
class RoleMiddleware { public function check(...$args) { return true; } }
require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/models/Jabatan.php';
require __DIR__ . '/../app/models/KelasGuruMurid.php';
require __DIR__ . '/../app/controllers/ManajemenGuruController.php';
$db = Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE jabatan(id INTEGER, nama TEXT, is_active INTEGER);
CREATE TABLE karyawan(id INTEGER, nama TEXT, jabatan_id INTEGER, is_active INTEGER);
CREATE TABLE kelas(id INTEGER, nama_kelas TEXT, level_kelas TEXT);
CREATE TABLE murid(id INTEGER, nama_lengkap TEXT, kelas_id INTEGER);
CREATE TABLE kelas_guru_murid(guru_id INTEGER, murid_id INTEGER, kelas_id INTEGER);
INSERT INTO jabatan VALUES(1,'Guru Kelas',1),(2,'Guru Shadow',1),(3,'Admin',1);
INSERT INTO karyawan VALUES(1,'Guru A',1,1),(2,'Guru B',2,1),(3,'Guru C',1,1),(4,'Admin',3,1),(5,'Nonaktif',1,0);
INSERT INTO kelas VALUES(1,'Akasia','Ranting');
INSERT INTO murid VALUES(1,'Murid A',1);
INSERT INTO kelas_guru_murid VALUES(1,1,1);");
$controller = new ManajemenGuruController();
$controller->index();
$teachers = $controller->data['guruList'];
if (array_column($teachers, 'id') !== [1,2,3]) throw new Exception('Must include all active teachers of both positions');
if (count($teachers[0]['murid']) !== 1 || $teachers[1]['murid'] !== [] || $teachers[2]['murid'] !== []) throw new Exception('Separate student lists including empty teachers');
$controller->inputs = ['jabatan_id'=>'2'];
$controller->index();
if (array_column($controller->data['guruList'], 'id') !== [2]) throw new Exception('Position filter');
$controller->inputs = ['q'=>'Guru C'];
$controller->index();
if (array_column($controller->data['guruList'], 'id') !== [3]) throw new Exception('Search filter');
echo "PASS: multiple classroom/shadow teachers, separate student lists, empty lists, filters and exclusion of nonteachers/inactive employees.\n";
