-- Definition-only BING V1. Requires catalog, RTS seed history and source snapshot.
CREATE TABLE erapor_bing_skala (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(20) NOT NULL,
    label VARCHAR(40) NOT NULL,
    peringkat INT NOT NULL,
    urutan_tampil INT NOT NULL,
    definisi TEXT NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,peringkat),
    UNIQUE KEY (rubrik_id,urutan_tampil),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (peringkat BETWEEN 1 AND 4 AND urutan_tampil BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_bing_indikator (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(100) NOT NULL,
    urutan INT NOT NULL,
    label_cetak VARCHAR(160) NOT NULL,
    grup VARCHAR(60) NULL,
    penanda_cetak VARCHAR(4) NULL,
    jenis ENUM('SKALA') NOT NULL,
    wajib BOOLEAN NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,urutan),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (urutan > 0 AND wajib IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_bing_komentar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(40) NOT NULL,
    urutan INT NOT NULL,
    penanda_cetak VARCHAR(4) NOT NULL,
    label_cetak VARCHAR(160) NOT NULL,
    jenis ENUM('TEXTAREA') NOT NULL,
    wajib BOOLEAN NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,urutan),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (urutan > 0 AND wajib IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
