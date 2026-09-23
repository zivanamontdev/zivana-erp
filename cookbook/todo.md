# Todo — Roadmap Implementasi Zivana ERP

Urutan disusun berdasarkan dependency logis: fondasi dulu (auth, RBAC, app shell), baru modul yang bergantung padanya.

## Prioritas aktif — Login, RBAC, dan Portal Guru (23 September 2026)

**Pembaruan lanjutan:** alur HTTP/MySQL terisolasi tambah akun → login → periode → murid/penugasan baru → draft → kirim → persetujuan admin → PDF sudah lulus, termasuk pencabutan/pemberian izin setelah login ulang dan penonaktifan/aktivasi akun. Pemeriksaan visual browser dan audit positif seluruh aksi tetap belum tuntas; jangan menganggap seluruh roadmap sudah selesai.

**Status: perbaikan login/RBAC dan tes otomatis awal sudah dikerjakan; gerbang A–C belum ditutup karena pengujian browser serta E2E positif seluruh fitur belum selesai.** Lihat hasil dan batas cakupan di [rbac-verification.md](rbac-verification.md). Kerjakan A–C sampai lolos sebelum melanjutkan D–G. Checkbox Fase 0–9 di bawah merupakan riwayat implementasi/pengujian versi lama, bukan bukti bahwa permintaan terbaru sudah selesai. Jika bertentangan, kebutuhan pada bagian prioritas aktif ini yang berlaku.

### A. Akun karyawan dan login

- [x] Telusuri alur Tambah Karyawan → akun `users` → role dari jabatan → login; pastikan tidak ada akun hilang, duplikat, atau role tidak sesuai.
- [x] Uji akun baru untuk Kepala Sekolah, Admin, Guru Kelas, dan Guru Shadow; verifikasi hashing password, validasi email unik, dan aturan password yang sudah ditetapkan.
- [ ] Uji ubah data/email, ubah kata sandi, aktifkan/nonaktifkan, dan hapus karyawan terhadap akun login terkait; password lama tidak berlaku setelah diganti dan akun nonaktif tidak boleh login.
- [x] Verifikasi identitas `karyawan_id` dan role pada sesi serta tujuan setelah login: guru masuk Portal Guru dan hanya memperoleh modul Portal Guru sesuai izinnya.

### B. Cakupan dan penegakan RBAC

- [x] Inventarisasi seluruh menu, submenu, route, tombol aksi, modal, dan fitur: lihat/detail/pratinjau, tambah, ubah, hapus, aktif/nonaktif, penugasan, ubah kata sandi, simpan/kirim nilai, persetujuan, PDF, serta fitur lain yang benar-benar tersedia.
- [x] Buat pemetaan fitur → checkbox permission → visibilitas UI → guard backend. Catat fitur yang belum tercakup; lengkapi tanpa membuat fitur produk baru yang belum diminta.
- [ ] Pastikan checkbox mengatur sidebar, tombol, action menu, dan pemicu modal secara konsisten. Role tanpa izin tidak boleh melihat aksi terkait.
- [ ] Pastikan akses URL langsung dan permintaan tulis tetap ditolak ketika izin tidak diberikan; menyembunyikan tombol saja tidak cukup. Pertahankan proteksi CSRF.
- [ ] Verifikasi simpan/muat ulang matriks RBAC, centang semua/turunan, indeterminate, dan kombinasi izin kosong/lihat-saja/tulis.
- [ ] Verifikasi guru tidak memperoleh modul administrasi atau persetujuan rapor. Kepala Sekolah/Admin dapat menyetujui hanya bila izin RBAC terkait diberikan.
- [x] Uji perubahan permission dengan logout lalu login ulang: HTTP/MySQL menguji role Guru lihat-saja, PDF tersembunyi/403, lalu izin dipulihkan dan PDF kembali tersedia. Tidak membangun pembaruan permission real-time.

### C. Gerbang pengujian sebelum pengerjaan Portal Guru

- [x] Gunakan database/akun fixture terisolasi untuk tes tulis; jangan mengganti password atau permission akun pengguna nyata demi pengujian.
- [ ] Uji login berhasil/gagal, akun nonaktif, role tanpa izin, lihat-saja, dan izin aksi diberikan/dicabut setelah login ulang.
- [ ] Uji tiap fitur hasil inventarisasi melalui UI serta endpoint langsung: termasuk request POST buatan, CSRF tidak valid, dan akses lintas guru/murid/rapor.
- [ ] Pastikan penolakan aksi tidak mengubah database; bandingkan kondisi sebelum/sesudah.
- [x] Catat matriks hasil uji per role/fitur, bug yang diperbaiki, regresi, serta keterbatasan lingkungan. Bedakan tes unit/render, integrasi HTTP/database, dan pengujian browser visual.
- [ ] **Lulus A–C sebelum lanjut D–G.** Jangan mengklaim semua kondisi sudah aman hanya berdasarkan lint atau tes render; jika browser tidak dapat dijalankan, catat bagian UI yang belum terverifikasi.

### D. Sumber data periode dan penugasan rapor

- [x] Sinkronisasi pada tambah/ubah murid, perubahan penugasan guru, dan hapus kelas: buat draft hanya untuk sesi berjalan/mendatang dengan tahun ajaran kelas yang cocok; lepaskan kepemilikan draft yang tidak lagi memenuhi syarat. Tes SQLite terisolasi mencakup duplikasi, perpindahan tahun, murid nonaktif, riwayat terkirim, nilai yang sudah tersimpan, dan rollback kegagalan. Integrasi tulis HTTP/MySQL masih perlu diuji.

- [x] Semester Ganjil/Genap terpisah dari tipe Tengah/Akhir; empat kombinasi unik per tahun ajaran.
- [x] Delapan periode contoh dibuat untuk tahun ajaran 2024/2025 dan 2025/2026; tahun aktif 2026/2027 tidak diubah. Dua periode lama dan data rapor terkait dihapus atas izin pengguna, dengan cadangan lokal.
- [x] Daftar Rapor Murid admin memakai satu tabel per periode, tanpa card sesi bersarang; baris rapor tersedia menuju pratinjau dan draft tidak dapat dibuka.
- [ ] Audit sinkronisasi periode, tahun ajaran kelas, murid aktif, dan guru penanggung jawab, termasuk penugasan/perpindahan murid setelah periode dibuat. Hindari rapor duplikat dan perubahan nilai historis tanpa dasar.
- [x] Pastikan membuka dashboard bukan pemicu membuat nilai atau memalsukan status selesai. Nilai hanya berasal dari pengisian guru; daftar berasal dari periode dan penugasan.
- [ ] Uji periode lalu, sedang berlangsung, berikutnya, tanpa periode, tanpa murid, serta lebih dari satu agenda yang waktunya beririsan; dokumentasikan aturan pemilihan/tampilan agenda.
- [x] Bedakan kekosongan data karena filter tahun, belum ada murid/penugasan, atau template belum lengkap. Jangan mengisi nilai/kurikulum resmi dengan data contoh tanpa persetujuan.

