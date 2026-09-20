# Security — Zivana ERP

Dokumen ini disusun dari hasil crawling praktik keamanan di `zivanamontdev-php-repo` (repo native PHP Zivana sebelumnya) — baik yang **layak ditiru** maupun **temuan yang harus diperbaiki**, ditambah kebutuhan baru khusus zivana-erp (RBAC granular).

---

## 1. Autentikasi & Password

- Password di-hash pakai `password_hash()` (bcrypt, `PASSWORD_DEFAULT`) dan diverifikasi dengan `password_verify()` — **ikuti pola ini**, jangan pakai md5/sha1 atau hash custom
- Remember-me ("Ingat Saya" di Login): token disimpan sebagai **hash SHA-256** di kolom `users.remember_token`, bukan plaintext. Cookie di-set `httpOnly` supaya tidak bisa diakses JS
- Reset password ("Lupa kata sandi?"): token dengan expiry (`[ASUMSI]` 1 jam, ikut pola repo referensi), disimpan di tabel terpisah `password_resets`, bukan kolom di tabel `users` — supaya bisa multiple request tanpa saling menimpa
- Ganti password (di modal "Ubah Informasi Karyawan") dipisah dari form edit data lain lewat tombol "Ganti Kata Sandi" tersendiri — pertahankan pola ini supaya update password selalu punya konfirmasi eksplisit, tidak numpang di form update data biasa

---

## 2. CSRF Protection

Repo referensi punya implementasi CSRF (`CSRF_IMPLEMENTATION_PLAN.md`): token UUID v4 disimpan di `$_SESSION['csrf_token']` dengan expiry 1 jam, single-use (regenerate setelah validasi), divalidasi manual di tiap controller lewat pemanggilan `csrf_verify()`.

**Perbaikan untuk zivana-erp:** implementasi manual per-controller riskan lupa di-pasang di satu endpoint. Di zivana-erp, **CSRF wajib di-enforce otomatis** di level `Router` atau `Controller` base class untuk SEMUA route dengan method POST/PUT/DELETE, sehingga tidak bergantung pada disiplin developer memanggil fungsi manual di tiap handler.

```php
// Contoh pola di Controller base class
public function __construct() {
    if ($this->isPost() && !validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('CSRF token tidak valid');
    }
}
```

---

## 3. RBAC (Baru — Tidak Bisa Dicontek dari Repo Lama)

Repo referensi hanya punya role enum sederhana (`admin`/`super_admin` di kolom `users.role`) — **tidak cukup** untuk kebutuhan zivana-erp yang butuh matriks permission granular per modul/section/sub-section/aksi (lihat layar RBAC di `design-system.md` 5.3 dan tabel `roles`/`permissions`/`role_permissions` di `schema.md`).

**Wajib diterapkan server-side, bukan hanya sembunyikan menu di UI:**

```php
// RoleMiddleware — dipanggil di awal controller method
public function handle($modul, $subSection, $aksi = 'lihat') {
    $userRole = Auth::user()->role_id;
    if (!RolePermission::hasAccess($userRole, $modul, $subSection, $aksi)) {
        http_response_code(403);
        $this->view('errors/403');
        exit;
    }
}
```

Menyembunyikan nav item di sidebar untuk role tanpa akses itu perlu (UX), tapi **tidak boleh jadi satu-satunya lapisan proteksi** — user tetap bisa mengakses URL langsung kalau server-side check tidak ada.

**Pemisahan akses Portal Guru vs Admin:** dari matriks RBAC, role Guru hanya punya akses ke modul Portal Guru. Middleware harus menolak akses guru ke route admin (`/murid`, `/karyawan`, dst) walau URL diketik manual — bukan sekadar tidak menampilkan link-nya.

---

## 4. Error Handling

Pola repo referensi: seluruh `$router->dispatch()` dibungkus try-catch di `index.php`.
- `APP_DEBUG=true` (lokal) → tampilkan detail error + stack trace
- `APP_DEBUG=false` (produksi) → generic error message, detail di-log via `error_log()`
- **Wajib** `APP_DEBUG=false` di server produksi — kalau lupa, stack trace yang bocor ke publik bisa membocorkan struktur folder/query database

