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
    aksi VARCHAR(40) NOT NULL,
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
    -- [FIX] Bukan child dari section 'Karyawan' — dikonfirmasi dari
    -- assets/ss/Sistem - menu_RBAC.svg, "Manajemen Guru" adalah section
    -- top-level tersendiri di bawah modul 'Human Capital', sejajar
    -- dengan 'Karyawan' (sama seperti struktur sidebar, lihat fix
    -- shell-header.php). RoleMiddleware::hasAccess() tetap match lewat
    -- kondisi "p.section = :sub_section" (OR fallback), jadi perubahan
    -- ini murni benerin tampilan tree RBAC, tidak mengubah perilaku
    -- permission check yang sudah ada.
    UNION ALL SELECT 'Human Capital', 'Manajemen Guru', NULL, 'lihat', 11
    UNION ALL SELECT 'Human Capital', 'Manajemen Guru', NULL, 'edit', 12
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
    UNION ALL SELECT 'eRapor', 'Persetujuan', NULL, 'lihat', 100
    UNION ALL SELECT 'eRapor', 'Persetujuan', NULL, 'edit', 101
    UNION ALL SELECT 'eRapor', 'Penugasan Penyetuju', NULL, 'lihat', 102
    UNION ALL SELECT 'eRapor', 'Penugasan Penyetuju', NULL, 'edit', 103
    UNION ALL SELECT 'eRapor', 'Profil Penandatangan', NULL, 'lihat', 104
    UNION ALL SELECT 'eRapor', 'Profil Penandatangan', NULL, 'edit', 105
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

-- role_id: setiap jabatan punya SATU role RBAC tetap (bukan dipilih per
-- karyawan) — keputusan bersama user, lihat cookbook/todo.md Fase 4.
-- Karyawan otomatis mewarisi role dari jabatan-nya saat dibuat.
CREATE TABLE IF NOT EXISTS jabatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NULL,
    nama VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_jabatan_nama (nama),
    CONSTRAINT fk_jabatan_role FOREIGN KEY (role_id) REFERENCES roles(id)
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

-- =========================================================
-- MURID & KELAS (Fase 5)
-- =========================================================

CREATE TABLE IF NOT EXISTS kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT NOT NULL,
    level_kelas VARCHAR(50) NOT NULL,
    nama_kelas VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_kelas_tahun_ajaran FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS murid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Tab Data Murid
    nama_lengkap VARCHAR(150) NOT NULL,
    nama_panggilan VARCHAR(50) NOT NULL,
    nisn VARCHAR(20) NULL,
    agama VARCHAR(30) NOT NULL,
    nik VARCHAR(20) NOT NULL,
    no_registrasi_akte VARCHAR(50) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    tempat_lahir VARCHAR(100) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    alamat TEXT NOT NULL,
    -- Tab Informasi Pendaftaran
    tanggal_masuk_sekolah DATE NOT NULL,
    status_kondisi VARCHAR(50) NOT NULL,
    jenis_kebutuhan VARCHAR(150) NULL,
    kelengkapan_berkas VARCHAR(150) NULL,
    kelas_id INT NULL,
    -- Tab Relasi & Kontak
    alamat_domisili VARCHAR(255) NOT NULL,
    anak_ke INT NULL,
    jumlah_saudara INT NOT NULL,
    nama_ayah VARCHAR(150) NOT NULL,
    pendidikan_ayah VARCHAR(100) NOT NULL,
    pekerjaan_ayah VARCHAR(100) NOT NULL,
    telp_ayah VARCHAR(30) NOT NULL,
    nama_ibu VARCHAR(150) NOT NULL,
    pendidikan_ibu VARCHAR(100) NOT NULL,
    pekerjaan_ibu VARCHAR(100) NOT NULL,
    telp_ibu VARCHAR(30) NOT NULL,
    -- Status
    status ENUM('bersekolah', 'tanpa_keterangan', 'tamat', 'berhenti') NOT NULL DEFAULT 'bersekolah',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_murid_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot 3-arah: satu kelas bisa punya beberapa guru, masing-masing
-- guru punya subset murid sendiri di kelas itu. Dipakai bersama oleh
-- modul Manajemen Guru dan Detail Kelas (satu implementasi reusable).
CREATE TABLE IF NOT EXISTS kelas_guru_murid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_id INT NOT NULL,
    guru_id INT NOT NULL,
    murid_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_kelas_guru_murid (kelas_id, guru_id, murid_id),
    UNIQUE KEY uq_murid_single_guru (murid_id),
    CONSTRAINT fk_kgm_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    CONSTRAINT fk_kgm_guru FOREIGN KEY (guru_id) REFERENCES karyawan(id) ON DELETE CASCADE,
    CONSTRAINT fk_kgm_murid FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- KURIKULUM & TEMPLATE RAPOR (Fase 6)
-- =========================================================

