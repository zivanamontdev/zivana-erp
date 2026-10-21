# Perbaikan Imagick pada watermark PDF — 21 Oktober 2026

## Bukti diagnosis

File `diagnosis-pdf.json` yang diberikan pemilik menunjukkan:

- PHP hosting 8.2.34, MariaDB 11.8.9, Dompdf 3.1.6; font Quicksand dan registry sudah terbaca.
- Capture data serta penyaringan dokumen selesai; kegagalan terjadi saat `render_layout`.
- Trace: callback watermark `EraporPackagePdfRenderer` → `CPDF::image` → `Cpdf::addPngFromFile` → `addImagePngAlpha` → **`Imagick::readImage`**, baris vendor 6107.

Ini bukan bukti kesalahan font atau kebutuhan migrasi schema. Canvas sebelumnya menerima `data:image/png;base64,...` langsung. Tidak seperti elemen HTML `<img>` yang di-resolve/cache Dompdf menjadi file lokal, pemanggilan canvas diteruskan langsung ke backend PNG. PHP/GD menerima data stream tersebut, tetapi reader Imagick hosting gagal. Pesan Imagick aslinya disaring, sehingga aturan policy/delegate spesifik hosting tidak dapat dipastikan dari JSON saja.

## Perubahan

Renderer baru: **`erapor-print-v11`**.

- Snapshot watermark di-decode dengan base64 ketat dan divalidasi sebagai PNG dari bytes, bukan melalui URL/stream wrapper.
- PNG disalin **tanpa mengubah satu pun byte gambar** ke file sementara lokal privat, di direktori sementara Dompdf. Tidak ada URL eksternal atau path dari pengguna yang diterima.
- Canvas menerima path lokal tersebut, bukan data URI. Tidak mengubah vendor, mematikan Imagick, melonggarkan security policy hosting, atau menambah dependency.
- Ukuran, rasio, posisi dan opacity watermark tetap sama; gambar tetap di belakang konten. Save/restore graphics state berpasangan bahkan saat reader melempar exception.
- File watermark dibersihkan pada `finally`; shutdown cleanup tambahan untuk file tersebut juga didaftarkan. Cache gambar HTML Dompdf dibersihkan saat render gagal agar file sementara logo/TTD tidak tertinggal akibat exception.
- Jika file sementara tidak dapat dibuat, render **gagal dengan jelas**, bukan diam-diam menghapus watermark.
- Diagnosis kini menampilkan keberadaan GD/Imagick/Gmagick, versi renderer dan strategi input watermark. Jika error reader terjadi lagi, tahapnya dapat menunjukkan `render_watermark_image`.

Font, layout, definisi rubrik, ukuran TTD/cap, akses akun, nilai, persetujuan dan proses distribusi tidak diubah. PDF resmi tersimpan tetap dibaca sebagai bytes lama yang terverifikasi, tidak diganti otomatis.

## Pengujian

### Backend dan kompatibilitas

- `php tests/erapor-watermark-compat.php`: **24** pemeriksaan; output PDF asli, validasi PNG/protokol, temp directory, file lifecycle pada sukses/exception, serta pembersihan cache gambar.
- Fixture subprocess **test double Imagick ketat**, bukan extension native: menolak inline/data URI dan mengaktifkan cabang CPDF Imagick. Pemanggilan canvas cara lama menghasilkan `ImagickException`; renderer baru berhasil, **0 inline reads**, watermark dibaca dari file lokal. Exception reader yang disuntikkan tetap membersihkan file/cache.
- PHP lokal **tidak memasang Imagick native**. Pengujian native menggunakan GD; simulasi reader ketat bukan klaim telah menjalankan ImageMagick hosting yang sebenarnya. Verifikasi production sesudah deploy tetap diperlukan.
- Regresi renderer **65**, PDF artifact **34**, diagnosis privat **63**, diagnosis MySQL clone **45**, workflow PPI clone **76**, recovery clone **13** lulus.

### HTTP pada server lokal utama port 8000

Server pemilik yang sudah berjalan digunakan; tidak dihentikan atau diganti. Login memakai akun/seed lokal yang diverifikasi dari hash, tanpa mengubah password/role.

**157 assertion / 29 request PDF lulus**:

