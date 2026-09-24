-- Explicit approval configuration; no automatic user assignments.
CREATE TABLE erapor_alur_penyetuju (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(40) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    urutan INT NOT NULL,
    cakupan ENUM('SEMUA','TERBATAS') NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT 1,
    CHECK (urutan>0 AND aktif IN (0,1)),
    CHECK (kode IN ('KOORDINATOR_QURAN','KOORDINATOR_BING','KEPALA_SEKOLAH'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_alur_dokumen (
    penyetuju_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    PRIMARY KEY (penyetuju_id,rubrik_id),
    FOREIGN KEY (penyetuju_id) REFERENCES erapor_alur_penyetuju(id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_penyetuju_user (
    penyetuju_id INT NOT NULL,
    user_id INT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY (penyetuju_id,user_id),
    FOREIGN KEY (penyetuju_id) REFERENCES erapor_alur_penyetuju(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (aktif IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi_penyetuju (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    penyetuju_id INT NOT NULL,
    kode VARCHAR(40) NOT NULL,
    label VARCHAR(150) NOT NULL,
    urutan INT NOT NULL,
    cakupan ENUM('SEMUA','TERBATAS') NOT NULL,
    status ENUM('MENUNGGU','DISETUJUI') NOT NULL DEFAULT 'MENUNGGU',
    UNIQUE KEY (sesi_id,penyetuju_id),
    UNIQUE KEY (sesi_id,kode),
    UNIQUE KEY (id,sesi_id),
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    FOREIGN KEY (penyetuju_id) REFERENCES erapor_alur_penyetuju(id) ON DELETE RESTRICT,
    CHECK (urutan>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi_penyetuju_dokumen (
    sesi_penyetuju_id INT NOT NULL,
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    PRIMARY KEY (sesi_penyetuju_id,dokumen_id),
    FOREIGN KEY (sesi_penyetuju_id,sesi_id) REFERENCES erapor_sesi_penyetuju(id,sesi_id) ON DELETE RESTRICT,
    FOREIGN KEY (sesi_id,dokumen_id) REFERENCES erapor_sesi_dokumen(sesi_id,dokumen_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
