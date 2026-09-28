<?php

/** Assignment gate paired with the role permission; neither grants authority alone. */
final class EraporApprovalAccess
{
    public static function assigned(int $userId): bool
    {
        if ($userId<1 || !defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) return false;
        try {
            $db=Database::getInstance();
            $q=$db->prepare("SELECT 1 FROM erapor_penyetuju_user a
                JOIN users u ON u.id=a.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE a.user_id=? AND a.aktif=1 AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                UNION SELECT 1 FROM erapor_wali_kelas w
                JOIN users u ON u.id=w.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE w.user_id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 LIMIT 1");
            $q->execute([$userId,$userId]); return (bool)$q->fetchColumn();
        } catch (PDOException $e) {
            // If the opt-in schema is incomplete, fail closed.
            return false;
        }
    }

    /** Mode pantau: Superadmin tanpa penugasan melihat seluruh antrean dan tinjauan secara baca-saja. */
    public static function monitor(int $userId): bool
    {
        return ($_SESSION['role_name'] ?? '') === 'Superadmin' && !self::assigned($userId);
    }

    /**
     * Pemegang sah satu baris tahap persetujuan: WALI_KELAS = wali kelas kelas sesi (erapor_wali_kelas);
     * tahap lain = penugasan eksplisit aktif. Kepala Sekolah wajib berjabatan Kepala Sekolah.
     * @return array{nama:string,jabatan:string}|false
     */
    public static function actor(PDO $db,array $target,array $session,int $userId,bool $lock=false): array|false
    {
        $active='u.is_active=1 AND k.is_active=1 AND j.is_active=1';
        if (($target['kode'] ?? '')==='WALI_KELAS') {
            $q=$db->prepare("SELECT k.nama,j.nama AS jabatan FROM erapor_wali_kelas w
                JOIN users u ON u.id=w.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE w.kelas_id=? AND w.user_id=? AND $active".($lock?' FOR UPDATE':''));
            $q->execute([(int)$session['kelas_id'],$userId]);
        } else {
            $q=$db->prepare("SELECT k.nama,j.nama AS jabatan FROM erapor_penyetuju_user a
                JOIN users u ON u.id=a.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE a.penyetuju_id=? AND a.user_id=? AND a.aktif=1 AND $active".($lock?' FOR UPDATE':''));
            $q->execute([(int)$target['penyetuju_id'],$userId]);
        }
        $actor=$q->fetch(PDO::FETCH_ASSOC);
        if (!$actor || (($target['kode'] ?? '')==='KEPALA_SEKOLAH' && $actor['jabatan']!=='Kepala Sekolah')) return false;
        return $actor;
    }
}
