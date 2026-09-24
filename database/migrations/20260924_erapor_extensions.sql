CREATE TABLE erapor_periode_perpanjangan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    periode_id INT NOT NULL,
    tanggal_akhir_lama DATE NOT NULL,
    tanggal_akhir_baru DATE NOT NULL,
    alasan TEXT NOT NULL,
    diperpanjang_oleh INT NOT NULL,
    diperpanjang_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (periode_id) REFERENCES periode_penilaian(id) ON DELETE RESTRICT,
    FOREIGN KEY (diperpanjang_oleh) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (tanggal_akhir_baru>tanggal_akhir_lama),
    INDEX (periode_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
