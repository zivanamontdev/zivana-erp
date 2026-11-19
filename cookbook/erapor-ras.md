# eRapor RAS (Rapor Akhir Semester)

Status: selesai dan sudah terpasang di production (migrasi `20261023_erapor_ras.sql` dan seed rubrik sudah dijalankan; halaman seed sementara sudah dihapus dari kode). Diuji nyata di production: pembuatan sesi, form RAS, simpan narasi, pratinjau PDF.

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

## Template dan cetakan
- Manajemen Rapor memiliki bagian "Rapor Akhir Semester" berisi template RAS (pratinjau + Simpan PDF). Rubrik lain memakai template Tengah Semester.
- Cetakan RAS memiliki footer "Laporan Perkembangan Akhir Semester Tahun Ajaran X-Y" hanya pada halaman RAS (penanda `ras-start`/`ras-end` di `EraporPackagePdfRenderer`).

## Catatan operasional
- Docker lokal (`docker-start.cmd`) menerapkan migrasi sesudah dump, mencatat ledger, dan menyeed rubrik RAS otomatis.
- Rubrik RAS berstatus `draft` sampai sesi pertama dibuat (lalu `terkunci`); Manajemen Rapor menampilkan keduanya.
