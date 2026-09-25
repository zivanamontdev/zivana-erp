# Migrasi eRapor melalui cPanel Cron

Gunakan prosedur ini hanya setelah backup database production selesai. Prosedur ini menambah skema dan katalog pilot eRapor; ia **tidak** mengimpor dump lokal, mengonversi data `rapor` lama, membuat assignment penyetuju, atau mengaktifkan `ERAPOR_API_ENABLED`.

## Prasyarat

- Deploy commit yang berisi `database/run-erapor-pilot.php`, file migrasi, model seeder, dan JSON rubrik resmi.
- File `.env` production tetap berada di root aplikasi dan memuat kredensial database production yang benar.
- Backup production telah diekspor, diunduh, dan disimpan di luar document root.
- `ERAPOR_API_ENABLED=false`.
- Tabel eRapor belum ada. Runner juga mengulang pemeriksaan ini sebelum menulis.

## Pemeriksaan read-only

Di cPanel **Cron Jobs**, pilih **PHP**. Kolom perintah menerima path skrip dan argumen sesudah prefix PHP yang disediakan cPanel. Sesuaikan path dengan root aplikasi aktual; jangan menaruh kredensial di perintah.

Contoh jika root aplikasi adalah `/home/ACCOUNT/public_html/subdomain/erp`:

```text
public_html/subdomain/erp/database/run-erapor-pilot.php --check
```

Jalankan sekali, ambil output Cron, lalu hapus Cron Job tersebut. `--check` hanya membaca konfigurasi dan database; ia memeriksa versi MySQL/MariaDB, tabel induk, tipe ID, ledger, serta collision skema. Output harus menyatakan `Preflight passed` dan `Check-only mode: no writes performed`. Jika gagal, **jangan** lanjut ke `--apply`.

## Penerapan

Setelah hasil `--check` ditinjau, buat Cron Job terpisah dengan argumen:

```text
public_html/subdomain/erp/database/run-erapor-pilot.php --apply-production-schema-only
```

Pilih waktu yang segera, aktifkan notifikasi output Cron, lalu hapus Cron Job setelah hasil diterima. Cron cPanel berulang sesuai jadwal; jangan biarkan job penerapan tetap terjadwal.

Runner menjalankan 17 migrasi ber-checksum secara berurutan, lalu seed resmi RTS, UMMI, PPI, BING, Agama, dan seed konfigurasi permission/alur persetujuan. Seeder dan DDL punya pemeriksaan idempotensi; namun DDL MySQL bisa gagal parsial. Bila output menunjukkan `STOP`, hentikan Cron dan **jangan** menghapus row ledger atau menandai migrasi complete secara manual. Simpan output dan audit skema sebelum tindakan pemulihan.

Hasil sukses harus mencakup:

```text
Verified: migrations=17, rubrics=5, approval stages=3, existing user assignments preserved=0
ERAPOR_API_ENABLED remains false.
```

## Sesudah penerapan

Di phpMyAdmin, verifikasi tanpa mengubah data:

```sql
SELECT id, sha256, status FROM erapor_migrations ORDER BY id;
SELECT kode, jenis_dokumen, versi, status FROM erapor_rubrik ORDER BY kode;
SELECT kode, urutan, cakupan FROM erapor_alur_penyetuju ORDER BY urutan, kode;
SELECT COUNT(*) AS assignment_count FROM erapor_penyetuju_user;
```

Expected: 17 migrasi berstatus `complete`, lima rubrik V1 (RTS, UMMI, PPI, BING, AGAMA), tiga tahap persetujuan, dan nol assignment user. Assignment calon penyetuju diatur kemudian melalui fitur setup oleh pengelola yang berwenang.

Feature flag tetap OFF. Aktivasi dan uji browser dilakukan terpisah setelah hasil database dibaca kembali dan prasyarat pilot lainnya siap. Jangan mengimpor ulang `database/schema.sql` atau dump database lokal ke production.