- PDF resmi sesi lokal 1 dan 3: inline/attachment berhasil dan bytes identik pada pembacaan berulang.
- Sesi lokal 2: render ulang paket reguler yang menunggu TTD, termasuk watermark.
- Sesi lokal 4 dan 6: draft ABK/PPI, tetap tanpa watermark sebelum Finalisasi.
- Akun koordinator Agama dan English: sesi lokal 1/2/3, masing-masing preview/attachment; semua 200 PDF, font tertanam Quicksand, watermark pada setiap halaman yang sudah Finalisasi.
- Template RTS, Agama, Ummi, BING, PPI × dua semester: Quicksand, tanpa watermark, kebijakan slot orang tua sesuai permintaan.
- Seluruh teks yang diperiksa berada di dalam kertas. Slot orang tua tetap pada RTS/Agama/PPI dan tidak ada pada Ummi/BING.

Watermark diuji melalui perintah draw canvas PDF dan raster visual. Ekstraksi `get_image_info()` MuPDF dapat melewatkan gambar dengan opacity rendah, sehingga tidak dipakai sendirian untuk menyatakan watermark hilang.

### Perbandingan visual

Paket sintetis lokal salinan dirender dengan baseline Git v10 dan patch v11, memakai input yang sama dan transaksi READ ONLY:

| Paket | Halaman | Hasil raster sebelum/sesudah |
| --- | ---: | --- |
| Regular tanpa PPI | 17 | Semua identik pixel-for-pixel |
| ABK dengan PPI | 20 | Semua identik pixel-for-pixel |
| Rapor utama ABK dengan PPI dipisah | 17 | Semua identik pixel-for-pixel |
| PPI standalone | 3 | Semua identik pixel-for-pixel |

Total **57 halaman** dibandingkan pada skala 1×. Contact sheet keempat paket dan blok pengesahan juga diperiksa visual. Ini membuktikan patch transport gambar tidak mengubah desain hasil GD yang sebelumnya benar, bukan klaim byte PDF identik atau kesetaraan semua backend Imagick.

Fingerprint seluruh **82 tabel database utama identik** sebelum/sesudah pengujian port 8000. Tidak ada perubahan nilai, status, role, password, persetujuan, artefak resmi atau distribusi. Tidak mengakses/mengubah production dalam pengerjaan patch ini. File diagnosis pemilik tidak diubah.

Bukti aman: `docs/testing/erapor-watermark-imagick-20261021/`. Hanya hasil assertion dan contoh sintetis; PDF mentah/nama/isian asli lokal tidak disimpan ke repository.

## Setelah push/deploy

**Tidak perlu migrasi atau perubahan database.** Deploy seluruh perubahan kode, khususnya:

- `app/models/EraporPackagePdfRenderer.php`
- `app/models/EraporPdfDiagnostics.php`

Tidak perlu mengganti font, mengubah konfigurasi Imagick, atau mengunggah folder uji privat. Jangan memasukkan `credentials`, `.env` lokal, `.local-ppi-test/` atau JSON diagnosis privat ke paket deploy.

Kemudian:

1. Buka halaman diagnosis yang sudah tersedia sebagai **Superadmin / akun 17**. Gunakan token maintenance yang sudah dipasang sementara; jangan kirim tokennya.
2. Jalankan **Jalur akun**: Yusti (akun 09), sesi 14 atau 9; ulangi untuk Nur Fathirah (akun 16). Jalankan **Render ulang uji**, sesi 16, sebagai pembanding jalur render penuh tanpa menyimpan.
3. Harapkan `ok: true`, `environment.renderer: erapor-print-v11`, dan `watermark_input: validated_local_png`. Unduh JSON jika masih gagal; jangan mengubah nilai/penugasan untuk mencoba memperbaikinya.
4. Buka preview normal pada akun koordinator dan pastikan file tampil. PDF resmi historis yang dibuka sebagai Wali/PIC tetap file lama; jangan menjadikannya satu-satunya uji render baru.
5. Setelah verifikasi berhasil, hapus/comment seluruh baris `ERAPOR_PDF_DIAGNOSTIC_TOKEN` dari `.env` dan pastikan halaman diagnosis kembali 404.

Pembaruan font/desain pada PDF resmi historis tetap pekerjaan revisi berversi yang terpisah. Patch ini memperbaiki jalur render, tidak menerbitkan ulang arsip lama.
