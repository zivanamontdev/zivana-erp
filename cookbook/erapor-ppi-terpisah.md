# PPI terpisah dari rapor utama

Implementasi 18 Oktober 2026. Renderer baru `erapor-print-v9`; artefak resmi lama tidak dirender ulang.

## Alur pengguna

1. Pada tab **Rapor PPI**, PIC dapat menghapus centang **Sertakan PPI dalam rapor utama**, sebelum Finalisasi. Pilihan juga dikonfirmasi pada modal Finalisasi.
2. Nilai PPI tetap pada dokumen/sesi semula. PPI yang dikecualikan tidak masuk hitungan kelengkapan maupun PDF utama; rapor utama tetap dapat diselesaikan, disetujui, diterbitkan, dan dibagikan.
3. Setelah Finalisasi utama, PIC dapat melengkapi PPI yang belum diajukan melalui tab yang sama. Dokumen utama tetap terkunci. Penyuntingan PPI mengikuti tenggat periode; pengajuan nilai yang sudah lengkap tidak diblokir tenggat.
4. PIC menekan **Ajukan Rapor PPI**. Seluruh kolom wajib harus lengkap. Nilai terkunci dan masuk persetujuan **Wali Kelas → Kepala Sekolah**, tanpa koordinator Agama/BING. Sebelum diajukan, draf terpisah hanya dapat dilihat PIC aktif pemilik sesi atau Superadmin.
5. Kepala Sekolah menyetujui, lalu **Terbitkan PPI**. Persetujuan terakhir menyimpan PDF immutable dengan snapshot tanda tangan tersendiri; penerbitan mengunggah bytes tersebut ke bucket private, bukan membuat PDF ulang.
6. PIC membagikan tautan PPI tersendiri melalui tombol **Bagikan PPI**. File: `PPI-Nama-Anak-TS-Ganjil-2026-2027.pdf`. Token, kedaluwarsa 60 hari, pencatatan pembagian, dan hitungan unduh terpisah dari rapor utama. Tombol WhatsApp membuka pesan; aplikasi tidak mengirim pesan otomatis.

Antrean baru di `/erapor/ppi`, ditautkan dari **Persetujuan eRapor → PPI Terpisah**. PIC melihat status dan akses PDF pada tab PPI di sesi utama. Penugasan kelas/penyetuju dan RBAC tetap wajib; koordinator yang bukan Wali tidak memperoleh akses hanya karena pernah menyetujui rapor utama. Superadmin dapat memantau, tetapi tidak dapat menyetujui tanpa penugasan sah.

### Jika Kepala Sekolah yang memisahkan

Pada modal persetujuan terakhir rapor utama, Kepala Sekolah dapat menghapus centang **Sertakan PPI pada PDF rapor utama**. Ini hanya tersedia jika PPI semula disertakan dan belum ada artefak resmi. Nilai maupun bukti cakupan persetujuan utama dipertahankan, tetapi PDF utama dibuat tanpa PPI.

PIC tetap harus menekan **Ajukan Rapor PPI**, lalu memperoleh persetujuan Wali dan Kepala lagi pada pengajuan terpisah. Tidak ada pengajuan atau penggunaan ulang tanda tangan otomatis. Setelah PPI dipisahkan, Kepala tidak dapat menyisipkannya kembali pada persetujuan akhir; cakupan yang dibekukan pada Finalisasi tidak diperluas diam-diam. Setelah PDF utama dibuat, isi dan bytes-nya tidak dapat diubah.

Jika PIC mencoba menyertakan kembali PPI yang belum lengkap pada modal Finalisasi, pilihan tersimpan tetapi Finalisasi tidak terjadi. Halaman memperbarui kelengkapan dan tetap dapat diedit; nilai yang telah diisi tidak hilang.

## Penyimpanan dan kompatibilitas

Migrasi: `database/migrations/20261018_erapor_ppi_terpisah.sql`.

- `erapor_sesi.ppi_disertakan`: pilihan PIC yang menentukan cakupan persetujuan saat Finalisasi. Default `1` menjaga paket historis.
- `erapor_sesi.ppi_terpisah`: pengecualian efektif dari PDF utama. Default `0`; dapat menjadi `1` karena pilihan PIC atau pengecualian Kepala sebelum artefak ada.
- `erapor_ppi_pengajuan`: status independen, pengaju, tanggal pengesahan waktu Makassar, dan cadangan nilai saat pemisahan pertama. Nilai kerja tetap pada `erapor_ppi_isian`, bukan disalin/dimasukkan ulang.
- `erapor_ppi_persetujuan`: satu snapshot per tahap, identitas, persetujuan pemilik gambar, PNG, dan hash.
- `erapor_ppi_log`: perubahan alur/pembagian oleh aktor terautentikasi.
- `erapor_ppi_pdf`: PDF resmi/hash/manifest, metadata penerbitan dan distribusi. Pemeriksaan integritas gagal tertutup; artefak korup tidak diganti dengan render nilai terkini.