CREATE TABLE IF NOT EXISTS skala_nilai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- simbol: kode pendek untuk render bentuk visual di view (bukan teks
-- simbol asli, karena simbolnya berupa bentuk segitiga custom, bukan
-- karakter unicode standar). Lihat cookbook/design-system.md 3.5.
CREATE TABLE IF NOT EXISTS skala_nilai_opsi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    skala_id INT NOT NULL,
    simbol VARCHAR(20) NOT NULL,
    label VARCHAR(100) NOT NULL,
    display_order INT DEFAULT 0,
    CONSTRAINT fk_skala_opsi_skala FOREIGN KEY (skala_id) REFERENCES skala_nilai(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_rapor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    kategori ENUM('rapor_murid', 'rapor_sekolah') NOT NULL DEFAULT 'rapor_murid',
    tipe ENUM('system', 'custom') NOT NULL DEFAULT 'system',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_rapor_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    nama_area VARCHAR(150) NOT NULL,
    display_order INT DEFAULT 0,
    CONSTRAINT fk_tr_area_template FOREIGN KEY (template_id) REFERENCES template_rapor(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_rapor_subkategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    label VARCHAR(5) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    display_order INT DEFAULT 0,
    CONSTRAINT fk_tr_sub_area FOREIGN KEY (area_id) REFERENCES template_rapor_area(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS template_rapor_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subkategori_id INT NOT NULL,
    nama_tujuan VARCHAR(255) NOT NULL,
    skala_nilai_id INT NOT NULL,
    display_order INT DEFAULT 0,
    CONSTRAINT fk_tr_item_sub FOREIGN KEY (subkategori_id) REFERENCES template_rapor_subkategori(id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_item_skala FOREIGN KEY (skala_nilai_id) REFERENCES skala_nilai(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS periode_penilaian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tahun_ajaran_id INT NOT NULL,
    semester ENUM('ganjil','genap') NULL,
    nama VARCHAR(150) NOT NULL,
    tipe VARCHAR(50) NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    awal_periode DATE NOT NULL,
    akhir_periode DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_periode_year_semester_type (tahun_ajaran_id, semester, tipe),
    CONSTRAINT fk_periode_tahun_ajaran FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed skala nilai Montessori 4 simbol, dikonfirmasi dari crawling
-- Pratinjau Template & Pratinjau Rapor Murid (lihat
-- cookbook/design-system.md bagian 3.5).
INSERT INTO skala_nilai (id, nama) VALUES (1, 'Montessori 4 Simbol')
ON DUPLICATE KEY UPDATE nama = VALUES(nama);

INSERT INTO skala_nilai_opsi (skala_id, simbol, label, display_order)
SELECT * FROM (
    SELECT 1 AS skala_id, 'slash' AS simbol, 'Baru dikenalkan' AS label, 1 AS display_order
    UNION ALL SELECT 1, 'triangle-sm', 'Mulai Berkembang', 2
    UNION ALL SELECT 1, 'triangle-lg', 'Berkembang Sesuai Harapan', 3
    UNION ALL SELECT 1, 'triangle-full', 'Berkembang Sangat Baik', 4
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM skala_nilai_opsi WHERE skala_id = 1);

-- Seed 4 template sistem, dikonfirmasi dari crawling Manajemen
-- Template (semua bertipe "System", tidak ada tombol tambah — lihat
-- cookbook/design-system.md 5.2). Hanya "Rapor Montessori Tengah
-- Semester" yang dibangun strukturnya secara detail (area/subkategori/
-- item) karena hanya itu yang ter-crawl lengkap dari Pratinjau
-- Template; 3 template lain dibuat sebagai baris kosong dulu.
INSERT INTO template_rapor (id, nama, kategori, tipe) VALUES
    (1, 'Rapor Montessori Tengah Semester', 'rapor_murid', 'system'),
    (2, 'Rapor Montessori Akhir Semester', 'rapor_murid', 'system'),
    (3, 'Rapor Al-Qur''an', 'rapor_sekolah', 'system'),
    (4, 'Rapor Bahasa Inggris', 'rapor_sekolah', 'system')
ON DUPLICATE KEY UPDATE nama = VALUES(nama);

-- Struktur "Rapor Montessori Tengah Semester" (template_id=1).
-- [ASUMSI] Hanya 1 area ("Area Keterampilan Hidup") dan 1 item contoh
-- ("Menutup mulut saat batuk dan bersin") yang terkonfirmasi persis
-- dari crawling halaman 1 dari 4 dokumen. Sisa item di bawah ini
-- PLACEHOLDER masuk akal secara konteks Montessori, BUKAN konten
-- kurikulum resmi — WAJIB diganti dengan data asli dari user sebelum
-- dipakai produksi.
INSERT INTO template_rapor_area (id, template_id, nama_area, display_order) VALUES
    (1, 1, 'AREA KETERAMPILAN HIDUP', 1)
ON DUPLICATE KEY UPDATE nama_area = VALUES(nama_area);

INSERT INTO template_rapor_subkategori (id, area_id, label, nama, display_order) VALUES
    (1, 1, 'a', 'Perawatan Diri', 1),
    (2, 1, 'b', 'Motorik Halus', 2),
    (3, 1, 'c', 'Motorik Kasar', 3),
    (4, 1, 'd', 'Kepedulian Terhadap Lingkungan', 4)
ON DUPLICATE KEY UPDATE nama = VALUES(nama);

INSERT INTO template_rapor_item (subkategori_id, nama_tujuan, skala_nilai_id, display_order)
SELECT * FROM (
    SELECT 1 AS subkategori_id, 'Menutup mulut saat batuk dan bersin' AS nama_tujuan, 1 AS skala_nilai_id, 1 AS display_order
    UNION ALL SELECT 1, 'Mencuci tangan sebelum dan sesudah makan', 1, 2
    UNION ALL SELECT 1, 'Memakai dan melepas sepatu sendiri', 1, 3
    UNION ALL SELECT 2, 'Menggunting mengikuti garis lurus', 1, 1
    UNION ALL SELECT 2, 'Meronce manik-manik', 1, 2
    UNION ALL SELECT 3, 'Berjalan di atas garis lurus', 1, 1
    UNION ALL SELECT 3, 'Melompat dengan dua kaki', 1, 2
    UNION ALL SELECT 4, 'Membuang sampah pada tempatnya', 1, 1
    UNION ALL SELECT 4, 'Merapikan alat main setelah digunakan', 1, 2
) AS seed_data
WHERE NOT EXISTS (SELECT 1 FROM template_rapor_item WHERE subkategori_id IN (1, 2, 3, 4));

-- =========================================================
-- RAPOR (Fase 7)
-- =========================================================

CREATE TABLE IF NOT EXISTS sesi_pembagian_rapor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    template_id INT NOT NULL,
    nama VARCHAR(150) NOT NULL,
    tanggal_mulai DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sesi_periode FOREIGN KEY (periode_id) REFERENCES periode_penilaian(id) ON DELETE CASCADE,
    CONSTRAINT fk_sesi_template FOREIGN KEY (template_id) REFERENCES template_rapor(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- disetujui_oleh: FK ke users, BUKAN role tertentu yang di-hardcode.
-- [KEPUTUSAN pengganti item asumsi "role approver"] siapa yang boleh
-- approve diatur lewat RBAC (permission 'edit' pada Murid > Rapor
-- Murid), bukan role spesifik yang ditulis di kode. Sekolah bebas
-- assign permission itu ke role manapun (Koordinator Guru, Admin,
-- dst) lewat halaman RBAC yang sudah ada.
CREATE TABLE IF NOT EXISTS rapor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    murid_id INT NOT NULL,
    sesi_pembagian_id INT NOT NULL,
    template_id INT NOT NULL,
    guru_id INT NULL,
    status ENUM('belum_diisi', 'menunggu_persetujuan', 'disetujui') NOT NULL DEFAULT 'belum_diisi',
    disetujui_oleh INT NULL,
    disetujui_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rapor_murid_sesi (murid_id, sesi_pembagian_id),
    CONSTRAINT fk_rapor_murid FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE,
    CONSTRAINT fk_rapor_sesi FOREIGN KEY (sesi_pembagian_id) REFERENCES sesi_pembagian_rapor(id) ON DELETE CASCADE,
    CONSTRAINT fk_rapor_template FOREIGN KEY (template_id) REFERENCES template_rapor(id),
    CONSTRAINT fk_rapor_guru FOREIGN KEY (guru_id) REFERENCES karyawan(id) ON DELETE SET NULL,
    CONSTRAINT fk_rapor_disetujui_oleh FOREIGN KEY (disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapor_nilai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rapor_id INT NOT NULL,
    item_id INT NOT NULL,
    semester ENUM('ganjil', 'genap') NOT NULL,
    skala_nilai_opsi_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rapor_nilai (rapor_id, item_id, semester),
    CONSTRAINT fk_rapor_nilai_rapor FOREIGN KEY (rapor_id) REFERENCES rapor(id) ON DELETE CASCADE,
    CONSTRAINT fk_rapor_nilai_item FOREIGN KEY (item_id) REFERENCES template_rapor_item(id),
    CONSTRAINT fk_rapor_nilai_opsi FOREIGN KEY (skala_nilai_opsi_id) REFERENCES skala_nilai_opsi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rapor_catatan_guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rapor_id INT NOT NULL,
    area_id INT NOT NULL,
    catatan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rapor_catatan (rapor_id, area_id),
    CONSTRAINT fk_rapor_catatan_rapor FOREIGN KEY (rapor_id) REFERENCES rapor(id) ON DELETE CASCADE,
    CONSTRAINT fk_rapor_catatan_area FOREIGN KEY (area_id) REFERENCES template_rapor_area(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
