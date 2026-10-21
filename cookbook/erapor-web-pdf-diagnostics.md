# Diagnosis PDF melalui halaman web — tanpa SSH

## Cakupan deploy tahap pertama

Halaman **`/sistem/diagnosis-pdf`** ditambahkan untuk menemukan exception penyebab HTTP 500 di hosting, tanpa membaca log server secara langsung dan tanpa mengubah rapor. Ini **alat diagnosis, bukan perbaikan 500 yang sudah diketahui penyebabnya**.

**Tidak memerlukan migrasi database.** Tidak ada fitur yang menimpa/merevisi PDF resmi pada deploy ini. Mode kesiapan revisi sengaja berhenti: schema lama hanya menyimpan PDF dan hash sumber, belum paket sumber historis lengkap yang dapat diverifikasi untuk penerbitan ulang berversi. Jangan menghapus artefak atau mengganti metadata versi secara manual.

## Setelah push/deploy

1. Deploy semua perubahan kode seperti biasa. Jangan unggah `credentials`, `.env` lokal atau `.local-ppi-test/`.
2. Melalui File Manager hosting, tambahkan satu baris pada `.env` production:

   ```dotenv
   ERAPOR_PDF_DIAGNOSTIC_TOKEN="GANTI_DENGAN_TOKEN_ACAK_PRIVAT_MINIMAL_32_KARAKTER"
   ```

   **Jangan memakai teks contoh tersebut sebagai token.** Buat token acak 64 karakter menggunakan password manager; jangan gunakan password akun atau token migrasi/reset. Jangan kirim token itu kepada saya.
3. Biarkan `ERAPOR_API_ENABLED=true`. Tidak perlu maintenance/mematikan eRapor, tidak perlu menjalankan migrasi, dan jangan membuka reset data.
4. Login **akun 17 / Superadmin** pada file privat `credentials` (bukan Kepala Sekolah/Admin biasa).
5. Buka:

   `https://erp.sekolahzivanamontessori.sch.id/sistem/diagnosis-pdf`

6. Isi token dari `.env`. Jalankan **satu pemeriksaan pada satu waktu**. Tunggu hasil selesai; ada jeda 10 detik sesudah pemeriksaan untuk mencegah klik berulang.

## Pemeriksaan yang perlu dijalankan

| Urutan | Pemeriksaan | Nomor sesi | Akun / template | Tujuan |
| --- | --- | --- | --- | --- |
| 1 | Jalur akun yang mengalami masalah | **14** | **Yusti Yudianti** / email akun 09 | Menangkap tahap error jalur koordinator Agama/Ummi. |
| 2 | Jalur akun yang mengalami masalah | **16** | **Yusti Yudianti** | Pembanding jalur Wali: semestinya membaca PDF resmi tersimpan tanpa render baru. |
| 3 | Render ulang uji · tanpa menyimpan | **16** | Tidak perlu memilih akun | Memaksa jalur render di memori, untuk memeriksa apakah file tersimpan hanya melewati masalah renderer/capture. |
| 4 | Jalur akun yang mengalami masalah | **14** | **Nur Fathirah** / email akun 16 | Memeriksa jalur koordinator Bahasa Inggris. |
| 5 | Template pembanding | Tidak perlu | **Ummi**, Ganjil; ulangi **Bahasa Inggris** bila diperlukan | Memisahkan masalah render data siswa dari template kosong. |
| Opsional | Jalur akun yang mengalami masalah | **9** | Yusti / Nur Fathirah | Pembanding siswa ABK/PPI. |
| Opsional | Kesiapan revisi PDF lama | **12** atau **15** | Tidak perlu | Memastikan file asli valid; revisi otomatis tetap diblokir, bukan memperbarui font lama. |

Pemilihan akun hanya menentukan **jalur akses yang diuji oleh Superadmin**. Tidak login sebagai akun itu, tidak mengganti role/penugasan, dan tidak memberi akun itu akses baru.

Setelah setiap pemeriksaan, klik **Unduh diagnosis** sebelum menjalankan pemeriksaan berikutnya. Beri nama file yang jelas, misalnya `yusti-14.json`, `yusti-16.json`, `render-16.json`, `english-14.json`, `template-ummi.json`, lalu kirim file JSON tersebut untuk dianalisis. Jangan mengirim `.env`, token maintenance, atau file `credentials`.

Laporan terakhir disimpan sementara pada sesi login yang sama selama **15 menit**, atau sampai digantikan pemeriksaan berikutnya. Tidak disimpan sebagai artefak rapor/database. Dengan JavaScript, unduhan memperbarui CSRF kedua form secara otomatis. Tanpa JavaScript, muat ulang halaman setelah mengunduh sebelum mengirim form baru.

## Hasil yang terlihat

