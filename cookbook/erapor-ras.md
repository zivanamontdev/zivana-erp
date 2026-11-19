# eRapor RAS (Rapor Akhir Semester)

Status: backend, form guru, persetujuan, dan PDF selesai serta lulus uji lokal (Docker). Belum dirilis ke production.

## Isi
- Rubrik `RAS_MONTESSORI_V1`: 62 indikator, 6 area, skala BD/MB/BSH/BSB + "-" (Belum Dikenalkan). Seed: `database/seeds/rubrik_ras_seed.json`. Spesifikasi lengkap: `eRapor_Zivana_Spesifikasi/SPEK_RUBRIK_RAS.md` (lokal).
- Satu dokumen per tahun ajaran: sesi 2 (Akhir Ganjil) dan sesi 4 (Akhir Genap) menyimpan nilai terpisah di `erapor_rts_nilai` / `erapor_rts_belum_dikenalkan`.
- Narasi "Perkembangan anak secara keseluruhan": teks bebas, wajib, satu per sesi di `erapor_ras_narasi`. Sesi 4 menampilkan narasi dan nilai Ganjil hanya-baca.
- Tanda tangan RAS: Kepala Sekolah, Guru Kelas (= Wali Kelas, dari snapshot persetujuan), Orang Tua. Judul pengesahan mengikuti semester yang sudah final.
- Paket Akhir Semester: RAS + Agama + Ummi + BING (+ PPI untuk ABK). Config: `config/erapor-package.php`.
- Seed rubrik semua jenis ikut ter-commit di `database/seeds/` (installer membaca dari sana dulu).

## Kode
`EraporRasSeed`, `EraporRtsEntry::save(..., 'RAS')`, `EraporCompleteness`, `EraporTeacherForm`, `EraporTeacherApiController`, `EraporPublication`, `EraporPackagePdfRenderer::ras()`, `EraporApprovalReview`, view `portal-guru/erapor-sesi.php`.

## Tes
`tests/erapor-ras-seed.php`, `tests/erapor-ras-renderer.php`, `tests/erapor-package-plan.php`. Alur penuh (isi -> Selesaikan -> Finalisasi -> persetujuan -> PDF terbit) diuji di Docker lokal.

## Rilis ke production (belum dilakukan)
1. Deploy kode. 2. Buat tabel `erapor_ras_narasi` (`database/migrations/20261023_erapor_ras.sql`). 3. Seed rubrik RAS (`EraporRasSeed::apply`; tidak bisa lewat SQL biasa karena menulis hash seed). Rencana cara menjalankan perlu disepakati (halaman migrasi bertoken atau halaman sementara).
4. Setelah rubrik ada, sesi Akhir Semester dapat dibuat guru; sebelum itu paket Akhir Semester tetap tertahan ("Rubrik belum tersedia: RAS").

## Belum dikerjakan
- Template RAS di Manajemen Template (halaman template masih RTS saja).
- Footer "Laporan Perkembangan Akhir Semester Tahun Ajaran …" pada cetakan (RTS juga belum punya footer).
