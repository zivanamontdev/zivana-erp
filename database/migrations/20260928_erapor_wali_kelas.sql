-- Tahap persetujuan Wali Kelas: satu wali kelas (akun pegawai) per kelas.
-- Pemegang tahap WALI_KELAS ditentukan dari kelas sesi, bukan dari erapor_penyetuju_user.
CREATE TABLE erapor_wali_kelas (
    kelas_id INT NOT NULL PRIMARY KEY,
    user_id INT NOT NULL,
    ditetapkan_oleh INT NULL,
    ditetapkan_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (ditetapkan_oleh) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Runner mengganti CHECK kode lama (tanpa WALI_KELAS) dengan constraint bernama ini.
ALTER TABLE erapor_alur_penyetuju ADD CONSTRAINT erapor_alur_kode_v2 CHECK (kode IN ('KOORDINATOR_QURAN','KOORDINATOR_BING','WALI_KELAS','KEPALA_SEKOLAH'));
