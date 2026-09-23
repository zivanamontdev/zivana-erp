<?php

/** Synchronize only open/future sessions on explicit student/assignment writes. */
class StudentReportSync
{
    public static function sync(int $studentId, ?string $today = null): void
    {
        $db = Database::getInstance();
        if (!$db->inTransaction()) throw new LogicException('Student report synchronization requires a transaction.');
        $today ??= date('Y-m-d');
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $query = $db->prepare('SELECT id FROM murid WHERE id=?' . $lock);
        $query->execute([$studentId]);
        if (!$query->fetch()) return;
        $query = $db->prepare("SELECT m.status,k.tahun_ajaran_id,
            (SELECT kg.guru_id FROM kelas_guru_murid kg JOIN karyawan ka ON ka.id=kg.guru_id
             JOIN jabatan j ON j.id=ka.jabatan_id WHERE kg.murid_id=m.id AND kg.kelas_id=m.kelas_id
             AND ka.is_active=1 AND j.nama IN ('Guru Kelas','Guru Shadow') LIMIT 1) AS guru_id
            FROM murid m LEFT JOIN kelas k ON k.id=m.kelas_id WHERE m.id=?");
        $query->execute([$studentId]); $student = $query->fetch();
        $sessions = $db->prepare('SELECT s.id,s.template_id,p.tahun_ajaran_id,p.semester FROM sesi_pembagian_rapor s
            JOIN periode_penilaian p ON p.id=s.periode_id WHERE s.tanggal_selesai>=? ORDER BY s.id');
        $sessions->execute([$today]);
        foreach ($sessions->fetchAll() as $session) {
            $eligible = $student['status'] === 'bersekolah' && $student['tahun_ajaran_id'] !== null
                && (int)$student['tahun_ajaran_id'] === (int)$session['tahun_ajaran_id']
                && in_array($session['semester'], ['ganjil','genap'], true);
            $existing = $db->prepare('SELECT id,status FROM rapor WHERE murid_id=? AND sesi_pembagian_id=?' . $lock);
            $existing->execute([$studentId,$session['id']]); $report = $existing->fetch();
            if ($report && $report['status'] === 'belum_diisi') {
                // Preserve draft values, but remove access if class/year/status no longer qualifies.
                $db->prepare('UPDATE rapor SET guru_id=? WHERE id=?')->execute([$eligible ? $student['guru_id'] : null,$report['id']]);
            } elseif (!$report && $eligible) {
                $db->prepare("INSERT INTO rapor(murid_id,sesi_pembagian_id,template_id,guru_id,status) VALUES(?,?,?,?,'belum_diisi')")
                    ->execute([$studentId,$session['id'],$session['template_id'],$student['guru_id']]);
            }
        }
    }
}
