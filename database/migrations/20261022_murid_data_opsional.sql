-- Hanya data dasar murid yang wajib (nama, jenis kelamin, kondisi, kelas).
-- Kolom lain boleh kosong dan dilengkapi kemudian lewat Ubah Murid. Aman diulang; tidak mengubah data.
ALTER TABLE murid
    MODIFY agama VARCHAR(30) NULL,
    MODIFY nik VARCHAR(20) NULL,
    MODIFY no_registrasi_akte VARCHAR(50) NULL,
    MODIFY tempat_lahir VARCHAR(100) NULL,
    MODIFY tanggal_lahir DATE NULL,
    MODIFY alamat TEXT NULL,
    MODIFY tanggal_masuk_sekolah DATE NULL,
    MODIFY alamat_domisili VARCHAR(255) NULL,
    MODIFY jumlah_saudara INT NULL,
    MODIFY nama_ayah VARCHAR(150) NULL,
    MODIFY pendidikan_ayah VARCHAR(100) NULL,
    MODIFY pekerjaan_ayah VARCHAR(100) NULL,
    MODIFY telp_ayah VARCHAR(30) NULL,
    MODIFY nama_ibu VARCHAR(150) NULL,
    MODIFY pendidikan_ibu VARCHAR(100) NULL,
    MODIFY pekerjaan_ibu VARCHAR(100) NULL,
    MODIFY telp_ibu VARCHAR(30) NULL;
