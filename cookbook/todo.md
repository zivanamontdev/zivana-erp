# Todo — Roadmap Implementasi Zivana ERP

Urutan disusun berdasarkan dependency logis: fondasi dulu (auth, RBAC, app shell), baru modul yang bergantung padanya.

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
- [ ] Form Pengisian Rapor (desktop) — struktur berjenjang Kategori→Sub-kategori→item, textarea Catatan Guru, progress bar
- [ ] Versi mobile Pengisian Rapor (WAJIB jalan baik di HP — satu-satunya halaman dengan requirement mobile eksplisit)
- [ ] Tombol "Selesaikan Rapor" → ubah status jadi "menunggu_persetujuan", kunci form dari edit lebih lanjut
- [ ] Pratinjau Rapor Murid (versi Guru, sebelum submit)

## Fase 9 — Hardening & Deploy

- [ ] Jalankan seluruh checklist `security.md` bagian 10
- [ ] Uji akses tiap role sesuai matriks RBAC (termasuk uji akses URL langsung tanpa lewat menu)
- [ ] Setup `deploy.sh` lokal (`composer install --no-dev --optimize-autoloader`)
- [ ] Ikuti langkah deploy di `deploy.md`, jalankan checklist go-live-nya
- [ ] Test PDF generation di environment produksi shared hosting (cek extension PHP yang tersedia)

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
