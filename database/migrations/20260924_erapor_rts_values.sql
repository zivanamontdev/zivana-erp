-- New assessment storage only, no conversion of legacy grades.
CREATE TABLE erapor_rts_nilai (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    indikator_id INT NOT NULL,
    skala_id INT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,indikator_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (indikator_id) REFERENCES erapor_rubrik_indikator(id) ON DELETE RESTRICT,
    FOREIGN KEY (skala_id) REFERENCES erapor_skala_nilai(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_isian_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    jenis VARCHAR(20) NOT NULL,
    kunci VARCHAR(191) NOT NULL,
    nilai_lama JSON NULL,
    nilai_baru JSON NULL,
    aktor_id INT NOT NULL,
    status_sesi VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (aktor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
