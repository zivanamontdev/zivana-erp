# Architecture — Zivana ERP (Native PHP)

## Prinsip

Aplikasi ini **native PHP tanpa framework** (bukan Laravel/Symfony/CodeIgniter), mengikuti pola arsitektur yang sudah terbukti dipakai di project Zivana sebelumnya (`zivanamontdev-php-repo`). Target deploy adalah **shared hosting**, sehingga:
- Tidak ada proses build/compile di server (semua PHP native, asset CSS/JS langsung dipakai)
- `vendor/` (dependency Composer) **ikut di-commit ke git**, bukan di-generate di server — shared hosting umumnya tidak menyediakan akses SSH untuk `composer install`
- Tidak ada dependency ke Node.js/npm untuk runtime produksi

Bagian struktur, routing, dan pola MVC di bawah **meniru langsung** pola `zivanamontdev-php-repo` supaya konsisten dengan codebase Zivana lain dan tim tidak perlu belajar dua pola berbeda. Bagian yang murni baru untuk zivana-erp ditandai eksplisit.

---

## 1. Struktur Folder

```
zivana-erp/
├── app/
│   ├── controllers/     # 1 class per resource: SekolahController, MuridController, RaporController, dst
│   ├── core/            # Controller.php, Model.php, Database.php, Router.php — inti "framework" custom
│   ├── helpers/         # functions.php, Security.php, UploadManager.php (kalau perlu upload file)
│   ├── middleware/       # AuthMiddleware, GuestMiddleware, RoleMiddleware (BARU — lihat security.md)
│   ├── models/          # 1 class per tabel: Murid, Kelas, Karyawan, Rapor, dst
│   └── views/
│       ├── layouts/      # app-shell.php (sidebar + page header, dipakai semua halaman kecuali login)
│       ├── admin/        # view per modul: sekolah/, murid/, karyawan/, kurikulum/, rapor/, rbac/
│       ├── portal-guru/  # view khusus Portal Guru (dashboard, pengisian-rapor, dst)
│       ├── auth/         # login.php
│       └── errors/       # 404.php, 500.php
├── config/
│   ├── config.php        # load .env, define() konstanta global
│   └── colors.php        # [OPSIONAL] mapping token warna design-system.md ke PHP constant, kalau dibutuhkan server-side (mis. generate PDF)
├── database/
│   ├── schema.sql         # single file DDL — lihat schema.md
│   └── migrations/        # kalau perlu incremental change setelah schema.sql awal
├── routes/
│   └── web.php            # semua route didaftarkan manual di sini
├── public/                 # DOCUMENT ROOT — hanya folder ini yang boleh diakses langsung dari browser
│   ├── index.php           # front controller tunggal
│   ├── assets/
│   │   ├── css/            # tokens.css (design token dari design-system.md), components.css, app.css
│   │   ├── js/             # vanilla JS per halaman/komponen, tanpa bundler
│   │   ├── icons/           # salinan dari assets/icons/ project ini
│   │   └── fonts/           # [ASUMSI] self-host Plus Jakarta Sans kalau tidak mau bergantung ke Google Fonts CDN saat runtime; kalau tidak, load via <link> ke fonts.googleapis.com
│   └── uploads/             # fallback upload lokal (lihat security.md soal validasi)
├── vendor/                  # Composer dependency — DI-COMMIT (lihat deploy.md)
├── .env.example
├── .gitignore
└── composer.json
```

**Kenapa `public/` dipisah dari root:** supaya `app/`, `config/`, `database/`, `routes/` tidak bisa diakses langsung lewat URL di shared hosting. Document root cPanel diarahkan ke `public/`, bukan ke root repo. Ini pola yang sama dipakai di `zivanamontdev-php-repo` dan **wajib** dipertahankan (lihat temuan keamanan di `security.md` soal file debug yang ketinggalan di `public/` pada repo lama — jangan diulang).

---

## 2. Routing (Front Controller Pattern)

Tidak ada framework routing. Mekanismenya manual, identik dengan `zivanamontdev-php-repo`:

