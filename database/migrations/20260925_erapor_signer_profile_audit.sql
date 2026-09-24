CREATE TABLE erapor_profil_penandatangan_audit (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    aksi ENUM('PERBARUI_NUPTK','SETUJUI_TANDA_TANGAN','CABUT_TANDA_TANGAN') NOT NULL,
    nuptk VARCHAR(32) NULL,
    ttd_sha256 CHAR(64) NULL,
    versi_persetujuan VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY erapor_signer_audit_user (user_id,created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK ((aksi='PERBARUI_NUPTK' AND ttd_sha256 IS NULL AND versi_persetujuan IS NULL)
        OR (aksi IN ('SETUJUI_TANDA_TANGAN','CABUT_TANDA_TANGAN') AND ttd_sha256 IS NOT NULL AND versi_persetujuan IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
