-- Ummi storage; client-generated random tokens make dynamic test-row retries idempotent.
CREATE TABLE erapor_ummi_bacaan (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    materi_id INT NOT NULL,
    nilai VARCHAR(10) NOT NULL,
    FOREIGN KEY (materi_id) REFERENCES erapor_ummi_materi(id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id,nilai) REFERENCES erapor_skala_huruf(rubrik_id,kode) ON DELETE RESTRICT,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,materi_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ummi_catatan (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    isi TEXT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ummi_periode (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    mulai_pra_tk BOOLEAN NOT NULL,
    CHECK (mulai_pra_tk IN (0,1)),
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ummi_tes (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    token CHAR(32) NOT NULL,
    urutan INT NOT NULL,
    tanggal_tes DATE NOT NULL,
    jilid VARCHAR(150) NOT NULL,
    nilai VARCHAR(10) NOT NULL,
    CHECK (urutan>0),
    FOREIGN KEY (rubrik_id,nilai) REFERENCES erapor_skala_huruf(rubrik_id,kode) ON DELETE RESTRICT,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,token),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
