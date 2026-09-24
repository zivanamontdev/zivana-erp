-- Additive identity/session foundation only; existing reports are not converted.
CREATE TABLE erapor_dokumen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    murid_id INT NOT NULL,
    tahun_ajaran_id INT NOT NULL,
    rubrik_id INT NOT NULL,
    semester ENUM('GANJIL','GENAP','TAHUNAN') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (murid_id,tahun_ajaran_id,rubrik_id,semester),
    UNIQUE KEY (id,murid_id,tahun_ajaran_id,semester),
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE RESTRICT,
    FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    murid_id INT NOT NULL,
    periode_id INT NOT NULL,
    tahun_ajaran_id INT NOT NULL,
    semester ENUM('GANJIL','GENAP') NOT NULL,
    jenis ENUM('TENGAH','AKHIR') NOT NULL,
    kondisi ENUM('Regular','ABK') NOT NULL,
    kelas_id INT NOT NULL,
    guru_user_id INT NOT NULL,
    status ENUM('BELUM_DIISI','TELAH_DIISI','MENUNGGU_TTD','SELESAI') NOT NULL DEFAULT 'BELUM_DIISI',
    paket_sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (murid_id,periode_id),
    UNIQUE KEY (id,murid_id,tahun_ajaran_id,semester),
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE RESTRICT,
    FOREIGN KEY (periode_id) REFERENCES periode_penilaian(id) ON DELETE RESTRICT,
    FOREIGN KEY (tahun_ajaran_id) REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE RESTRICT,
    FOREIGN KEY (guru_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi_dokumen (
    sesi_id INT NOT NULL,
    dokumen_id INT NOT NULL,
    murid_id INT NOT NULL,
    tahun_ajaran_id INT NOT NULL,
    semester ENUM('GANJIL','GENAP') NOT NULL,
    dokumen_semester ENUM('GANJIL','GENAP','TAHUNAN') NOT NULL,
    urutan INT NOT NULL,
    wajib BOOLEAN NOT NULL DEFAULT 1,
    PRIMARY KEY (sesi_id,dokumen_id),
    UNIQUE KEY (sesi_id,urutan),
    FOREIGN KEY (sesi_id,murid_id,tahun_ajaran_id,semester) REFERENCES erapor_sesi(id,murid_id,tahun_ajaran_id,semester) ON DELETE RESTRICT,
    FOREIGN KEY (dokumen_id,murid_id,tahun_ajaran_id,dokumen_semester) REFERENCES erapor_dokumen(id,murid_id,tahun_ajaran_id,semester) ON DELETE RESTRICT,
    CHECK (dokumen_semester='TAHUNAN' OR dokumen_semester=semester),
    CHECK (urutan>0 AND wajib IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_sesi_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesi_id INT NOT NULL,
    aktor_id INT NOT NULL,
    aksi VARCHAR(60) NOT NULL,
    status_lama VARCHAR(30) NULL,
    status_baru VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesi_id) REFERENCES erapor_sesi(id) ON DELETE RESTRICT,
    FOREIGN KEY (aktor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
