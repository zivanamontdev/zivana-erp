# Deploy — Zivana ERP (Shared Hosting)

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

Document root cPanel **wajib** diarahkan ke folder `public/`, **bukan** ke root repo:

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
