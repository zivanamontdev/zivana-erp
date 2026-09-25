# Panduan Uji Visual eRapor

Panduan manual untuk sesi yang memiliki akses browser. Ini adalah checklist pengujian, bukan klaim bahwa uji visual sudah dijalankan atau lulus. Catat temuan dan lampirkan screenshot agar bisa ditindaklanjuti.

## Batas cakupan saat ini

- Uji dilakukan di **database dan server lokal**. Jangan mengaktifkan flag atau membuat/mengunci sesi uji di production.
- eRapor baru bersifat opt-in lewat `ERAPOR_API_ENABLED=true`. Jika flag mati, `/portal-guru/dashboard` tetap menampilkan alur lama; hasilnya bukan uji panduan ini.
- Yang diuji: dashboard guru, editor sesi eRapor, antrean/tinjauan persetujuan, penugasan penyetuju, dan profil penandatangan.
- Paket **Tengah Semester** yang siap digunakan: Regular memuat RTS, Agama, Ummi, dan Bahasa Inggris; ABK mendapat tambahan PPI.
- Paket **Akhir Semester/RAS belum tersedia**. Dashboard seharusnya menandai paket belum siap dan tidak menawarkan pembuatan sesi. Jangan menganggap ini sebagai kegagalan visual.
- Hasil Capaian PPI pasca-terbit, unduh/cetak ulang publik, email, dan WhatsApp berada di luar cakupan saat ini.
- `/portal-guru/rapor/{id}` adalah alur rapor lama. Sesi baru menggunakan `/portal-guru/sesi/{id}`; jangan mencampur ID atau menilai halaman lama sebagai UI eRapor baru.

## Persiapan lokal

1. Dari root repo, pastikan skema dan seed sudah lengkap:

   ```sh
   php database/run-erapor-pilot.php --check
   ```

   Perintah ini hanya memeriksa; tidak menulis ke database. Pastikan keluarannya menyatakan 17/17 migrasi, 5 rubrik, dan 3 tahap persetujuan.

2. Di `.env` lokal saja, atur:

   ```dotenv
   ERAPOR_API_ENABLED=true
   ```

   Jangan commit `.env`. Setelah mengganti flag, restart server PHP. Gunakan alamat lokal yang sudah dipakai, misalnya `http://localhost:8000`.

3. Gunakan akun demo lokal yang tercantum di [panduan data dummy](demo-ui-testing.md). Akun demo tidak untuk production. Untuk pengujian utama gunakan `guru-a` (Regular) dan `guru-b` (memiliki murid ABK); gunakan `guru-kosong` untuk empty state.

4. Untuk menampilkan alur persetujuan, beri izin dan tetapkan akun penyetuju **di database lokal** melalui RBAC dan halaman Penugasan Penyetuju. Seed konfigurasi tidak menetapkan akun secara otomatis. Perubahan RBAC pada sebuah role berdampak pada semua akun role itu, jadi jangan lakukan eksperimen ini di production.

## Urutan uji dan hasil yang diharapkan

### 1. Dashboard guru — desktop

Login sebagai `guru-a`, lalu buka `http://localhost:8000/portal-guru/dashboard`.

- Halaman memakai shell, tipografi, warna, tabel, filter, tombol, dan card dari [design system](design-system.md); tidak terlihat style baru yang menyimpang atau warna hex hardcode.
- Filter periode dapat digunakan. Daftar hanya memuat murid yang memang ditugaskan kepada guru yang login; pergantian periode mengganti daftar dan konteks agendanya.
- Daftar murid berupa tabel/list yang rapi, bukan card bertumpuk. Nama dan kelas terbaca; aksi/status berada konsisten di sisi kanan.
- Murid tanpa sesi, dengan paket Tengah valid, menampilkan **Isi Rapor**. Kunjungan/reload dashboard saja tidak membuat sesi baru.
- Status sesi yang ada dibedakan dengan jelas: draft dapat dibuka/diedit, `Menunggu Persetujuan` bersifat baca-saja bagi guru, dan rapor selesai dapat dilihat.
- Periode Akhir/RAS yang belum didukung menampilkan kondisi paket belum siap; tidak membuat sesi atau paket parsial.
- Login sebagai `guru-kosong`: tampil empty state yang informatif, tanpa tabel kosong yang rusak.

### 2. Membuat sesi dan menguji editor — Regular

Sebagai `guru-a`, pilih satu murid Regular yang ditugaskan dan klik **Isi Rapor** pada periode Tengah yang paketnya siap. Ini membuat data sesi demo; lakukan hanya di lokal.

- Browser membuka `/portal-guru/sesi/{id}`. Nama murid, periode, semester, kelas, status, dan progress konsisten dengan pilihan dashboard.
- Header, aksi, kartu dokumen, form/select, teks, dan indikator progress sejajar serta mengikuti komponen UI yang sudah ada.
- Paket Regular berisi empat dokumen: **RTS, Agama, Ummi, Bahasa Inggris**. Setiap dokumen memiliki judul dan progressnya sendiri.
- Bagian panjang dapat dibuka/tutup tanpa menghilangkan isian. Pilihan skala RTS terbaca dan simbol penilaian tampil proporsional; dropdown tidak memotong label panjang.
- Ubah satu-dua nilai demo. Status autosave memberi umpan balik; setelah reload nilainya tetap tersimpan. Mengosongkan nilai mengikuti perilaku kosong yang dijelaskan form, bukan menampilkan data lama.
- Untuk Ummi yang belum diinisialisasi, terlihat ajakan **Mulai pengisian Rapor Ummi**. Membuka halaman tidak menginisialisasi atau mengubah data; tombol mulai adalah aksi tulis eksplisit.
- Tombol **Selesaikan Rapor** belum aktif saat isian wajib belum lengkap. Jangan menyelesaikan/konfirmasi penerimaan kecuali memang sedang menguji alur penuh pada sesi lokal yang boleh dikunci.

