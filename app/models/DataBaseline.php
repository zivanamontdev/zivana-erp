<?php

/**
 * Data dasar agar database kosong (schema.sql + migrasi eRapor) langsung bisa dipakai:
 * empat jabatan tetap, RBAC dasar, dan akun Superadmin. Dipakai database/bootstrap.php.
 * RBAC_BASELINE = konfigurasi produksi 25 Sep 2026 (sebelum eRapor) + izin eRapor.
 */
final class DataBaseline
{
    /** Jabatan tetap (JabatanController hanya mengizinkan empat nama ini) => role. */
    public const JABATAN = ['Kepala Sekolah' => 'Admin', 'Admin' => 'Admin', 'Guru Kelas' => 'Guru', 'Guru Shadow' => 'Guru'];

    /** Izin per role: [modul, section, sub_section, aksi]. */
    public const RBAC_BASELINE = [
    'Koordinator Guru' => [
        ['Sekolah', 'Data Sekolah', null, 'lihat'],
        ['Sekolah', 'Data Sekolah', null, 'edit'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'edit'],
        ['Murid', 'Rapor Murid', null, 'lihat'],
        ['Murid', 'Rapor Murid', null, 'edit'],
        ['Sekolah', 'Data Sekolah', null, 'tahun_ajaran'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'pdf'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'tambah'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'hapus'],
        ['Murid', 'Rapor Murid', null, 'pdf'],
    ],
    'Admin' => [
        ['Sekolah', 'Data Sekolah', null, 'lihat'],
        ['Sekolah', 'Data Sekolah', null, 'edit'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'edit'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'edit'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'lihat'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'edit'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'lihat'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'edit'],
        ['Human Capital', 'Manajemen Guru', null, 'lihat'],
        ['Human Capital', 'Manajemen Guru', null, 'edit'],
        ['Murid', 'Manajemen Murid', null, 'lihat'],
        ['Murid', 'Manajemen Murid', null, 'edit'],
        ['Murid', 'Manajemen Kelas', null, 'lihat'],
        ['Murid', 'Manajemen Kelas', null, 'edit'],
        ['Murid', 'Rapor Murid', null, 'lihat'],
        ['Murid', 'Rapor Murid', null, 'edit'],
        ['Portal Guru', 'Dashboard', null, 'lihat'],
        ['Portal Guru', 'Dashboard', null, 'edit'],
        ['Portal Guru', 'Daftar Murid', null, 'lihat'],
        ['Portal Guru', 'Daftar Murid', null, 'edit'],
        ['Sekolah', 'Data Sekolah', null, 'tahun_ajaran'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'pdf'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'tambah'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'hapus'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'tambah'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'hapus'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'status'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'kata_sandi'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'tambah'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'hapus'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'status'],
        ['Murid', 'Manajemen Murid', null, 'tambah'],
        ['Murid', 'Manajemen Kelas', null, 'tambah'],
        ['Murid', 'Manajemen Kelas', null, 'hapus'],
        ['Murid', 'Manajemen Kelas', null, 'atur_murid'],
        ['Murid', 'Rapor Murid', null, 'pdf'],
        ['Portal Guru', 'Daftar Murid', null, 'kirim'],
        ['Portal Guru', 'Daftar Murid', null, 'pdf'],
    ],
    'Guru' => [
        ['Portal Guru', 'Dashboard', null, 'lihat'],
        ['Portal Guru', 'Daftar Murid', null, 'lihat'],
        ['Portal Guru', 'Daftar Murid', null, 'edit'],
        ['Portal Guru', 'Daftar Murid', null, 'kirim'],
        ['Portal Guru', 'Daftar Murid', null, 'pdf'],
    ],
    'Superadmin' => [
        ['Sekolah', 'Data Sekolah', null, 'lihat'],
        ['Sekolah', 'Data Sekolah', null, 'edit'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'edit'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'lihat'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'edit'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'lihat'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'edit'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'lihat'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'edit'],
        ['Human Capital', 'Manajemen Guru', null, 'lihat'],
        ['Human Capital', 'Manajemen Guru', null, 'edit'],
        ['Murid', 'Manajemen Murid', null, 'lihat'],
        ['Murid', 'Manajemen Murid', null, 'edit'],
        ['Murid', 'Manajemen Kelas', null, 'lihat'],
        ['Murid', 'Manajemen Kelas', null, 'edit'],
        ['Murid', 'Rapor Murid', null, 'lihat'],
        ['Murid', 'Rapor Murid', null, 'edit'],
        ['Portal Guru', 'Dashboard', null, 'lihat'],
        ['Portal Guru', 'Dashboard', null, 'edit'],
        ['Portal Guru', 'Daftar Murid', null, 'lihat'],
        ['Portal Guru', 'Daftar Murid', null, 'edit'],
        ['Sistem', 'RBAC', null, 'lihat'],
        ['Sistem', 'RBAC', null, 'edit'],
        ['Sekolah', 'Data Sekolah', null, 'tahun_ajaran'],
        ['Sekolah', 'Kurikulum', 'Manajemen Template', 'pdf'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'tambah'],
        ['Sekolah', 'Kurikulum', 'Periode Penilaian', 'hapus'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'tambah'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'hapus'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'status'],
        ['Human Capital', 'Karyawan', 'Daftar Karyawan', 'kata_sandi'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'tambah'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'hapus'],
        ['Human Capital', 'Karyawan', 'Jabatan', 'status'],
        ['Murid', 'Manajemen Murid', null, 'tambah'],
        ['Murid', 'Manajemen Kelas', null, 'tambah'],
        ['Murid', 'Manajemen Kelas', null, 'hapus'],
        ['Murid', 'Manajemen Kelas', null, 'atur_murid'],
        ['Murid', 'Rapor Murid', null, 'pdf'],
        ['Portal Guru', 'Daftar Murid', null, 'kirim'],
        ['Portal Guru', 'Daftar Murid', null, 'pdf'],
    ],
    ];

