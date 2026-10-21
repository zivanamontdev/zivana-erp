# Revisi desain PDF historis — web, tanpa SSH

## Status dan batas fitur

Fitur ini **sudah diimplementasikan dan diuji lokal**, belum dijalankan pada production oleh agen.

- File asli pada `erapor_publikasi_pdf` / `erapor_ppi_pdf` tetap utuh.
- PDF revisi disimpan terpisah, bukan menimpa row/file asli.
- Superadmin menyiapkan, membandingkan, lalu memilih versi **preview internal** secara eksplisit. Ada tindakan kembali ke asli; riwayat tidak dihapus.
- Publikasi, pembagian WhatsApp, token, masa berlaku, hitungan unduh dan objek R2 lama **tidak diubah**. Publikasi berikutnya lewat alur lama pun tetap menggunakan artefak asli, bukan pointer preview internal.
- Ini bukan pengesahan nilai/TTD baru atau penerbitan ulang kepada orang tua. Mengirim versi revisi kepada orang tua memerlukan alur distribusi versi baru yang terpisah; belum termasuk fitur ini.
- **Deploy/migrasi saja belum mengubah font preview arsip.** Harus verifikasi → siapkan → bandingkan → aktifkan per sesi/paket.

## Mengapa perlu migrasi sekarang?

Pemasangan Quicksand dan perbaikan Imagick v11 sebelumnya tidak memerlukan migrasi. Fitur **riwayat revisi** membutuhkan satu migrasi aditif:

`database/migrations/20261021_erapor_pdf_revisi.sql`

Hanya membuat empat tabel dan mencatat ledger migrasi; tidak mengubah kolom, data atau file resmi lama:

| Tabel | Isi |
| --- | --- |
| `erapor_pdf_sumber` | Paket sumber lengkap privat, hash paket, referensi hash asli, metode verifikasi |
| `erapor_pdf_revisi` | Bytes PDF revisi, jumlah halaman, renderer/desain/hash, pembuat |
| `erapor_pdf_aktif` | Pilihan versi preview per sesi dan jenis paket |
| `erapor_pdf_revisi_log` | Audit siapkan/aktifkan/kembali ke asli, aktor, alasan, waktu |

Jenis paket `RAPOR` dan `PPI` terpisah memiliki scope masing-masing. Foreign key komposit mencegah pemindahan revisi ke sesi/paket lain. Artefak dan sumber hanya di-INSERT oleh layanan; perubahan pilihan hanya terjadi di tabel pointer dan audit.

Daftar installer untuk instalasi baru ikut diperbarui (23 migrasi). Klasifikasi tabel reset juga mengenali tabel baru agar reset administratif yang memang disetujui tidak menyisakan data privat. **Jangan menjalankan reset data untuk pekerjaan ini.**

## Verifikasi sumber — tidak memakai data sekarang secara diam-diam

### Rapor baru setelah migrasi

Pada transaksi persetujuan terakhir, paket render lengkap dibekukan bersama artefak asli, termasuk identitas, umur/tanggal, nilai, seluruh definisi cetak, logo, dan bytes PNG TTD dalam data URI. Jika transaksi gagal, snapshot dan persetujuan ikut rollback. Hal yang sama berlaku untuk persetujuan akhir PPI terpisah.

Revisi berikutnya memakai snapshot itu, walaupun nama/kelas/profil/TTD terkini sudah berubah. Tidak mengambil TTD dari profil terbaru. Snapshot privat mengandung data murid/TTD: **jangan ekspor ke Git atau tempat publik**.

### Rapor historis tanpa paket beku

Untuk formula sumber renderer v5–v11 yang dikenal:

1. Periksa integritas PDF asli serta manifest, rubrik/seed, bukti persetujuan dan aktor Kepala Sekolah.
2. Rekonstruksi kandidat dengan tanggal pengesahan asli dan history semester yang telah ada pada waktu artefak dibuat. Tidak memasukkan nilai semester yang baru terbit kemudian.
3. Gunakan identitas sekolah dari manifest asli; bukan alamat/sekolah terkini. Urutan key JSON MySQL dinormalisasi kembali ke urutan formula PHP asli.
4. Bandingkan snapshot nama, NUPTK, hash PNG, peran dan consent penandatangan dengan manifest asli. Peran yang memang tidak ada dahulu tidak ditambahkan dari profil sekarang. Tanggal pengesahan semester RTS terdahulu diperiksa terhadap manifest artefaknya.
5. Jika murid telah pindah kelas, kelas yang direferensikan sesi lama boleh menjadi kandidat tambahan. Kandidat itu juga **wajib cocok dengan seluruh hash sumber asli**, bukan dianggap benar otomatis.
6. Karena manifest lama tidak menyimpan detik `published_at`, cari hanya 86.400 kemungkinan detik pada **tanggal pengesahan yang tercatat**. Prefix JSON besar di-hash sekali. Tidak mencoba menebak nama, nilai, TTD atau tanggal lain.
7. Terima **hanya jika SHA-256 sumber cocok persis** dan manifest penandatangan/rubrik juga cocok. Baru setelah tindakan Siapkan, paket diverifikasi ini dibekukan di tabel baru.

Hash lama adalah proyeksi sumber yang didefinisikan renderer saat itu, bukan arsip lengkap semua instruksi layout. Rubrik tetap terkunci; logo/layout mengikuti desain baru. Karena itu perbandingan visual **semua halaman** tetap wajib sebelum aktivasi. Perubahan font, ukuran TTD/cap dan penghilangan slot orang tua hanya Ummi/BING mengikuti renderer v11 yang sudah disepakati.

Formula yang dikenal bukan jaminan semua rapor dapat dipulihkan: perubahan data, versi schema/tipe fetch PDO, definisi lama atau sumber yang hilang dapat membuat hash tidak cocok. Tidak ada tombol paksa atau fallback ke nilai terkini. Umur dan tanggal terbit tidak diganti menjadi tanggal revisi.

PPI terpisah lama hanya memiliki hash nilai, bukan bukti lengkap identitas dan semua input cetak. Tanpa snapshot lengkap, **`PPI_SOURCE_NOT_ARCHIVED`** sengaja memblokir revisi. Versi PPI baru setelah migrasi memiliki paket beku lengkap.

## Deploy melalui File Manager / deployment biasa

### 1. Backup, lalu deploy seluruh perubahan

- Backup database lewat panel hosting/phpMyAdmin dan salinan file aplikasi. Backup privat, jangan Git.
- Deploy semua file dalam perubahan ini, termasuk model/controller/view baru, CSS dan file SQL. Jangan hanya menyalin controller.
- Tidak perlu mengubah vendor, policy Imagick, font atau kredensial R2.
- Jangan mengunggah `credentials`, `.env` lokal, `.local-ppi-test/`, direktori Temp, PDF/JSON diagnosis privat atau database uji.
- Tetap `APP_DEBUG=false`.

Sebelum tabel terpasang, kode tetap membaca arsip asli; tidak otomatis membuat tabel saat membuka preview atau melakukan persetujuan.

### 2. Aktifkan halaman maintenance sementara

Di **`.env` production**, tambahkan `ERAPOR_PDF_REVISION_TOKEN` dengan token privat acak minimal 32 karakter (disarankan 64 hex). Gunakan generator kriptografis/password manager; jangan memakai contoh literal, password akun, atau token migrasi/reset/diagnosis lain. Jangan taruh token di URL.

Untuk jendela migrasi singkat, ubah sementara:

```dotenv
ERAPOR_API_ENABLED=false
```

Pastikan tidak ada guru sedang mengisi/finalisasi; koordinasikan jendela maintenance. Modul lain tidak di-reset.

Login **akun 17 — Superadmin**, lalu buka:

`https://erp.sekolahzivanamontessori.sch.id/sistem/revisi-pdf`

Halaman ini tetap dapat diakses saat API eRapor nonaktif, hanya jika token sudah dikonfigurasi. Admin biasa/Kepala Sekolah bukan Superadmin akan mendapat 404.