### E. Dashboard Guru `/portal-guru/dashboard`

- [ ] Sesuaikan dengan gambar acuan: sapaan dan waktu, Agenda sedang berlangsung, tabel Daftar Murid (tahun ajaran dan jumlah), serta Agenda Berikutnya.
- [x] Gunakan komponen card/tabel/teks/tombol yang sudah ada; tambahkan varian reusable hanya jika perlu. Semua warna harus berasal dari `config/colors.php`.
- [x] Hanya tampilkan murid/rapor milik guru yang login. `Isi Rapor` merah → form pengisian murid tersebut; `Lihat Rapor` hijau → pratinjau rapor tersebut.
- [x] Bedakan draft, menunggu persetujuan, dan disetujui secara konsisten; guru tidak memiliki tombol maupun endpoint persetujuan.
- [ ] Uji tampilan responsif, tanggal/countdown, expand/collapse tabel, navigasi chevron, dan empty state dengan data fixture.

### F. Daftar dan Detail Murid Guru `/portal-guru/murid`

- [x] Tampilkan hanya murid yang ditugaskan kepada guru login; pencarian/filter tidak boleh membuka data guru lain.
- [x] Klik more vertical langsung menuju detail murid read-only, bukan dropdown perantara.
- [x] Gunakan breadcrumb `Daftar Murid Guru > Detail Murid` dengan tautan kembali ke daftar Portal Guru, bukan modul administrasi.
- [x] Pakai komponen detail data standar; verifikasi guard kepemilikan pada URL detail, termasuk ID murid guru lain/tidak ditemukan dan kondisi penugasan berubah.

### G. Pengisian, pengiriman, pratinjau, dan persetujuan

- [x] Alur positif HTTP/MySQL dengan akun/data fixture: tambah akun guru melalui controller asli → login → buat periode/kelas/murid → penugasan → simpan draft → tolak kirim belum lengkap → kirim lengkap → admin setujui → guru unduh PDF. Akses detail/form/PDF guru lain dan CSRF salah juga ditolak. Pengujian ini bukan tes interaksi/visual browser.

- [ ] Baca aset `Portal Guru - halaman_pengisian_rapor(desktop_mode).svg` dan `Portal Guru - halaman_pengisian_rapor(mobile_mode).svg` sebelum implementasi UI pengisian.
- [ ] Form dibuka per murid dari Dashboard Guru; tampilkan identitas, periode, semester, item penilaian, progres, dan catatan berdasarkan data sebenarnya.
- [x] Semester isian mengikuti field semester periode, **bukan** pemetaan Tengah→Ganjil/Akhir→Genap. Uji keempat kombinasi periode.
- [x] Uji simpan draft, muat ulang nilai, validasi item/skala sesuai template, catatan, progres, dan pengiriman setelah seluruh nilai wajib terisi; template kosong tidak boleh dikirim sebagai rapor lengkap.
- [x] Pengiriman mengubah status menjadi `menunggu_persetujuan`; pastikan klik ulang/pengiriman ganda tidak merusak nilai atau status.
- [ ] Konfirmasi kebijakan edit setelah dikirim: apakah langsung terkunci atau boleh diedit selama menunggu persetujuan? **Belum dijawab; jangan mengubah kebijakan/kembangkan alur revisi atas asumsi.** Implementasi lama mengunci setelah dikirim dan perlu diverifikasi ulang.
- [ ] Pratinjau guru mengikuti gambar acuan: pemilih murid yang diizinkan, refresh, Simpan PDF, dokumen horizontal berlatar abu-abu, tanpa tombol Setujui; gunakan komponen dokumen dan bank warna yang ada.
- [ ] Uji PDF/pratinjau hanya untuk rapor milik guru; konten dan semester konsisten dengan nilai tersimpan. Periksa status yang mengizinkan pratinjau draft secara terpisah dari alur `Lihat Rapor`.
- [ ] Persetujuan hanya dari `/rapor-murid` oleh Kepala Sekolah/Admin dengan izin terkait dan hanya untuk status menunggu persetujuan.
- [ ] Uji end-to-end: tambah akun → login guru → murid yang ditugaskan → isi/simpan/kirim rapor → login approver → pratinjau/setujui → guru melihat rapor. Sertakan tes negatif akses lintas akun dan desktop/mobile.

### Catatan yang menggantikan asumsi roadmap lama

- Rapor Murid bukan lagi accordion tiga tingkat dan tidak mempunyai tombol Tambah Sesi; periodenya dikelola melalui Periode Rapor.
- Persetujuan bukan bebas diberikan kepada sembarang role; batas bisnis saat ini Kepala Sekolah/Admin, dengan RBAC tetap ditegakkan.
- Guru Kelas dan Guru Shadow adalah jabatan yang relevan; pemetaan jabatan ke role tetap harus diaudit, tidak diasumsikan dari nama saja.
- Periode contoh 2024–2026 tidak otomatis menyediakan rapor untuk tahun aktif 2026/2027. Jangan mengubah tahun aktif atau menambah periode di luar permintaan hanya agar dashboard terlihat berisi.
- Konten template resmi yang belum lengkap tetap dicatat sebagai kebutuhan data; contoh visual bukan nilai murid nyata.

---

## Fase 0 — Fondasi Teknis

