<?php

/**
 * Pengganti cPanel Cron untuk hosting tanpa SSH/output cron (lihat cookbook/erapor-web-migration.md).
 * Berlapis: 404 kecuali ERAPOR_MIGRATION_TOKEN ada di .env (tidak pernah di git), hanya Superadmin,
 * token diketik ulang + CSRF, dan EraporPilotInstaller tetap menolak jalan saat ERAPOR_API_ENABLED=true.
 * Hapus token dari .env setelah migrasi selesai; halaman kembali 404.
 */
class EraporMigrationController extends Controller
{
    public function index(): void
    {
        $this->guard();
        $this->render(null);
    }

    public function run(): void
    {
        $this->guard();
        $mode = (string) $this->input('mode', '');
        if (!in_array($mode, ['check', 'apply'], true)) {
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Mode tidak dikenal.']]);
            return;
        }
        if (!hash_equals(self::token(), (string) $this->input('token', ''))) {
            error_log('eRapor web migration: token mismatch by user ' . (int) $_SESSION['user_id']);
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Token migrasi salah. Tidak ada perubahan yang dilakukan.']]);
            return;
        }

        // DDL tidak boleh terputus di tengah jalan karena tab ditutup atau batas waktu request.
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) @set_time_limit(0);

        $lines = [];
        $stage = 'bootstrap';
        $ok = false;
        try {
            $db = Database::getInstance();
            $status = EraporPilotInstaller::preflight($db, ROOT_PATH);
            $lines[] = 'Preflight passed; database server: ' . $status['server'];
            $lines[] = $status['migration_count'] . '/' . $status['migration_total'] . ' migrations complete; rubric seeds='
                . $status['rubric_count'] . '; approval stages=' . $status['approval_stage_count'];
            if ($mode === 'check') {
                $lines[] = 'Check-only mode: no writes performed.';
            } else {
                error_log('eRapor web migration: apply started by user ' . (int) $_SESSION['user_id']);
                $result = EraporPilotInstaller::apply($db, ROOT_PATH, static function (string $message) use (&$lines, &$stage): void {
                    $stage = $message;
                    $lines[] = $message;
                });
                $lines[] = $result['already_complete']
                    ? 'Schema and pilot seeds were already complete; no changes needed.'
                    : 'ERAPOR_API_ENABLED remains false.';
                error_log('eRapor web migration: apply finished by user ' . (int) $_SESSION['user_id']);
            }
            $ok = true;
        } catch (Throwable $error) {
            error_log('eRapor web migration STOP at ' . $stage . ': ' . $error->getMessage());
            $lines[] = 'STOP at ' . $stage . ': ' . $error->getMessage();
            $lines[] = 'Jangan menghapus baris ledger atau mengulang setelah DDL parsial; simpan output ini untuk diperiksa.';
        }
        $this->render(['mode' => $mode, 'ok' => $ok, 'lines' => $lines]);
    }

    private function guard(): void
    {
        if (strlen(self::token()) < 32) $this->notFound();
        $this->middleware(AuthMiddleware::class);
        $row = Database::getInstance()->prepare('SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');
        $row->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        if ($row->fetchColumn() !== 'Superadmin') $this->notFound();
    }

    private static function token(): string
    {
        return defined('ERAPOR_MIGRATION_TOKEN') ? (string) ERAPOR_MIGRATION_TOKEN : '';
    }

    private function notFound(): never
    {
        http_response_code(404);
        require VIEW_PATH . '/errors/404.php';
        exit;
    }

    private function render(?array $result): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $this->view('admin.erapor-migration.index', [
            'pageTitle' => 'Migrasi eRapor',
            'breadcrumb' => breadcrumb('Sistem', 'Migrasi eRapor'),
            'activeNavItem' => '',
            'result' => $result,
            'apiEnabled' => defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED,
        ]);
    }
}