### 3. Paket ABK

Login sebagai `guru-b`, pilih satu murid ABK yang ditugaskan, lalu buat sesi periode Tengah yang valid.

- Paket menampilkan empat dokumen Regular ditambah **PPI** sebagai dokumen kelima.
- PPI menampilkan lima aspek dan enam isian per aspek (30 isian wajib). Tidak ada form Hasil Capaian PPI pasca-terbit pada pilot ini.
- Form panjang tetap dapat digunakan pada lebar desktop dan mobile tanpa teks, kontrol, atau tombol saling menimpa.

### 4. Mobile dan responsive

Ulangi dashboard dan editor pada viewport sekitar **390 × 844 px** (opsional juga 375 × 812 px), lalu bandingkan dengan desktop **1440 × 900 px**.

- Tidak ada halaman yang melebar keluar viewport atau memunculkan horizontal scroll yang tidak disengaja.
- Judul, progress, kontrol form, tombol, serta card tetap terbaca dan memiliki ruang sentuh yang cukup.
- Bagian yang bisa collapse tetap jelas sebagai kontrol; dropdown dapat dibuka dan ditutup tanpa menutup halaman/kehilangan fokus secara tidak terduga.
- Tidak ada footer/action bar yang menutupi isian terakhir. Jika ditemukan overflow tabel atau kontrol, catat elemen dan ukuran viewport-nya.

### 5. Antrean dan tinjauan persetujuan

Untuk menguji tampilan antrean, harus ada sesi lokal berstatus `MENUNGGU_TTD` dan assignment aktif bagi akun penyetuju. Tanpa keduanya, empty state antrean adalah hasil yang benar.

- Buka **eRapor > Persetujuan** sebagai penyetuju yang ditugaskan. Tabel menjelaskan murid, periode, tahap, dokumen dalam cakupan, dan status; tombol **Tinjau** hanya muncul untuk data yang boleh ditinjau.
- Halaman tinjauan menunjukkan identitas/periode dan hanya nilai dokumen dalam cakupan penugasan. Tabel tetap terbaca pada layar kecil.
- Tombol setuju memunculkan dialog konfirmasi yang jelas; status menunggu tahap sebelumnya tidak dapat disetujui.
- Urutan yang diharapkan: Koordinator Al-Qur’an (Ummi) dan Koordinator Bahasa Inggris di tahap pertama/paralel, lalu Kepala Sekolah untuk seluruh paket. Jangan menganggap Admin otomatis menjadi penyetuju.
- Persetujuan Kepala Sekolah terakhir menyiapkan artefak PDF privat, tetapi saat ini sesi tetap `MENUNGGU_TTD`. Tidak ada tombol unduh/cetak publik yang perlu diharapkan.

### 6. Penugasan dan profil penandatangan

- Di **Penugasan Penyetuju**, tiga tahap dan cakupan dokumen terlihat jelas; checkbox, akun non-eligible, alasan perubahan, serta aksi simpan tidak bertumpuk.
- Tanpa sedikitnya satu akun yang valid di setiap tahap, konfigurasi belum siap. Halaman harus memberi pesan, bukan membuat assignment diam-diam.
- Di **Profil Penandatangan**, status tanda tangan/NUPTK, petunjuk file, consent, preview privat, dan tombol cabut persetujuan tampil jelas. Jangan unggah tanda tangan asli saat tes visual; bila perlu uji upload gunakan gambar sintetis dan akun demo lokal.

## Alur penuh (opsional dan mengubah data demo)

Hanya lanjut bila sesi lokal memang boleh diisi penuh dan dikunci. Lengkapi semua kolom wajib, klik **Selesaikan Rapor**, lalu **Konfirmasi Penerimaan**. Hasil yang diharapkan berturut-turut adalah `BELUM_DIISI` → `TELAH_DIISI` → `MENUNGGU_TTD`; penerimaan mengunci sesi guru. Uji persetujuan setelah assignment lokal siap. Jangan gunakan sesi yang sama untuk tes lain setelah terkunci.

## Bukti yang diminta dari sesi browser

Ambil screenshot tanpa password, token CSRF, atau data murid nyata. Gunakan nama `[DEMO]` saja. Catat browser dan viewport serta kirim hasil dalam format ringkas:

| Halaman/skenario | Viewport | Hasil (Lulus/Gagal) | Screenshot/catatan |
| --- | --- | --- | --- |
| Dashboard guru Regular | 1440 × 900 |  |  |
| Editor Regular + autosave | 1440 × 900 |  |  |
| Editor ABK/PPI | 390 × 844 |  |  |
| Dashboard empty state | 390 × 844 |  |  |
| Antrean/tinjauan persetujuan | 1440 × 900 |  |  |

Laporkan juga console error, request gagal, teks terpotong, komponen yang tidak mengikuti design system, dan elemen yang overflow. Jangan tandai hasil sebagai lulus jika hanya berdasarkan tes PHP/HTTP—ini memerlukan inspeksi browser visual.
