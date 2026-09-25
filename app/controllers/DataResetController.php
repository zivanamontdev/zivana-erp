<?php

/**
 * Kosongkan data operasional + seed master data uji coba (lihat cookbook/data-reset.md, DataResetSeeder).
 * Pola pengaman sama dengan EraporMigrationController: 404 kecuali DATA_RESET_TOKEN (≥32 karakter) ada di .env,
 * hanya Superadmin, token diketik ulang + CSRF. Jalankan juga mensyaratkan kalimat konfirmasi.
 * Hapus token dari .env setelah selesai; halaman kembali 404.
 */
class DataResetController extends Controller
{
    private const CONFIRMATION = 'KOSONGKAN DATA';

    public function index(): void
    {
        $this->guard();
        $this->render(null, null);
    }

    public function run(): void
    {
        $this->guard();
        $mode = (string) $this->input('mode', '');
        $actorId = (int) $_SESSION['user_id'];
        if (!in_array($mode, ['preview', 'run'], true)) {
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Mode tidak dikenal.']], null);
            return;
        }
        if (!hash_equals(self::token(), (string) $this->input('token', ''))) {
            error_log('Data reset: token mismatch by user ' . $actorId);
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Token reset salah. Tidak ada perubahan yang dilakukan.']], null);
            return;
        }
        $db = Database::getInstance();
        if ($mode === 'preview') {
            try {
                $plan = DataResetSeeder::plan($db, $actorId);
                $this->render(['mode' => $mode, 'ok' => !$plan['blockers'], 'lines' => $plan['blockers'] ?: ['Pratinjau selesai. Tidak ada perubahan yang dilakukan.']], $plan);
            } catch (Throwable $e) {
                $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Pratinjau gagal: ' . $e->getMessage()]], null);
            }
            return;
        }
        if (trim((string) $this->input('confirmation', '')) !== self::CONFIRMATION) {
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Ketik "' . self::CONFIRMATION . '" persis untuk menjalankan. Tidak ada perubahan yang dilakukan.']], null);
            return;
        }
        $password = (string) $this->input('password', '');
        if ($password !== (string) $this->input('password_confirmation', '')) {
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Konfirmasi password awal tidak sama. Tidak ada perubahan yang dilakukan.']], null);
            return;
        }

        ignore_user_abort(true);
        if (function_exists('set_time_limit')) @set_time_limit(0);
        $lines = [];
        $ok = false;
        try {
            error_log('Data reset: started by user ' . $actorId);
            DataResetSeeder::run($db, $actorId, $password, static function (string $line) use (&$lines): void { $lines[] = $line; });
            error_log('Data reset: finished by user ' . $actorId);
            $lines[] = 'Selesai. Hapus DATA_RESET_TOKEN dari .env agar halaman ini kembali tidak tersedia.';
            $ok = true;
        } catch (Throwable $e) {
            error_log('Data reset STOP: ' . $e->getMessage());
            $lines[] = 'Dibatalkan, seluruh perubahan di-rollback: ' . $e->getMessage();
        }
        $this->render(['mode' => $mode, 'ok' => $ok, 'lines' => $lines], null);
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
        return defined('DATA_RESET_TOKEN') ? (string) DATA_RESET_TOKEN : '';
    }

    private function notFound(): never
    {
        http_response_code(404);
        require VIEW_PATH . '/errors/404.php';
        exit;
    }

    private function render(?array $result, ?array $plan): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        $this->view('admin.data-reset.index', [
            'pageTitle' => 'Reset & Seed Data',
            'breadcrumb' => breadcrumb('Sistem', 'Reset & Seed Data'),
            'activeNavItem' => '',
            'result' => $result,
            'plan' => $plan,
            'confirmation' => self::CONFIRMATION,
        ]);
    }
}
