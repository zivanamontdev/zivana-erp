# API sesi rapor baru — belum aktif secara default

Endpoint ini berbeda dari `/portal-guru/rapor/{id}` legacy. `{id}` adalah ID `erapor_sesi`, bukan ID rapor lama. Tidak ada konversi ID otomatis.

Aktivasi eksplisit melalui `.env`: `ERAPOR_API_ENABLED=true`. Default false. Flag ini membuka cabang dashboard/sesi Portal Guru yang baru dan API di bawah; jangan aktifkan sebagai rilis produksi sebelum backup, migrasi/seed terverifikasi, penugasan approver, editor nilai, dan uji browser siap. Ketika belum aktif, API JSON mengembalikan 503 `ERAPOR_NOT_ENABLED`, halaman sesi dan approval baru tidak tersedia (route approval HTML mengembalikan 404), dan dashboard/route legacy tetap bekerja.

## Keputusan cakupan dan pembekuan dokumen (25 September 2026)

- BING memakai teks dan definisi pada seed V1 apa adanya untuk pilot. Perubahan copy di masa mendatang harus menjadi versi rubrik baru, bukan mutasi seed atau PDF lama.
- Narasi Agama memakai `AGAMA_NARASI_V1`. Versinya dicatat pada manifest; redaksi, enam isian, dan PDF ikut tercakup dalam hash sumber. Perubahan narasi hanya berlaku untuk publikasi baru.
- Tabel tes Ummi selalu dicetak dengan minimal dua baris; sel tanpa nilai berisi `—` sebagai placeholder cetak, tidak sebagai data. Semua tes yang tercatat tetap ditampilkan.
- RAS/Akhir Semester dan alur Hasil Capaian PPI setelah penerbitan ditahan dari cakupan pilot. Paket ABK tetap memuat dokumen PPI awal dengan Hasil Capaian kosong. Pengiriman ke orang tua juga belum aktif.

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

Antrean persetujuan HTML (flag aktif, login, permission `eRapor > Persetujuan`, dan assignment aktif untuk pengguna yang sama):

| Metode dan path | Izin | Fungsi |
|---|---|---|
| GET `/erapor/persetujuan` | lihat | Antrean sesi `MENUNGGU_TTD` yang ditugaskan langsung |
| GET `/erapor/persetujuan/{sessionId}/{approvalId}` | lihat | Tinjauan baca-saja atas dokumen yang tercakup pada baris persetujuan sesi |
| POST `/erapor/persetujuan/{sessionId}/{approvalId}/setujui` | edit + CSRF form | Catat persetujuan/snapshot; tahap terakhir menyiapkan artefak PDF privat Tengah, tetapi belum mengirim atau menyelesaikan sesi |
| GET `/erapor/persetujuan/penugasan` | `eRapor > Penugasan Penyetuju` lihat | Kelola assignment eksplisit tiga tahap; akun eligible dan assignment lama noneligible ditampilkan |
| POST `/erapor/persetujuan/penugasan` | `eRapor > Penugasan Penyetuju` edit + CSRF form | Simpan akun terpilih per tahap, wajib alasan audit; sedikitnya satu akun valid per tahap |
| GET `/erapor/profil-penandatangan` | `eRapor > Profil Penandatangan` lihat | Status NUPTK/consent dan preview privat milik sendiri |
| POST `/erapor/profil-penandatangan` | `eRapor > Profil Penandatangan` edit + CSRF multipart | Update NUPTK dan/atau unggah PNG dengan consent eksplisit |
| POST `/erapor/profil-penandatangan/cabut` | `eRapor > Profil Penandatangan` edit + CSRF | Cabut pemakaian tanda tangan untuk snapshot mendatang |
| GET `/erapor/profil-penandatangan/tanda-tangan` | `eRapor > Profil Penandatangan` lihat | Stream PNG privat pemilik saja, no-store |

Route ini terpisah dari permission `Murid > Rapor Murid` legacy. Role saja maupun assignment saja tidak cukup. Tahap kepala sekolah tetap mensyaratkan jabatan Kepala Sekolah aktif; cakupan dokumen berasal dari snapshot sesi, dan POST service kembali memvalidasi urutan, scope, status, signature evidence, serta assignment saat ini. Halaman reviewer tidak menyertakan signature bytes, memakai no-store/noindex, dan menampilkan modal konfirmasi sebelum aksi permanen.

