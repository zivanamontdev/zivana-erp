<?php

/** Read-only dashboard data: opening a page never creates reports or marks. */
class TeacherPortal
{
    public static function dashboard(int $teacherId, ?int $requestedSession = null, ?string $today = null): array
    {
        $db = Database::getInstance();
        $today ??= date('Y-m-d');
        $stmt = $db->prepare("SELECT s.*, p.semester,p.tipe,t.tahun_awal,t.tahun_akhir
            FROM sesi_pembagian_rapor s JOIN periode_penilaian p ON p.id=s.periode_id
            JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id
            WHERE EXISTS (SELECT 1 FROM rapor r WHERE r.sesi_pembagian_id=s.id AND r.guru_id=?
                AND (r.status<>'belum_diisi' OR EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id AND kg.guru_id=r.guru_id)))
            ORDER BY s.tanggal_mulai DESC,s.id DESC");
        $stmt->execute([$teacherId]);
        $sessions = $stmt->fetchAll();
        $current = null; $next = null; $past = null; $selected = null;
        foreach ($sessions as $session) {
            if ($session['tanggal_mulai'] <= $today && $session['tanggal_selesai'] >= $today && !$current) $current = $session;
            if ($session['tanggal_selesai'] < $today && !$past) $past = $session;
            if ($session['tanggal_mulai'] > $today && (!$next || $session['tanggal_mulai'] < $next['tanggal_mulai'])) $next = $session;
            if ((int)$session['id'] === $requestedSession) $selected = $session;
        }
        $selected ??= $current ?? $past ?? $next;
        if ($current) $current['sisa_hari'] = (int)(new DateTimeImmutable($today))->diff(new DateTimeImmutable($current['tanggal_selesai']))->format('%a');
        if ($selected) {
            $stmt = $db->prepare("SELECT m.id,m.nama_lengkap,r.id AS rapor_id,r.status AS rapor_status FROM rapor r
                JOIN murid m ON m.id=r.murid_id WHERE r.guru_id=? AND r.sesi_pembagian_id=?
                AND (r.status<>'belum_diisi' OR EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=r.murid_id AND kg.guru_id=r.guru_id))
                ORDER BY m.nama_lengkap");
            $stmt->execute([$teacherId,$selected['id']]);
        } else {
            $stmt = $db->prepare('SELECT m.id,m.nama_lengkap,NULL AS rapor_id,NULL AS rapor_status FROM murid m WHERE EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=m.id AND kg.guru_id=?) ORDER BY m.nama_lengkap');
            $stmt->execute([$teacherId]);
        }
        $year = $selected ?: $db->query('SELECT tahun_awal,tahun_akhir FROM tahun_ajaran WHERE is_active=1 ORDER BY id DESC LIMIT 1')->fetch();
        return ['agendaBerlangsung'=>$current,'agendaBerikutnya'=>$next,'selectedSession'=>$selected,
            'sessionOptions'=>$sessions,'daftarMurid'=>$stmt->fetchAll(),
            'tahunLabel'=>$year ? $year['tahun_awal'].'/'.$year['tahun_akhir'] : 'Belum ditentukan'];
    }

    public static function student(int $teacherId, int $studentId): ?array
    {
        $stmt=Database::getInstance()->prepare('SELECT m.*,k.level_kelas,k.nama_kelas FROM murid m LEFT JOIN kelas k ON k.id=m.kelas_id
            WHERE m.id=? AND EXISTS (SELECT 1 FROM kelas_guru_murid kg WHERE kg.murid_id=m.id AND kg.guru_id=?)');
        $stmt->execute([$studentId,$teacherId]);
        return $stmt->fetch() ?: null;
    }
}
