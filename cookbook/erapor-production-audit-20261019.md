# Audit production dan perbandingan lokal — 19 Oktober 2026

## Lingkup dan pengamanan

Atas permintaan pemilik aplikasi, pengujian memakai browser Chrome Headless terpisah untuk production `https://erp.sekolahzivanamontessori.sch.id` dan server lokal yang sudah berjalan di `http://127.0.0.1:8000`.

- Login 17 akun pada masing-masing lingkungan (Superadmin, Kepala/Admin, dan guru). Akun production memakai file privat `credentials`; lokal memakai password seed lokal yang sudah ada, tanpa mengganti password siapa pun.
- `/credentials` sudah ditambahkan ke `.gitignore`, dipastikan diabaikan Git dan tidak tercatat di index. Password tidak disalin ke dokumentasi/screenshot repository.
- Pada production, pengaman browser memblokir request mutasi selain login. Pengecualian hanya untuk tiga request negatif yang terkontrol: simpan ke PPI terkunci (409), CSRF salah (419), dan payload pengajuan berisi aktor palsu (422). Ketiganya ditolak sebelum perubahan nilai/status.
- Tidak membuat murid/sesi uji di production, tidak menyelesaikan/finalisasi/menyetujui/menerbitkan rapor nyata, tidak mengirim WhatsApp, tidak menjalankan reset/migrasi production, dan tidak menimpa artefak resmi.
- Sembilan sesi production ditinjau ulang lewat API; nilai dokumen, status utama, dan flag PPI tetap sama dengan awal pengujian. Ini bukan fingerprint seluruh database production: tidak ada akses SQL production.
- Sebelum/sesudah pengujian lokal, fingerprint seluruh **82 tabel asli** diperiksa. Database asli, konfigurasi `.env`, dan file PDF resmi lokal tidak diubah. Skenario mutasi positif berjalan di database clone acak pada port uji `8013`, bukan server pengguna `8000`.

## Perbedaan lingkungan yang benar-benar ditemukan

| Aspek | Production | Lokal utama :8000 |
|---|---|---|
| Migrasi PPI | Aktif; antrean PPI dapat dibuka, proyeksi PPI tersedia pada sesi ABK | Masih 21 migrasi, 82 tabel; PPI terpisah belum tersedia |
| Data sesi tahun berjalan yang dibuka | 9: 8 `SELESAI`, 1 `MENUNGGU_TTD` dengan 4/4 persetujuan dan PDF sudah disiapkan | 6: dua selesai, satu menunggu persetujuan, tiga draft |
| Kondisi ABK production | Lima sesi, masing-masing 30/30 isian PPI; PPI masih disertakan pada paket utama | Dua draft ABK, tenggat pengisian telah berakhir |
| Password akun | Kredensial dari file privat pengguna | Password seed lokal yang berbeda; tidak diubah untuk menyamakan production |
| ID periode Tengah Ganjil 2026/2027 | 41 | 5 |
| Default dashboard saat audit | Periode berikutnya: Akhir Ganjil | Sama; pemeriksaan sesi lama memilih periode Tengah secara eksplisit |

Sebelum perbaikan audit, hash JS editor dan CSS bersama di production sama dengan sumber lokal. Selisih PPI lokal berasal dari migrasi yang belum diterapkan, bukan karena file JS tertinggal. Data/ID antarlingkungan memang tidak sama; tidak dilakukan import dump production ke lokal atau sebaliknya.

## Bug yang diperbaiki di sumber lokal

### 1. PDF disiapkan dianggap sudah terbit

Ditemukan pada sesi production **15**: API guru menyatakan `MENUNGGU_TTD`, seluruh persetujuan selesai, dan halaman Kepala masih menawarkan **Terbitkan Rapor**, tetapi ringkasan Superadmin memberi label **Disetujui · PDF terbit**.

Perbaikan:

- `app/models/EraporOverview.php`: membedakan `SIAP_TERBIT` (**Disetujui · belum diterbitkan**) dari `TERBIT` (**Rapor telah terbit**). Hanya status sesi `SELESAI` dengan artefak yang dianggap terbit.
- `app/views/admin/rapor-murid/erapor-index.php`: label **PDF Resmi** ditentukan oleh keberadaan artefak, terpisah dari status distribusi. PDF resmi yang sudah ditandatangani tetap resmi walaupun belum diterbitkan ke orang tua.