Konfigurasi tetap dan cakupan disediakan oleh `database/seeds/20260925_erapor_approval_setup.sql`, setelah migrasi flow dan seed rubrik resmi. Seed idempotent, tidak mengubah flow yang sudah ada, dan tidak membuat assignment user. Jika menambah versi rubrik UMMI/BING, jalankan ulang seed untuk melengkapi mapping. Jalankan juga migrasi additive `20260925_erapor_approval_assignment_audit.sql`. Perubahan assignment dicatat di `erapor_penugasan_penyetuju_audit`; assignment yang dicabut berhenti mengakses pekerjaan pending, sedangkan bukti approval terdahulu tidak berubah.

Dashboard baru hanya mengeluarkan tombol Isi Rapor jika kalender valid dan semua rubrik paket siap. RAS yang belum tersedia memblokir paket AKHIR tanpa membuat data parsial. Editor sesi mendukung autosave untuk RTS, Agama, BING, PPI, dan Ummi. Form Ummi baru menampilkan nilai setelah tindakan eksplisit “Mulai pengisian Ummi”; GET tidak membuat baris `erapor_ummi_periode`. Setelah inisialisasi, guru dapat mengisi 27 materi bacaan (opsional), sakelar PRA TK, tes dinamis, dan catatan guru (wajib). Jangan aktifkan flag production sebelum uji browser, seluruh rubrik dan alur penutupan diverifikasi.

Profil penandatangan menyimpan NUPTK opsional dan PNG privat pada extension akun pegawai. Upload dibatasi 2 MB, 4096x2048 dan 4 megapiksel; PHP GD mendekode lalu mengodekan ulang gambar sebelum disimpan. Consent pemilik wajib eksplisit setiap upload baru. NUPTK dapat diedit tanpa mengganti gambar; pencabutan menghapus gambar/consent aktif tetapi mempertahankan audit hash serta snapshot historis. Hosting harus mengaktifkan ekstensi GD. Route stream hanya mengirim gambar akun login dan melarang cache publik.

Approval Kepala Sekolah terakhir menyiapkan PDF paket Tengah secara sinkron di transaksi approval yang sama. Artefak disimpan privat pada `erapor_publikasi_pdf` bersama manifest, hash sumber/PDF, ukuran, versi renderer, dan tanggal pengesahan. Kegagalan renderer/insert me-rollback approval terakhir beserta snapshot dan auditnya; retry memverifikasi lalu menggunakan kembali artefak yang sama. Paket Akhir belum didukung karena RAS belum tersedia. Belum ada route unduh, cetak ulang, email, atau WhatsApp; status sesi tetap `MENUNGGU_TTD` dan status kirim `SIAP_DIKIRIM`. Tabel ini bukan izin untuk membuka BLOB melalui route generik.

Wajib cookie session login aktif; tidak melakukan remember-login otomatis pada API. Actor diambil dari session, bukan body. Layanan internal tetap memeriksa penugasan guru/murid/kelas aktif. JSON API belum menyediakan pembuatan sesi, perpanjangan, atau PDF; provisioning sesi, profil, dan approval memakai rute HTML terproteksi di atas.

Penutupan di editor memakai dua aksi berurutan yang berbeda. `BELUM_DIISI` menawarkan **Selesaikan Rapor** (validasi lengkap, sesi tetap dapat diedit); setelah itu `TELAH_DIISI` menawarkan **Konfirmasi Penerimaan** (membuat snapshot/alur approval dan mengunci sesi). Tombol menggunakan komponen UI bersama dan capability read-model. Status `MENUNGGU_TTD`/`SELESAI` tidak menawarkan aksi guru. Konfirmasi penerimaan menampilkan peringatan bahwa penguncian tidak dapat dibatalkan.

POST wajib `Content-Type: application/json` dan header `X-CSRF-Token`. Token aplikasi sekali pakai: gunakan `csrf_token` baru dari setiap respons sebelum request mutasi berikutnya. Antrean autosave harus serial untuk session browser yang sama; jangan mengirim beberapa request paralel dengan token sama. Respons memakai `Cache-Control: no-store`; frontend fetch harus memakai same-origin credentials dan tidak mencatat data rapor/token ke log.

