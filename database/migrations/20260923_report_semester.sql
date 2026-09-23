-- Preserve old data: semester stays NULL until explicitly classified by admin.
ALTER TABLE periode_penilaian ADD COLUMN semester ENUM('ganjil','genap') NULL AFTER nama;
ALTER TABLE periode_penilaian ADD UNIQUE KEY uq_periode_year_semester_type (tahun_ajaran_id, semester, tipe);