Tidak mengubah status sesi atau membuat ulang PDF.

### 2. Tindakan penerbitan tersembunyi di tab Selesai

Sesi production 15 tidak muncul pada antrean default Kepala; hanya ada pada riwayat **Selesai**, meskipun penerbitan belum dilakukan.

Perbaikan:

- `app/models/EraporApprovalInbox.php`: antrean menyertakan tahap Kepala yang telah disetujui jika seluruh persetujuan lengkap, artefak utama ada, dan sesi masih `MENUNGGU_TTD`. Penugasan aktif dan pemeriksaan snapshot tetap wajib.
- `app/views/admin/erapor-approval/index.php`: badge **Siap diterbitkan**.
- Setelah sesi benar-benar diterbitkan, tindakan tersebut hilang dari antrean. Riwayat tetap tersedia. Koordinator/Wali yang sudah menyetujui tidak mendapat tindakan penerbitan baru; mode pantau Superadmin tidak memperoleh wewenang menyetujui.

Sesi production 15 **tidak diterbitkan oleh pengujian ini**. Sebelum perbaikan di-deploy, Kepala masih dapat mengaksesnya melalui tab Selesai → Tinjau → Terbitkan Rapor jika memang akan diterbitkan.

### 3. Ringkasan Rapor Murid melebar pada mobile/tablet

Reproduksi pada production dan lokal: pada perangkat 390 px, lebar layout menjadi sekitar 962–969 px, menyebabkan halaman mengecil/bergeser horizontal. Penyebabnya `span.ui-visually-hidden` pada judul kolom PDF yang absolut tetapi tidak memiliki containing block di dalam scroller.

Perbaikan `public/assets/css/components.css`: memberi `position: relative` pada `.report-period-table .erapor-overview-table .col-action`. Label aksesibilitas tetap ada; tidak memakai `overflow:hidden` pada seluruh halaman untuk menyembunyikan masalah.

Verifikasi lokal pada 1440, 1280, 768, 390, dan 375 px: lebar halaman tidak melampaui viewport. Tabel tetap dapat digulir di dalam wadah dan tombol PDF dapat dijangkau. Tidak ada warna, font, framework, atau komponen baru.

## Temuan data/operasional, bukan perubahan kode otomatis

1. **Kontak orang tua belum tersedia/valid pada delapan rapor yang telah terbit.** Tombol Bagikan memang disabled dan pesan pengisian kontak tampil. Tidak diisi nomor dummy. Pengujian tautan unduh orang tua sampai R2 **terblokir prasyarat ini**: tidak ada token/URL yang dibuka, tidak ada kenaikan counter unduh, dan tidak ada pesan WhatsApp yang dikirim. Unduh PDF resmi terautentikasi tetap berhasil.
2. **PDF resmi lama masih memakai format lama.** Tiga artefak yang diperiksa berukuran 17/19/19 halaman, tanpa watermark besar/slot Guru Kelas baru. Pengunduhan ulang identik byte-per-byte. Deploy renderer baru tidak otomatis mengganti artefak yang sudah disiapkan; hal ini disengaja untuk menjaga dokumen resmi. Template PPI production dan lokal yang dirender saat ini sudah menampilkan slot Guru Kelas. Koreksi PDF lama memerlukan keputusan/proses penerbitan ulang berversi, bukan menimpa diam-diam.
3. **Halaman migrasi production sudah tertutup (404)** setelah token migrasi dihapus, sesuai prosedur. **Halaman Reset & Seed masih aktif untuk Superadmin**, menunjukkan token reset masih dikonfigurasi. Kepala/Admin dan guru tetap mendapat 404. Sebaiknya pemilik menghapus `DATA_RESET_TOKEN` dari `.env` production setelah tidak digunakan. Tidak ada tombol/pratinjau/reset yang dijalankan saat audit.
4. **Database lokal utama belum menerima migrasi PPI.** Dibiarkan utuh untuk melindungi data asli. Pengujian PPI positif menggunakan clone bermigrasi. Untuk mengaktifkan fitur PPI terpisah pada server lokal utama, terapkan migrasi PPI yang sama melalui prosedur maintenance yang tersedia; jangan reset/import ulang data.

## Hasil pengujian

### Production nyata