Body maksimal 1 MiB, depth JSON 32, changes array maksimal 200; batas khusus tiap rubrik tetap diberlakukan service. Field asing ditolak termasuk actor_id, clock, complete, status, type. Dispatch rubrik ditentukan dari dokumen yang benar-benar menjadi anggota sesi terotorisasi. Route pembentukan sesi adalah POST form biasa, dilindungi CSRF global, RBAC dan validasi assignment di factory.

- RTS: `{"indikator_id":1,"nilai":2,"expected":null}`. Reader mengembalikan key `nilai:<id>`; frontend mengadaptasi ke indikator_id.
- BING/Agama/PPI/Ummi: `{"key":"catatan","value":"Teks","expected":null}` dengan key sesuai read model.
- Ummi `changes:[]` adalah write inisialisasi yang diaudit, bukan GET. Jangan lakukan pada form terkunci; nilai flag yang belum diinisialisasi tetap NULL.
- Isian khusus Ummi: `bacaan:<material_id>` menerima kode skala huruf atau `null`; `mulai_pra_tk` menerima boolean dan expected boolean; `tes:<32 hex>` menerima objek lengkap `{urutan,tanggal_tes,jilid,nilai}` atau `null` untuk menghapus. Tes baru memakai token acak klien yang sama saat retry.
- expected memakai nilai terakhir yang dibaca untuk mendeteksi stale write. Nilai baru boleh NULL untuk mengosongkan sesuai aturan layanan.

Sukses: `{"ok":true,"data":{...},"csrf_token":"..."}`. Respons autosave menyertakan `completion`, `capabilities`, dan `session` yang dihitung ulang server-side; UI tidak boleh memutuskan sendiri apakah rapor siap dikirim. Incomplete confirmation merupakan hasil domain sukses dengan `data.result=incomplete`, bukan transisi status; UI harus membaca hasil dan daftar isian kosong.

Error: `{"ok":false,"error":{"code":"...","message":"..."},"csrf_token":"..."}`. Status 401 login/akun tidak aktif, 403 RBAC, 419 CSRF, 422 bentuk payload/ID, 409 penolakan domain (akses sesi, status, stale value, isian), 503 belum diaktifkan/layanan gagal. Detail penolakan domain digeneralisasi untuk tidak membocorkan data murid lain. Respons/log tidak membawa SQL, kredensial, gambar tanda tangan atau stack trace. UI harus memuat ulang form untuk melihat status/kelengkapan terkini pada 409.

## Verifikasi dan batasan

`tests/erapor-api-adapter.php`: 25 skenario controller JSON/RBAC/CSRF di subprocess CLI dengan SQLite terisolasi dan service spies. `tests/erapor-catalog-mysql.php --run --backup=<backup.sql>` juga menjalankan loopback HTTP nyata terhadap database restore disposable: dashboard read-only, sesi dibuat melalui POST, pembukaan ID sesi baru, login, GET sesi, autosave RTS/Agama/BING/PPI/Ummi, inisialisasi Ummi eksplisit, pencatatan/penghapusan tes dinamis, completion otoritatif, CSRF sekali pakai, dokumen asing, payload invalid, transisi salah, retry penerimaan terotorisasi, kepemilikan guru lain, inbox/review approval, pembatasan scope, retry persetujuan, CSRF dan RBAC lihat/edit. Harness berakhir dengan pemeriksaan checksum semua tabel legacy. `tests/erapor-session-ui.php` memeriksa markup form, dan `tests/erapor-approval-ui.php` markup inbox/reviewer/modal. `tests/route-rbac-regression.php` juga memastikan route approval 404 saat flag mati. Regresi akun, portal guru dan workflow legacy tetap wajib lulus.

Uji HTTP E2E di atas bukan uji browser visual atau tinjauan visual PDF. Harness MySQL disposable menguji renderer/publikasi, rollback atomik, hash artefak, dan retry; migrasi belum dijalankan pada database aplikasi. Feature flag tetap opt-in dan PDF belum dapat diunduh atau dikirim.