- [x] Setup struktur folder sesuai `architecture.md` (app/, config/, database/, routes/, public/)
- [x] Setup `Router`, `Controller` base class, `Model` base class, `Database` (PDO wrapper)
- [x] Setup autoload manual (`spl_autoload_register`) + `composer.json` awal
- [x] Setup `.env` + `.env.example` + `config/config.php` (tanpa fallback hardcoded — lihat `security.md`)
- [x] Import `database/schema.sql` versi awal (tabel auth/RBAC dulu, tabel lain menyusul per fase)
  <!-- CATATAN: file schema.sql sudah ditulis lengkap (tabel roles/permissions/role_permissions/users/password_resets), tapi belum benar-benar di-"import"/dites ke server MySQL nyata karena tidak ada instance MySQL di environment ini. Jalankan `mysql -u root -p zivana_erp < database/schema.sql` (atau import via phpMyAdmin) di environment lokal/hosting yang sebenarnya, lalu verifikasi tidak ada error SQL sebelum lanjut. -->
- [x] Setup design token: `public/assets/css/tokens.css` dari `design-system.md` bagian 1.1–1.3
- [x] Setup font Plus Jakarta Sans (Google Fonts atau self-host — putuskan sesuai `architecture.md` poin 6)
  <!-- Dipakai via Google Fonts CDN (client-side), lihat app/views/layouts/head.php. Terverifikasi render benar lewat screenshot headless browser. -->
- [x] Build App Shell layout (`views/layouts/app-shell.php`): sidebar + 3-baris page header sesuai `design-system.md` bagian 2
  <!-- Diimplementasi sebagai app/views/layouts/shell-header.php + shell-footer.php (pola include, bukan satu file, supaya view halaman bisa menyisipkan konten di antaranya tanpa output buffering). Sudah diverifikasi visual lewat screenshot, cocok dengan struktur di screenshot asli. -->

## Fase 1 — Auth & RBAC (blocker untuk semua modul lain)

- [x] Tabel `roles`, `permissions`, `role_permissions`, `users`, `password_resets`
  <!-- Sudah dibuat di database/schema.sql pada Batch 2 (Fase 0), lihat catatan di task tersebut soal belum diuji import ke MySQL nyata. -->
- [x] Halaman Login (email, password, Ingat Saya, Lupa kata sandi) + `AuthMiddleware`, `GuestMiddleware`
  <!-- Terverifikasi visual via screenshot (headless browser) — cocok dengan desain asli. Link "Lupa kata sandi?" mengarah ke /lupa-kata-sandi yang belum dibangun (task terpisah di bawah). Icon toggle show/hide password pakai icon_search sementara [ASUMSI], belum ada icon "eye" di assets/icons. -->
- [x] Seed 4 role: Superadmin, Admin, Koordinator Guru, Guru
  <!-- INSERT idempotent ditambahkan di database/schema.sql. Belum diverifikasi ke MySQL nyata (lihat catatan task schema.sql di Fase 0). -->
- [x] `RoleMiddleware` untuk enforce permission server-side (lihat `security.md` poin 3)
  <!-- Logika sudah ditulis dan lolos php -l, tapi query ke tabel role_permissions/permissions BELUM bisa diuji fungsional karena tidak ada instance MySQL di environment kerja ini. Uji ulang begitu DB tersedia. -->
- [x] Halaman Sistem → RBAC: render matriks permission, simpan perubahan checkbox ke `role_permissions`
  <!-- Terverifikasi visual (screenshot accordion tertutup & terbuka dengan data tiruan) — cocok dengan screenshot desain asli. Belum diuji fungsional simpan-ke-DB karena tidak ada MySQL live. Bukan tri-state indeterminate sungguhan, pakai pola "centang semua turunan" (lihat catatan di rbac.js). -->
- [x] CSRF protection otomatis di `Controller`/`Router` base class
  <!-- Diuji end-to-end: GET tidak terpengaruh, POST tanpa token ditolak HTTP 419. -->
- [x] `[Konfirmasi ke user dulu]` Alur reset password (belum ada screenshot halamannya)
  <!-- Diimplementasi mengikuti struktur password_resets di schema.md (token hash + expiry 1 jam), TANPA desain Figma acuan jadi tampilan mengikuti gaya Login apa adanya [ASUMSI]. Pengiriman email BELUM disambungkan ke SMTP asli (kredensial kosong) — link reset untuk sementara ditulis ke error_log dan ditampilkan di layar hanya saat APP_DEBUG=true. Sambungkan ke PHPMailer + SMTP produksi begitu kredensial tersedia. -->

**[UPDATE] Database live sudah tersambung** (izin diberikan user). Koneksi: `127.0.0.1` (bukan `localhost` — lihat catatan di `.env.example` soal error `mysql_native_password`), user `root` tanpa password, database `zivana_erp` (baru dibuat, tidak menyentuh database lain di server yang sama). `schema.sql` sukses dieksekusi (8 tabel + seed 4 role + 24 permission).

**Pengujian end-to-end sungguhan yang sudah dilakukan dan LOLOS**: login (real password_verify), guard AuthMiddleware/GuestMiddleware, halaman Sekolah (baca kondisi kosong + simpan data + baca ulang), halaman RBAC (baca permission ter-grant + simpan perubahan), modal Perbarui Tahun Ajaran, logout.

**1 bug ditemukan dan diperbaiki** selama pengujian ini: `RoleMiddleware::hasAccess()` memakai named parameter PDO (`:sub_section`) dua kali dalam satu query — tidak valid untuk native prepared statement (`PDO::ATTR_EMULATE_PREPARES = false`). Diperbaiki jadi `:sub_section1`/`:sub_section2`.

Ada akun uji tersimpan di DB lokal untuk lanjut testing: `superadmin@zivana-erp.test` / `password123` (role Superadmin, semua permission granted). Ini data development, bukan untuk dibawa ke produksi.

## Fase 2 — Komponen UI Dasar (reusable, dipakai semua modul)

- [x] Komponen Button (Primary/Secondary/Tertiary × Regular/Pressed/Disable)
  <!-- public/assets/css/components.css. Pressed/Disable untuk Secondary/Tertiary diturunkan [ASUMSI] karena design-system.md hanya spesifikasi Regular untuk keduanya. Diverifikasi visual di halaman Login & RBAC. -->
- [x] Komponen Input (textfield/textarea × 5 state × 4 varian icon)
  <!-- public/assets/css/components.css: .field-input/.field-textarea dengan state default/active/filled/viewonly/negative dan varian icon left/right/double. -->
- [x] Komponen Checkbox (termasuk logika tri-state untuk RBAC)
  <!-- public/assets/js/checkbox-tree.js (modul reusable) + refactor app/views/admin/rbac/index.php untuk pakai tri-state sungguhan (indeterminate), menggantikan pola "select all" sederhana dari Fase 1. Terverifikasi visual: indikator indeterminate (minus merah) muncul benar saat sebagian anak tercentang. -->