- 17 login akun yang diberikan berhasil; tidak mengaktifkan Remember Me dan tidak mencabut remember-token pengguna melalui logout.
- Semua sembilan sesi yang dibuka dapat dibaca oleh PIC sah; komposisi Regular empat dokumen, ABK lima dokumen. Nilai dan status tetap sama setelah pemeriksaan.
- UI rapor yang telah dikunci tidak menawarkan edit/finalisasi/pemisahan PPI baru. Backend juga menolak simpan ke PPI terkunci, token CSRF salah, dan aktor yang dikirim dari client.
- Guru lain tidak dapat membaca API sesi/PDF anak yang bukan tugasnya; tamu mendapat 401; URL PPI terpisah untuk paket yang masih disertakan mendapat 404.
- Tampilan editor ABK/PPI diperiksa pada 1440×900, 768×1024, 390×844, 375×812; tidak ada overflow halaman atau exception JavaScript aplikasi pada alur tersebut.
- Tiga PDF resmi terautentikasi berhasil diunduh dua kali, hash setiap pasangan sama. Tidak ada blok teks di luar halaman. Template PPI production/lokal masing-masing dua halaman.
- Halaman tautan unduh tidak valid aman/404 dan memakai tema aplikasi pada mobile.
- Antrean PPI production kosong; belum ada data uji pengajuan terpisah yang dapat diproses tanpa menyentuh rapor nyata. Tidak mengklaim pengajuan/persetujuan/publikasi PPI baru sudah dilakukan di production.

### Lokal dan clone, kode sesudah perbaikan

- `php tests/erapor-ppi-workflow-mysql.php --run`: **65 pemeriksaan**, termasuk sebelas regresi baru untuk antrean penerbitan, status siap/terbit, penugasan dicabut, mode pantau, proyeksi read-only, serta hasil numerik PDO bertipe string seperti di hosting MariaDB.
- `php tests/erapor-overview-publication.php`: **13 pemeriksaan** model/tampilan/tipe data PDO/aksesibilitas/responsive contract tanpa DB aplikasi.
- `php tests/erapor-save-recovery-mysql.php --run`: **13 pemeriksaan** autosave, konflik, dua peran, dan artefak resmi pada clone.
- Browser clone: **25 pemeriksaan alur lengkap** PIC pisahkan PPI → utama disetujui → tampil di antrean penerbitan → utama diterbitkan → PPI diedit/diajukan → Wali → Kepala → PPI diterbitkan → unduh/bagikan lokal. PDF utama sebelum/sesudah proses PPI identik. Cloud storage memakai pengganti lokal dan tidak mengirim WhatsApp.
- Browser lokal utama: **8 pemeriksaan responsive** pada lima ukuran, termasuk pengguliran internal untuk menjangkau tombol PDF. Tiga pemeriksaan tambahan memastikan bukti visual antrean/status pada data sintetis.
- Uji teks panjang: hanya mengambil **panjang baris**, bukan isi/nama/catatan production. Maksimum per-field digabung menjadi sekitar 9.043 karakter sintetis pada 30 kolom PPI, lebih panjang daripada satu rapor production terpanjang yang diamati (6.194 karakter). Renderer v9 menghasilkan delapan halaman, semua 30 penanda akhir field ditemukan, tidak ada blok di luar halaman, watermark dan dua tanda tangan diperiksa visual.
- Regresi API 37 kasus, RBAC 91 route, editor/persetujuan, penamaan PDF 10, renderer 24, artefak PDF 24, profil tanda tangan, dan JS autosave lulus. Lint file PHP berubah dan `git diff --check` lulus.

Empat screenshot/PDF-raster di `docs/testing/erapor-production-audit-20261019/` seluruhnya **sintetis dari clone**, bukan nama/nilai/TTD production. Bukti mentah production tidak dimasukkan Git. Profil browser autentikasi dan konfigurasi/database clone sementara dibersihkan sesudah audit; server pengguna :8000 dan database aslinya tetap berjalan.

## Deployment lanjutan

Perbaikan audit ini **belum commit/push/deploy**. Perubahan model, view, dan CSS membutuhkan deploy kode tetapi **tidak memerlukan migrasi tambahan di production**, yang sudah menerima migrasi PPI. File `credentials` tetap lokal/ignored dan jangan diunggah manual ke document root.

Batas: tidak menguji beban produksi, perangkat fisik, iOS/Safari, pengiriman pesan WhatsApp, atau siklus pengajuan/publikasi dengan nilai nyata. Pengujian positif yang mengubah alur dijalankan pada clone untuk menjaga rapor resmi.
