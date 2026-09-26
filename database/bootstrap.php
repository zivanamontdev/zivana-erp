<?php
/**
 * Bangun ulang ekosistem LOKAL dari nol dalam satu perintah:
 *   schema.sql -> migrasi + seed rubrik eRapor (spesifikasi) -> jabatan, RBAC dasar, Superadmin -> seed uji coba (DataResetSeeder).
 *
 *   php database/bootstrap.php --fresh --superadmin-email=EMAIL --superadmin-password=PASS --seed-password=PASS [--data=pilot.xlsx]
 *
 * --fresh MENGHAPUS SEMUA TABEL di database .env. Hanya untuk database lokal (host localhost/127.0.0.1).
 * Production memakai /sistem/migrasi-erapor lalu /sistem/reset-data (cookbook/data-reset.md).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$opts = getopt('', ['fresh', 'superadmin-email:', 'superadmin-password:', 'seed-password:', 'data:']);
if (!isset($opts['fresh'], $opts['superadmin-email'], $opts['superadmin-password'], $opts['seed-password'])) {
    fwrite(STDERR, "Usage: php database/bootstrap.php --fresh --superadmin-email=EMAIL --superadmin-password=PASS --seed-password=PASS\n");
    exit(64);
}

// Installer eRapor menolak berjalan saat flag aktif; getenv() didahulukan config.php.
putenv('ERAPOR_API_ENABLED=false');
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('BASE_PATH', '');
require CONFIG_PATH . '/config.php';
require ROOT_PATH . '/vendor/autoload.php';
require APP_PATH . '/helpers/functions.php';
spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'middleware'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) { require $file; return; }
    }
});
if (!in_array(DB_HOST, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Menolak: bootstrap --fresh hanya untuk database lokal (DB_HOST=" . DB_HOST . ").\n");
    exit(1);
}
$say = static fn(string $m) => print('[bootstrap] ' . $m . PHP_EOL);

try {
    $db = Database::getInstance();
    $name = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    $say("Database: $name@" . DB_HOST);

    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $tables = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) $db->exec('DROP TABLE `' . str_replace('`', '', $table) . '`');
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $say('Tabel lama dihapus: ' . count($tables));

    $db->exec((string) file_get_contents(ROOT_PATH . '/database/schema.sql'));
    $say('schema.sql diimpor.');

    $result = EraporPilotInstaller::apply($db, ROOT_PATH, static fn(string $m) => $say('eRapor: ' . $m));
    $say('Migrasi eRapor selesai' . ($result['already_complete'] ? ' (sudah lengkap).' : '.'));

    $say('Jabatan ditambahkan: ' . DataBaseline::ensureJabatan($db));
    $say('Aksi izin tambahan (PermissionCatalog) ditambahkan: ' . DataBaseline::ensurePermissions($db));
    $say('Izin RBAC ditambahkan: ' . DataBaseline::grantRbac($db));
    DataBaseline::ensureSuperadmin($db, $opts['superadmin-email'], $opts['superadmin-password']);
    $superId = (int) $db->query('SELECT id FROM users WHERE email=' . $db->quote($opts['superadmin-email']))->fetchColumn();
    $say('Superadmin: ' . $opts['superadmin-email']);

    // --data=path.xlsx: guru & murid dari file "Data Piloting" (tidak disimpan di repository).
    $pilot = isset($opts['data']) ? PilotDataImport::fromXlsx($opts['data'], DataResetSeeder::EMAIL_DOMAIN, DataResetSeeder::SCHOOL_YEAR_START) : null;
    if ($pilot) $say('Data pilot: ' . count($pilot['teachers']) . ' guru, ' . count($pilot['students']) . ' murid, ' . count($pilot['classes']) . ' kelas.');
    $run = DataResetSeeder::run($db, $superId, $opts['seed-password'], static fn(string $m) => $say($m), $pilot);
    $say($run['warnings'] ? 'Selesai dengan ' . count($run['warnings']) . ' peringatan.' : 'Selesai tanpa peringatan.');
    exit($run['warnings'] ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, '[bootstrap] GAGAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
