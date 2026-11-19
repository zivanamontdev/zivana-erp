<?php

/**
 * Halaman SEMENTARA untuk memasukkan rubrik RAS di hosting tanpa SSH.
 * Berlapis: 404 kecuali ERAPOR_RAS_SEED_TOKEN (min. 32 karakter) ada di .env, hanya Superadmin,
 * token diketik ulang + CSRF. Hanya menulis rubrik RAS (EraporRasSeed); aman diulang ("already_seeded").
 * Prasyarat: tabel erapor_ras_narasi sudah dibuat (database/migrations/20261023_erapor_ras.sql).
 * Hapus token dari .env, lalu hapus controller/view/route ini setelah rubrik terpasang.
 */
class EraporRasSeedController extends Controller
{
    private const SEED_FILE = '/database/seeds/rubrik_ras_seed.json';

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
            error_log('eRapor RAS seed: token mismatch by user ' . (int) $_SESSION['user_id']);
            $this->render(['mode' => $mode, 'ok' => false, 'lines' => ['Token salah. Tidak ada perubahan yang dilakukan.']]);
            return;
        }
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) @set_time_limit(120);

        $lines = []; $ok = false;
        try {
            $db = Database::getInstance();
            require_once ROOT_PATH . '/app/models/EraporRtsSeed.php';
            require_once ROOT_PATH . '/app/models/EraporRasSeed.php';
            $seed = EraporRasSeed::load(ROOT_PATH . self::SEED_FILE);
            $lines[] = 'Berkas seed valid: 62 indikator, 6 area.';
            $table = $this->count($db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='erapor_ras_narasi'");
            $lines[] = $table ? 'Tabel erapor_ras_narasi: ada.' : 'Tabel erapor_ras_narasi: BELUM ADA. Jalankan 20261023_erapor_ras.sql di phpMyAdmin lebih dulu.';
            if (!$table) throw new RuntimeException('Prasyarat belum terpenuhi.');
            $before = $this->state($db);
            $lines[] = 'Rubrik RAS sebelum: ' . $before;
            if ($mode === 'check') {
                $lines[] = 'Mode periksa: tidak ada perubahan.';
            } else {
                error_log('eRapor RAS seed: apply by user ' . (int) $_SESSION['user_id']);
                $lines[] = 'Hasil seed: ' . EraporRasSeed::apply($db, $seed);
                $lines[] = 'Rubrik RAS sesudah: ' . $this->state($db);
                error_log('eRapor RAS seed: finished');
            }
            $ok = true;
        } catch (Throwable $error) {
            error_log('eRapor RAS seed STOP: ' . $error->getMessage());
            $lines[] = 'BERHENTI: ' . $error->getMessage();
        }
        $this->render(['mode' => $mode, 'ok' => $ok, 'lines' => $lines]);
    }

    private function state(PDO $db): string
    {
        $q = $db->query("SELECT r.id,r.status FROM erapor_rubrik r WHERE r.kode='RAS_MONTESSORI_V1'");
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return 'belum ada';
        $n = $this->count($db, 'SELECT COUNT(*) FROM erapor_rubrik_indikator i JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=' . (int) $row['id']);
        $h = $this->count($db, 'SELECT COUNT(*) FROM erapor_seed_history WHERE rubrik_id=' . (int) $row['id']);
        return 'ada (status ' . $row['status'] . ', ' . $n . ' indikator, riwayat seed ' . ($h ? 'tercatat' : 'tidak ada') . ')';
    }

    private function count(PDO $db, string $sql): int { return (int) $db->query($sql)->fetchColumn(); }

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
        return defined('ERAPOR_RAS_SEED_TOKEN') ? (string) ERAPOR_RAS_SEED_TOKEN : '';
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
        $this->view('admin.erapor-ras-seed.index', [
            'pageTitle' => 'Seed Rubrik RAS',
            'breadcrumb' => breadcrumb('Sistem', 'Seed Rubrik RAS'),
            'activeNavItem' => '',
            'result' => $result,
        ]);
    }
}
