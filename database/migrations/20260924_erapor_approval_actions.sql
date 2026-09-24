CREATE TABLE erapor_persetujuan_snapshot (
    sesi_penyetuju_id INT PRIMARY KEY,
    sesi_id INT NOT NULL,
    user_id INT NOT NULL,
    nama VARCHAR(255) NOT NULL,
    nuptk VARCHAR(32) NULL,
    ttd_png MEDIUMBLOB NULL,
    ttd_sha256 CHAR(64) NULL,
    ttd_disetujui_pada DATETIME NULL,
    log_id INT NOT NULL UNIQUE,
    disetujui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_penyetuju_id,sesi_id) REFERENCES erapor_sesi_penyetuju(id,sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (log_id) REFERENCES erapor_sesi_log(id) ON DELETE RESTRICT,
    CHECK ((ttd_png IS NULL AND ttd_sha256 IS NULL AND ttd_disetujui_pada IS NULL)
        OR (ttd_png IS NOT NULL AND ttd_sha256 IS NOT NULL AND ttd_disetujui_pada IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
