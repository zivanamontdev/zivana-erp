<?php

/** Temporary production installer. Delete this file after the pilot schema is installed. */
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_time_limit(180);

$root = dirname(__DIR__);
define('ROOT_PATH', $root);
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', APP_PATH . '/core');
define('MODEL_PATH', APP_PATH . '/models');
define('CONTROLLER_PATH', APP_PATH . '/controllers');
define('MIDDLEWARE_PATH', APP_PATH . '/middleware');
define('VIEW_PATH', APP_PATH . '/views');
define('HELPER_PATH', APP_PATH . '/helpers');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('BASE_PATH', '');

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}
spl_autoload_register(static function (string $class): void {
    foreach ([CORE_PATH, MODEL_PATH, CONTROLLER_PATH, MIDDLEWARE_PATH] as $path) {
        $file = $path . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
require HELPER_PATH . '/functions.php';
require CONFIG_PATH . '/config.php';

header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function eraporInstallerEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eraporInstallerRespond(int $status, string $title, string $message): void
{
    http_response_code($status);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . eraporInstallerEscape($title) . '</title><body style="font:16px/1.5 system-ui,sans-serif;max-width:680px;margin:10vh auto;padding:24px;color:#272727">';
    echo '<h1>' . eraporInstallerEscape($title) . '</h1><p>' . eraporInstallerEscape($message) . '</p></body></html>';
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    eraporInstallerRespond(405, 'Metode tidak didukung', 'Gunakan halaman ini melalui browser.');
}

try {
    (new AuthMiddleware())->handle();
    $db = Database::getInstance();
    $roleQuery = $db->prepare('SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $roleQuery->execute([(int)($_SESSION['user_id'] ?? 0)]);
    if ($roleQuery->fetchColumn() !== 'Admin') {
        eraporInstallerRespond(404, 'Halaman tidak ditemukan', 'Halaman tidak tersedia.');
    }
} catch (Throwable $error) {
    error_log('[eRapor temporary installer] Authentication/bootstrap failed: ' . $error->getMessage());
    eraporInstallerRespond(503, 'Tidak dapat memeriksa akses', 'Coba lagi nanti atau gunakan login Admin yang aktif.');
}

require_once MODEL_PATH . '/EraporPilotInstaller.php';
$message = '';
$steps = [];
$canApply = false;
$status = null;
$applyFailed = false;

try {
    $status = EraporPilotInstaller::preflight($db, ROOT_PATH);
    $canApply = !$status['complete'];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
            $message = 'Token keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        } elseif ($status['complete']) {
            $message = 'Skema dan seed sudah lengkap. Eksekusi ulang ditolak.';
        } elseif (($_POST['confirmation'] ?? '') !== 'PASANG ERAPOR PILOT') {
            $message = 'Teks konfirmasi tidak cocok; tidak ada perubahan yang dijalankan.';
        } elseif (($_POST['apply'] ?? '') !== '1') {
            $message = 'Konfirmasi penerapan tidak diterima.';
        } else {
            $canApply = false;
            $applyFailed = true;
            $result = EraporPilotInstaller::apply($db, ROOT_PATH, static function (string $step) use (&$steps): void {
                $steps[] = $step;
            });
            $status = $result['preflight'];
            $canApply = false;
            $applyFailed = false;
            $message = $result['already_complete']
                ? 'Skema dan seed sebelumnya sudah lengkap; tidak ada perubahan yang diperlukan.'
                : 'Migrasi dan seed selesai diverifikasi. ERAPOR_API_ENABLED tetap OFF.';
        }
    }
} catch (Throwable $error) {
    $applyFailed = true;
    $canApply = false;
    $message = 'Penerapan dihentikan: ' . $error->getMessage()
        . ' Jangan hapus ledger atau mencoba ulang jika ada migrasi parsial; simpan pesan ini untuk pemeriksaan.';
    error_log('[eRapor temporary installer] ' . $error->getMessage());
}

$csrf = getCsrfToken();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup eRapor Pilot</title>
  <style>
    :root{font-family:"Plus Jakarta Sans",Arial,sans-serif;color:#272727;background:#fcfcfd}
    body{margin:0;padding:clamp(16px,4vw,48px)}
    main{max-width:760px;margin:0 auto;background:#fff;border:1px solid #e4e3e5;border-radius:12px;padding:clamp(20px,4vw,32px)}
    h1{font-size:24px;margin:0 0 12px} p{line-height:1.55} .warning{padding:14px 16px;background:#fff2f0;border:1px solid #e3766e;border-radius:8px}
    .status{border-collapse:collapse;width:100%;margin:20px 0}.status th,.status td{text-align:left;padding:10px 8px;border-bottom:1px solid #e4e3e5}
    label{display:block;margin:16px 0 6px}input[type=text]{box-sizing:border-box;width:100%;padding:12px;border:1px solid #b4b3b6;border-radius:4px;font:inherit}
    button{margin-top:16px;background:#c92c2f;color:#fff;border:0;border-radius:4px;padding:12px 18px;font:inherit;cursor:pointer}
    button:disabled{opacity:.5;cursor:not-allowed}.message{padding:12px;border-radius:6px;background:#f5f5f5}.steps{font-family:ui-monospace,monospace;font-size:13px;white-space:pre-wrap;background:#f7f7f8;padding:12px;border-radius:6px}
  </style>
</head>
<body>
<main>
  <h1>Setup skema eRapor pilot</h1>
  <p class="warning"><strong>Halaman sementara khusus Admin.</strong> GET hanya membaca. Penerapan menambah 17 migrasi dan seed resmi; tidak mengonversi data rapor lama. Jangan aktifkan eRapor dari halaman ini.</p>
  <?php if ($message !== ''): ?><p class="message" role="status"><?= eraporInstallerEscape($message) ?></p><?php endif; ?>
  <?php if ($status !== null): ?>
    <table class="status">
      <tbody>
        <tr><th>Database server</th><td><?= eraporInstallerEscape($status['server']) ?></td></tr>
        <tr><th>Migrasi lengkap</th><td><?= (int)$status['migration_count'] ?> / <?= (int)$status['migration_total'] ?></td></tr>
        <tr><th>Rubrik ter-seed</th><td><?= (int)$status['rubric_count'] ?> / 5</td></tr>
        <tr><th>Tahap persetujuan</th><td><?= (int)$status['approval_stage_count'] ?> / 3</td></tr>
        <tr><th>Assignment penyetuju saat ini</th><td><?= (int)$status['assignment_count'] ?> (tidak diubah)</td></tr>
      </tbody>
    </table>
  <?php endif; ?>
  <?php if ($status !== null && $status['complete']): ?>
    <p>Skema dan seed sudah lengkap. Tombol penerapan dinonaktifkan; hapus file sementara ini dari folder <code>public</code>.</p>
  <?php elseif ($canApply && !$applyFailed): ?>
    <p>Preflight berhasil. Flag fitur tetap OFF. Untuk melanjutkan, masukkan frasa persis dan konfirmasi.</p>
    <form method="post" action="<?= eraporInstallerEscape($_SERVER['SCRIPT_NAME'] ?? '/erapor-migration-once.php') ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= eraporInstallerEscape($csrf) ?>">
      <input type="hidden" name="apply" value="1">
      <label for="confirmation">Ketik <strong>PASANG ERAPOR PILOT</strong></label>
      <input type="text" id="confirmation" name="confirmation" required>
      <button type="submit">Jalankan migrasi dan seed</button>
    </form>
  <?php else: ?>
    <p>Penerapan tidak tersedia sampai masalah preflight diperiksa. Jangan mencoba mengulang setelah kegagalan migrasi parsial.</p>
  <?php endif; ?>
  <?php if ($steps): ?><h2>Langkah yang selesai</h2><pre class="steps"><?= eraporInstallerEscape(implode("\n", $steps)) ?></pre><?php endif; ?>
  <p>Setelah selesai, hapus <code>public/erapor-migration-once.php</code> melalui File Manager. Fitur eRapor tetap OFF.</p>
</main>
</body>
</html>
