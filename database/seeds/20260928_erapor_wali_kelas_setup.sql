-- Apply after 20260925_erapor_approval_setup.sql and 20260928_erapor_wali_kelas.sql.
-- Tahap Wali Kelas di antara koordinator (1) dan Kepala Sekolah (3). Pemegangnya ditentukan per kelas (erapor_wali_kelas).
INSERT INTO erapor_alur_penyetuju (kode,label,urutan,cakupan,aktif)
SELECT 'WALI_KELAS','Wali Kelas',2,'SEMUA',1
WHERE NOT EXISTS (SELECT 1 FROM erapor_alur_penyetuju WHERE kode='WALI_KELAS');
UPDATE erapor_alur_penyetuju SET urutan=3 WHERE kode='KEPALA_SEKOLAH' AND urutan=2;
-- Koordinator Agama (kode tetap KOORDINATOR_QURAN) juga menyetujui Rapor Agama.
INSERT IGNORE INTO erapor_alur_dokumen (penyetuju_id,rubrik_id)
SELECT f.id,r.id FROM erapor_alur_penyetuju f JOIN erapor_rubrik r ON f.kode='KOORDINATOR_QURAN' AND r.jenis_dokumen='AGAMA';
UPDATE erapor_alur_penyetuju SET label='Koordinator Agama' WHERE kode='KOORDINATOR_QURAN' AND label<>'Koordinator Agama'
