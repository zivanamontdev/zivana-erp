# Inventaris kesiapan revisi PDF production — 21 Oktober 2026

Pemeriksaan browser 22:08–22:16 WITA. Login **17/17 akun** berhasil; **52 request preview PDF / 52 HTTP 200** melalui akses PIC, Wali/penyetuju, koordinator, Kepala Sekolah dan Superadmin yang relevan. Seluruh tahun/periode yang tersedia ditelusuri tanpa membuat sesi baru.

## Hasil paket penuh

| Sesi | Kondisi | PIC (nomor akun credentials) | Font preview penuh | Revisi | Kesiapan sumber |
| ---: | --- | ---: | --- | --- | --- |
| 8 | ABK | 07 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 9 | ABK | 04 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 10 | Regular | 14 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 11 | ABK | 10 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 12 | ABK | 06 | **Quicksand** | **Revisi #1 aktif** | SOURCE_VERIFIED |
| 13 | Regular | 12 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 14 | Regular | 03 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 15 | Regular | 06 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |
| 16 | ABK | 08 | DejaVu Sans | Belum dibuat | SOURCE_VERIFIED |

Artinya **8, 9, 10, 11, 13, 14, 15, 16** siap untuk tahap *Siapkan preview revisi*, kemudian perbandingan manual dan aktivasi. Tidak ada satu pun dari delapan sesi itu yang disiapkan/diaktifkan oleh audit ini. Sesi 12 tidak perlu diproses ulang.

Semua menggunakan paket **Rapor utama**. PPI pada lima sesi ABK di atas masih digabung; tidak perlu memilih PPI terpisah untuk memperbarui paket tersebut. Tidak ditemukan paket PPI terpisah terbit pada sembilan sesi ini.

Status bisnis sesi 15 adalah **Disetujui · belum diterbitkan** (`MENUNGGU_TTD`). Delapan sesi lainnya sudah `SELESAI`. Kesiapan revisi desain tidak sama dengan kewenangan/persiapan penerbitan kepada orang tua.

Preview koordinator yang scoped dapat memakai Quicksand melalui render baru walaupun paket penuh masih arsip lama. Karena itu daftar font di atas memakai hasil preview penuh Superadmin, bukan menganggap font scoped sebagai bukti revisi penuh aktif.

## Batas dan pengamanan

- POST diizinkan hanya **17 login** dan **9 `action=inspect`** ke halaman revisi. Pemeriksaan sumber dijalankan dengan transaksi SQL READ ONLY oleh model yang telah direview.
- Tidak ada prepare, activate, restore, migrate, setujui, terbitkan, simpan nilai, bagikan atau reset.
- Tidak mengakses link unduh orang tua; tidak mengunggah/menghapus R2. Header/status/font dibaca tanpa menyimpan PDF production ke Git.
- Sembilan payload API sesi/dokumen/PPI/persetujuan sebelum/sesudah **tetap identik**. Ini bukan fingerprint seluruh database production atau bukti tidak ada aktivitas pengguna lain di luar payload tersebut.
- Browser interception menolak request di luar daftar baca-saja; telemetry, favicon dan landing `/sekolah` yang tidak dibutuhkan diblokir. Akses otomatis HTTP sebelumnya 403, tetapi pemeriksaan browser kali ini berhasil.
- Bukti privat/skrip berada di direktori Temp, bukan repository. Tidak ada password, token, nilai murid atau gambar TTD asli dalam laporan ini.

Hasil ini adalah snapshot waktu pemeriksaan; perubahan operator setelahnya dapat mengubah pilihan revisi. Panduan tindakan: [revisi PDF](erapor-pdf-revisions.md).
