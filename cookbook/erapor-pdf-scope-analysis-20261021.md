# Analisis PDF Yusti: sesi 16 berhasil, sesi lain 500

Analisis 21 Oktober 2026; tidak mengubah aplikasi/database production. Dasar: audit production hanya-baca sebelumnya, pemetaan penugasan production, pembacaan kode, serta pengujian lokal terisolasi. Ini analisis, bukan implementasi perbaikan atau fitur penerbitan ulang.

## Dua jalur pada URL yang sama

`EraporPdfController::show()` memanggil `EraporPdfAccess::canView()` terlebih dahulu:

1. **Akses seluruh paket**: pemilik/PIC, Kepala, Wali kelas yang sah, atau Superadmin. Jika ada PDF resmi, `officialArtifact()` membaca bytes tersimpan, memeriksa ukuran/hash, lalu mengirimnya. Tidak memanggil renderer/font baru.
2. **Akses terbatas koordinator**: `scopedDocumentIds()` menentukan dokumen yang boleh dilihat. `sendScoped()` memanggil `EraporPublication::draftPackage()`, menyaring dokumen sesuai scope, kemudian merender pratinjau baru. Tidak menimpa artefak resmi.

Yusti memiliki dua tugas production: **Koordinator Agama** dan **Wali Ranting Flamboyan**. Riwayat persetujuan yang dibaca dalam audit memperlihatkan sesi **16** sebagai tugas Wali sekaligus koordinator. Karena berwenang atas paket lengkap sesi itu, ia dapat mengambil PDF resmi yang sudah tersimpan.

Pada sampel sesi **14** (Regular) dan **9** (ABK), pengujian akses Yusti sebagai koordinator menghasilkan 500 pada inline maupun attachment. Koordinator Bahasa Inggris juga menghasilkan 500 pada kedua sampel. Keberhasilan PDF sesi 16 tidak membuktikan jalur pratinjau koordinator sehat, karena jalur eksekusinya berbeda.

## Arti HTML 500 yang dikirim pemilik

HTML tersebut adalah keluaran umum `app/views/errors/500.php`. `public/index.php` menangkap `Throwable`, mencatat pesan serta stack trace melalui `error_log()`, menetapkan HTTP 500, lalu menampilkan halaman itu. Saat debug publik mati, detail exception tidak disertakan.

Warna CSS, tulisan halaman error, dan script analytics di HTML itu bukan bukti penyebab exception. Jangan mengaktifkan `APP_DEBUG` di production untuk membocorkan detailnya ke pengguna.

**Yang terkonfirmasi:** kegagalan pada jalur akses terbatas/penyiapan preview baru; file resmi tersimpan tetap dapat dilayani melalui akses yang sah.

**Yang belum terkonfirmasi:** statement/fungsi yang melempar exception dan penyebab spesifik hosting/data/deployment. Belum dapat dipastikan apakah terjadi pada query scope, penangkapan paket, pemrosesan gambar/watermark, atau renderer. Untuk memastikan diperlukan potongan log error PHP dari request yang gagal, beserta waktu dan endpoint. Tidak ada dasar untuk langsung mengubah nilai, role, penugasan, schema atau gambar TTD.

## Pengujian analisis lokal

Server manual `:8013`, database salinan, identitas dan TTD sintetis; bukan database utama dan bukan production:

- Pengujian CLI menggunakan **transaksi READ ONLY**: sesi 17 (Regular), 18 (ABK included), 19 (ABK separated) × koordinator Agama/English. Keenam render berhasil; Agama/Ummi 5 halaman, English 2 halaman. Untuk Wali/PIC atas sesi yang sama, akses lengkap membaca artefak tersimpan. Transaksi diakhiri rollback.
- Pengujian HTTP/controller nyata: akun koordinator Agama dan English, masing-masing sesi 17 dan 18. Keempat GET `/erapor/sesi/{id}/pdf` mengembalikan **200 application/pdf**, bytes diawali `%PDF-`.
- Tidak mengetik, mengirim isian, menyetujui atau menerbitkan apa pun. Login lokal hanya membuat sesi autentikasi biasa.

Dengan data sintetis, konfigurasi PHP lokal dan MySQL lokal, error production **belum berhasil direproduksi**. Hasil lokal tidak menghapus temuan production dan tidak cukup untuk menyatakan penyebabnya sudah ditemukan. Pengujian PDF penuh sebelumnya juga tidak otomatis mencakup seluruh variasi jalur koordinator production.

## Perubahan font versus perubahan data

### Quicksand pada render baru

**Tidak membutuhkan perubahan schema/database.** Typeface ditentukan oleh CSS renderer, file TTF/UFM lokal dan `EraporPrintFont::options()`, bukan oleh kolom font pada database. Template production telah diverifikasi memakai Quicksand Regular/Bold tertanam untuk lima jenis × dua semester.

Nilai rapor, rubrik, siswa, akun, role, penugasan, bukti persetujuan dan snapshot tanda tangan tidak perlu diubah untuk memasang font.

### Quicksand pada PDF resmi lama, termasuk sesi 12 dan 15

Font sudah tertanam di bytes PDF lama. Mengubah CSS, metadata `renderer_versi`, atau refresh browser tidak mengubah font di dalam file itu.

Artefak utama berada pada `erapor_publikasi_pdf`:

- `pdf_bytes`: file PDF sebenarnya;
- `pdf_sha256`, `ukuran_byte`: integritas bytes;
- `renderer_versi`, `manifest`, `sumber_sha256`, `filename`: metadata sumber/render.

File yang telah diterbitkan juga memiliki salinan R2 dan metadata pada `erapor_publikasi_distribusi`. Mengubah hanya bytes di database dapat membuat salinan unduhan orang tua berbeda dari preview atau gagal pemeriksaan hash. Mengubah hanya kolom versi/font tidak menyelesaikan masalah.

Jika pemilik menghendaki desain baru untuk rapor resmi lama, diperlukan **fitur revisi/penerbitan ulang yang disepakati**, bukan reset nilai atau reapproval otomatis. Desain perlu menjaga file lama, sumber/bukti pengesahan yang sah, membuat file baru beserta checksum/manifest, dan menentukan versi yang disajikan serta salinan R2-nya tanpa menghilangkan audit distribusi.

Schema saat ini menggunakan **`sesi_id` sebagai primary key tunggal** pada `erapor_publikasi_pdf`, sehingga hanya ada satu artefak resmi utama per sesi. Penyimpanan revisi berdampingan belum diimplementasikan; fitur versioning tersebut dapat memerlukan migrasi/tabel riwayat tambahan. Jangan menganggap fitur penerbitan ulang berversi sudah tersedia atau cukup dijalankan dengan satu SQL UPDATE.

Kesimpulan: **deployment font saat ini tidak memerlukan migrasi**. Mengubah dokumen resmi yang sudah terbit adalah pekerjaan berbeda, memerlukan otorisasi serta rancangan artefak/revisi. Dalam analisis ini tidak ada file resmi, nilai, metadata atau R2 yang diubah.
