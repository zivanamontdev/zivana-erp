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

**Catatan lingkungan kerja**: ditemukan proses MySQL yang sudah berjalan di komputer ini (port 3306, PID terpisah dari instalasi project ini). TIDAK dicoba disambungkan tanpa izin karena tidak jelas kepunyaan/isi datanya. Semua kode Fase 1 yang menyentuh database baru lolos `php -l` dan review manual, BELUM diuji fungsional terhadap MySQL nyata. Kalau user berkenan share kredensial DB lokal (atau konfirmasi aman memakai server MySQL yang terdeteksi ini), pengujian end-to-end sesungguhnya bisa dilakukan mulai fase berikutnya.

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
  <!-- Ditambahkan ke database/schema.sql. Belum diuji ke MySQL nyata (lihat catatan lingkungan kerja di Fase 1). -->
- [ ] Halaman Data Sekolah (tab Informasi Umum, tab Kontak & Media) — mode lihat/ubah, validasi wajib
- [ ] Modal Perbarui Tahun Ajaran

## Fase 4 — Human Capital

- [ ] Tabel `jabatan`, `karyawan` (+ relasi ke `users`)
- [ ] CRUD Jabatan
- [ ] CRUD Daftar Karyawan (termasuk pembuatan akun `users` saat tambah karyawan, form "Ganti Kata Sandi" terpisah)
- [ ] Halaman Manajemen Guru (card guru + list murid ampuan) — bergantung pada modul Murid/Kelas sudah ada datanya

## Fase 5 — Murid & Kelas

- [ ] Tabel `kelas`, `murid`, `kelas_guru_murid`
- [ ] CRUD Manajemen Kelas + Detail Kelas (multi-guru per kelas)
- [ ] Modal "Atur Anak Murid" (assign, dipakai dari Manajemen Guru dan Detail Kelas — pastikan satu implementasi reusable)
- [ ] CRUD Manajemen Murid — 3 tab (Data Murid, Informasi Pendaftaran, Relasi & Kontak), field lengkap sesuai `schema.md`
- [ ] Mode Detail Murid (read-only + field relasi Level Kelas/Kelas)
- [ ] `[Konfirmasi ke user dulu]` Fitur "Import" murid (format file, mapping kolom)

## Fase 6 — Kurikulum & Template Rapor

- [ ] Tabel `template_rapor`, `template_rapor_area`, `template_rapor_subkategori`, `template_rapor_item`, `skala_nilai`, `skala_nilai_opsi`
- [ ] Seed skala nilai Montessori 4 simbol
- [ ] `[Konfirmasi ke user dulu]` Skala nilai untuk PAI/Bacaan Jilid
- [ ] CRUD Periode Penilaian
- [ ] Halaman Manajemen Template (daftar, read-only kalau memang semua "System")
- [ ] Pratinjau Template — render dokumen 1 halaman dulu sesuai screenshot yang ada, `[konfirmasi struktur halaman 2-4 ke user/designer sebelum lanjut]`
- [ ] Pilih & integrasikan library PDF (dompdf/mpdf — lihat `architecture.md` poin 5), fitur "Simpan PDF"

## Fase 7 — Rapor (Bagian Paling Kompleks)

- [ ] Tabel `sesi_pembagian_rapor`, `rapor`, `rapor_nilai`, `rapor_catatan_guru`
- [ ] Halaman Rapor Murid (Admin): accordion 3 level Periode → Sesi → per-murid dengan status
- [ ] Pratinjau Rapor Murid (Admin): render dokumen terisi data nyata, tombol Simpan PDF
- [ ] `[Konfirmasi dulu]` Definisikan role approver rapor (Koordinator Guru vs Kepala Sekolah) sebelum bangun alur approval

## Fase 8 — Portal Guru

- [ ] Dashboard Guru: 3 blok (Agenda Sedang Berlangsung, Daftar Murid ampuan, Agenda Berikutnya + empty state)
- [ ] Daftar Murid (read-only, versi guru)
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
