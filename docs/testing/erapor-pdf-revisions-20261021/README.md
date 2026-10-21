# Bukti revisi desain PDF — lokal

Seluruh gambar/PDF pembanding berasal dari paket dan TTD **sintetis** pada database salinan. Tidak ada PDF production atau snapshot mentah di folder ini.

- `http-checks.json`: 132 assertion; migrasi browser, autentikasi/RBAC/CSRF/token, preview asli/revisi, aktivasi/restore, cakupan koordinator, penutupan tool, legacy PPI diblokir.
- `ui-checks.json`: 69 assertion Chrome pada 1440/768/390/375px, alur form nyata dan tema ERP.
- `visual-checks.json`: font tertanam DejaVu Sans → Quicksand; 17/20/17 halaman sebelum dan sesudah (54+54).
- `database-checks.json`: 85 tabel bisnis lama pada clone HTTP dan 86 tabel sumber manual tetap identik. Ledger migrasi memang ditambah; empat tabel revisi merupakan penyimpanan baru.
- Database utama port 8000: fingerprint 82 tabel tetap identik; 157 assertion / 29 request PDF regresi kembali lulus.
- Uji CLI: 75 schema/privacy/view, 51 MySQL revisi, 81 workflow PPI (termasuk arsip sumber dan publikasi bytes asli saat revisi aktif), 45 diagnosis MySQL, 13 recovery MySQL. Regresi renderer/artifact/watermark/route/UI lainnya juga lulus; lihat cookbook.

`01` membandingkan halaman TTD sintetis sebelum/sesudah. `02` contact sheet ABK/PPI. `03` verifikasi desktop, `04` konfirmasi revisi mobile, `05` blokir sumber PPI tidak lengkap.

Tidak ada token, password, alamat email akun, source package lengkap, PNG tanda tangan asli, atau data nilai murid asli dalam bukti ini. Uji tidak mengubah production. Petunjuk deploy: `cookbook/erapor-pdf-revisions.md`.
