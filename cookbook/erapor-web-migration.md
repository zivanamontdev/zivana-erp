# Migrasi eRapor lewat Browser (Hostinger, tanpa SSH)

Pengganti `erapor-cpanel-cron.md` bila Cron tidak menampilkan output. Menjalankan logika yang sama (`EraporPilotInstaller`) dengan `database/run-erapor-pilot.php`: 17 migrasi ber-checksum, seed rubrik RTS/UMMI/PPI/BING/AGAMA, dan seed alur persetujuan. Tidak mengimpor dump, tidak mengubah data rapor lama, tidak membuat assignment penyetuju, dan tidak menyalakan `ERAPOR_API_ENABLED`.

## Pengaman

- `GET/POST /sistem/migrasi-erapor` mengembalikan **404** kecuali `.env` berisi `ERAPOR_MIGRATION_TOKEN` minimal 32 karakter. `.env` tidak ikut Git deploy.
- Hanya akun aktif ber-role **Superadmin**; role lain mendapat 404, tamu diarahkan ke login.
- Token harus diketik ulang di form (dibandingkan dengan `hash_equals`) dan request membawa CSRF.
- Installer menolak berjalan selama `ERAPOR_API_ENABLED=true`.
- Request memakai `ignore_user_abort` agar DDL tidak terputus bila tab tertutup. Mulai dan selesai dicatat ke error log server.

## Langkah

1. Backup database produksi (phpMyAdmin → Export) dan simpan di luar server.
2. Deploy kode lewat Git Hostinger. Pastikan `eRapor_Zivana_Spesifikasi/` dan `assets/` ikut ter-deploy.
3. Di File Manager, edit `.env` produksi:
   ```
   ERAPOR_API_ENABLED=false
   ERAPOR_MIGRATION_TOKEN=<string acak minimal 32 karakter>
   ```
   Contoh membuat token di komputer lokal: `php -r "echo bin2hex(random_bytes(24));"`.
4. Login sebagai Superadmin, buka `https://erp.sekolahzivanamontessori.sch.id/sistem/migrasi-erapor`.
5. Isi token, klik **Periksa**. Harus tampil `Preflight passed` dan `Check-only mode: no writes performed`. Bila `STOP`, jangan lanjut.
6. Klik **Jalankan Migrasi** sekali, tunggu hasil tampil. Hasil sukses:
   ```
   [eRapor] Verified: migrations=17, rubrics=5, approval stages=3, existing user assignments preserved=0
   [eRapor] ERAPOR_API_ENABLED remains false.
   ```
   Klik ulang aman: hasilnya `Schema and pilot seeds were already complete; no changes needed.`
7. Salin/screenshot output, lalu **hapus baris `ERAPOR_MIGRATION_TOKEN`** dari `.env` dan ubah `ERAPOR_API_ENABLED=true`. Halaman migrasi kembali 404.
8. Lanjutkan ke RBAC (izin eRapor untuk Superadmin dan Admin) dan Penugasan Penyetuju.

Bila output menunjukkan `STOP` setelah sebagian migrasi, jangan menghapus baris ledger `erapor_migrations` atau mengulang secara manual. Simpan output dan audit skema dulu.