- [x] Komponen Badge Status (4 varian warna)
  <!-- .badge-positif/peringatan/netral/destruktif di components.css. Diverifikasi visual, cocok persis dengan screenshot Manajemen Murid. -->
- [x] Komponen Modal (dua ukuran: 440px, 668px)
  <!-- .modal-overlay/.modal-box/.modal-sm/.modal-lg + modal.js (buka/tutup, klik overlay, ESC). Dimuat global lewat shell-footer.php. Diverifikasi visual (modal-sm) sesuai spesifikasi persis. -->
- [x] Komponen Tabel (header, data row, kolom aksi ellipsis)
  <!-- .data-table-wrapper/.data-table di components.css. Diverifikasi visual, cocok dengan pola di semua screenshot daftar. -->
- [x] Pattern "Assign Many-to-Many" (dropdown dinamis + tombol tambah/hapus baris) — dipakai di 2 tempat nanti
  <!-- assign-list.js (clone via <template>) + .assign-list-* di components.css. Diverifikasi visual, siap dipakai di Manajemen Guru & Detail Kelas (Fase 4/5). -->
- [x] Pattern "Accordion Bertingkat" — dipakai di RBAC, Rapor Murid, Pengisian Rapor
  <!-- accordion.js generik + .accordion-item/.accordion-header/.accordion-body di components.css. Diverifikasi visual termasuk nested accordion. RBAC (Fase 1) tetap pakai varian khususnya sendiri (.rbac-role) karena ada chrome tambahan. -->

## Fase 3 — Modul Sekolah

- [x] Tabel `sekolah`, `sekolah_media`, `tahun_ajaran`
  <!-- [UPDATE] Sudah diuji ke MySQL live: schema.sql sukses dieksekusi tanpa error. -->
- [x] Halaman Data Sekolah (tab Informasi Umum, tab Kontak & Media) — mode lihat/ubah, validasi wajib
  <!-- SekolahController + view. Pattern Tabs baru ditambahkan di components.css. [ASUMSI] opsi dropdown Bentuk Pendidikan (KB/TK/TPA) dan Jenis Media diturunkan dari konteks Montessori, bukan daftar resmi dari user. [UPDATE] Sudah diuji end-to-end ke MySQL live: simpan data + baca ulang terbukti benar. -->
- [x] Modal Perbarui Tahun Ajaran
  <!-- Modal + SekolahController::updateTahunAjaran(). Nonaktifkan tahun ajaran lama, insert baru sebagai aktif. Diverifikasi visual sesuai spesifikasi. -->

**Fase 3 selesai.**

## Fase 4 — Human Capital

- [x] Tabel `jabatan`, `karyawan` (+ relasi ke `users`)
  <!-- FK users.karyawan_id -> karyawan.id ditambahkan lewat ALTER TABLE (lihat catatan non-idempotent di schema.sql). Diuji ke MySQL live: berhasil dibuat. -->
- [x] CRUD Jabatan
  <!-- JabatanController + view. Hapus = soft-delete (is_active=0), bukan DELETE FROM, konsisten dengan konvensi is_active di schema.md. Diuji end-to-end ke MySQL live: tambah/ubah/hapus semua benar. Ditambahkan juga komponen baru yang ternyata dibutuhkan: Action Menu (dropdown "⋮") dan List Toolbar (search+filter), belum ada di Fase 2. -->
- [x] CRUD Daftar Karyawan (termasuk pembuatan akun `users` saat tambah karyawan, form "Ganti Kata Sandi" terpisah)
  <!-- KaryawanController + view. [KEPUTUSAN, sudah dikonfirmasi user] Role RBAC ditempel ke Jabatan (kolom jabatan.role_id), bukan dipilih manual per karyawan — karyawan otomatis mewarisi role dari jabatan-nya. Alasan: role adalah properti fungsi/posisi bukan orang, tetap sesuai desain Figma untuk form Karyawan (tidak perlu field Role baru di situ), dan menutup celah role Superadmin/Koordinator Guru yang sebelumnya tidak bisa didapat (sekarang tinggal buat/ubah Jabatan dan pilih role-nya). Heuristik tebak nama "mengandung kata guru" yang dipakai sebelumnya sudah DIHAPUS. Diuji end-to-end ke MySQL live: migrasi kolom + backfill data lama berhasil, buat jabatan baru dengan role Koordinator Guru lalu buat karyawan dengannya - role Koordinator Guru ter-assign benar (sebelumnya tidak mungkin). -->
- [x] **[BARU]** Field Role Sistem di form Tambah/Ubah Jabatan
  <!-- Deviasi kecil dari desain Figma asli (Jabatan cuma punya field Nama), tapi disepakati bersama user sebagai solusi lebih baik daripada menambah field Role di form Karyawan. -->
- [x] Halaman Manajemen Guru (card guru + list murid ampuan) — bergantung pada modul Murid/Kelas sudah ada datanya
  <!-- ManajemenGuruController + view, reuse Guru-Murid Card dari Detail Kelas (Fase 5). Guru = karyawan aktif dengan jabatan.role_id -> role "Guru". Assign murid lintas kelas (beda dari Detail Kelas yang scoped 1 kelas) via KelasGuruMurid::replaceForGuru() - kelas_id per baris diambil otomatis dari murid.kelas_id, murid tanpa kelas otomatis tersaring dari pilihan dropdown. Diuji end-to-end ke MySQL live: assign murid berhasil, murid tanpa kelas terbukti tidak muncul di pilihan. -->

**Fase 4 selesai.**

## Fase 5 — Murid & Kelas

- [x] Tabel `kelas`, `murid`, `kelas_guru_murid`
  <!-- Diuji ke MySQL live: 3 tabel berhasil dibuat, FK dan cascade behavior (murid.kelas_id SET NULL saat kelas dihapus) terverifikasi jalan. -->
- [x] CRUD Manajemen Kelas + Detail Kelas (multi-guru per kelas)
  <!-- KelasController + view index/show. Hapus kelas = hard DELETE (bukan soft-delete, karena kelas tidak punya is_active di schema.md). Diuji end-to-end ke MySQL live: tambah/ubah/hapus kelas, dan verifikasi murid.kelas_id benar-benar jadi NULL setelah kelas dihapus (bukan ikut terhapus). -->
