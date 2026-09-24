# API sesi rapor baru — belum aktif secara default

Endpoint ini berbeda dari `/portal-guru/rapor/{id}` legacy. `{id}` adalah ID `erapor_sesi`, bukan ID rapor lama. Tidak ada konversi ID otomatis.

Aktivasi eksplisit melalui `.env`: `ERAPOR_API_ENABLED=true`. Default false. Flag ini membuka cabang dashboard/sesi Portal Guru yang baru dan API di bawah; jangan aktifkan sebagai rilis produksi sebelum backup, migrasi/seed terverifikasi, penugasan approver, editor nilai, dan uji browser siap. Ketika belum aktif, API JSON mengembalikan 503 `ERAPOR_NOT_ENABLED`, halaman sesi baru tidak tersedia, dan dashboard/route legacy tetap bekerja.

## Kontrak

| Metode dan path | Izin Portal Guru / Daftar Murid | Body |
|---|---|---|
| GET `/api/erapor/sesi/{id}` | lihat | tidak ada |
| POST `/api/erapor/sesi/{id}/dokumen/{documentId}/simpan` | lihat + edit | `{"changes":[...]}` |
| POST `/api/erapor/sesi/{id}/konfirmasi-isi` | lihat + edit + kirim | `{}` |
| POST `/api/erapor/sesi/{id}/konfirmasi-penerimaan` | lihat + edit + kirim | `{}` |

Route UI tambahan (juga memerlukan flag dan session login):

| Metode dan path | Izin Portal Guru | Fungsi |
|---|---|---|
| GET `/portal-guru/dashboard?periode_id={id}` | Dashboard / lihat | Baca daftar periode/murid; tidak membuat sesi |
| POST `/portal-guru/sesi/siapkan` | Dashboard / lihat + Daftar Murid / edit | Membentuk sesi dengan CSRF; field `murid_id` dan `periode_id` |
| GET `/portal-guru/sesi/{id}` | Dashboard / lihat + Daftar Murid / lihat | Membaca sesi milik guru tersebut |

Dashboard baru hanya mengeluarkan tombol Isi Rapor jika kalender valid dan semua rubrik paket siap. RAS yang belum tersedia memblokir paket AKHIR tanpa membuat data parsial. Editor sesi kini mendukung autosave untuk RTS, Agama, BING, dan PPI. Ummi masih belum bisa diedit di UI karena perlu tindakan inisialisasi periode dan kontrol pencatatan tes khusus. Jangan aktifkan flag pada production sebelum editor Ummi, uji browser, seluruh rubrik dan alur penutupan diverifikasi.

Wajib cookie session login aktif; tidak melakukan remember-login otomatis pada API. Actor diambil dari session, bukan body. Layanan internal tetap memeriksa penugasan guru/murid/kelas aktif. JSON API belum menyediakan pembuatan sesi, approval, perpanjangan, upload profil atau PDF; provisioning sesi hanya tersedia melalui POST form UI yang dicantumkan di atas.

POST wajib `Content-Type: application/json` dan header `X-CSRF-Token`. Token aplikasi sekali pakai: gunakan `csrf_token` baru dari setiap respons sebelum request mutasi berikutnya. Antrean autosave harus serial untuk session browser yang sama; jangan mengirim beberapa request paralel dengan token sama. Respons memakai `Cache-Control: no-store`; frontend fetch harus memakai same-origin credentials dan tidak mencatat data rapor/token ke log.

Body maksimal 1 MiB, depth JSON 32, changes array maksimal 200; batas khusus tiap rubrik tetap diberlakukan service. Field asing ditolak termasuk actor_id, clock, complete, status, type. Dispatch rubrik ditentukan dari dokumen yang benar-benar menjadi anggota sesi terotorisasi. Route pembentukan sesi adalah POST form biasa, dilindungi CSRF global, RBAC dan validasi assignment di factory.

- RTS: `{"indikator_id":1,"nilai":2,"expected":null}`. Reader mengembalikan key `nilai:<id>`; frontend mengadaptasi ke indikator_id.
- BING/Agama/PPI/Ummi: `{"key":"catatan","value":"Teks","expected":null}` dengan key sesuai read model.
- Ummi `changes:[]` adalah write inisialisasi yang diaudit, bukan GET. Jangan lakukan pada form terkunci; nilai flag yang belum diinisialisasi tetap NULL.
- expected memakai nilai terakhir yang dibaca untuk mendeteksi stale write. Nilai baru boleh NULL untuk mengosongkan sesuai aturan layanan.

Sukses: `{"ok":true,"data":{...},"csrf_token":"..."}`. Respons autosave menyertakan `completion`, `capabilities`, dan `session` yang dihitung ulang server-side; UI tidak boleh memutuskan sendiri apakah rapor siap dikirim. Incomplete confirmation merupakan hasil domain sukses dengan `data.result=incomplete`, bukan transisi status; UI harus membaca hasil dan daftar isian kosong.

Error: `{"ok":false,"error":{"code":"...","message":"..."},"csrf_token":"..."}`. Status 401 login/akun tidak aktif, 403 RBAC, 419 CSRF, 422 bentuk payload/ID, 409 penolakan domain (akses sesi, status, stale value, isian), 503 belum diaktifkan/layanan gagal. Detail penolakan domain digeneralisasi untuk tidak membocorkan data murid lain. Respons/log tidak membawa SQL, kredensial, gambar tanda tangan atau stack trace. UI harus memuat ulang form untuk melihat status/kelengkapan terkini pada 409.

## Verifikasi dan batasan

`tests/erapor-api-adapter.php`: 25 skenario controller JSON/RBAC/CSRF di subprocess CLI dengan SQLite terisolasi dan service spies. `tests/erapor-catalog-mysql.php --run --backup=<backup.sql>` juga menjalankan loopback HTTP nyata terhadap database restore disposable: dashboard read-only, sesi dibuat melalui POST, pembukaan ID sesi baru, login, GET sesi, autosave RTS/Agama/BING/PPI, completion otoritatif, CSRF sekali pakai, dokumen asing, payload invalid, transisi salah, kepemilikan guru lain dan pencabutan RBAC termasuk penyembunyian aksi edit di dashboard. Harness berakhir dengan pemeriksaan checksum semua tabel legacy. Total 1112 pemeriksaan MySQL. `tests/route-rbac-regression.php`: 59 private routes ditolak lewat router/middleware nyata bila role tanpa izin. Regresi akun, portal guru dan workflow legacy lulus.

Uji HTTP E2E di atas bukan uji browser visual. Berikutnya: integrasikan editor nilai dan autosave ke halaman sesi, lalu uji browser visual dan aksesibilitasnya. Endpoint ini tetap opt-in dan tidak menerbitkan PDF.
