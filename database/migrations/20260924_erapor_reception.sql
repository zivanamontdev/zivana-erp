-- Additive employee profile extension; private signature bytes, never a public URL.
CREATE TABLE erapor_profil_penandatangan (
    user_id INT PRIMARY KEY,
    nuptk VARCHAR(32) NULL,
    ttd_png MEDIUMBLOB NULL,
    ttd_disetujui_pada DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK ((ttd_png IS NULL AND ttd_disetujui_pada IS NULL) OR (ttd_png IS NOT NULL AND ttd_disetujui_pada IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi_penerimaan (
    sesi_id INT PRIMARY KEY,
    guru_user_id INT NOT NULL,
    guru_nama VARCHAR(255) NOT NULL,
    guru_nuptk VARCHAR(32) NULL,
    guru_ttd_png MEDIUMBLOB NULL,
    guru_ttd_sha256 CHAR(64) NULL,
    guru_ttd_disetujui_pada DATETIME NULL,
    diterima_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    FOREIGN KEY (guru_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK ((guru_ttd_png IS NULL AND guru_ttd_sha256 IS NULL AND guru_ttd_disetujui_pada IS NULL)
        OR (guru_ttd_png IS NOT NULL AND guru_ttd_sha256 IS NOT NULL AND guru_ttd_disetujui_pada IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