- [x] Modal "Atur Anak Murid" (assign, dipakai dari Manajemen Guru dan Detail Kelas — pastikan satu implementasi reusable)
  <!-- Diimplementasi via KelasGuruMurid::replaceForGuruInKelas() (DELETE lalu INSERT ulang) — dipakai baik untuk "+ Tambah Guru" (guru baru) maupun "Atur Anak Murid" (ubah assignment guru yang sudah ada), logikanya identik. Diuji end-to-end: assign 2 murid -> ubah jadi 1 murid berbeda -> hapus guru dari kelas, semua benar di database. Komponen baru yang dibutuhkan: Guru-Murid Card (akan dipakai lagi di Manajemen Guru, Fase 4 item terakhir). -->
- [x] CRUD Manajemen Murid — 3 tab (Data Murid, Informasi Pendaftaran, Relasi & Kontak), field lengkap sesuai `schema.md`
  <!-- MuridController + satu view form.php reusable untuk tambah/ubah/detail (mengurangi duplikasi ~20 field x 3 mode). Field "Umur" computed dari tanggal_lahir, tidak disimpan ke DB. Tidak ada fitur Hapus murid — memang tidak ada modal_hapus di screenshot manapun untuk modul ini (status murid berubah lewat field status, bukan dihapus). [ASUMSI] opsi dropdown Status Kondisi (Reguler/Berkebutuhan Khusus) diturunkan dari konteks, hanya "Reguler" yang terkonfirmasi di crawling. Diuji end-to-end ke MySQL live: tambah (termasuk validasi 19 field wajib + old-input preserved saat gagal), ubah, dan detail semua benar. -->
- [x] Mode Detail Murid (read-only + field relasi Level Kelas/Kelas)
  <!-- Dibangun bersamaan dengan form.php di atas ($mode='detail'). Level Kelas/Kelas terkonfirmasi tampil sebagai hasil relasi (JOIN ke tabel kelas), bukan input langsung, sesuai temuan crawling. -->
- [ ] `[Konfirmasi ke user dulu]` Fitur "Import" murid (format file, mapping kolom)
  <!-- BLOCKED: tidak ada default di dokumen manapun soal format file (CSV/Excel?) atau mapping kolom yang diharapkan. Tombol "Import" sudah ditampilkan di UI (disabled, dengan tooltip penjelasan) supaya tidak menyesatkan user seolah fitur ini aktif. Perlu keputusan user sebelum diimplementasikan. -->

**Fase 5 selesai** (kecuali item Import yang BLOCKED menunggu keputusan user).

## Fase 6 — Kurikulum & Template Rapor

- [x] Tabel `template_rapor`, `template_rapor_area`, `template_rapor_subkategori`, `template_rapor_item`, `skala_nilai`, `skala_nilai_opsi`
  <!-- Diuji ke MySQL live: 6 tabel berhasil dibuat (7 termasuk periode_penilaian yang digabung di batch sama). Catatan lingkungan: MySQL sempat mati total (proses Laragon berhenti), dinyalakan ulang lewat `laragon.exe /auto-start` — kalau di sesi mendatang MySQL mati lagi, jalankan itu, BUKAN start mysqld langsung (mysqld standalone gagal load component_reference_cache.dll di instalasi Laragon ini). -->
- [x] Seed skala nilai Montessori 4 simbol
  <!-- 4 opsi (slash/triangle-sm/triangle-lg/triangle-full) berhasil di-seed dan diverifikasi di MySQL live. -->
- [ ] `[Konfirmasi ke user dulu]` Skala nilai untuk PAI/Bacaan Jilid
  <!-- BLOCKED: tidak ada default terdokumentasi, hanya contoh dummy "A-"/"Tahfizh Mumtaz" yang terlihat di crawling form guru, tidak cukup untuk membuat skala baku. -->
- [x] CRUD Periode Penilaian
  <!-- PeriodePenilaianController + view. [ASUMSI] opsi dropdown Tipe (Tengah/Akhir Semester) dan Kategori (Rapor Murid/Rapor Sekolah) diturunkan dari pola nama periode di screenshot, bukan daftar resmi. Diuji end-to-end ke MySQL live: tambah/ubah/hapus semua benar. -->
- [x] Halaman Manajemen Template (daftar, read-only kalau memang semua "System")
  <!-- TemplateRaporController::index() + view, murni read-only sesuai temuan crawling (semua tipe System, tidak ada tombol tambah). Diuji end-to-end ke MySQL live: 4 template ter-seed tampil benar dengan kategori/status/tanggal. -->
- [x] Pratinjau Template — render dokumen 1 halaman dulu sesuai screenshot yang ada, `[konfirmasi struktur halaman 2-4 ke user/designer sebelum lanjut]`
  <!-- View show.php + CSS rapor-document.css + helper renderSkalaSimbol() (render segitiga custom via inline SVG). Diverifikasi visual: watermark, header, identitas placeholder, legenda 4 simbol, tabel area/subkategori/item — semua cocok dengan desain. [ASUMSI BESAR] Struktur data (9 item di 4 subkategori "AREA KETERAMPILAN HIDUP") adalah PLACEHOLDER masuk akal secara konteks Montessori, BUKAN kurikulum resmi — hanya 1 item ("Menutup mulut saat batuk dan bersin") yang terkonfirmasi asli dari crawling. WAJIB diganti data asli dari user sebelum produksi. Halaman 2-4 dokumen (kemungkinan area lain + Bacaan Jilid + PAI + catatan guru, lihat pola di form Pengisian Rapor guru) BELUM dibangun, menunggu konfirmasi user/designer. -->

**[KEPUTUSAN, dikonfirmasi user]** Tidak ada CRUD untuk struktur kurikulum (area/sub-kategori/item). Ini disengaja, bukan celah — konten kurikulum bersifat *fixed*, dikelola manual lewat developer/AI assistant langsung ke database saat ada revisi, BUKAN lewat UI Admin. `template_rapor.tipe = 'System'` mencerminkan ini. Tidak perlu bangun halaman Tambah/Ubah/Hapus untuk area/subkategori/item di fase manapun, kecuali user secara eksplisit minta ini diubah nanti.

