# Data dummy untuk uji UI

Untuk uji browser visual **eRapor baru**, ikuti [panduan uji visual eRapor](erapor-visual-testing.md). Akun dan rapor legacy di bawah ini tidak otomatis berarti sesi eRapor baru sudah dibuat.

Data sudah diterapkan pada database lokal. Semua entitas contoh diberi penanda `[DEMO]` atau email `@demo.zivana.test`. Data sekolah, akun asli, hak akses role, tahun aktif, template resmi, dan nilai asli tidak diganti. Seeder memeriksa checksum seluruh baris yang sudah ada sebelum commit.

## Akun

Buka http://localhost:8000/login. Password awal semua akun berikut: `DemoZivana123$`.

| Email | Kegunaan |
| --- | --- |
| `superadmin@demo.zivana.test` | Semua menu termasuk RBAC; akun standalone tanpa jabatan tambahan |
| `admin@demo.zivana.test` | Administrasi dan persetujuan sesuai izin Admin yang ada; RBAC saat ini tidak diberikan |
| `kepala@demo.zivana.test` | Kepala Sekolah; persetujuan rapor |
| `guru-a@demo.zivana.test` | Guru Kelas, empat murid di beberapa kelas |
| `guru-b@demo.zivana.test` | Guru Shadow, empat murid termasuk ABK |
| `guru-kosong@demo.zivana.test` | Guru tanpa murid untuk empty state |
| `nonaktif@demo.zivana.test` | Login harus ditolak |

Password ini khusus data dummy lokal, bukan untuk deployment publik. Hak akses mengikuti konfigurasi role yang sudah ada. Perubahan checkbox pada role bersama juga memengaruhi akun non-demo pada role itu: jangan sembarang mengubah RBAC jika akun asli sedang digunakan.

## Data dan urutan pembuatan

1. Gunakan role/jabatan dan tahun aktif 2026/2027 yang sudah tersedia.
2. Tambah enam karyawan beserta akun, dan satu akun Superadmin standalone.
3. Tambah empat kelas: Akar Melati, Batang Kenanga, Ranting Akasia, Daun Kosong (semuanya `[DEMO]`).
4. Tambah 13 murid: Regular/ABK, Bersekolah/Tanpa Keterangan/Tamat/Berhenti, tanpa guru dan tanpa kelas; lalu delapan penugasan guru unik.
5. Tambah dua template **custom dummy**, Tengah dan Akhir Semester, masing-masing 80 item dalam empat area. Ini bukan kurikulum resmi.
6. Tambah empat periode di tahun aktif (Ganjil/Genap × Tengah/Akhir), empat sesi demo, dan satu sesi riwayat demo pada periode terakhir tahun 2025/2026. Periode/sesi lama tidak diubah.
7. Tambah 45 rapor khusus murid demo, nilai dan catatan yang sesuai template/semester. Kondisi awal: 33 draft (dua diisi sebagian), dua menunggu persetujuan, sepuluh disetujui.

Tanggal periode demo saat pertama dibuat relatif terhadap hari seeding: periode berjalan mulai tujuh hari sebelumnya hingga 14 hari sesudahnya; periode berikutnya mulai 45, 120, dan 180 hari sesudahnya. Menjalankan ulang tidak menggeser tanggal atau mereset hasil uji.

## Urutan uji manual

1. Login **guru-a**, buka Dashboard: Alya kosong, Bima draft sebagian (20/80), Citra menunggu persetujuan, Daffa disetujui. Klik Isi Rapor untuk Alya/Bima, coba simpan, lanjutkan seluruh nilai, lalu kirim. Rapor yang dikirim terkunci.
2. Login **kepala** atau **admin**, buka Rapor Murid, pilih **2026/2027**. Buka Citra atau rapor baru yang dikirim lalu Setujui. Draft belum boleh dibuka dari modul admin.
3. Kembali sebagai **guru-a**, buka Lihat Rapor dan Simpan PDF. Pilih sesi riwayat pada dashboard untuk melihat rapor terdahulu.
4. Login **guru-b** untuk daftar murid berbeda. Akun ini tidak boleh membuka rapor/detail milik guru-a.
5. Login **superadmin** untuk tabel, filter, detail, modal, dan RBAC. Cek Manajemen Guru: masing-masing guru mempunyai daftar sendiri; guru-kosong mempunyai empty state.
6. Di Manajemen Murid, filter berbagai status, kelas, guru, dan cari `[DEMO]`. Intan belum punya guru, Maya belum punya kelas. Uji penugasan pada mereka jika diperlukan.
7. Cek Daun Kosong untuk detail kelas tanpa murid. Gunakan hanya data demo untuk tes hapus/nonaktifkan. Uji login akun nonaktif: harus tetap di halaman login dengan pesan gagal.

Sesi demo menunjuk template custom, bukan template sistem yang masih belum lengkap. Perubahan periode atau pembuatan murid baru melalui aplikasi dapat menjalankan sinkronisasi normal aplikasi; dokumen ini menggambarkan kondisi awal, bukan dataset yang otomatis di-reset.

## Seeder dan bukti pemeriksaan

```sh
php database/seed-ui-demo.php --dry-run
php database/seed-ui-demo.php --apply
```

CLI saja, koneksi database lokal saja. Pembuatan memakai transaksi dan FK tetap aktif. Dry run rollback semua insert (nomor auto-increment bisa melompat). Pengulangan tidak memperbarui data yang sudah ditemukan atau nilai/progres tes. Bukan alat reset/restore setelah pengguna menghapus sebagian data.

Teruji: dry run berhasil; apply berhasil; pengulangan tidak menambah duplikat. Login seluruh akun, halaman admin utama/RBAC Superadmin, dashboard guru, dua form draft dan dua pratinjau guru-a berhasil lewat HTTP. Akun nonaktif ditolak. Ini **bukan** hasil pemeriksaan visual browser. Data tersimpan di database lokal, sehingga `git push` saja tidak memindahkannya ke komputer lain; jalankan seeder pada database lokal tujuan bila dibutuhkan.
