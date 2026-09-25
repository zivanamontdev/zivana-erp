<?php

/** Ringkasan hanya-baca seluruh sesi eRapor per periode untuk Superadmin (menu Rapor Murid saat eRapor aktif). */
final class EraporOverview
{
    public const STATUS = [
        'BELUM_DIBUKA' => ['Belum dibuka', 'netral'],
        'BELUM_DIISI' => ['Sedang diisi', 'peringatan'],
        'TELAH_DIISI' => ['Selesai diisi', 'peringatan'],
        'MENUNGGU_TTD' => ['Menunggu persetujuan', 'peringatan'],
        'TERBIT' => ['Disetujui · PDF terbit', 'positif'],
        'SELESAI' => ['Selesai', 'positif'],
    ];

    /** Setiap periode tahun ajaran: murid aktif yang punya guru, dengan sesi (bila sudah dibuka) dan progresnya. */
    public static function forYear(PDO $db, int $yearId): array
    {
        $periods = $db->prepare('SELECT * FROM periode_penilaian WHERE tahun_ajaran_id=? ORDER BY awal_periode DESC');
        $periods->execute([$yearId]);
        $students = $db->prepare("SELECT m.id,m.nama_lengkap,m.status_kondisi,k.level_kelas,k.nama_kelas,
                (SELECT g.nama FROM kelas_guru_murid a JOIN karyawan g ON g.id=a.guru_id WHERE a.murid_id=m.id ORDER BY a.id LIMIT 1) AS guru
            FROM murid m JOIN kelas k ON k.id=m.kelas_id WHERE m.status='bersekolah' AND k.tahun_ajaran_id=? ORDER BY m.nama_lengkap");
        $students->execute([$yearId]);
        $studentRows = $students->fetchAll(PDO::FETCH_ASSOC);
        $sessionQuery = $db->prepare("SELECT s.*,g.nama AS guru_sesi,
                (SELECT COUNT(*) FROM erapor_sesi_penyetuju p WHERE p.sesi_id=s.id) AS tahap_total,
                (SELECT COUNT(*) FROM erapor_sesi_penyetuju p WHERE p.sesi_id=s.id AND p.status='DISETUJUI') AS tahap_disetujui,
                EXISTS(SELECT 1 FROM erapor_publikasi_pdf a WHERE a.sesi_id=s.id) AS terbit
            FROM erapor_sesi s LEFT JOIN users u ON u.id=s.guru_user_id LEFT JOIN karyawan g ON g.id=u.karyawan_id WHERE s.periode_id=?");
        $result = [];
        foreach ($periods->fetchAll(PDO::FETCH_ASSOC) as $period) {
            $sessionQuery->execute([$period['id']]);
            $sessions = [];
            foreach ($sessionQuery->fetchAll(PDO::FETCH_ASSOC) as $session) $sessions[(int) $session['murid_id']] = $session;
            $rows = [];
            foreach ($studentRows as $student) {
                $session = $sessions[(int) $student['id']] ?? null;
                $row = $student + ['sesi_id' => null, 'status' => 'BELUM_DIBUKA', 'filled' => 0, 'required' => 0, 'tahap' => '—', 'guru_sesi' => null];
                if ($session) {
                    $row['sesi_id'] = (int) $session['id'];
                    $row['guru_sesi'] = $session['guru_sesi'];
                    $row['status'] = $session['terbit'] ? 'TERBIT' : $session['status'];
                    $row['tahap'] = (int) $session['tahap_total'] ? $session['tahap_disetujui'] . '/' . $session['tahap_total'] : '—';
                    try {
                        // Guard paket mensyaratkan transaksi; hanya membaca, lalu di-rollback.
                        $db->beginTransaction();
                        $completion = EraporCompleteness::inspect($db, $session);
                        $row['filled'] = array_sum(array_column($completion['documents'], 'filled'));
                        $row['required'] = array_sum(array_column($completion['documents'], 'required'));
                    } catch (Throwable) {
                        $row['required'] = 0; // paket berubah/tidak valid: tampilkan tanpa progres
                    } finally {
                        if ($db->inTransaction()) $db->rollBack();
                    }
                }
                $rows[] = $row;
            }
            $period['rows'] = $rows;
            $period['opened'] = count(array_filter($rows, fn($r) => $r['sesi_id'] !== null));
            $result[] = $period;
        }
        return $result;
    }
}