**PENTING — masih perlu konten dari user sebelum lanjut Fase 7:**
1. Konten kurikulum asli (semua area/subkategori/tujuan penilaian) untuk menggantikan placeholder di atas — akan di-input manual ke database begitu tersedia
2. Struktur halaman 2-4 dokumen rapor
3. Skala nilai untuk PAI/Bacaan Jilid (poin di atas)
- [x] Pilih & integrasikan library PDF (dompdf/mpdf — lihat `architecture.md` poin 5), fitur "Simpan PDF"
  <!-- Pilih dompdf/dompdf ^3.1 (lebih ringan untuk shared hosting, sesuai saran di architecture.md). Diinstall via composer.phar (ditemukan di ~/.config/herd-lite/bin/), vendor/ di-regenerate dengan `composer install --no-dev --optimize-autoloader` sebelum commit sesuai konvensi deploy.md. Markup dokumen diekstrak ke partial _document.php yang dipakai bersama preview web dan generate PDF (hindari duplikasi). CSS khusus PDF (rapor-document-pdf.css, nilai literal bukan var()) dibuat terpisah karena dukungan CSS custom property di Dompdf tidak konsisten. Gambar di-inline base64 untuk PDF (path relatif tidak reliable di Dompdf). Diuji end-to-end: PDF ter-generate valid (%PDF-1.7), konten diverifikasi lengkap via pdftotext (semua teks termasuk placeholder, legenda, area/subkategori/item, footer, urutan benar). Verifikasi visual render PDF tidak bisa dilakukan (tidak ada pdftoppm/ImageMagick di environment ini), tapi markup HTML-nya identik dengan yang sudah diverifikasi visual di preview web. -->

**Fase 6 selesai** (kecuali skala nilai PAI/Bacaan Jilid yang BLOCKED, dan konten kurikulum asli yang masih placeholder — lihat catatan "PENTING" di atas).

## Fase 7 — Rapor (Bagian Paling Kompleks)

- [x] Tabel `sesi_pembagian_rapor`, `rapor`, `rapor_nilai`, `rapor_catatan_guru`
  <!-- Diuji ke MySQL live: 4 tabel berhasil dibuat, FK ke periode/template/murid/guru/users semua benar. -->
- [x] Halaman Rapor Murid (Admin): accordion 3 level Periode → Sesi → per-murid dengan status
  <!-- RaporMuridController + view, reuse pattern accordion generik dari Fase 2. Modal "Tambah Sesi Pembagian" otomatis generate baris rapor untuk semua murid berstatus bersekolah + assign guru dari kelas_guru_murid. Diuji end-to-end ke MySQL live: buat sesi -> 4 baris rapor otomatis ter-generate dengan status belum_diisi, guru_id ter-assign benar untuk murid yang sudah punya guru. Visual sangat cocok dengan desain (badge jumlah murid, badge status merah/oranye/hijau). -->
- [x] Pratinjau Rapor Murid (Admin): render dokumen terisi data nyata, tombol Simpan PDF
  <!-- Reuse pattern _document.php dari Pratinjau Template tapi placeholder diganti data murid+kelas+nisn sungguhan, dan sel nilai menampilkan simbol asli (bukan kosong) kalau sudah diisi guru. Diuji end-to-end dengan data simulasi (9 nilai + 1 catatan guru): nama/kelas asli tampil benar, simbol nilai per item tampil sesuai data, PDF ter-generate valid. -->
- [x] `[Konfirmasi dulu]` Definisikan role approver rapor (Koordinator Guru vs Kepala Sekolah) sebelum bangun alur approval
  <!-- [KEPUTUSAN] Diselesaikan TANPA hardcode role tertentu — approval (`RaporMuridController::approve()`) dilindungi oleh permission RBAC 'edit' pada Murid > Rapor Murid yang SUDAH ADA di infrastruktur RBAC, bukan pengecekan role spesifik di kode. Sekolah bebas assign permission itu ke role manapun (Koordinator Guru, Admin, dst) lewat halaman RBAC yang sudah dibangun di Fase 1. Diuji end-to-end: approve mengubah status jadi disetujui + mencatat disetujui_oleh (user_id) dan disetujui_at. -->

**Catatan lingkungan:** MySQL (Laragon) tetap perlu dinyalakan via `laragon.exe /auto-start` kalau mati, bukan mysqld.exe langsung (lihat catatan Fase 6).

**Fase 7 selesai.**

## Fase 8 — Portal Guru

- [x] Dashboard Guru: 3 blok (Agenda Sedang Berlangsung, Daftar Murid ampuan, Agenda Berikutnya + empty state)
  <!-- PortalGuruController::dashboard(). Agenda Sedang Berlangsung/Berikutnya diturunkan langsung dari tanggal_mulai/tanggal_selesai sesi_pembagian_rapor (tidak perlu tabel "agenda" terpisah). Tambahan penting: session sekarang menyimpan karyawan_id saat login (AuthController + AuthMiddleware) supaya Portal Guru bisa identifikasi "murid ampuan saya". BONUS FIX: sidebar sekarang filter item sesuai permission RBAC role aktif (sebelumnya semua role lihat semua menu meski nanti di-403 kalau diklik) - ditemukan saat testing sebagai guru, App Shell (shell-header.php) diupdate untuk semua modul, bukan cuma Portal Guru. Diuji end-to-end ke MySQL live sebagai akun guru sungguhan: badge count, countdown hari, link kontekstual Isi Rapor, empty state Agenda Berikutnya, dan filter sidebar semua benar. -->
- [x] Daftar Murid (read-only, versi guru)
  <!-- PortalGuruController::daftarMurid(), 5 kolom tanpa Status/aksi CRUD sesuai temuan crawling. Diuji end-to-end: hanya menampilkan murid yang diampu guru yang login. -->
- [x] Form Pengisian Rapor (desktop) — struktur berjenjang Kategori→Sub-kategori→item, textarea Catatan Guru, progress bar
  <!-- PengisianRaporController::show()/simpan(). [ASUMSI] Crawl form guru cuma tunjukkan SATU dropdown per item, padahal skema rapor_nilai punya kolom semester ganjil/genap terpisah (dipakai bareng di dokumen/Pratinjau yang menampilkan kolom TS Ganjil + TS Genap sekaligus). Diselesaikan dengan menurunkan semester aktif otomatis dari tipe periode sesi pembagian rapor saat ini ('Tengah Semester'→ganjil, 'Akhir Semester'→genap, sesuai nilai tipe yang sudah jadi keputusan di PeriodePenilaianController Fase 6) lewat semesterUntukSesi(), lalu buildStructureForPengisian() cuma bangun 1 nilai per item untuk semester itu — beda dengan buildStructureWithNilai() (dipakai Pratinjau) yang tetap 2 kolom. IDOR dicegah lewat findOwnRapor() (WHERE guru_id = karyawan_id session), diuji: guru lain akses rapor bukan miliknya → 404. Diuji end-to-end ke MySQL live sebagai sari.guru@zivana.test: isi 9 item + 1 catatan via "Arsip Rapor" (progress bar update 1/9→9/9), reload menampilkan nilai tersimpan (opsi terpilih persist). -->
