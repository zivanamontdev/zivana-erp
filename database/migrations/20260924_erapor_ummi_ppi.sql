-- Definition tables only; no assessment data or legacy changes.
-- Requires catalog and RTS migrations (shared seed history).
CREATE TABLE erapor_rubrik_sumber (
    rubrik_id INT PRIMARY KEY,
    seed_json JSON NOT NULL,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ummi_jilid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(100) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    urutan INT NOT NULL,
    hanya_pra_tk BOOLEAN NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,urutan),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (urutan > 0),
    CHECK (hanya_pra_tk IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ummi_materi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jilid_id INT NOT NULL,
    kode VARCHAR(191) NOT NULL UNIQUE,
    teks TEXT NOT NULL,
    urutan INT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT 1,
    UNIQUE KEY (jilid_id,urutan),
    FOREIGN KEY (jilid_id) REFERENCES erapor_ummi_jilid(id) ON DELETE RESTRICT,
    CHECK (urutan > 0),
    CHECK (aktif IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_skala_huruf (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(10) NOT NULL,
    label VARCHAR(100) NOT NULL,
    peringkat INT NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,peringkat),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (peringkat BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_aspek (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(20) NOT NULL,
    nama VARCHAR(200) NOT NULL,
    urutan INT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT 1,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,urutan),
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT,
    CHECK (urutan > 0),
    CHECK (aktif IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_ppi_kolom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    bagian VARCHAR(40) NOT NULL,
    kode VARCHAR(100) NOT NULL,
    urutan INT NOT NULL,
    label_cetak VARCHAR(255) NOT NULL,
    grup_cetak VARCHAR(100) NULL,
    jenis ENUM('TEXTAREA') NOT NULL,
    render ENUM('BULLET_PER_BARIS') NOT NULL,
    cetak BOOLEAN NOT NULL,
    wajib BOOLEAN NOT NULL,
    diisi_di_sesi BOOLEAN NOT NULL,
    lebar_cetak_cm DECIMAL(5,2) NOT NULL,
    UNIQUE KEY (rubrik_id,kode),
    UNIQUE KEY (rubrik_id,bagian,urutan),
    FOREIGN KEY (rubrik_id,bagian) REFERENCES erapor_rubrik_bagian(rubrik_id,kode) ON DELETE RESTRICT,
    CHECK (urutan > 0 AND lebar_cetak_cm > 0),
    CHECK (cetak IN (0,1) AND wajib IN (0,1) AND diisi_di_sesi IN (0,1)),
    CHECK (wajib = 0 OR diisi_di_sesi = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