### 3. Pasang empat tabel melalui halaman itu

- Ketik token revisi pada form.
- Centang bahwa backup sudah dibuat dan migrasi aditif disetujui.
- Klik **Pasang tabel revisi**.
- Harapkan pesan empat tabel sudah terpasang.
- Kembalikan `.env` menjadi `ERAPOR_API_ENABLED=true`, lalu muat ulang halaman.

Tombol hanya menjalankan satu file SQL di atas; tidak menjalankan seed/reset atau seluruh migrasi lama. Runner menggunakan lock dan checksum ledger yang sama dengan alur eRapor. Jika migrasi sudah lengkap, pengulangan tidak menambah row bisnis.

Jika ada error DDL parsial/checksum, **berhenti**. Simpan kode dan rincian teknis tersaring; jangan menghapus ledger/tabel, mengedit migration lama, atau menekan reset. DDL MySQL tidak bisa di-rollback seperti transaksi nilai.

### 4. Mulai satu rapor, misalnya sesi 12

1. Nomor sesi: **12**, paket **Rapor utama**.
2. Ketik token, klik **Verifikasi sumber historis**. Ini hanya baca.
3. Jika **Sumber terverifikasi**, isi catatan pemeriksaan, centang konfirmasi, lalu **Siapkan preview revisi**.
4. Ini menyimpan salinan saja. Preview normal masih file asli sampai diaktifkan.
5. Buka **arsip asli** dan **revisi** melalui dua tombol di halaman yang sama, di tab baru, pada sesi login Superadmin ini. Kedua tombol bukan link orang tua dan tidak menambah hitungan unduh.
6. Periksa seluruh halaman: nilai, narasi, identitas/kelas, tanggal/umur, rubrik, TTD/cap, tidak terpotong, serta font Quicksand. Jangan menyetujui perubahan data karena terlihat "lebih baru".
7. Kedua file harus dibuka dalam 30 menit terakhir, masing-masing terikat hash dan sesi login. Centang hasil perbandingan dan isi alasan, lalu **Aktifkan untuk preview internal**.
8. Tutup/buka kembali preview normal `/erapor/sesi/12/pdf`. Preview penuh kini membaca bytes revisi; koordinator tetap hanya mendapat dokumen cakupannya dari sumber beku.
9. Ulangi untuk sesi **15** setelah sesi 12 benar. Tidak ada proses massal otomatis.

File unduhan revisi diberi awalan **`Revisi-Desain-`**. Header respons internal juga menunjukkan `X-Erapor-Pdf-Source` dan `X-Erapor-Pdf-Revision` untuk membedakan arsip/revisi. Bukti persetujuan/tanggal dalam dokumen tetap yang lama; aktivasi bukan tanda tangan baru.

Snapshot koordinator di-render sesuai cakupan dengan renderer yang terpasang; nilai/TTD berasal dari paket beku. Bytes PDF penuh tetap immutable. Pada perubahan renderer/desain mendatang, siapkan dan periksa revisi baru, jangan menganggap layout scoped otomatis merupakan arsip byte renderer lama.

### 5. Jika hasilnya diblokir

| Kode | Tindakan |
| --- | --- |
| `HISTORICAL_SOURCE_HASH_MISMATCH` | Sumber tidak cocok atau format belum pulih. Kirim kode + nomor sesi/jenis paket; perlu pemeriksaan/backup asli, bukan mengedit nilai agar lolos. |
| `HISTORICAL_SIGNERS_MISMATCH` | Bukti TTD/nama/consent tidak cocok. Jangan mengganti dari profil terkini. |
| `HISTORICAL_ENDORSEMENT_DATE_*` | Tanggal semester terdahulu belum dapat diverifikasi. Jangan mengubah tanggal pengesahan. |
| `HISTORICAL_RENDERER_UNSUPPORTED` | Formula historis belum didukung. Simpan arsip; perlu investigasi terpisah. |
| `PPI_SOURCE_NOT_ARCHIVED` | PPI lama belum mempunyai sumber lengkap yang dapat dibuktikan. |
| `REVISION_CHECK_STALE` | Sumber/desain berubah sejak pemeriksaan. Verifikasi ulang. |
| `REVISION_SELECTION_CHANGED` | Tab/operator lain mengubah pilihan aktif. Muat ulang; jangan menimpa dari tab lama. |
| `REVISION_REVIEW_REQUIRED` | Buka kedua PDF lagi pada login yang sama, lalu konfirmasi. |
| `REVISION_SCHEMA_*` | Skema/ledger belum lengkap/konsisten. Berhenti dan simpan kode. |

