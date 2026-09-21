<?php

class PortalGuruController extends Controller
{
    public function dashboard(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');

        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);
        $db = Database::getInstance();
        $today = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT sp.*, DATEDIFF(sp.tanggal_selesai, :today) AS sisa_hari
             FROM sesi_pembagian_rapor sp
             WHERE :today2 BETWEEN sp.tanggal_mulai AND sp.tanggal_selesai
             ORDER BY sp.tanggal_mulai ASC LIMIT 1"
        );
        $stmt->execute(['today' => $today, 'today2' => $today]);
        $agendaBerlangsung = $stmt->fetch() ?: null;

        $stmt = $db->prepare(
            'SELECT * FROM sesi_pembagian_rapor WHERE tanggal_mulai > :today ORDER BY tanggal_mulai ASC LIMIT 1'
        );
        $stmt->execute(['today' => $today]);
        $agendaBerikutnya = $stmt->fetch() ?: null;

        $daftarMurid = [];
        if ($guruId > 0) {
            $sql = 'SELECT DISTINCT mu.id, mu.nama_lengkap
                    FROM kelas_guru_murid kgm
                    JOIN murid mu ON mu.id = kgm.murid_id
                    WHERE kgm.guru_id = :guru_id
                    ORDER BY mu.nama_lengkap ASC';
            $stmt = $db->prepare($sql);
            $stmt->execute(['guru_id' => $guruId]);
            $daftarMurid = $stmt->fetchAll();

            if ($agendaBerlangsung) {
                $raporStmt = $db->prepare(
                    'SELECT id, status FROM rapor WHERE murid_id = :murid_id AND sesi_pembagian_id = :sesi_id'
                );
                foreach ($daftarMurid as &$m) {
                    $raporStmt->execute(['murid_id' => $m['id'], 'sesi_id' => $agendaBerlangsung['id']]);
                    $r = $raporStmt->fetch();
                    $m['rapor_id'] = $r['id'] ?? null;
                    $m['rapor_status'] = $r['status'] ?? null;
                }
                unset($m);
            }
        }

        $this->view('portal-guru.dashboard', [
            'pageTitle' => 'Dashboard',
            'breadcrumb' => null,
            'activeNavItem' => 'portal-dashboard',
            'agendaBerlangsung' => $agendaBerlangsung,
            'agendaBerikutnya' => $agendaBerikutnya,
            'daftarMurid' => $daftarMurid,
        ]);
    }

    public function daftarMurid(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Daftar Murid', 'lihat');

        $guruId = (int) ($_SESSION['karyawan_id'] ?? 0);
        $muridList = [];

        if ($guruId > 0) {
            $sql = "SELECT DISTINCT mu.nisn, mu.nama_lengkap, mu.jenis_kelamin, k.level_kelas, k.nama_kelas
                    FROM kelas_guru_murid kgm
                    JOIN murid mu ON mu.id = kgm.murid_id
                    LEFT JOIN kelas k ON k.id = mu.kelas_id
                    WHERE kgm.guru_id = :guru_id
                    ORDER BY mu.nama_lengkap ASC";
            $stmt = Database::getInstance()->prepare($sql);
            $stmt->execute(['guru_id' => $guruId]);
            $muridList = $stmt->fetchAll();
        }

        $this->view('portal-guru.daftar-murid', [
            'pageTitle' => 'Daftar Murid',
            'breadcrumb' => null,
            'activeNavItem' => 'portal-daftar-murid',
            'muridList' => $muridList,
        ]);
    }
}
