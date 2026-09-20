-- Zivana ERP — Skema Database
-- Lihat cookbook/schema.md untuk dokumentasi lengkap tiap tabel dan relasinya.
-- File ini ditambah bertahap per fase implementasi (lihat cookbook/todo.md).
--
-- Fase 0-1: Auth & RBAC (roles, permissions, role_permissions, users, password_resets)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================
-- AUTH & RBAC
-- =========================================================

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modul VARCHAR(50) NOT NULL,
    section VARCHAR(100) NULL,
    sub_section VARCHAR(100) NULL,
    aksi ENUM('lihat', 'edit') NOT NULL,
    display_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- karyawan_id sengaja tanpa FOREIGN KEY dulu: tabel karyawan baru dibuat
-- di Fase 4 (Human Capital). Constraint FK ditambahkan lewat file di
-- database/migrations/ setelah tabel karyawan tersedia.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    karyawan_id INT NULL,
    role_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    remember_token VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 4 role tetap, dikonfirmasi dari layar Sistem -> RBAC
-- (lihat cookbook/design-system.md bagian 5.3). Idempotent lewat
-- UNIQUE KEY uq_roles_nama di atas.
INSERT INTO roles (nama) VALUES
    ('Superadmin'),
    ('Admin'),
    ('Koordinator Guru'),
    ('Guru')
ON DUPLICATE KEY UPDATE nama = VALUES(nama);

-- Seed node permission mengikuti persis struktur navigasi di
-- cookbook/design-system.md bagian 2.2. Tiap node punya aksi 'lihat'
-- dan 'edit' secara konsisten supaya matriks RBAC seragam, walau di
-- praktiknya tidak semua kombinasi dipakai (lihat cookbook/prd.md
-- untuk detail per modul).
-- Skrip ini idempotent: jalankan hanya kalau tabel permissions kosong,
-- supaya tidak duplikat kalau schema.sql dijalankan ulang.
INSERT INTO permissions (modul, section, sub_section, aksi, display_order)
SELECT * FROM (
    SELECT 'Sekolah' AS modul, 'Data Sekolah' AS section, NULL AS sub_section, 'lihat' AS aksi, 1 AS display_order
    UNION ALL SELECT 'Sekolah', 'Data Sekolah', NULL, 'edit', 2
    UNION ALL SELECT 'Sekolah', 'Kurikulum', 'Manajemen Template', 'lihat', 3
    UNION ALL SELECT 'Sekolah', 'Kurikulum', 'Manajemen Template', 'edit', 4
    UNION ALL SELECT 'Sekolah', 'Kurikulum', 'Periode Penilaian', 'lihat', 5
    UNION ALL SELECT 'Sekolah', 'Kurikulum', 'Periode Penilaian', 'edit', 6
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Daftar Karyawan', 'lihat', 7
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Daftar Karyawan', 'edit', 8
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Jabatan', 'lihat', 9
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Jabatan', 'edit', 10
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Manajemen Guru', 'lihat', 11
    UNION ALL SELECT 'Human Capital', 'Karyawan', 'Manajemen Guru', 'edit', 12
    UNION ALL SELECT 'Murid', 'Manajemen Murid', NULL, 'lihat', 13
    UNION ALL SELECT 'Murid', 'Manajemen Murid', NULL, 'edit', 14
    UNION ALL SELECT 'Murid', 'Manajemen Kelas', NULL, 'lihat', 15
    UNION ALL SELECT 'Murid', 'Manajemen Kelas', NULL, 'edit', 16
    UNION ALL SELECT 'Murid', 'Rapor Murid', NULL, 'lihat', 17
    UNION ALL SELECT 'Murid', 'Rapor Murid', NULL, 'edit', 18
    UNION ALL SELECT 'Portal Guru', 'Dashboard', NULL, 'lihat', 19
    UNION ALL SELECT 'Portal Guru', 'Dashboard', NULL, 'edit', 20
    UNION ALL SELECT 'Portal Guru', 'Daftar Murid', NULL, 'lihat', 21
    UNION ALL SELECT 'Portal Guru', 'Daftar Murid', NULL, 'edit', 22
    UNION ALL SELECT 'Sistem', 'RBAC', NULL, 'lihat', 23
    UNION ALL SELECT 'Sistem', 'RBAC', NULL, 'edit', 24
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM permissions LIMIT 1);

-- =========================================================
-- SEKOLAH (Fase 3)
-- =========================================================

-- Singleton: hanya 1 baris (aplikasi tidak multi-tenant), lihat
-- cookbook/schema.md bagian 2.
CREATE TABLE IF NOT EXISTS sekolah (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_legal VARCHAR(200) NOT NULL,
    nama_komersial VARCHAR(200) NOT NULL,
    bentuk_pendidikan VARCHAR(50) NOT NULL,
    npsn VARCHAR(20) NOT NULL,
    alamat TEXT NOT NULL,
    no_telepon VARCHAR(30) NOT NULL,
    email VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sekolah_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sekolah_id INT NOT NULL,
    jenis_media VARCHAR(50) NOT NULL,
    nama_akun VARCHAR(150) NOT NULL,
    url VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sekolah_media_sekolah FOREIGN KEY (sekolah_id) REFERENCES sekolah(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tahun_ajaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun_awal YEAR NOT NULL,
    tahun_akhir YEAR NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- HUMAN CAPITAL (Fase 4)
-- =========================================================

CREATE TABLE IF NOT EXISTS jabatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_jabatan_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS karyawan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jabatan_id INT NOT NULL,
    nama VARCHAR(150) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_karyawan_jabatan FOREIGN KEY (jabatan_id) REFERENCES jabatan(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tambahkan FK users.karyawan_id -> karyawan.id sekarang tabel karyawan
-- sudah ada (tabel users dibuat lebih dulu di Fase 1 tanpa FK ini,
-- lihat komentar di CREATE TABLE users).
-- CATATAN PENTING: berbeda dari CREATE TABLE IF NOT EXISTS di atas,
-- ALTER TABLE ini TIDAK idempotent. Kalau schema.sql dijalankan dari
-- nol ke database kosong, aman. Kalau dijalankan ulang ke database
-- yang sudah pernah menjalankan baris ini sebelumnya, akan muncul
-- error "Duplicate foreign key constraint name" — itu WAJAR dan aman
-- diabaikan (tandanya constraint memang sudah terpasang).
ALTER TABLE users
    ADD CONSTRAINT fk_users_karyawan FOREIGN KEY (karyawan_id) REFERENCES karyawan(id);
