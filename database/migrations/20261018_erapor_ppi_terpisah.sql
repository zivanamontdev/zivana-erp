-- Preserve historical packages by default. The PIC choice freezes the main approval scope.
ALTER TABLE erapor_sesi ADD COLUMN ppi_disertakan TINYINT(1) NOT NULL DEFAULT 1;
-- Effective publication choice can also be detached by the head before any official artifact exists.
ALTER TABLE erapor_sesi ADD COLUMN ppi_terpisah TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE erapor_ppi_pengajuan (
    sesi_id INT NOT NULL PRIMARY KEY,
    status ENUM('BELUM_DIAJUKAN','MENUNGGU_WALI','MENUNGGU_KEPALA','DISETUJUI','TERBIT') NOT NULL DEFAULT 'BELUM_DIAJUKAN',
    diajukan_oleh INT NULL,
    diajukan_pada TIMESTAMP NULL,
    tanggal_pengesahan DATE NULL,
    nilai_saat_pisah LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    FOREIGN KEY (diajukan_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_persetujuan (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    kode ENUM('WALI_KELAS','KEPALA_SEKOLAH') NOT NULL,
    user_id INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    nuptk VARCHAR(16) NULL,
    ttd_png MEDIUMBLOB NULL,
    ttd_sha256 CHAR(64) NULL,
    ttd_disetujui_pada DATETIME NULL,
    disetujui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY erapor_ppi_per_stage (sesi_id,kode),
    FOREIGN KEY (sesi_id) REFERENCES erapor_ppi_pengajuan(sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    aktor_id INT NOT NULL,
    aksi VARCHAR(40) NOT NULL,
    status_lama VARCHAR(32) NULL,
    status_baru VARCHAR(32) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES erapor_ppi_pengajuan(sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (aktor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_pdf (
    sesi_id INT NOT NULL PRIMARY KEY,
    filename VARCHAR(200) NOT NULL,
    renderer_versi VARCHAR(80) NOT NULL,
    pdf_bytes LONGBLOB NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    ukuran_byte INT UNSIGNED NOT NULL,
    manifest LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    diterbitkan_oleh INT NULL,
    diterbitkan_pada TIMESTAMP NULL,
    r2_bucket VARCHAR(255) NULL,
    r2_key VARCHAR(512) NULL,
    unduh_token CHAR(64) NULL,
    unduh_url VARCHAR(1024) NULL,
    unduh_kedaluwarsa DATETIME NULL,
    dibagikan_pada TIMESTAMP NULL,
    dibagikan_oleh INT NULL,
    dibagikan_ke ENUM('AYAH','IBU') NULL,
    jumlah_unduh INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY erapor_ppi_download_token (unduh_token),
    FOREIGN KEY (sesi_id) REFERENCES erapor_ppi_pengajuan(sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (diterbitkan_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (dibagikan_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