- [x] Versi mobile Pengisian Rapor (WAJIB jalan baik di HP — satu-satunya halaman dengan requirement mobile eksplisit)
  <!-- CSS sudah disiapkan sejak Batch 1 (portal-guru.css: .pengisian-item-row stack vertikal, .pengisian-actions full-width di @media max-width:480px). [ASUMSI/DEVIASI] Crawl bilang tombol aksi pindah ke sticky bar bawah HANYA di mobile (desktop di toolbar atas) — disederhanakan jadi sticky bar bawah di SEMUA ukuran layar, tidak mengubah fungsi. BUG DITEMUKAN saat verifikasi mobile sungguhan: App Shell (app-shell.css) tidak punya breakpoint sama sekali, sidebar 236px penuh memakan ~74% dari layar 320px sehingga form nyaris tidak terpakai. Diperbaiki dengan @media (max-width:480px) baru yang memaksa sidebar jadi mode ikon-saja 64px (aman karena grup nav Portal Guru — satu-satunya yang dipakai role Guru — tidak punya submenu yang perlu disembunyikan), plus toolbar atas (search field, nama user) dirapikan. Diuji dengan screenshot Chrome headless via CDP Emulation.setDeviceMetricsOverride (bukan flag --window-size yang terbukti tidak reliable di bawah ~500px pada versi Chrome ini) di viewport 320px: sidebar collapse benar, form 1 kolom, tombol full-width di bar sticky bawah. -->
- [x] Tombol "Selesaikan Rapor" → ubah status jadi "menunggu_persetujuan", kunci form dari edit lebih lanjut
  <!-- PengisianRaporController::selesaikan() — simpan nilai+catatan sekali lagi (jaga-jaga ada perubahan belum di-"Arsip Rapor"), lalu update status rapor + redirect ke Pratinjau. show() cek status !== 'belum_diisi' → redirect paksa ke Pratinjau (form terkunci). Diuji end-to-end: klik Selesaikan Rapor pada rapor 9/9 terisi → status DB berubah jadi menunggu_persetujuan, akses ulang /portal-guru/rapor/{id} di-redirect ke /pratinjau (tidak bisa edit lagi). -->
- [x] Pratinjau Rapor Murid (versi Guru, sebelum submit)
  <!-- PengisianRaporController::pratinjau() reuse langsung app/views/admin/rapor-murid/_document.php (sama persis dokumen 2 kolom TS Ganjil/TS Genap dengan RaporMuridController versi Admin) — cuma toolbar beda: link "Kembali Mengisi" muncul kalau status masih belum_diisi. Diuji end-to-end: simbol nilai tampil benar di kolom TS Ganjil sesuai isian guru, kolom TS Genap kosong (belum ada sesi Akhir Semester di data), identitas murid (nama/kelas/NISN) benar. Mobile: dokumen tetap fixed-width (perlu scroll horizontal) — ini bukan halaman yang wajib mobile menurut design-system.md bagian 6, sengaja tidak diubah supaya konsisten dengan Pratinjau versi Admin. -->

**Fase 8 selesai.**

## Fase 9 — Hardening & Deploy

- [x] Jalankan seluruh checklist `security.md` bagian 10
  <!-- Audit item per item: (1) APP_DEBUG=false — default aman di config.php kalau .env tidak set, dan diverifikasi ganti-ganti true/false live mempengaruhi output error. (2) Tidak ada kredensial hardcoded — dikonfirmasi, config.php `die()` kalau .env gagal dibaca, tidak ada fallback. (3) Tidak ada file debug/diagnostic di public/ — bersih. (4) CSRF enforce semua POST/PUT/DELETE — sudah di Controller::__construct(), tidak ada route PUT/DELETE terdaftar sama sekali (semua lewat POST). (5) RoleMiddleware terpasang di semua route yang butuh permission — diaudit lewat grep ke SEMUA controller: setiap method publik (selain AuthController yang memang publik) sudah punya AuthMiddleware+RoleMiddleware, tidak ada yang lolos. (6) .env tidak ter-commit — dikonfirmasi via `git ls-files`. (7) Validasi server-side — diaudit semua controller Store/Update, semua sudah validasi manual (bukan cuma HTML5 required).
  BUG DITEMUKAN & DIPERBAIKI saat audit poin (4)-(5): `$router->dispatch()` di public/index.php TIDAK dibungkus try-catch (padahal security.md eksplisit mewajibkan ini) — exception tak tertangani akan jadi halaman putih kosong di produksi (APP_DEBUG=false mematikan display_errors tapi tidak ada pesan generic). Diperbaiki: try-catch di index.php + app/views/errors/500.php baru (detail exception cuma tampil kalau APP_DEBUG true, selalu dicatat error_log()). Diuji live: trigger exception sungguhan dengan APP_DEBUG=true (trace tampil) dan APP_DEBUG=false (trace tersembunyi, halaman generic, tetap tercatat di log) — keduanya benar.
  BUG KEDUA: PengisianRaporController::simpan()/selesaikan() (Fase 8) memakai aksi 'lihat' padahal keduanya menulis data — beda dari konvensi SELURUH controller lain (aksi tulis selalu 'edit'). Diperbaiki jadi 'edit', diuji live: akses tanpa permission edit -> 403, setelah role Guru diberi permission edit lewat halaman RBAC -> 302 sukses.
  TAMBAHAN hardening: cookie session dan remember_token sekarang set flag `secure` otomatis kalau request lewat HTTPS (sebelumnya tidak ada sama sekali) — tidak mengganggu HTTP lokal karena kondisional. -->
