-- Per-session BING/PPI values only; no legacy conversion or outcome storage.
CREATE TABLE erapor_bing_nilai (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    indikator_id INT NOT NULL,
    pilihan_kode VARCHAR(20) NOT NULL,
    FOREIGN KEY (indikator_id) REFERENCES erapor_bing_indikator(id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id,pilihan_kode) REFERENCES erapor_bing_skala(rubrik_id,kode) ON DELETE RESTRICT,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,indikator_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_bing_isian (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    komentar_id INT NOT NULL,
    isi TEXT NOT NULL,
    FOREIGN KEY (komentar_id) REFERENCES erapor_bing_komentar(id) ON DELETE RESTRICT,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,komentar_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_isian (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    aspek_id INT NOT NULL,
    kolom_id INT NOT NULL,
    isi TEXT NOT NULL,
    FOREIGN KEY (aspek_id) REFERENCES erapor_ppi_aspek(id) ON DELETE RESTRICT,
    FOREIGN KEY (kolom_id) REFERENCES erapor_ppi_kolom(id) ON DELETE RESTRICT,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,aspek_id,kolom_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
