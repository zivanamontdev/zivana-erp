-- Additive only. Official artifacts, approval evidence and public distribution are never updated.
CREATE TABLE erapor_pdf_sumber (
    sesi_id INT NOT NULL,
    jenis ENUM('RAPOR','PPI') NOT NULL,
    paket_json LONGTEXT NOT NULL,
    paket_sha256 CHAR(64) NOT NULL,
    sumber_sha256 CHAR(64) NOT NULL,
    pdf_asli_sha256 CHAR(64) NOT NULL,
    metode ENUM('SAAT_PERSETUJUAN','HASH_HISTORIS') NOT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,jenis),
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    CHECK (JSON_VALID(paket_json)),
    CHECK (paket_sha256 REGEXP '^[0-9a-f]{64}$' AND sumber_sha256 REGEXP '^[0-9a-f]{64}$' AND pdf_asli_sha256 REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_pdf_revisi (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    jenis ENUM('RAPOR','PPI') NOT NULL,
    paket_sha256 CHAR(64) NOT NULL,
    pdf_asli_sha256 CHAR(64) NOT NULL,
    desain_sha256 CHAR(64) NOT NULL,
    renderer_versi VARCHAR(80) NOT NULL,
    filename VARCHAR(200) NOT NULL,
    pdf_bytes LONGBLOB NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    ukuran_byte INT UNSIGNED NOT NULL,
    halaman SMALLINT UNSIGNED NOT NULL,
    dibuat_oleh INT NOT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY erapor_revisi_desain (sesi_id,jenis,paket_sha256,desain_sha256),
    UNIQUE KEY erapor_revisi_scope (sesi_id,jenis,id),
    FOREIGN KEY (sesi_id,jenis) REFERENCES erapor_pdf_sumber(sesi_id,jenis) ON DELETE RESTRICT,
    FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (ukuran_byte > 5 AND ukuran_byte <= 33554432 AND halaman BETWEEN 1 AND 80),
    CHECK (SUBSTRING(pdf_bytes,1,5) = '%PDF-'),
    CHECK (pdf_sha256 REGEXP '^[0-9a-f]{64}$' AND desain_sha256 REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_pdf_aktif (
    sesi_id INT NOT NULL,
    jenis ENUM('RAPOR','PPI') NOT NULL,
    revisi_id BIGINT NOT NULL,
    diaktifkan_oleh INT NOT NULL,
    diaktifkan_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,jenis),
    FOREIGN KEY (sesi_id,jenis,revisi_id) REFERENCES erapor_pdf_revisi(sesi_id,jenis,id) ON DELETE RESTRICT,
    FOREIGN KEY (diaktifkan_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_pdf_revisi_log (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    jenis ENUM('RAPOR','PPI') NOT NULL,
    revisi_id BIGINT NOT NULL,
    aktor_id INT NOT NULL,
    aksi ENUM('SIAPKAN','AKTIFKAN','KEMBALI_ASLI') NOT NULL,
    alasan VARCHAR(500) NOT NULL,
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id,jenis,revisi_id) REFERENCES erapor_pdf_revisi(sesi_id,jenis,id) ON DELETE RESTRICT,
    FOREIGN KEY (aktor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
