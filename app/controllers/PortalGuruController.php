<?php

class PortalGuruController extends Controller
{
    public function dashboard(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');

        if (defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED) {
            try {
                $data = EraporTeacherDashboard::read(
                    Database::getInstance(),
                    (int)($_SESSION['user_id'] ?? 0),
                    (int)$this->input('periode_id', 0) ?: null
                );
                $this->view('portal-guru.erapor-dashboard', $data + [
                    'pageTitle'=>'Dashboard','breadcrumb'=>null,'activeNavItem'=>'portal-dashboard',
                ]);
            } catch (Throwable $e) {
                error_log('Erapor teacher dashboard failure: '.get_class($e));
                http_response_code(503);
                require VIEW_PATH.'/errors/500.php';
            }
            return;
        }

        $data = TeacherPortal::dashboard((int) ($_SESSION['karyawan_id'] ?? 0), (int) $this->input('sesi_id', 0));
        $this->view('portal-guru.dashboard', $data + [
            'pageTitle'=>'Dashboard','breadcrumb'=>null,'activeNavItem'=>'portal-dashboard',
        ]);
    }

    /** Explicit POST only: opening/reloading the dashboard is always read-only. */
    public function prepareEraporSession(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'edit');
        if (!defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) {
            http_response_code(404); require VIEW_PATH.'/errors/404.php'; return;
        }
        $studentId = filter_var($this->input('murid_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $periodId = filter_var($this->input('periode_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($studentId === false || $periodId === false) {
            $_SESSION['report_error'] = 'Pilihan murid atau periode tidak valid.';
            $this->redirect('/portal-guru/dashboard');
        }
        try {
            $result = EraporSessionFactory::create(
                Database::getInstance(), (int)$studentId, (int)$periodId, (int)($_SESSION['user_id'] ?? 0)
            );
            $this->redirect('/portal-guru/sesi/'.$result['id']);
        } catch (DomainException $e) {
            $message = str_starts_with($e->getMessage(), 'Rubrik belum tersedia:')
                ? 'Paket rapor periode ini belum lengkap dan belum dapat dibuka.'
                : 'Sesi rapor belum dapat dibuka. Periksa penugasan guru, periode, dan status data.';
            $_SESSION['report_error'] = $message;
            $this->redirect('/portal-guru/dashboard?periode_id='.(int)$periodId);
        } catch (Throwable $e) {
            error_log('Erapor session provisioning failure: '.get_class($e));
            $_SESSION['report_error'] = 'Layanan rapor sedang tidak tersedia. Silakan coba lagi.';
            $this->redirect('/portal-guru/dashboard?periode_id='.(int)$periodId);
        }
    }

    public function showEraporSession(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');
        if (!defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED || !preg_match('/^[1-9][0-9]*$/D', $id)) {
            http_response_code(404); require VIEW_PATH.'/errors/404.php'; return;
        }
        try {
            $form = EraporTeacherForm::read(Database::getInstance(), (int)$id, (int)($_SESSION['user_id'] ?? 0));
            $this->view('portal-guru.erapor-sesi', [
                'pageTitle'=>'Pengisian Rapor','form'=>$form,'activeNavItem'=>'portal-dashboard',
            ]);
        } catch (DomainException) {
            http_response_code(404); require VIEW_PATH.'/errors/404.php';
        } catch (Throwable $e) {
            error_log('Erapor teacher session page failure: '.get_class($e));
            http_response_code(503); require VIEW_PATH.'/errors/500.php';
        }
    }

    public function showMurid(string $id): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');
        $student = TeacherPortal::student((int) ($_SESSION['karyawan_id'] ?? 0), (int) $id);
        if (!$student) {
            http_response_code(404);
            require VIEW_PATH . '/errors/404.php';
            return;
        }
        $class = $student['kelas_id'] ? (new Kelas())->find($student['kelas_id']) : null;
        $this->view('admin.murid.form', [
            'pageTitle'=>'Detail Murid',
            'breadcrumb'=>breadcrumb(['Daftar Murid Guru','/portal-guru/murid'],'Detail Murid'),
            'activeNavItem'=>'portal-daftar-murid', 'mode'=>'detail', 'muridId'=>(int)$id,
            'murid'=>$student, 'kelasOptions'=>$class ? [$class] : [], 'old'=>[], 'errors'=>[], 'canEdit'=>false,
        ]);
    }

    public function daftarMurid(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');

        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);
        $search = trim((string)$this->input('q', ''));
        $muridList = [];

        if ($guruId > 0) {
            $sql = "SELECT DISTINCT mu.id, mu.nisn, mu.nama_lengkap, mu.jenis_kelamin, k.level_kelas, k.nama_kelas
                    FROM kelas_guru_murid kgm
                    JOIN murid mu ON mu.id = kgm.murid_id
                    LEFT JOIN kelas k ON k.id = mu.kelas_id
                    WHERE kgm.guru_id = :guru_id AND mu.nama_lengkap LIKE :search
                    ORDER BY mu.nama_lengkap ASC";
            $stmt = Database::getInstance()->prepare($sql);
            $stmt->execute(['guru_id' => $guruId, 'search'=>'%' . $search . '%']);
            $muridList = $stmt->fetchAll();
        }

        $this->view('portal-guru.daftar-murid', [
            // [FIX] Dikonfirmasi dari assets/ss/Portal Guru - menu_daftar_
            // murid.svg — judul H1 "Daftar Murid Guru", bukan "Daftar Murid"
            // (nama menu sidebar-nya sendiri tetap "Daftar Murid").
            'pageTitle' => 'Daftar Murid Guru',
            'breadcrumb' => null,
            'activeNavItem' => 'portal-daftar-murid',
            'muridList' => $muridList,
            'search' => $search,
        ]);
    }
}