Tidak ada proses yang memulihkan nilai dari hash saja. Import backup/snapshot eksternal dan penerbitan link publik revisi belum diimplementasikan.

### 6. Kembali ke asli dan tutup maintenance

- Jika revisi kurang sesuai, isi alasan dan konfirmasi **Gunakan arsip asli kembali**. Hanya pointer internal yang diubah; revisi dan audit tetap ada.
- Setelah selesai, hapus/comment seluruh baris `ERAPOR_PDF_REVISION_TOKEN` dari `.env`.
- Halaman dan endpoint PDF maintenance kembali 404; preview revisi yang sudah dipilih tetap bekerja tanpa token.
- Token diagnosis Imagick lama juga tidak perlu dibiarkan aktif. Jangan menonaktifkan API eRapor setelah maintenance selesai.

## Validasi lokal

- **75** pemeriksaan schema/privacy/view.
- **51** pemeriksaan MySQL salinan disposable: sumber/timestamp/kelas, RBAC, stale proof, idempotensi, aktivasi/restore, korupsi, kegagalan render dan rollback snapshot, schema parsial, original business data tidak berubah.
- **81** pemeriksaan workflow PPI disposable: persetujuan asli benar-benar membekukan sumber utama/PPI; publikasi R2 tetap bytes asli meskipun revisi internal aktif; alur included/separate/late-excluded tetap berjalan.
- **132** assertion HTTP pada port **8014** dan PDO stringify aktif: migrasi via browser, kontrol akses/CSRF/token, siklus tiga paket, review terikat login, preview/attachment, scoped Agama/BING, penghapusan token, PPI legacy diblokir.
- **69** assertion browser Chrome: 1440/768/390/375px, tema Plus Jakarta Sans aplikasi tetap, input token kosong setelah respons, siapkan/restore/bandingkan/aktifkan lewat form nyata, tidak ada exception JavaScript.
- Tiga paket sintetis dari engine v9: **54 halaman asli + 54 revisi**. Font tertanam benar-benar berpindah **DejaVu Sans → Quicksand**, jumlah halaman tetap **17 / 20 / 17**. Teks yang diperiksa berada dalam kertas; contact sheet dan blok TTD diperiksa visual. Bukan klaim semua pixel sama—desain memang diperbarui.
- Regresi watermark/Imagick ketat **24**, renderer **65**, artefak **34**, diagnosis privat **63**, diagnosis MySQL **45**, recovery MySQL **13**, private routes **97**, serta UI/filename/snapshot/API lainnya lulus.
- Fingerprint **82 tabel database utama** dan **86 tabel sumber manual lokal** tidak berubah. Pada clone HTTP, seluruh **85 tabel bisnis lama** tetap identik (ledger migrasi dikecualikan karena memang ditambah satu migration). Empat tabel baru saja dipakai untuk revisi.
- Regresi HTTP server utama **8000** kembali lulus: **157 assertion / 29 request PDF** (arsip asli, scoped Agama/English, draf ABK/PPI, template dua semester).
- Port **8000 tetap berjalan**, `.env` utama dan production tidak diubah. Tidak ada call R2/WhatsApp production.

Bukti aman: `docs/testing/erapor-pdf-revisions-20261021/`. Hanya assertion teknis dan contoh sintetis; PDF mentah, snapshot lengkap dan kredensial tidak dimasukkan Git.
