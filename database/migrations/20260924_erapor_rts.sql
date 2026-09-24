-- RTS definitions only. No pupil grades or legacy tables are touched.
CREATE TABLE erapor_rubrik_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    kode VARCHAR(100) NOT NULL,
    nama VARCHAR(200) NOT NULL,
    urutan INT NOT NULL,
    UNIQUE KEY erapor_area_code (rubrik_id,kode),
    UNIQUE KEY erapor_area_order (rubrik_id,urutan),
    CONSTRAINT erapor_area_parent FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id),
    CONSTRAINT erapor_area_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_sub_area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    area_id INT NOT NULL,
    kode VARCHAR(100) NOT NULL,
    huruf CHAR(1) NULL,
    nama VARCHAR(200) NULL,
    implisit BOOLEAN NOT NULL,
    urutan INT NOT NULL,
    UNIQUE KEY erapor_sub_code (area_id,kode),
    UNIQUE KEY erapor_sub_order (area_id,urutan),
    CONSTRAINT erapor_sub_parent FOREIGN KEY (area_id) REFERENCES erapor_rubrik_area(id),
    CONSTRAINT erapor_sub_positive CHECK (urutan > 0),
    CONSTRAINT erapor_sub_implicit CHECK ((implisit=1 AND nama IS NULL AND huruf IS NULL) OR (implisit=0 AND nama IS NOT NULL AND huruf IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_grup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sub_area_id INT NOT NULL,
    nama VARCHAR(200) NOT NULL,
    urutan INT NOT NULL,
    UNIQUE KEY erapor_group_name (sub_area_id,nama),
    UNIQUE KEY erapor_group_parent_id (sub_area_id,id),
    CONSTRAINT erapor_group_parent FOREIGN KEY (sub_area_id) REFERENCES erapor_rubrik_sub_area(id),
    CONSTRAINT erapor_group_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_rubrik_indikator (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sub_area_id INT NOT NULL,
    grup_id INT NULL,
    kode VARCHAR(191) NOT NULL UNIQUE,
    tujuan TEXT NOT NULL,
    aparatus TEXT NULL,
    urutan INT NOT NULL,
    aktif BOOLEAN NOT NULL DEFAULT 1,
    UNIQUE KEY erapor_indicator_order (sub_area_id,urutan),
    CONSTRAINT erapor_indicator_parent FOREIGN KEY (sub_area_id) REFERENCES erapor_rubrik_sub_area(id),
    CONSTRAINT erapor_indicator_group FOREIGN KEY (sub_area_id,grup_id) REFERENCES erapor_rubrik_grup(sub_area_id,id),
    CONSTRAINT erapor_indicator_positive CHECK (urutan > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_skala_nilai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rubrik_id INT NOT NULL,
    nilai SMALLINT NOT NULL,
    kode VARCHAR(20) NOT NULL,
    label VARCHAR(150) NOT NULL,
    simbol VARCHAR(20) NOT NULL,
    urutan INT NOT NULL,
    UNIQUE KEY erapor_scale_value (rubrik_id,nilai),
    UNIQUE KEY erapor_scale_code (rubrik_id,kode),
    CONSTRAINT erapor_scale_parent FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id),
    CONSTRAINT erapor_scale_range CHECK (nilai BETWEEN 1 AND 4 AND urutan BETWEEN 1 AND 4)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE erapor_seed_history (
    rubrik_id INT PRIMARY KEY,
    source_sha256 CHAR(64) NOT NULL,
    content_sha256 CHAR(64) NOT NULL,
    seeded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT erapor_seed_parent FOREIGN KEY (rubrik_id) REFERENCES erapor_rubrik(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