1. **`public/index.php`** — satu-satunya entry point. Tugasnya:
   - Set konstanta path dasar (`ROOT_PATH`, `APP_PATH`, `VIEW_PATH`, dst)
   - Daftarkan autoloader manual via `spl_autoload_register` — cari class berdasarkan nama file persis sama dengan nama class, di folder `core/`, `models/`, `controllers/`, `middleware/`
   - `require` Composer autoload (`vendor/autoload.php`)
   - Load `config/config.php` (baca `.env`, `define()` konstanta)
   - Load `routes/web.php` — daftarkan semua route ke instance `Router`
   - Panggil `$router->dispatch()`
   - Bungkus semuanya dalam try-catch (lihat `security.md` untuk pola error handling)

2. **`routes/web.php`** — semua endpoint didaftarkan eksplisit, contoh pola:
   ```php
   $router->get('/murid', [MuridController::class, 'index']);
   $router->get('/murid/{id}', [MuridController::class, 'show']);
   $router->post('/murid', [MuridController::class, 'store']);
   $router->post('/murid/{id}', [MuridController::class, 'update']);
   $router->post('/murid/{id}/hapus', [MuridController::class, 'destroy']);
   ```

3. **`Router::dispatch()`** — cocokkan `REQUEST_METHOD` + regex dari path (`{param}` jadi capture group), lalu panggil `[ControllerClass, method]` lewat `call_user_func_array`. Tidak ada dependency injection — controller `new` model langsung di constructor-nya sendiri.

---

## 3. Pola MVC Manual

**`Controller` (base class di `app/core/Controller.php`)** menyediakan:
- `view($namaView, $data = [])` — `require` file `.php` dari `VIEW_PATH`, `extract($data)` supaya variabel langsung terpakai di view
- `json($data)` — response JSON (dipakai untuk endpoint AJAX, misal validasi async atau autocomplete dropdown murid)
- `redirect($path)`
- `middleware($class)` — instantiate middleware + panggil method `handle()`-nya, dipanggil manual di awal method controller
- `input()`, `isPost()`, `isGet()`

**`Model` (base class di `app/core/Model.php`)** menyediakan method generik lewat PDO:
- `all()`, `find($id)`, `where($kolom, $nilai)`, `create($data)`, `update($id, $data)`, `delete($id)`, `paginate($page, $perPage)`
- Tiap model turunan cukup override `$table` dan `$primaryKey`, contoh:
  ```php
  class Murid extends Model {
      protected $table = 'murid';
      protected $primaryKey = 'id';
  }
  ```

**View** adalah PHP murni dengan `<?= ?>`, tidak ada template engine (Blade/Twig). Layout shell (`views/layouts/app-shell.php`) di-`include` di setiap view halaman untuk konsistensi sidebar + page header sesuai `design-system.md` bagian 2.

**Contoh alur satu request** (lihat rapor guru sebagai contoh nyata):
```
POST /portal-guru/rapor/{id}/selesaikan
  → routes/web.php cocokkan ke [PengisianRaporController::class, 'selesaikan']
  → Controller: $this->middleware(AuthMiddleware::class); $this->middleware(RoleMiddleware::class, 'guru');
  → validasi input, panggil Model Rapor::update($id, ['status' => 'menunggu_persetujuan'])
  → redirect ke halaman pratinjau
```

---

## 4. Konfigurasi

`config/config.php` membaca `.env` (parser manual, dengan fallback kalau `parse_ini_file` gagal — penting untuk kompatibilitas berbagai versi PHP di shared hosting), lalu `define()` jadi konstanta global (`DB_HOST`, `APP_URL`, `APP_DEBUG`, dst).

**PERINGATAN dari temuan repo referensi (jangan ditiru):** repo lama sempat punya *hardcoded fallback credential* database produksi langsung di `config.php` untuk jaga-jaga kalau `.env` gagal ke-load. Ini celah keamanan serius. **Di zivana-erp, TIDAK BOLEH ada fallback hardcoded apapun** — kalau `.env` gagal load, aplikasi harus fail-fast dengan error jelas, bukan diam-diam pakai kredensial yang nempel di source code.

