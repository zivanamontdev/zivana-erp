-- Pilihan RTS "Belum Dikenalkan" (tanda -). Skala rubrik V1 (1..4) tetap tidak diubah;
-- baris di sini menggantikan erapor_rts_nilai untuk indikator yang sama (saling eksklusif, dijaga EraporRtsEntry).
CREATE TABLE erapor_rts_belum_dikenalkan (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    indikator_id INT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id,indikator_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (indikator_id) REFERENCES erapor_rubrik_indikator(id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
