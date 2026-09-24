-- Apply after 20260924_erapor_approval_flow.sql and after official rubric seeds.
-- Idempotent and non-overwriting: existing flow values are never updated.
-- Deliberately creates NO erapor_penyetuju_user assignments.

INSERT INTO permissions (modul,section,sub_section,aksi,display_order)
SELECT seed.modul,seed.section,NULL,seed.aksi,seed.display_order
FROM (
    SELECT 'eRapor' AS modul,'Persetujuan' AS section,'lihat' AS aksi,100 AS display_order
    UNION ALL SELECT 'eRapor','Persetujuan','edit',101
    UNION ALL SELECT 'eRapor','Penugasan Penyetuju','lihat',102
    UNION ALL SELECT 'eRapor','Penugasan Penyetuju','edit',103
    UNION ALL SELECT 'eRapor','Profil Penandatangan','lihat',104
    UNION ALL SELECT 'eRapor','Profil Penandatangan','edit',105
) seed
LEFT JOIN permissions p ON p.modul=seed.modul AND p.section=seed.section AND p.sub_section IS NULL AND p.aksi=seed.aksi
WHERE p.id IS NULL;

INSERT INTO erapor_alur_penyetuju (kode,label,urutan,cakupan,aktif)
SELECT 'KOORDINATOR_QURAN','Koordinator Al-Qur’an',1,'TERBATAS',1
WHERE NOT EXISTS (SELECT 1 FROM erapor_alur_penyetuju WHERE kode='KOORDINATOR_QURAN');
INSERT INTO erapor_alur_penyetuju (kode,label,urutan,cakupan,aktif)
SELECT 'KOORDINATOR_BING','Koordinator Bahasa Inggris',1,'TERBATAS',1
WHERE NOT EXISTS (SELECT 1 FROM erapor_alur_penyetuju WHERE kode='KOORDINATOR_BING');
INSERT INTO erapor_alur_penyetuju (kode,label,urutan,cakupan,aktif)
SELECT 'KEPALA_SEKOLAH','Kepala Sekolah',2,'SEMUA',1
WHERE NOT EXISTS (SELECT 1 FROM erapor_alur_penyetuju WHERE kode='KEPALA_SEKOLAH');

-- Scope every rubric version already provisioned. Re-run this seed after adding a new UMMI/BING rubric.
INSERT IGNORE INTO erapor_alur_dokumen (penyetuju_id,rubrik_id)
SELECT f.id,r.id FROM erapor_alur_penyetuju f JOIN erapor_rubrik r
  ON (f.kode='KOORDINATOR_QURAN' AND r.jenis_dokumen='UMMI')
  OR (f.kode='KOORDINATOR_BING' AND r.jenis_dokumen='BING');
