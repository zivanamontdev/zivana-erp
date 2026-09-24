-- Concrete session values, stage and sublevel stored separately.
CREATE TABLE erapor_agama_nilai (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    item_id INT NOT NULL,
    tahapan_id INT NOT NULL,
    tahapan_kode VARCHAR(20) NOT NULL,
    subtingkat_id INT NULL,
    subtingkat_kode CHAR(1) NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,item_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES erapor_agama_item(id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id,tahapan_id,tahapan_kode) REFERENCES erapor_agama_tahapan(rubrik_id,id,kode) ON DELETE RESTRICT,
    FOREIGN KEY (tahapan_id,subtingkat_id,subtingkat_kode) REFERENCES erapor_agama_subtingkat(tahapan_id,id,kode) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK ((tahapan_kode='TAHFIZH' AND subtingkat_id IS NOT NULL AND subtingkat_kode IS NOT NULL)
        OR (tahapan_kode<>'TAHFIZH' AND subtingkat_id IS NULL AND subtingkat_kode IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_catatan (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    lingkup_id INT NOT NULL,
    isi TEXT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,lingkup_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (lingkup_id) REFERENCES erapor_agama_lingkup(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
