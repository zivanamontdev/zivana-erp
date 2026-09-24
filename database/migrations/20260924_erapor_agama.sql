-- Definition-only Agama V1. Requires catalog, RTS ledger and Ummi/PPI snapshot.
CREATE TABLE erapor_agama_lingkup (
    id INT AUTO_INCREMENT PRIMARY KEY, rubrik_id INT NOT NULL,
    kode VARCHAR(100) NOT NULL, nomor_romawi VARCHAR(6) NOT NULL, nama VARCHAR(200) NOT NULL, urutan INT NOT NULL,
    catatan_wajib BOOLEAN NOT NULL DEFAULT 1,
    UNIQUE KEY (rubrik_id,kode), UNIQUE KEY (rubrik_id,urutan),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (urutan > 0 AND catatan_wajib IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_sub (
    id INT AUTO_INCREMENT PRIMARY KEY, lingkup_id INT NOT NULL,
    kode VARCHAR(120) NOT NULL, huruf CHAR(1) NULL, nama VARCHAR(200) NULL, implisit BOOLEAN NOT NULL, urutan INT NOT NULL,
    UNIQUE KEY (lingkup_id,kode), UNIQUE KEY (lingkup_id,urutan),
    FOREIGN KEY (lingkup_id) REFERENCES erapor_agama_lingkup(id) ON DELETE RESTRICT,
    CHECK (urutan > 0),
    CHECK ((implisit=1 AND huruf IS NULL AND nama IS NULL) OR (implisit=0 AND huruf IS NOT NULL AND nama IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_item (
    id INT AUTO_INCREMENT PRIMARY KEY, sub_id INT NOT NULL,
    kode VARCHAR(191) NOT NULL UNIQUE, teks TEXT NOT NULL, semester ENUM('GANJIL','GENAP') NOT NULL,
    urutan INT NOT NULL, nomor INT NULL, aktif BOOLEAN NOT NULL DEFAULT 1,
    UNIQUE KEY (sub_id,urutan), KEY (sub_id,semester,urutan),
    FOREIGN KEY (sub_id) REFERENCES erapor_agama_sub(id) ON DELETE RESTRICT,
    CHECK (urutan > 0 AND aktif IN (0,1)), CHECK (nomor IS NULL OR nomor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_item_nama (
    id INT AUTO_INCREMENT PRIMARY KEY, item_id INT NOT NULL, nama VARCHAR(120) NOT NULL, urutan INT NOT NULL,
    UNIQUE KEY (item_id,urutan), FOREIGN KEY (item_id) REFERENCES erapor_agama_item(id) ON DELETE RESTRICT,
    CHECK (urutan BETWEEN 1 AND 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_tahapan (
    id INT AUTO_INCREMENT PRIMARY KEY, rubrik_id INT NOT NULL,
    kode VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, definisi TEXT NOT NULL, urutan INT NOT NULL,
    UNIQUE KEY (rubrik_id,kode), UNIQUE KEY (rubrik_id,urutan), UNIQUE KEY (rubrik_id,id,kode), UNIQUE KEY (id,kode),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (kode IN ('TELADAN','TALQIN','TAHFIZH','TAFHIM','TADIB')), CHECK (urutan BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_subtingkat (
    id INT AUTO_INCREMENT PRIMARY KEY, tahapan_id INT NOT NULL, tahapan_kode VARCHAR(20) NOT NULL,
    kode CHAR(1) NOT NULL, label VARCHAR(100) NOT NULL, arti VARCHAR(100) NOT NULL, definisi TEXT NOT NULL, urutan INT NOT NULL,
    UNIQUE KEY (tahapan_id,kode), UNIQUE KEY (tahapan_id,urutan), UNIQUE KEY (tahapan_id,id,kode),
    FOREIGN KEY (tahapan_id,tahapan_kode) REFERENCES erapor_agama_tahapan(id,kode) ON DELETE RESTRICT,
    CHECK (tahapan_kode='TAHFIZH'), CHECK (kode IN ('D','J','M')), CHECK (urutan BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_agama_pilihan (
    id INT AUTO_INCREMENT PRIMARY KEY, rubrik_id INT NOT NULL,
    tahapan_id INT NOT NULL, tahapan_kode VARCHAR(20) NOT NULL,
    subtingkat_id INT NULL, subtingkat_kode CHAR(1) NULL,
    label VARCHAR(100) NOT NULL, kolom_cetak VARCHAR(30) NOT NULL, urutan INT NOT NULL,
    UNIQUE KEY (rubrik_id,urutan), UNIQUE KEY (rubrik_id,kolom_cetak),
    FOREIGN KEY (rubrik_id,tahapan_id,tahapan_kode) REFERENCES erapor_agama_tahapan(rubrik_id,id,kode) ON DELETE RESTRICT,
    FOREIGN KEY (tahapan_id,subtingkat_id,subtingkat_kode) REFERENCES erapor_agama_subtingkat(tahapan_id,id,kode) ON DELETE RESTRICT,
    CHECK (urutan BETWEEN 1 AND 7),
    CHECK ((tahapan_kode='TAHFIZH' AND subtingkat_id IS NOT NULL AND subtingkat_kode IS NOT NULL)
        OR (tahapan_kode<>'TAHFIZH' AND subtingkat_id IS NULL AND subtingkat_kode IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
