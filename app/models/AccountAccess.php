<?php

/** Shared identity checks for password login, remembered login and navigation. */
class AccountAccess
{
    public static function active(array $user): bool
    {
        if (empty($user['is_active'])) return false;
        if (empty($user['karyawan_id'])) return true;
        $employee = (new Karyawan())->find((int) $user['karyawan_id']);
        return $employee && !empty($employee['is_active']);
    }

    public static function isTeacher(): bool
    {
        if (($_SESSION['role_name'] ?? '') === 'Guru') return true;
        $stmt = Database::getInstance()->prepare('SELECT j.nama FROM users u LEFT JOIN karyawan k ON k.id=u.karyawan_id LEFT JOIN jabatan j ON j.id=k.jabatan_id WHERE u.id=?');
        $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
        return in_array($stmt->fetchColumn(), Jabatan::TEACHER_NAMES, true);
    }

    public static function landing(): string
    {
        $pages = [
            ['/sekolah', 'Sekolah', 'Data Sekolah'],
            ['/kurikulum/manajemen-template', 'Sekolah', 'Manajemen Template'],
            ['/kurikulum/periode-penilaian', 'Sekolah', 'Periode Penilaian'],
            ['/karyawan', 'Human Capital', 'Daftar Karyawan'],
            ['/jabatan', 'Human Capital', 'Jabatan'],
            ['/manajemen-guru', 'Human Capital', 'Manajemen Guru'],
            ['/murid', 'Murid', 'Manajemen Murid'],
            ['/kelas', 'Murid', 'Manajemen Kelas'],
            ['/rapor-murid', 'Murid', 'Rapor Murid'],
            ['/portal-guru/dashboard', 'Portal Guru', 'Dashboard'],
            ['/portal-guru/murid', 'Portal Guru', 'Daftar Murid'],
            ['/rbac', 'Sistem', 'RBAC'],
        ];
        $checker = new RoleMiddleware();
        foreach ($pages as [$path, $module, $section]) {
            if ($checker->check($module, $section)) return $path;
        }
        return '/akses-terbatas';
    }
}
