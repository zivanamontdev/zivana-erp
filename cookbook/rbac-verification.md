# Verifikasi login dan RBAC — 23 September 2026

## Implementasi dan cakupan izin

`Lihat` mencakup daftar, pencarian/filter, detail, dan pratinjau dalam menu yang sama. Aksi tulis membutuhkan `Lihat` serta checkbox aksi terkait. Kirim Rapor juga membutuhkan Isi & Simpan Rapor karena endpoint pengiriman menyimpan isian terakhir.

| Menu | Checkbox aksi di luar Lihat |
| --- | --- |
| Data Sekolah | Ubah Data, Perbarui Tahun Ajaran |
| Manajemen Rapor | Simpan PDF |
| Periode Rapor | Tambah, Ubah Data, Hapus |
| Daftar Karyawan | Tambah, Ubah Data, Hapus, Aktifkan/Nonaktifkan, Ubah Kata Sandi |
| Jabatan | Tambah, Ubah Data, Hapus, Aktifkan/Nonaktifkan |
| Manajemen Guru | Atur Anak Murid |
| Manajemen Murid | Tambah, Ubah Data |
| Manajemen Kelas | Tambah, Ubah Data, Hapus, Atur Anak Murid (termasuk melepas penugasan) |
| Rapor Murid | Setujui Rapor, Simpan PDF |
| Portal Guru — Dashboard | Tidak ada aksi tulis dashboard; tautan rapor memeriksa izin Daftar Murid |
| Portal Guru — Daftar Murid | Isi & Simpan Rapor, Kirim Rapor untuk Persetujuan |
| RBAC | Simpan Hak Akses |

PDF khusus Portal Guru kini tersedia melalui `/portal-guru/rapor/{id}/pdf`, dengan checkbox Simpan PDF dan guard kepemilikan yang sama dengan pratinjau. Fitur Import Murid/Tambah Role belum berfungsi dan bukan izin operasional baru.

Batas bisnis tetap berlaku di samping checkbox: role Guru atau karyawan dengan jabatan Guru Kelas/Guru Shadow hanya dapat mengakses Portal Guru. Kepala Sekolah/Admin dengan izin terkait dapat menyetujui rapor; akun Superadmin tanpa jabatan tetap mengikuti pengecualian sistem yang sudah ada. Pemilih checkbox role Guru hanya menampilkan Portal Guru. Sidebar dan route memakai pemeriksa izin yang sama.

Perubahan izin saat ini dibaca dari database oleh middleware; tidak diperlukan mekanisme pembaruan real-time tambahan. Perubahan role akun karena perubahan jabatan dibaca pada login berikutnya. Pengujian pengguna sebaiknya dilakukan setelah logout/login ulang.

## Perbaikan

- Akun baru memakai password hash, identitas karyawan, dan role dari jabatan aktif yang valid.
- Email duplikat ditolak sebelum membuat karyawan sehingga tidak menghasilkan akun yatim atau error database pada alur normal.
- Perubahan role jabatan disinkronkan secara transaksional ke akun karyawannya; remember token akun terkait dibersihkan.
- Login mengarah ke menu pertama yang diizinkan; akun tanpa akses mendapat halaman penjelasan dengan tautan keluar, bukan putaran redirect login.
- Akun/karyawan nonaktif tidak lolos login biasa, remember login, atau pemeriksaan sesi aktif.
- Halaman RBAC lihat-saja menonaktifkan checkbox dan menyembunyikan tombol Simpan. ID role/permission tidak valid tidak menghapus izin lama.
- Tombol/modal fitur granular memeriksa izin yang sama dengan handler backend; aksi Edit tanpa Lihat ditolak.
- Checkbox parent memperbarui state parent anak dan tidak mengubah checkbox disabled.

## Bukti pengujian

