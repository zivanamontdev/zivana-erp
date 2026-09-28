<?php

/** Profil baca-saja akun pegawai yang sedang login (Portal Guru > Profil). Tidak pernah menampilkan data akun lain. */
final class TeacherProfile
{
    public static function read(PDO $db, int $userId): ?array
    {
        $q = $db->prepare('SELECT u.id,u.email,u.is_active,k.id AS karyawan_id,k.nama,k.is_active AS karyawan_active,j.nama AS jabatan,r.nama AS role
            FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id JOIN roles r ON r.id=u.role_id WHERE u.id=?');
        $q->execute([$userId]);
        $profile = $q->fetch(PDO::FETCH_ASSOC);
        if (!$profile) return null;

        $tables = array_fill_keys($db->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()
            AND table_name IN ('erapor_profil_penandatangan','erapor_wali_kelas','erapor_penyetuju_user')")->fetchAll(PDO::FETCH_COLUMN), true);

        $profile['nuptk'] = null; $profile['has_signature'] = false; $profile['consented_at'] = null;
        if (isset($tables['erapor_profil_penandatangan'])) {
            $q = $db->prepare('SELECT nuptk,ttd_png IS NOT NULL AS has_signature,ttd_disetujui_pada FROM erapor_profil_penandatangan WHERE user_id=?');
            $q->execute([$userId]);
            if ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $profile['nuptk'] = $row['nuptk'];
                $profile['has_signature'] = (bool) $row['has_signature'];
                $profile['consented_at'] = $row['ttd_disetujui_pada'];
            }
        }

        $profile['wali_kelas'] = [];
        if (isset($tables['erapor_wali_kelas'])) {
            $q = $db->prepare("SELECT CONCAT(k.level_kelas,' ',k.nama_kelas) FROM erapor_wali_kelas w JOIN kelas k ON k.id=w.kelas_id WHERE w.user_id=? ORDER BY k.level_kelas,k.nama_kelas");
            $q->execute([$userId]);
            $profile['wali_kelas'] = $q->fetchAll(PDO::FETCH_COLUMN);
        }

        $profile['tahap_persetujuan'] = [];
        if (isset($tables['erapor_penyetuju_user'])) {
            $q = $db->prepare('SELECT f.label FROM erapor_penyetuju_user a JOIN erapor_alur_penyetuju f ON f.id=a.penyetuju_id WHERE a.user_id=? AND a.aktif=1 ORDER BY f.urutan,f.label');
            $q->execute([$userId]);
            $profile['tahap_persetujuan'] = $q->fetchAll(PDO::FETCH_COLUMN);
        }
        if ($profile['wali_kelas']) $profile['tahap_persetujuan'][] = 'Wali Kelas';

        // Murid yang diampu (kelompok guru-murid) beserta kelasnya.
        $q = $db->prepare("SELECT CONCAT(k.level_kelas,' ',k.nama_kelas) AS kelas,COUNT(*) AS jumlah FROM kelas_guru_murid g
            JOIN kelas k ON k.id=g.kelas_id JOIN murid m ON m.id=g.murid_id WHERE g.guru_id=? AND m.status='bersekolah'
            GROUP BY k.id ORDER BY k.level_kelas,k.nama_kelas");
        $q->execute([(int) $profile['karyawan_id']]);
        $profile['murid_diampu'] = $q->fetchAll(PDO::FETCH_ASSOC);
        return $profile;
    }
}