- [x] Uji akses tiap role sesuai matriks RBAC (termasuk uji akses URL langsung tanpa lewat menu)
  <!-- Diuji end-to-end ke MySQL live: login sebagai tiap 1 akun per role (Superadmin/Admin/Koordinator Guru/Guru), akses 12 route representatif LANGSUNG lewat URL (bukan klik menu) mencakup semua modul. Hasil PERSIS sesuai matriks permission yang dikonfigurasi: Superadmin 200 di semua, Admin 200 di semua KECUALI /rbac (403), Koordinator Guru 403 di semua KECUALI /rapor-murid (200 — cakupan approval sesuai keputusan Fase 7), Guru 403 di semua KECUALI 2 route Portal Guru (200). Ini membuktikan proteksi memang server-side, bukan cuma sembunyi menu (sesuai security.md bagian 3). Catatan setup: akun Admin test (nur.baru@zivana.test) sebelumnya is_active=0 dan password beda dari akun test lain — diaktifkan + password diseragamkan untuk kebutuhan testing ini. Permission Admin (semua modul kecuali Sistem>RBAC) dan Koordinator Guru (Murid>Rapor Murid lihat+edit saja, sesuai scope approval yang sudah diputuskan) dikonfigurasi lewat halaman RBAC sungguhan (bukan SQL manual) supaya RbacController::update() ikut teruji. -->
- [x] Setup `deploy.sh` lokal (`composer install --no-dev --optimize-autoloader`)
  <!-- deploy.sh dibuat di root repo, menjalankan persis `composer install --no-dev --no-scripts --optimize-autoloader` sesuai deploy.md bagian 2. Dites jalan: "Nothing to install, update or remove" (vendor/ sudah sesuai lock file), autoloader optimized ter-generate ulang. vendor/composer/InstalledVersions.php + installed.php berubah kecil (artefak versi composer lokal, bukan perubahan dependency) — ikut di-commit sesuai prinsip deploy.md "hasil regenerate yang di-commit". -->
- [x] Ikuti langkah deploy di `deploy.md`, jalankan checklist go-live-nya
  <!-- Item yang BISA diverifikasi tanpa hosting sungguhan — semua lolos: vendor/ ter-commit lengkap (328 file), .env tidak ter-commit, database/schema.sql ada & sudah terbukti valid dipakai selama Fase 1-8, checklist security.md selesai (lihat di atas), font Plus Jakarta Sans dimuat via Google Fonts dengan preconnect. Item yang PERLU shared hosting sungguhan untuk diverifikasi (document root ke public/, upload kredensial produksi asli) TIDAK bisa dites dari sini — tetap jadi langkah manual user saat deploy sungguhan, sudah didokumentasikan lengkap di deploy.md. -->
- [x] Test PDF generation di environment produksi shared hosting (cek extension PHP yang tersedia)
  <!-- [BATASAN] Tidak ada akses shared hosting sungguhan dalam task ini, jadi "environment produksi" didekati semaksimal mungkin secara lokal: PDF Rapor Murid digenerate ulang PAKAI vendor/ hasil `composer install --no-dev` (bukan vendor dev biasa) — 200 OK, PDF valid (v1.7, ~78KB, konten benar). Extension PHP yang dipakai Dompdf (gd, mbstring, dom) dikonfirmasi aktif di PHP 8.4 lokal — ketiganya termasuk extension yang HAMPIR SELALU aktif default di shared hosting cPanel, tapi WAJIB dicek ulang manual di cPanel > PHP Selector saat deploy sungguhan sebelum go-live (dicatat juga di deploy.md checklist). -->

**Fase 9 selesai.**

### Perbaikan pasca-Fase 9 (feedback user menjalankan aplikasi di lokal)

- [x] Area konten tidak full-width di layar nyata (tabel berhenti di tengah, sisa ruang kosong di kanan)
  <!-- Root cause: design-system.md 2.4 mendokumentasikan "lebar konten efektif 996px" — angka ini hasil ukur di kanvas Figma 1280px, bukan lebar tetap yang dimaksud untuk semua ukuran layar. Karena browser nyata hampir selalu lebih lebar dari 1280px, cap keras `max-width:996px` di .content-body (app-shell.css) membuat konten berhenti di tengah. Diperbaiki jadi max-width:1600px (tabel/accordion sekarang benar-benar full-width di lebar laptop/desktop umum 1280-1600px, tetap ada batas di monitor ultra-wide). Diverifikasi via screenshot CDP di 1512px pada: Manajemen Murid (tabel), Rapor Murid (accordion), Data Sekolah (form 2 kolom), modal Tambah Karyawan, Portal Guru Dashboard (grid), Manajemen Kelas (tabel), RBAC (tree) — semua rapi, tidak ada yang melar aneh. -->
- [x] Tombol terasa "tidak jelas" — kontras kurang dan padding terasa sempit
  <!-- Root cause ganda: (1) .btn punya padding 8px SERAGAM di semua sisi (sesuai design-system.md 3.2), membuat tombol berteks nyaris tanpa jarak horizontal — diperbaiki jadi 8px vertikal + 20px horizontal, plus font-weight bold supaya tombol terbaca lebih solid. (2) .btn-tertiary (background putih, border neutral-100) nyaris identik dengan .field-input (background putih, border neutral-75) — tinggi, radius, dan warna nyaris sama sehingga tombol Tertiary (mis. tombol "Cari" di baris filter) baur dengan dropdown/input di sebelahnya. Diperbaiki: border digelapkan ke neutral-150 + tambah box-shadow tipis supaya tetap terbaca sebagai elemen bisa-diklik. [KEPUTUSAN, dikonfirmasi user lewat AskUserQuestion] User mengonfirmasi kedua penyebab ini (kontras kurang DAN padding beda) lewat screenshot pembanding, bukan tebakan sepihak. -->

---

## Item yang Butuh Konfirmasi User Sebelum Dikerjakan

Dikumpulkan dari seluruh dokumen `cookbook/` — sebaiknya dikonfirmasi di awal Fase 1 supaya tidak menghambat fase-fase berikutnya:

1. Batas akses role Koordinator Guru & siapa approver rapor
2. Definisi jabatan "Guru Shadow"
3. Struktur halaman 2–4 dokumen rapor (Manajemen Template)
4. Skala nilai untuk PAI/Bacaan Jilid
5. Apakah foto murid masuk scope
6. Mekanisme fitur Import murid
7. Alur forgot-password
8. Tipe field "Kelengkapan Berkas" (text vs checklist vs upload)
9. Kebutuhan R2/upload storage eksternal (atau cukup lokal untuk pilot ini)
