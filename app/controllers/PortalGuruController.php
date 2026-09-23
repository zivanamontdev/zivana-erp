<?php

class PortalGuruController extends Controller
{
    public function dashboard(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');

        $data = TeacherPortal::dashboard((int) ($_SESSION['karyawan_id'] ?? 0), (int) $this->input('sesi_id', 0));
        $this->view('portal-guru.dashboard', $data + [
            'pageTitle'=>'Dashboard','breadcrumb'=>null,'activeNavItem'=>'portal-dashboard',
        ]);
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
