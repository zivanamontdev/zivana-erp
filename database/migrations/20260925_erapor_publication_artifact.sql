-- Private immutable PDF package artifact; this does not mark delivery complete.
ALTER TABLE erapor_sesi ADD COLUMN tanggal_pengesahan DATE NULL;

CREATE TABLE erapor_publikasi_pdf (
    sesi_id INT PRIMARY KEY,
    disiapkan_oleh INT NOT NULL,
    renderer_versi VARCHAR(40) NOT NULL,
    filename VARCHAR(180) NOT NULL,
    ukuran_byte INT UNSIGNED NOT NULL,
    pdf_sha256 CHAR(64) NOT NULL,
    sumber_sha256 CHAR(64) NOT NULL,
    manifest JSON NOT NULL,
    pdf_bytes LONGBLOB NOT NULL,
    status_kirim ENUM('SIAP_DIKIRIM','TERKIRIM') NOT NULL DEFAULT 'SIAP_DIKIRIM',
    dibuat_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY erapor_pdf_delivery (status_kirim,dibuat_pada),
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    FOREIGN KEY (disiapkan_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (ukuran_byte > 5 AND ukuran_byte <= 33554432),
    CHECK (pdf_sha256 REGEXP '^[0-9a-f]{64}$' AND sumber_sha256 REGEXP '^[0-9a-f]{64}$'),
    CHECK (SUBSTRING(pdf_bytes,1,5) = '%PDF-')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