Komposisi dokumen dasar ABK tetap lima dokumen dan hash paket tidak diubah. Cakupan utama yang semula menyertakan PPI tetap utuh jika Kepala mengecualikannya kemudian. Simpan Draft dan antrean autosave mendukung PPI yang masih dapat diedit meskipun sesi utama sudah terkunci/terbit. Sebelum pilihan/pengajuan, antrean perubahan dikuras; kontrol dibekukan selama permintaan penguncian agar tidak menerima perubahan baru yang tidak bisa disimpan.

Watermark logo mengikuti Finalisasi **pengajuan PPI sendiri**, bukan status publikasi utama: draf PPI yang belum diajukan bersih, pengajuan PPI bertanda logo besar di belakang isi. Tanda tangan PDF PPI berasal dari persetujuan PPI, bukan tanda tangan utama atau profil PIC saat unduh.

## Aktivasi dan riwayat pengujian

Pada pengembangan 18 Oktober, pengujian menggunakan salinan database lokal acak yang dihapus sesudah pengujian. Semua **82 tabel asli**, termasuk artefak resmi, diverifikasi tidak berubah; `.env` asli tidak diubah. Migrasi tidak diterapkan ke database utama saat pengembangan tersebut.

**Pembaruan 19 Oktober:** production telah dimigrasikan oleh pemilik dan fitur PPI telah diverifikasi lewat browser. Database lokal utama masih 21/22 migrasi; pilihan baru tetap disembunyikan pada skema lama. Lihat [audit production dan perbaikan lanjutan](erapor-production-audit-20261019.md).

Untuk aktivasi, gunakan prosedur migrasi yang sudah ada, bukan reset/bootstrap data:

1. Backup database dan tinjau perubahan.
2. Ikuti [panduan migrasi](erapor-migration.md) dan [runner CLI/cPanel](erapor-cpanel-cron.md), atau [migrasi web bertoken](erapor-web-migration.md). Nonaktifkan fitur eRapor sesuai prosedur maintenance, jalankan preflight `php database/run-erapor-pilot.php --check`, kemudian migrasi schema-only melalui runner yang sama setelah hasil diperiksa.
3. Migration runner/installer sudah mengenali enam target additive pada migrasi PPI. Jangan menjalankan SQL manual dua kali atau menandai ledger tanpa pemeriksaan.
4. Aktifkan kembali fitur, pastikan pemetaan Wali dan Kepala aktif, serta R2 private dikonfigurasi untuk penerbitan. **Tidak perlu reset data** atau mengganti PDF lama.

RAS tidak ditambahkan karena spesifikasi/rubrik resmi belum tersedia. Pengajuan ulang setelah PPI sudah terkunci/terbit, revisi artefak resmi, serta penolakan/pembukaan kembali bukan bagian perubahan ini.

## Verifikasi

- `php tests/erapor-ppi-workflow-mysql.php --run`: **65** pemeriksaan (termasuk regresi penerbitan dari audit 19 Oktober) pada clone lokal, migrasi/idempotensi/keutuhan data lama, penyertaan normal, pengecualian PIC/Kepala, nilai terjaga, akses/penugasan yang dicabut, urutan dan replay persetujuan, rollback saat renderer gagal, integritas artefak, gagal unggah/retry, unduh/kedaluwarsa, pemisahan token, dan metadata distribusi utama tidak berubah. R2 menggunakan pengganti in-memory; tidak menghubungi cloud.
- `php tests/erapor-save-recovery-mysql.php --run`: **13** pemeriksaan regresi autosave/konflik/dua peran/PDF resmi pada clone lain.
- API adapter **37** kasus, RBAC **91** route, render-only editor/persetujuan, penamaan PDF **10**, renderer **24**, artefak PDF **24**, serta regresi profil/penugasan/reset seed dan JS autosave lolos. Semua PHP yang berubah lolos lint; JS lolos syntax check.
- Browser Chrome Headless: **34** pemeriksaan melalui HTTP/controller/API nyata, database clone, penyimpanan lokal pengganti R2, dan identitas sintetis. Alur lengkap utama terbit dulu → edit/ajukan PPI → Wali → Kepala → terbit → unduh orang tua; pengecualian Kepala; penyertaan kembali PPI belum lengkap; viewport 1440×900, 768×1024, 390×844, 375×812.
- PDF utama **13 halaman** tanpa PPI; PDF PPI **2 halaman**. PDF utama sebelum/sesudah proses PPI identik byte-per-byte. Tidak ada blok teks di luar halaman. Watermark dan dua slot tanda tangan PPI diperiksa visual.
- Font yang terukur: **Plus Jakarta Sans** (body), **Geist** (field), warna primary `rgb(201,44,47)` dari tema aplikasi. Menggunakan komponen/kelas CSS yang sudah ada; tidak ada CSS framework, warna atau stylesheet baru.
- Empat screenshot sintetis di `docs/testing/erapor-ppi-20261018/`. Konfigurasi/kredensial, database, dan layanan browser sementara telah dibersihkan. Tidak mengirim WhatsApp, tidak menulis R2 produksi, tidak commit/push.

Pengujian browser bukan perangkat fisik/iOS/Safari atau pengujian beban konkuren produksi.
