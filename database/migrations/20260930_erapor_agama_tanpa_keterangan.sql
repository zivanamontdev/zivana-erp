-- Jawaban Agama "-" tanpa keterangan, terpisah dari pilihan tahapan resmi.
CREATE TABLE erapor_agama_belum_dikenalkan (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    item_id INT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,item_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    FOREIGN KEY (item_id) REFERENCES erapor_agama_item(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