- `php tests/account-rbac-regression.php`: controller asli, hash password asli, SQL RBAC asli; database SQLite in-memory, redirect/render ditangkap harness. Lulus empat jabatan, login/landing, pembatasan guru walau diberi grant admin, password lemah/lama, pergantian password, duplikasi email, aktif/nonaktif, hapus akun, sinkronisasi role jabatan, validasi penyimpanan RBAC, lihat-saja, dan pemisahan tombol/modal karyawan/jabatan/kelas/periode per izin tunggal.
- `php tests/account-rbac-regression.php deny`: middleware asli menolak POST tambah karyawan dengan 403; tidak ada karyawan dibuat.
- `php tests/account-rbac-regression.php csrf`: constructor controller asli menolak token salah dengan 419; tidak ada karyawan dibuat.
- `php tests/route-rbac-regression.php`: 51 route privat GET/POST melalui Router dan middleware asli dalam subprocess; role terautentikasi tanpa izin selalu 403. Ini bukan HTTP/browser E2E.
- Seluruh tes regresi lama di `tests/` selain fixture server juga lulus: assignment, penghapusan, form murid, alur semester/rapor, guard pratinjau, daftar guru, dan render template/periode.
- Lint PHP dan pemeriksaan sintaks JavaScript dijalankan. **Belum ada verifikasi visual/interaksi browser untuk perubahan ini.** Lingkungan sebelumnya menolak peluncuran browser headless; tes render tidak dianggap pengganti pengujian browser.

Tes menggunakan akun/database fixture, tidak mengganti password atau permission akun pengguna nyata demi tes. Perubahan database lokal aplikasi hanya migrasi penambahan izin yang mempertahankan grant lama.

## Migrasi

Jalankan setelah import schema atau pada database yang sudah ada, sebelum memakai kode RBAC terbaru:

```sh
php database/migrations/20260923_granular_permissions.php
```

Migrasi mengubah `permissions.aksi` menjadi VARCHAR dan menambahkan 17 izin. Grant awal disalin dari izin Lihat/Edit sebelumnya hanya ketika izin baru dibuat. Pengulangan tidak memberi ulang izin yang sudah dicabut. Database lokal: eksekusi pertama menambah 17, kedua menambah 0. Jangan menjalankan ulang seluruh `schema.sql` pada database berisi data hanya untuk perubahan ini.

## Belum dinyatakan selesai

### Pembaruan lanjutan Portal Guru

- Smoke test HTTP pada aplikasi lokal memakai akun uji Sari: login menuju dashboard 200, daftar murid 200, Karyawan/RBAC/Rapor Murid admin 403. Detail murid ampuan, form, pratinjau, dan PDF berhasil 200 (PDF mengembalikan `application/pdf`). Ini bukan pemeriksaan visual browser.
- `tests/teacher-portal-regression.php` lulus: agenda milik guru, periode lampau/aktif/berikutnya/kosong, akses detail/pratinjau lintas guru, empat semester/tipe, validasi item/skala/area, draft simpan/hapus nilai, rollback pengiriman belum lengkap, kirim lengkap, pengiriman ulang, dan pemutusan akses draft setelah penugasan dilepas. Database terisolasi; nilai murid aplikasi tidak diubah.
- `tests/report-entry-ui.js` lulus untuk progres, tombol kirim desktop/mobile, pengosongan pilihan, dan peringatan perubahan belum disimpan. Pengujian memakai DOM stub, bukan browser layout.
- Route privat bertambah menjadi 53 dan semuanya lulus tes penolakan role tanpa izin. Migrasi katalog kini berisi 18 izin tambahan; eksekusi lanjutan menambah 1 izin PDF Guru dengan mempertahankan grant sebelumnya.
- Template yang kosong diberi pesan eksplisit dan tidak dapat dikirim. Tahun ajaran aktif maupun isi kurikulum tidak diganti untuk membuat dashboard terlihat berisi.

### Sisa verifikasi

- Login dan matriks RBAC melalui browser nyata (desktop/mobile), termasuk interaksi checkbox tri-state dan cookie remember-login lintas request.
- Audit positif seluruh aksi melalui HTTP/database MySQL; tes semua route di atas merupakan tes penolakan, bukan pembuktian semua fitur berhasil.
- Alur lengkap penugasan → isi → kirim → persetujuan → PDF, kepemilikan lintas guru, serta kesesuaian UI Portal Guru dengan aset desktop/mobile. Pekerjaan Portal Guru tetap mengikuti gerbang A–C pada todo.
- Kebijakan edit sesudah kirim belum dikonfirmasi pengguna; perilaku lama terkunci tetap dipertahankan.
