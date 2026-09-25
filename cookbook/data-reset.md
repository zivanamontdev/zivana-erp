# Reset & Seed Data (Hostinger, tanpa SSH)

Halaman `/sistem/reset-data` mengosongkan data operasional lalu mengisi master data uji coba yang realistis (tanpa tag `[DEMO]`). Logika: `app/models/DataResetSeeder.php`. Tes: `tests/data-reset-seed.php`.

## Yang dipertahankan

- Akun **Superadmin** tanpa data pegawai yang email-nya bukan `@demo.zivana.test` (akun yang menjalankan reset wajib termasuk di sini).
- RBAC (`roles`, `permissions`, `role_permissions`), `jabatan`, template rapor lama.
- Seluruh katalog/rubrik eRapor dan alur persetujuan hasil migrasi.

## Yang dikosongkan

Semua akun lain, karyawan, data sekolah + media, tahun ajaran, periode, kelas, murid, kelompok guru-murid, rapor lama, serta seluruh sesi/isian/persetujuan/artefak PDF/profil tanda tangan eRapor. Daftar lengkap: `DataResetSeeder::CLEAR_TABLES`. Tabel yang tidak terdaftar di `KEEP_TABLES` atau `CLEAR_TABLES` **memblokir** proses sebelum ada penghapusan.

## Yang dibuat

- Data sekolah (alamat `Kota Makassar, Sulawesi Selatan`; perbarui telepon/email/alamat asli lewat Data Sekolah).
- Tahun ajaran 2025/2026 (tidak aktif) dan 2026/2027 (aktif), masing-masing 4 periode.
- 9 akun pegawai fiktif `@sekolahzivanamontessori.sch.id`: 1 Kepala Sekolah, 1 Admin, 5 Guru Kelas, 2 Guru Shadow. Password awal diisi di form.
- 4 kelas 2026/2027 (Akar Melati, Batang Kenanga, Ranting Akasia, Daun Mahoni).
- 10 murid fiktif: 7 aktif (5 Regular di semua level, 2 ABK dengan Guru Shadow; satu murid per guru), plus status tanpa keterangan, berhenti (ABK), dan tamat. Telepon orang tua memakai pola `08110000xxxx` agar tidak menjangkau nomor sungguhan.
- Penugasan penyetuju: Kepala Sekolah pada ketiga tahap (koordinator dapat diganti di menu Penugasan Penyetuju).
- Dua rapor contoh terisi penuh di Tengah Semester Ganjil 2026/2027 lewat layanan simpan editor guru: Fathan Al Ghifari (Regular) dan Zahra Aulia Kirana (ABK, termasuk PPI). Status BELUM DIISI sehingga guru tinggal meninjau lalu Selesaikan Rapor.

## Lokal: bangun ulang dari nol

`php database/bootstrap.php --fresh --superadmin-email=EMAIL --superadmin-password=PASS --seed-password=PASS` menghapus semua tabel database lokal di `.env`, lalu: `schema.sql` → migrasi + seed rubrik eRapor → jabatan, aksi izin `PermissionCatalog::EXTRA`, RBAC dasar (`DataBaseline`) dan Superadmin → seed di atas. Hanya berjalan untuk `DB_HOST` lokal.

## Langkah

1. **Backup database produksi** (phpMyAdmin → Export) dan simpan di luar server.
2. Deploy kode lewat Git Hostinger.
3. Tambahkan ke `.env` produksi: `DATA_RESET_TOKEN=<acak minimal 32 karakter>` (mis. `php -r "echo bin2hex(random_bytes(24));"`).
4. Login dengan **Superadmin utama** (bukan akun demo), buka `/sistem/reset-data`.
5. Isi token, klik **Pratinjau**. Periksa jumlah baris per tabel, akun yang dipertahankan, dan data yang akan dibuat. Bila ada pesan berhenti, jangan lanjut.
6. Isi password awal (dua kali) dan ketik `KOSONGKAN DATA`, klik **Jalankan Reset & Seed** sekali. Semua penghapusan dan seed berjalan dalam satu transaksi; bila gagal, tidak ada yang berubah.
7. **Hapus baris `DATA_RESET_TOKEN`** dari `.env` (halaman kembali 404). Bagikan password awal ke pengguna dan minta mereka menggantinya.
