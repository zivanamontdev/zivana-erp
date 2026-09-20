# Todo — Roadmap Implementasi Zivana ERP

Urutan disusun berdasarkan dependency logis: fondasi dulu (auth, RBAC, app shell), baru modul yang bergantung padanya.

---

## Fase 0 — Fondasi Teknis

- [ ] Setup struktur folder sesuai `architecture.md` (app/, config/, database/, routes/, public/)
- [ ] Setup `Router`, `Controller` base class, `Model` base class, `Database` (PDO wrapper)
- [ ] Setup autoload manual (`spl_autoload_register`) + `composer.json` awal
- [ ] Setup `.env` + `.env.example` + `config/config.php` (tanpa fallback hardcoded — lihat `security.md`)
- [ ] Import `database/schema.sql` versi awal (tabel auth/RBAC dulu, tabel lain menyusul per fase)
- [ ] Setup design token: `public/assets/css/tokens.css` dari `design-system.md` bagian 1.1–1.3
- [ ] Setup font Plus Jakarta Sans (Google Fonts atau self-host — putuskan sesuai `architecture.md` poin 6)
- [ ] Build App Shell layout (`views/layouts/app-shell.php`): sidebar + 3-baris page header sesuai `design-system.md` bagian 2

## Fase 1 — Auth & RBAC (blocker untuk semua modul lain)

- [ ] Tabel `roles`, `permissions`, `role_permissions`, `users`, `password_resets`
- [ ] Halaman Login (email, password, Ingat Saya, Lupa kata sandi) + `AuthMiddleware`, `GuestMiddleware`
- [ ] Seed 4 role: Superadmin, Admin, Koordinator Guru, Guru
- [ ] `RoleMiddleware` untuk enforce permission server-side (lihat `security.md` poin 3)
- [ ] Halaman Sistem → RBAC: render matriks permission, simpan perubahan checkbox ke `role_permissions`
- [ ] CSRF protection otomatis di `Controller`/`Router` base class
- [ ] `[Konfirmasi ke user dulu]` Alur reset password (belum ada screenshot halamannya)

## Fase 2 — Komponen UI Dasar (reusable, dipakai semua modul)

- [ ] Komponen Button (Primary/Secondary/Tertiary × Regular/Pressed/Disable)
- [ ] Komponen Input (textfield/textarea × 5 state × 4 varian icon)
- [ ] Komponen Checkbox (termasuk logika tri-state untuk RBAC)
- [ ] Komponen Badge Status (4 varian warna)
- [ ] Komponen Modal (dua ukuran: 440px, 668px)
- [ ] Komponen Tabel (header, data row, kolom aksi ellipsis)
- [ ] Pattern "Assign Many-to-Many" (dropdown dinamis + tombol tambah/hapus baris) — dipakai di 2 tempat nanti
- [ ] Pattern "Accordion Bertingkat" — dipakai di RBAC, Rapor Murid, Pengisian Rapor

## Fase 3 — Modul Sekolah

- [ ] Tabel `sekolah`, `sekolah_media`, `tahun_ajaran`
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