---

## 5. Dependency (Composer)

Berdasarkan kebutuhan fitur yang teridentifikasi dari crawling:

| Package | Kebutuhan |
|---|---|
| `phpmailer/phpmailer` | Kirim email reset password (fitur "Lupa kata sandi?" di Login) |
| Library PDF generation (mis. `dompdf/dompdf` atau `mpdf/mpdf`) | **BARU** — untuk generate dokumen rapor multi-halaman jadi PDF (tombol "Simpan PDF" di Pratinjau Template dan Pratinjau Rapor Murid). `[ASUMSI — pilih salah satu, dompdf lebih ringan untuk shared hosting, mpdf lebih baik untuk layout kompleks/watermark]` |
| `aws/aws-sdk-php` | **[ASUMSI]** Hanya diperlukan kalau zivana-erp juga pakai Cloudflare R2 untuk upload file seperti repo lama. Karena tidak ditemukan field upload file di modul manapun (termasuk foto murid — lihat prd.md asumsi #5), dependency ini **belum tentu dibutuhkan** di rilis pertama. Pertimbangkan tunda sampai ada fitur upload nyata. |

Tidak ada dependency framework/router — konsisten dengan pola native.

---

## 6. Asset Frontend (tanpa build step)

Karena tidak ada framework JS/bundler:
- `public/assets/css/tokens.css` — seluruh CSS variable dari `design-system.md` bagian 1.1–1.3
- `public/assets/css/components.css` — style komponen (button, input, badge, modal, tabel) sesuai spesifikasi
- `public/assets/js/` — vanilla JS per kebutuhan interaktif: sidebar collapse/expand, submenu toggle, modal open/close, form validasi client-side, checkbox tri-state RBAC, dropdown assign murid (kemungkinan butuh sedikit AJAX untuk search)
- Google Fonts Plus Jakarta Sans di-load via `<link>` tag di layout shell (lihat `design-system.md` 1.2) — **[ASUMSI]** kalau butuh independen dari koneksi eksternal (mis. sekolah dengan internet terbatas), pertimbangkan self-host font file di `public/assets/fonts/`

---

## 7. Konvensi Penamaan

Konsisten dengan `zivanamontdev-php-repo`:
- **Class**: PascalCase, nama file persis sama dengan nama class (`MuridController.php` → `class MuridController`) — ini penting karena autoload manual bergantung pada kecocokan nama file/class
- **Method**: camelCase
- **Route path**: kebab-case/lowercase (`/manajemen-murid`, `/rapor-murid/{id}/pratinjau`)
- **Tabel & kolom database**: snake_case (lihat `schema.md`)

---

## 8. Yang BERBEDA dari Repo Referensi (baru khusus zivana-erp)

| Area | Repo lama | zivana-erp |
|---|---|---|
| RBAC | Role enum sederhana (`admin`/`super_admin`) di kolom `users.role` | Matriks permission granular (modul→section→sub-section→aksi) per 4 role — butuh tabel `roles` + `role_permissions` dan `RoleMiddleware` baru, tidak bisa dicontek dari repo lama |
| CSRF | Manual per-controller (`csrf_verify()` dipanggil satu-satu) | **Enforce otomatis** di level `Router`/`Controller` base class untuk semua route POST, supaya tidak ada endpoint yang lupa diproteksi (lihat `security.md`) |
| File upload/R2 | Selalu pakai R2 kalau `R2_ENABLED` | Ditunda sampai ada fitur upload nyata (belum ditemukan di scope screenshot saat ini) |
| PDF generation | Tidak ada di repo lama | Fitur baru — perlu pilih & integrasikan library PDF |

---

## 9. Daftar Asumsi

1. Nama-nama controller/model spesifik di atas adalah contoh pola penamaan, bukan daftar final — akan disesuaikan saat implementasi per modul di `todo.md`
2. Pemilihan library PDF (dompdf vs mpdf) belum final
3. Kebutuhan `aws-sdk-php`/R2 ditunda sampai ada konfirmasi fitur upload file
4. Self-host font vs load dari Google Fonts CDN belum diputuskan
