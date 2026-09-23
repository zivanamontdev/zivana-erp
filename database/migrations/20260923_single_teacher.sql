-- Existing databases: first run this audit. Resolve duplicates explicitly;
-- the ALTER below refuses duplicates and never silently discards assignments.
SELECT murid_id, COUNT(*) AS jumlah_penugasan
FROM kelas_guru_murid GROUP BY murid_id HAVING COUNT(*) > 1;

ALTER TABLE kelas_guru_murid ADD UNIQUE KEY uq_murid_single_guru (murid_id);
