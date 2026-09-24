-- Additive catalog only. No legacy table is altered or populated.
-- Apply with EraporMigrationRunner after backup; immutable after first use.
CREATE TABLE erapor_rubrik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(100) NOT NULL UNIQUE,
    jenis_dokumen ENUM('RTS','RAS','AGAMA','UMMI','BING','PPI') NOT NULL,
    nama VARCHAR(200) NOT NULL,
    judul_cetak VARCHAR(255) NOT NULL,
    versi INT NOT NULL,
    status ENUM('draft','terkunci','arsip') NOT NULL DEFAULT 'draft',
    cakupan ENUM('TAHUNAN','SEMESTER') NOT NULL,
    jenis_periode ENUM('TENGAH','AKHIR') NULL,
    cetak_gabung_periode BOOLEAN NOT NULL,
    khusus_abk BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT erapor_rubrik_version CHECK (versi > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_bagian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(40) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    jenis ENUM('identitas','rubrik','matriks','daftar_catatan','teks_bebas') NOT NULL,
    wajib BOOLEAN NOT NULL,
    urutan INT NOT NULL,
    UNIQUE KEY erapor_bagian_code (rubrik_id,kode),
    UNIQUE KEY erapor_bagian_order (rubrik_id,urutan),
    CONSTRAINT erapor_bagian_rubrik FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CONSTRAINT erapor_bagian_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_periode (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(40) NOT NULL,
    label VARCHAR(100) NOT NULL,
    urutan INT NOT NULL,
    semester ENUM('GANJIL','GENAP') NULL,
    jenis ENUM('TENGAH','AKHIR') NOT NULL,
    UNIQUE KEY erapor_kolom_code (rubrik_id,kode),
    UNIQUE KEY erapor_kolom_order (rubrik_id,urutan),
    CONSTRAINT erapor_kolom_rubrik FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CONSTRAINT erapor_kolom_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_penandatangan (
    rubrik_id INT NOT NULL,
    urutan INT NOT NULL,
    peran ENUM('GURU_KELAS','KEPALA_SEKOLAH','KOORDINATOR_QURAN','KOORDINATOR_BING','ORANG_TUA') NOT NULL,
    jabatan_cetak VARCHAR(255) NOT NULL,
    prefiks VARCHAR(100) NULL,
    cetak_nuptk BOOLEAN NOT NULL,
    posisi_cetak VARCHAR(40) NULL,
    PRIMARY KEY (rubrik_id,urutan),
    CONSTRAINT erapor_ttd_rubrik FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CONSTRAINT erapor_ttd_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
