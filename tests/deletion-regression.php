<?php
// Run: php tests/deletion-regression.php. Uses only an in-memory database.
declare(strict_types=1);

class Database
{
    public static PDO $db;
    public static function getInstance(): PDO { return self::$db; }
}

// Isolate persistence behavior from HTTP redirects and session authentication.
class Controller
{
    protected function middleware(string $class, ...$args): void {}
    protected function redirect(string $path): void {}
}

require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/models/Jabatan.php';
require __DIR__ . '/../app/models/Karyawan.php';
require __DIR__ . '/../app/controllers/JabatanController.php';
require __DIR__ . '/../app/controllers/KaryawanController.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$_SESSION = [];
$db = Database::$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE jabatan (id INTEGER PRIMARY KEY, nama TEXT, is_active INTEGER)');
$db->exec('CREATE TABLE karyawan (id INTEGER PRIMARY KEY, jabatan_id INTEGER NOT NULL REFERENCES jabatan(id), is_active INTEGER)');
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, karyawan_id INTEGER REFERENCES karyawan(id), is_active INTEGER, remember_token TEXT)');

$positions = new Jabatan();
$controller = new JabatanController();
foreach ([1, 0] as $status) {
    $id = $positions->create(['nama' => 'New position', 'is_active' => $status]);
    $controller->destroy((string) $id);
    check($positions->find($id) === null, 'Delete must remove both active and inactive positions.');
}
$id = $positions->create(['nama' => 'Assigned position', 'is_active' => 1]);
$db->exec("INSERT INTO karyawan VALUES (1, $id, 1)");
$controller->deactivate((string) $id);
check((int) $positions->find($id)['is_active'] === 0, 'Deactivate must retain the position.');
$controller->activate((string) $id);
check((int) $positions->find($id)['is_active'] === 1, 'Activate must restore status.');
$controller->destroy((string) $id);
check(!empty($_SESSION['jabatan_delete_error']), 'Referenced position deletion must show an error.');
check((int) $positions->find($id)['is_active'] === 1, 'Failed delete must not deactivate.');

$employees = new KaryawanController();
$db->exec("INSERT INTO users VALUES (1, 1, 1, 'token')");
$employees->deactivate('1');
check((int) $db->query('SELECT is_active FROM karyawan WHERE id = 1')->fetchColumn() === 0, 'Employee deactivation must retain data.');
$employees->activate('1');
check((int) $db->query('SELECT is_active FROM users WHERE id = 1')->fetchColumn() === 1, 'Employee activation must restore login status.');
$employees->destroy('1');
check((int) $db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn() === 0, 'Employee delete must remove employee.');
check((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0, 'Employee delete must remove login.');

// If employee deletion fails, deletion of its login must roll back too.
$db->exec("INSERT INTO karyawan VALUES (2, $id, 0)");
$db->exec('INSERT INTO users VALUES (2, 2, 0, NULL)');
$db->exec('CREATE TABLE deletion_blocker (employee_id INTEGER REFERENCES karyawan(id))');
$db->exec('INSERT INTO deletion_blocker VALUES (2)');
try {
    $employees->destroy('2');
    throw new RuntimeException('Expected employee deletion to fail.');
} catch (PDOException $e) {
    check(!$db->inTransaction(), 'Failed delete must roll back transaction.');
}
check((int) $db->query('SELECT COUNT(*) FROM users WHERE id = 2')->fetchColumn() === 1, 'Rollback must preserve login.');
$db->exec('DELETE FROM deletion_blocker');
$employees->destroy('2');
check((int) $db->query('SELECT COUNT(*) FROM karyawan')->fetchColumn() === 0, 'Inactive employee must also be deleted.');
echo "PASS: position/employee deletion, separate status actions, FK protection, rollback.\n";