**Pola khusus yang layak ditiru:** kalau ada pemisahan area admin vs publik (mis. subdomain admin terpisah), request ke rute admin dari domain publik harus selalu return **404** (bukan 500/403) walau terjadi exception — supaya tidak bocor informasi bahwa ada backend admin di domain publik.

---

## 5. Middleware

Minimal middleware yang dibutuhkan (nama mengikuti pola repo referensi + tambahan baru):

| Middleware | Fungsi |
|---|---|
| `AuthMiddleware` | Cek session valid, fallback ke remember-token (hash SHA-256 dicocokkan ke DB), auto-login kalau valid |
| `GuestMiddleware` | Kebalikan — blokir user yang sudah login dari halaman Login |
| `RoleMiddleware` **(baru)** | Cek permission granular sesuai matriks RBAC — lihat poin 3 |

---

## 6. Upload File (Kondisional)

Belum ditemukan field upload file yang eksplisit di screenshot manapun (termasuk foto murid — lihat `prd.md` asumsi #5). **Kalau nanti ada** (logo sekolah, foto murid, lampiran berkas pendaftaran), ikuti pola repo referensi:
- Nama file di-generate ulang (`time() + random_bytes(8) + extension`), **jangan pernah pakai nama file asli dari user** — mencegah collision dan path traversal
- Validasi tipe file dan ukuran maksimum di server-side (jangan andalkan validasi client-side saja), daftar tipe/ukuran disimpan di `.env` (`ALLOWED_IMAGE_TYPES`, `MAX_UPLOAD_SIZE`)
- `[ASUMSI]` Simpan lokal di `public/uploads/` untuk shared hosting kecuali ada kebutuhan storage eksternal (R2) — lihat `architecture.md` poin 5 soal ini ditunda dulu

---

## 7. Konfigurasi & Credential

**Temuan kritis dari repo referensi (JANGAN DITIRU):** ditemukan kredensial database produksi ter-hardcode langsung di source code (`config/config.php`) sebagai fallback kalau `.env` gagal load, dan juga tercantum di `README.md`.

**Aturan wajib untuk zivana-erp:**
- Kredensial (DB, SMTP, dsb) **hanya** boleh berasal dari `.env`, yang **wajib** masuk `.gitignore`
- **Tidak boleh ada fallback hardcoded** apapun di source code — kalau `.env` gagal dibaca, aplikasi harus berhenti dengan error jelas, bukan diam-diam jalan dengan kredensial yang menempel di kode
- `.env.example` (template tanpa value asli) yang di-commit, bukan `.env` itu sendiri

---

## 8. File Debug/Diagnostic

**Temuan dari repo referensi:** banyak file debug tertinggal di folder `public/` (`debug_articles.php`, `diagnostic_admin.php`, `test_r2_*.php`, dll) — ini expose informasi diagnostik ke publik kalau tidak dihapus sebelum deploy, karena `public/` adalah document root yang bisa diakses siapapun.

**Aturan untuk zivana-erp:** jangan taruh file debug/diagnostic di `public/` sama sekali selama development. Kalau butuh alat debug, taruh di luar document root atau proteksi dengan auth check + `APP_DEBUG` guard, dan pastikan dihapus/di-review sebelum setiap deploy ke produksi (masukkan sebagai checklist di `deploy.md`).

---

## 9. Validasi Input Level Database

- Semua query wajib pakai **prepared statement/PDO**, tidak ada string concatenation untuk query — ini bawaan dari `Model` base class (`app/core/Model.php`) di `architecture.md`, pastikan tidak ada controller yang bypass dan menulis raw query sendiri
- Validasi server-side wajib untuk semua field yang ditandai wajib (`*`) di `schema.md`, jangan andalkan validasi HTML5 (`required`) saja karena bisa dilewati lewat request langsung (Postman/curl)

---

## 10. Ringkasan Checklist Sebelum Deploy Produksi

- [ ] `APP_DEBUG=false`
- [ ] Tidak ada kredensial hardcoded di source code
- [ ] Tidak ada file debug/diagnostic di `public/`
- [ ] CSRF ter-enforce di semua route POST/PUT/DELETE
- [ ] RoleMiddleware terpasang di semua route yang butuh permission spesifik, sudah dites dengan akun tiap role (Superadmin/Admin/Koordinator Guru/Guru)
- [ ] `.env` tidak ter-commit ke git (cek `.gitignore`)
- [ ] Semua form wajib punya validasi server-side, bukan hanya client-side