- **Berhasil / stored_official**: akses penuh menemukan PDF resmi, sehingga renderer baru tidak dipanggil. Ini menjelaskan mengapa satu URL berhasil pada Wali namun gagal pada koordinator.
- **Berhasil / fresh_scoped**: paket ditangkap lalu disaring ke cakupan akun sebelum render. Agama mencakup Agama/Ummi; English mencakup BING.
- **Berhasil / fresh_full**: render ulang penuh di memori, tidak menulis file resmi/R2.
- **Perlu diperiksa**: menampilkan tahap terakhir, kelas exception, detail teknis yang aman, file relatif dan nomor baris. Jejak teknis lengkap yang disaring tersedia di panel rincian/JSON.
- **Revisi diblokir**: kondisi yang disengaja. Tidak tersedia tombol menyimpan versi pengganti karena sumber historis lengkap belum diarsipkan.

Tahap mencakup akses akun, pembacaan identitas/umur, query riwayat, formulir/snapshot/definisi setiap dokumen, logo watermark, file font, layout Dompdf dan output PDF. Observer diagnosis opsional; request PDF normal tidak mengaktifkannya.

Jika terjadi fatal PHP sesudah pengaman aktif, handler berusaha mengirim **`diagnosis-pdf-fatal.json`** dengan cadangan memori kecil. Berikan file itu jika muncul. Pengujian mencakup fatal `E_USER_ERROR` dalam subprocess terisolasi; tidak menjamin proses yang dibunuh hosting, timeout proxy, atau kehabisan memori ekstrem selalu bisa mengirim respons. Jangan menaikkan memory limit tanpa diagnosis.

## Sesudah memperoleh hasil

1. Hapus/comment **seluruh baris** `ERAPOR_PDF_DIAGNOSTIC_TOKEN` pada `.env` production.
2. Pastikan halaman diagnosis kembali **404**, termasuk saat masih login Superadmin. Unduhan laporan juga ditutup.
3. Biarkan PDF lama dan data persetujuan tetap seperti sebelumnya. Perbaikan berikutnya dikerjakan di lokal berdasarkan hasil diagnosis, diuji lalu di-deploy tersendiri.

Tidak perlu mengaktifkan `APP_DEBUG`, menjalankan SQL manual, mengubah nilai, melakukan finalisasi/persetujuan ulang atau menerbitkan rapor demi diagnosis.

## Pengamanan implementasi

- Token terpisah minimal 32 karakter, Superadmin aktif diverifikasi ulang dari DB, eRapor aktif, CSRF otomatis pada seluruh POST.
- Admin biasa, Kepala, Guru dan akun nonaktif tidak dapat mengakses alat. Halaman/unduhan memakai no-store, noindex, no-referrer dan frame DENY.
- Seluruh query pekerjaan diagnosis berjalan pada **transaksi MySQL READ ONLY**, diakhiri rollback. Tidak memanggil fungsi simpan/publikasi/share/R2. Sebuah percobaan UPDATE yang disuntikkan pada test renderer ditolak oleh database.
- Laporan tidak menyertakan paket sumber, nama murid, isian, gambar TTD, PDF mentah, argumen stack trace, password atau token. Pesan aplikasi/driver arbitrer disembunyikan; SQLSTATE/kode driver dan lokasi/tahap tetap tersedia. File di luar project disajikan hanya sebagai basename.
- Laporan hanya milik sesi login yang menjalankannya; nonce unduh sesi lain ditolak. Token maintenance tidak disimpan dalam laporan/form hasil.
- Cache font/metadata runtime dan sesi autentikasi tetap dapat dibuat sebagaimana proses PHP normal; tidak ada mutasi data bisnis.

## Pengujian lokal

- `php tests/erapor-pdf-diagnostics.php`: **63** pemeriksaan privasi, view/XSS, kontrak guard, CSRF unduhan dan fallback fatal.
- `php tests/erapor-pdf-diagnostics-mysql.php --run`: **45** pemeriksaan pada clone sekali pakai, termasuk full/scoped, Regular/ABK, PDO numerik native/string, template, artefak korup (full harus memeriksa hash, scoped/forced-render tidak menambah pemeriksaan di luar jalur aslinya), kegagalan yang disuntikkan, READ ONLY, dan fingerprint seluruh tabel.
- HTTP/controller server lokal salinan: **48** assertion, termasuk role/token/CSRF, throttling, enam hasil pemeriksaan, JSON unduh privat, penutupan token dan lintas sesi ditolak.
- Browser: **53** assertion; form dan hasil scoped/stored/readiness/error diperiksa pada 1440/768/390/375px, JSON tetap di kontainer, tema/Plus Jakarta Sans terjaga, file unduhan nyata tervalidasi dan pemeriksaan sesudah unduh tidak gagal CSRF.
- Regresi PDF renderer **65**, artifact **34**, PPI workflow **76**, recovery **13**, dan route RBAC **94** lulus. Fingerprint isi **82 tabel utama tetap identik** dengan baseline; fixture hanya dibuat pada clone yang dihapus sesudah suite.

Bukti aman terpilih berada di `docs/testing/erapor-pdf-diagnostics-20261021/`. Server lokal manual tetap tersedia di `http://127.0.0.1:8013/sistem/diagnosis-pdf`; token lokal ada di file privat `.local-ppi-test/diagnostic-token.txt`, password Superadmin mengikuti `credentials`. Token lokal tidak boleh dipakai di production.