    /** Izin eRapor: Superadmin/Admin mengelola & menyetujui; Guru mengelola profil tanda tangannya sendiri. */
    public const RBAC_ERAPOR = [
        'Superadmin' => ['Persetujuan', 'Penugasan Penyetuju', 'Profil Penandatangan'],
        'Admin' => ['Persetujuan', 'Penugasan Penyetuju', 'Profil Penandatangan'],
        'Guru' => ['Profil Penandatangan'],
    ];

    /** Tambahkan jabatan yang belum ada; tidak mengubah jabatan yang sudah ada. */
    public static function ensureJabatan(PDO $db): int
    {
        $roles = $db->query('SELECT nama,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
        $added = 0;
        foreach (self::JABATAN as $name => $role) {
            if (!isset($roles[$role])) throw new DomainException("Role $role tidak tersedia; impor database/schema.sql terlebih dahulu.");
            $q = $db->prepare('SELECT 1 FROM jabatan WHERE nama=?'); $q->execute([$name]);
            if ($q->fetchColumn()) continue;
            $db->prepare('INSERT INTO jabatan (role_id,nama,is_active) VALUES (?,?,1)')->execute([$roles[$role], $name]);
            $added++;
        }
        return $added;
    }

    /** Aksi tambahan di PermissionCatalog::EXTRA tidak ada di schema.sql; tambahkan yang belum ada (produksi memakai display_order 100). */
    public static function ensurePermissions(PDO $db): int
    {
        $find = $db->prepare('SELECT 1 FROM permissions WHERE modul=? AND section<=>? AND sub_section<=>? AND aksi=?');
        $insert = $db->prepare('INSERT INTO permissions (modul,section,sub_section,aksi,display_order) VALUES (?,?,?,?,100)');
        $added = 0;
        foreach (PermissionCatalog::EXTRA as [$modul, $section, $sub, $aksi]) {
            $find->execute([$modul, $section, $sub, $aksi]);
            if ($find->fetchColumn()) continue;
            $insert->execute([$modul, $section, $sub, $aksi]);
            $added++;
        }
        return $added;
    }

    /** Berikan izin dasar yang belum ada (hanya menambah, tidak mencabut). Mengembalikan jumlah izin baru. */
    public static function grantRbac(PDO $db): int
    {
        $roles = $db->query('SELECT nama,id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
        $find = $db->prepare('SELECT id FROM permissions WHERE modul=? AND section<=>? AND sub_section<=>? AND aksi=?');
        $has = $db->prepare('SELECT 1 FROM role_permissions WHERE role_id=? AND permission_id=?');
        $grant = $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (?,?)');
        $wanted = self::RBAC_BASELINE;
        foreach (self::RBAC_ERAPOR as $role => $sections) {
            foreach ($sections as $section) foreach (['lihat', 'edit'] as $aksi) $wanted[$role][] = ['eRapor', $section, null, $aksi];
        }
        $added = 0;
        foreach ($wanted as $role => $perms) {
            if (!isset($roles[$role])) throw new DomainException("Role $role tidak tersedia.");
            foreach ($perms as [$modul, $section, $sub, $aksi]) {
                $find->execute([$modul, $section, $sub, $aksi]);
                $permissionId = $find->fetchColumn();
                if (!$permissionId) throw new DomainException("Izin $modul/$section/$sub/$aksi tidak ada di tabel permissions.");
                $has->execute([$roles[$role], $permissionId]);
                if ($has->fetchColumn()) continue;
                $grant->execute([$roles[$role], $permissionId]);
                $added++;
            }
        }
        return $added;
    }

    /** Buat akun Superadmin (tanpa data pegawai) bila email belum ada. */
    public static function ensureSuperadmin(PDO $db, string $email, string $password): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new DomainException('Email Superadmin tidak valid.');
        if (!employeePasswordIsValid($password)) throw new DomainException('Password Superadmin minimal 8 karakter dengan huruf kapital, angka, dan simbol.');
        $q = $db->prepare('SELECT 1 FROM users WHERE email=?'); $q->execute([$email]);
        if ($q->fetchColumn()) return false;
        $role = $db->query("SELECT id FROM roles WHERE nama='Superadmin'")->fetchColumn();
        if (!$role) throw new DomainException('Role Superadmin tidak tersedia.');
        $db->prepare('INSERT INTO users (karyawan_id,role_id,email,password_hash,is_active) VALUES (NULL,?,?,?,1)')
            ->execute([$role, $email, password_hash($password, PASSWORD_DEFAULT)]);
        return true;
    }
}
