-- Rapor Akhir Semester (RAS): narasi "Perkembangan anak secara keseluruhan", satu baris per sesi (Ganjil dan Genap terpisah).
-- Nilai indikator RAS memakai erapor_rts_nilai / erapor_rts_belum_dikenalkan yang sama (kunci sesi+dokumen+indikator).
CREATE TABLE erapor_ras_narasi (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    isi TEXT NOT NULL,
    diisi_oleh INT NOT NULL,
    diisi_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesi_id,dokumen_id),
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT,
    FOREIGN KEY (diisi_oleh) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
