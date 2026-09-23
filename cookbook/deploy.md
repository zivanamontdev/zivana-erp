# Deploy — Zivana ERP (Shared Hosting)

## Target subdomain ERP

### Alternatif jika document root masih root repository

Repo juga menyediakan `.htaccess` di root untuk document root `/public_html/subdomain/erp`. Upload **keduanya**, root `.htaccess` dan `public/.htaccess`, sebagai satu perubahan. File root meneruskan request secara internal ke `public/`; URL tetap `/login`, bukan `/public/login`. `public/index.php` dan `BASE_PATH` tidak perlu diubah. `RewriteBase /` di file public dihapus supaya target relatif dapat bekerja pada kedua struktur. Lihat [aturan RewriteBase Apache](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html#rewritebase).

Folder internal, hidden paths, file konfigurasi dan backup diblokir sebelum rewrite. File desain di root `assets/` tidak dilayani; URL `/assets/...` mengambil file dari `public/assets/`. Tidak ada pengecualian `!-f`/`!-d` di file root yang dapat membuka source repository. Apabila mod_rewrite tidak tersedia, akses ditolak. Header nosniff/SAMEORIGIN ditambahkan; aturan admin dari website lama tidak disalin.

Konfigurasi utama di bawah (document root langsung `public/`) tetap direkomendasikan. Fallback tidak menjamin menyelesaikan setiap 403: periksa log hosting, ownership, dan aturan folder induk. Jangan menghapus proteksi folder internal untuk mengatasi 403. Uji di hosting: `/`, `/login`, CSS/JS, route dengan query, POST login; lalu `/.env`, `/.git/config`, `/config/config.php`, `/database/`, `/vendor/` harus ditolak. Jangan mengunggah dump database ke area publik. Apache hosting belum diuji dari lingkungan lokal ini.

- Repository: `/public_html/subdomain/erp/`.
- Document root **khusus subdomain** `erp.sekolahzivanamontessori.sch.id`: `/public_html/subdomain/erp/public/`.
- Konfigurasi ERP: `/public_html/subdomain/erp/.env`, bukan `.env` aplikasi utama dan bukan di dalam `public/`.
- File routing yang disertakan repo: `public/.htaccess`. Jangan menyalinnya ke document root domain utama/admin lama. Tidak membutuhkan entry point perantara; `public/index.php` tetap tidak diubah dan `BASE_PATH` tetap kosong.
- Isi `.env` ERP: `APP_URL=https://erp.sekolahzivanamontessori.sch.id`, `APP_ENV=production`, `APP_DEBUG=false`, serta `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` khusus ERP. Jangan commit kredensial asli ke `.env.example`.
- Aktifkan sertifikat subdomain dan Force HTTPS Redirect di cPanel. Tidak ditambahkan redirect HTTPS berbasis hostname ke file lokal agar localhost tidak ikut dipaksa HTTPS.
- Tidak ada aturan `/admin` atau redirect ke subdomain admin di ERP; halaman login ERP adalah `/login`.

`.htaccess` memakai front controller Apache: file/direktori nyata tidak ditulis ulang; route aplikasi menuju `index.php` dengan query string asli. Directory listing dan MultiViews dinonaktifkan. Hidden paths diblokir kecuali `.well-known` untuk sertifikat. Halaman error Apache memakai status asli, bukan semuanya disamarkan menjadi halaman 404. Referensi: [Apache rewrite/remapping](https://httpd.apache.org/docs/2.4/rewrite/remapping.html), [mod_dir](https://httpd.apache.org/docs/2.4/mod/mod_dir.html).

Hosting perlu mengizinkan `mod_rewrite`, direktif `Options`, `DirectoryIndex`, dan `Require` dalam `.htaccess` (Apache 2.4 atau kompatibel). Bila muncul 500, periksa cPanel Errors untuk direktif yang ditolak; jangan langsung mengganti dengan `.htaccess` aplikasi lama. Periksa juga aturan `.htaccess` induk bila ada di `public_html`/`subdomain` yang mungkin memengaruhi ERP.

Setelah upload, uji `/`, `/login`, aset CSS/JS, `/murid?q=demo` setelah login, POST simpan, dan URL tak dikenal (404). Pastikan `/.env`/`/.git/config` ditolak dan `/assets/` tidak menampilkan daftar file. Pengujian dengan `php -S` tidak memverifikasi `.htaccess`; verifikasi rewrite harus di Apache/hosting. Akun demo/password contoh tidak boleh dibiarkan aktif pada layanan publik.

Proses ini mengikuti pola yang sudah terbukti dipakai di `zivanamontdev-php-repo`.

---

## 1. Prinsip Dasar

- Target hosting adalah **shared hosting** (cPanel), bukan VPS — tidak ada akses SSH untuk menjalankan `composer install` di server
- Karena itu, **`vendor/` (dependency Composer) di-generate secara LOKAL, lalu ikut di-commit ke git**. Ini mengapa `vendor/` **tidak boleh** masuk `.gitignore`
- `composer.lock` justru **DI-gitignore** (kebalikan dari praktik umum) — karena `vendor/` sudah final di-commit duluan, `composer.lock` di working copy developer lain berpotensi konflik. `[Ikuti pola ini sesuai repo referensi, konsisten dengan keputusan tim Zivana sebelumnya]`

---

## 2. Sebelum Commit (Lokal)

Jalankan sebelum setiap commit yang mengubah dependency:

```bash
composer install --no-dev --no-scripts --optimize-autoloader
```

Ini regenerate `vendor/` dengan autoloader yang dioptimasi untuk produksi (tanpa dev-dependency seperti testing tools). Hasilnya yang di-commit, bukan dijalankan di server.

**Catatan:** sesuai instruksi user, commit dan push **pertama** untuk repo zivana-erp dilakukan manual oleh user sendiri — dokumen `cookbook/` ini tidak melakukan operasi git apapun.

---

## 3. Struktur Document Root

Document root cPanel **direkomendasikan** mengarah ke folder `public/`. Jika hosting masih menunjuk root repo, gunakan fallback dua `.htaccess` yang dijelaskan di atas:

```
public_html/                  ← document root domain utama TIDAK di sini
zivana-erp/                   ← seluruh repo di-upload/clone ke sini (di luar public_html)
├── app/                      ← TIDAK bisa diakses langsung dari browser
├── config/
├── database/
├── routes/
├── vendor/
└── public/                   ← document root SEBENARNYA diarahkan ke sini
    ├── index.php
    └── assets/
```

Kalau cPanel tidak mendukung document root custom di luar `public_html`, alternatifnya: upload seluruh repo ke `public_html/zivana-erp/` lalu set document root domain ke `public_html/zivana-erp/public/` lewat menu "Domains" di cPanel (bukan symlink manual, supaya tidak rentan salah konfigurasi).

---

## 4. Langkah Deploy

Untuk revisi RBAC 23 September 2026, setelah schema tersedia jalankan `php database/migrations/20260923_granular_permissions.php` sebelum mengaktifkan kode terbaru. Migrasi ini diperlukan juga pada instalasi baru; lihat `rbac-verification.md`. Jangan memakai import ulang schema sebagai pengganti migrasi pada database yang sudah berisi data.

1. **Upload kode**: via Git deploy cPanel (kalau tersedia) atau upload manual/FTP seluruh isi repo (termasuk `vendor/`) ke server
2. **Setup environment**: copy `.env.example` → `.env` di server, isi kredensial produksi manual (DB, SMTP, dsb) — **jangan** commit `.env` berisi kredensial asli ke git
3. **Import database**: import `database/schema.sql` via phpMyAdmin (atau tool DB management cPanel lain)
4. **Set document root**: arahkan ke `public/` sesuai poin 3
5. **Set permission**: folder `public/uploads/` (kalau dipakai untuk upload lokal) perlu permission `755`
6. **Verifikasi**: pastikan `APP_DEBUG=false` di `.env` produksi, dan tidak ada file debug/diagnostic ikut ter-upload ke `public/` (lihat checklist `security.md`)
7. **Test smoke**: login dengan salah satu akun tiap role (Superadmin/Admin/Koordinator Guru/Guru), pastikan RBAC membatasi akses sesuai matriks

---

## 5. Environment Variable yang Dibutuhkan

Berdasarkan pola `.env.example` repo referensi, disesuaikan untuk zivana-erp (tanpa value asli — isi manual di server):

```
# Database
DB_HOST=
DB_PORT=
DB_NAME=
DB_USER=
DB_PASS=

# Aplikasi
APP_NAME="Zivana ERP"
APP_URL=
APP_ENV=production
APP_DEBUG=false
SESSION_LIFETIME=

# Upload (kalau dipakai)
MAX_UPLOAD_SIZE=
ALLOWED_IMAGE_TYPES=

# Keamanan
CSRF_TOKEN_NAME=

# Email (untuk fitur reset password)
SMTP_HOST=
SMTP_PORT=
SMTP_USER=
SMTP_PASS=
SMTP_FROM_EMAIL=

# PDF Generation
# [ASUMSI - tergantung library yang dipilih di architecture.md, umumnya tidak butuh env khusus kecuali path font/temp storage]
```

`[ASUMSI]` Variabel R2 (`R2_ENABLED`, `R2_ACCESS_KEY_ID`, dst) **tidak dimasukkan** dulu karena belum ada fitur upload file terkonfirmasi di scope saat ini — lihat `architecture.md` poin 5. Tambahkan kalau nanti dibutuhkan.

---

## 6. Perbedaan Environment

Ikuti pola repo referensi yang memisahkan `.env.production` dari `.env.example` sebagai template khusus nilai produksi (isinya tetap tidak boleh berisi kredensial asli di git — hanya struktur/komentar tambahan kalau perlu perbedaan konfigurasi non-sensitif antara lokal dan produksi, mis. `APP_ENV`).

---

## 7. Checklist Sebelum Go-Live

- [ ] `vendor/` sudah di-commit lengkap (cek dengan `git ls-files vendor | wc -l` di lokal sebelum push)
- [ ] `.env` sudah diisi kredensial produksi, `.env` tidak ter-commit ke git
- [ ] Document root mengarah ke `public/`, bukan root repo
- [ ] `database/schema.sql` sudah di-import
- [ ] Checklist keamanan di `security.md` bagian 10 sudah dicek semua
- [ ] Font Plus Jakarta Sans termuat dengan benar (cek koneksi ke Google Fonts kalau tidak self-host — lihat `architecture.md` poin 6)
- [ ] Generate PDF (Simpan PDF di Pratinjau Template/Rapor) sudah dites di environment produksi — library PDF kadang butuh extension PHP tambahan (GD/mbstring) yang belum tentu aktif default di shared hosting, cek dulu sebelum go-live
