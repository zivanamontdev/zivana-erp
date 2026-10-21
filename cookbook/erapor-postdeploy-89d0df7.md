# Audit production sesudah deploy `89d0df7`

21 Oktober 2026, sekitar 11:30–11:46 WITA. Domain: `https://erp.sekolahzivanamontessori.sch.id`. Pengujian hanya-baca atas permintaan pemilik; **hasil keseluruhan belum lulus karena preview PDF koordinator mengembalikan HTTP 500**.

Tidak ada perbaikan/deploy aplikasi yang dilakukan dalam audit ini. Hanya laporan dan bukti template non-personal yang ditambahkan secara lokal.

## Pengamanan

- Chrome Headless khusus; masing-masing akun menggunakan konteks autentikasi sendiri. Kredensial dibaca privat dari `credentials`, tidak disalin ke laporan.
- Intersepsi request aktif sebelum login: hanya GET/HEAD/OPTIONS aplikasi serta POST `/login` yang diizinkan. POST login dibuka hanya ketika autentikasi; Remember Me tidak dipakai.
- Logout, endpoint berbagi dan unduhan publik orang tua, reset kata sandi, serta seluruh request tulis lain diblokir sebelum dikirim. Tidak ada percobaan simpan/finalisasi/ajukan/setujui/terbitkan/reset/migrasi, bahkan untuk pengujian negatif.
- Tidak membuat siswa, sesi, kontak, data dummy atau artefak PDF resmi. Tidak membuka token unduhan orang tua, tidak mengirim WhatsApp, tidak mengubah `.env`/konfigurasi production.
- Nilai dan bukti persetujuan berada di memori untuk hashing. Tidak menyimpan PDF mentah resmi, isian, nama murid, gambar TTD asli, cookie, token atau password ke repository. Raster yang dipersistenkan hanya template beridentitas placeholder.
- Login tetap membuat sesi autentikasi normal; akses HTTP/render juga dapat menghasilkan log/cache teknis server. Ini bukan klaim tidak ada aktivitas teknis apa pun di server, melainkan tidak ada mutasi data bisnis yang diminta/dilakukan.
- Browser audit dan konteksnya ditutup; profil lokal browser dibersihkan. Tidak logout agar tidak mencabut token pengguna. Server uji lokal pengguna tidak dihentikan.

## Temuan yang perlu ditindaklanjuti

**Preview/unduhan PDF cakupan koordinator gagal HTTP 500**, pada kedua jenis siswa yang disampel:

| Sampel | Akun penyetuju | GET inline | GET `?unduh=1` |
| --- | --- | --- | --- |
| Sesi `14`, Regular/tanpa PPI | Koordinator Agama | 500 | 500 |
| Sesi `14`, Regular/tanpa PPI | Koordinator Bahasa Inggris | 500 | 500 |
| Sesi `9`, ABK/dengan PPI | Koordinator Agama | 500 | 500 |
| Sesi `9`, ABK/dengan PPI | Koordinator Bahasa Inggris | 500 | 500 |

Endpoint: `/erapor/sesi/{id}/pdf`, menggunakan konteks akun koordinator yang sah. Riwayat `/erapor/persetujuan?status=selesai` mengonfirmasi kedua akun memiliki tugas Koordinator Agama/Bahasa Inggris atas sesi yang diperiksa. Tidak mengubah penugasan untuk menguji kondisi ini.

Jalur PIC/Superadmin mendapatkan PDF resmi tersimpan dan berhasil. Jalur koordinator terbatas membutuhkan render pratinjau baru; server mengembalikan halaman 500 generik. **Penyebab exception belum diketahui**, karena audit browser tidak memiliki log PHP server. Jangan menyimpulkan ini pasti disebabkan font, gambar, database, deployment file PHP yang tidak lengkap, atau bahwa penerbitan baru pasti gagal/hanya-baca aman. Belum ada bukti untuk kesimpulan tersebut.

Langkah diagnosis berikutnya adalah membaca **log error PHP/server** sekitar waktu audit untuk route di atas (tanpa mengaktifkan debug publik atau mengubah data). Pengujian ini tidak memperbaiki atau memodifikasi production.

## Bagian yang berhasil diverifikasi

Tercatat **219 assertion positif**, di luar empat kombinasi kegagalan scoped PDF di atas. Ini **bukan** pernyataan semua fitur lulus.

| Pemeriksaan | Hasil |
| --- | --- |
| Login | Seluruh **17 akun** berhasil login menggunakan kredensial yang diberikan. |
| Aset deployment | Sembilan aset cocok dengan Git `89d0df7`: CSS preview/PDF/Portal Guru, JS editor, kedua TTF Quicksand, kedua UFM dan registry relatif `installed-fonts.json`. File teks dinormalisasi CRLF/LF; binary memakai SHA-256. Ini bukan checksum seluruh PHP server. |
| Template server | **10 PDF / 40 halaman**: RTS, Agama, Ummi, BING, PPI × Ganjil/Genap. Semua berhasil dirender oleh production. |
| Font | Template memakai **Quicksand Regular dan Bold tertanam**, bukan sekadar file font yang tersedia di URL. |
| Kolom orang tua | Tidak ada pada pengesahan Ummi/BING. Tetap ada pada RTS, Agama dan PPI; kolom Hasil Capaian Orangtua PPI tidak dihapus. |
| Layout PDF template | Semua halaman memiliki teks, tidak ada teks di luar kertas/glyph pengganti; gambar logo tidak terdistorsi atau keluar halaman. Contact sheet dirasterisasi untuk pemeriksaan visual, dengan sampel aman pada folder bukti. |
| Data rapor yang tersedia | **9 sesi**, terdiri dari 4 Regular dan 5 ABK; 8 terbit dan 1 disetujui-belum-diterbitkan. Kelima ABK masih menyertakan PPI. |
| PDF resmi | Seluruh **9 PDF / 175 halaman** dapat dibaca. Inline dan unduhan terautentikasi identik byte-for-byte. Semua masih font historis; tidak dirender ulang. Tiga hash yang memiliki baseline audit sebelum deploy juga tetap identik. |
| Kunci editor | Rapor utama/PPI yang sudah terkunci tetap readonly. Tidak mengetik atau menyimpan apa pun. |
| Responsif | Sampel daftar PIC dan editor Regular/ABK serta overview Superadmin tidak melebar pada 1440/768/390/375px. Font shell tetap Plus Jakarta Sans. |
| Halaman preview | Halaman preview PDF Ummi/BING terbuka dengan iframe dan kontainer yang muat pada 1440/375px. Isi PDF diuji terpisah melalui endpoint serta raster, bukan menganggap iframe saja membuktikan isi. |
| HTML preview legacy | Tengah/Akhir × 1440/768/390/375px: Quicksand lokal ter-load di empat lembar, seluruh isi muat tinggi kertas, shell tetap memakai font tema dan scroll horizontal internal. |
| Antrean | Antrean PPI Kepala/Superadmin serta antrean persetujuan Kepala terbuka. Tidak menekan aksi persetujuan/penerbitan. |
| Maintenance | `/sistem/migrasi-erapor` dan `/sistem/reset-data` 404, termasuk dalam konteks Superadmin. |
| Akses | PIC lain ditolak membaca API/PDF sampel; API tamu 401. Tidak menguji penulisan tanpa izin dengan POST. |
| JavaScript | Tidak ditemukan exception JavaScript aplikasi pada halaman yang diperiksa. HTTP 500 PDF tetap dicatat sebagai masalah server yang terpisah. |
| Integritas sebelum/sesudah | Hash kanonis dokumen/nilai, status sesi, state/flag PPI dan persetujuan kesembilan sesi identik, termasuk baseline dari percobaan audit pertama sebelum penelusuran 500. |

**Nol request tulis selain `/login` yang diizinkan.** Pengaman memblokir telemetry eksternal dan stylesheet internal PDF viewer browser (`origin=null`, `/pdf_embedder.css`); yang terakhir bukan endpoint aplikasi/tujuan production. Koreksi klasifikasi request browser ini dilakukan pada ledger pengaman, bukan dengan melonggarkan izin mutasi.

Perbandingan hash data melalui API **bukan fingerprint seluruh database production**, karena tidak ada akses SQL production. Status immutable PDF, pembatasan akun dan hasil template diuji sesuai akses yang sah, bukan bypass otorisasi.

## Batas pengujian

- Tidak ada PPI terpisah nyata pada periode yang ditinjau; alur pengajuan/persetujuan/publikasi PPI standalone tidak dicoba karena akan memerlukan perubahan data.
- Seluruh PDF resmi yang tersedia adalah artefak historis. **Ukuran TTD/cap baru pada rapor siswa belum berhasil diverifikasi di production**: template tidak memiliki gambar TTD, sedangkan render scoped aktual gagal 500. Bukti gambar sintetis lokal tetap bukan pengganti verifikasi production tersebut.
- Tidak membuat PDF resmi baru, menerbitkan ulang PDF lama, menguji printer fisik/Safari/iOS fisik, menjalankan load test, atau membuka unduhan publik R2 orang tua.
- PDF resmi lama tetap menunjukkan desain lama sesuai aturan immutable. Ini terpisah dari bug HTTP 500 dan bukan bukti font baru tidak ter-deploy.

## Klarifikasi tautan dan panduan akun

Tautan pemilik `/erapor/sesi/15/pdf` dan `/erapor/sesi/12/pdf` termasuk artefak resmi historis yang telah diverifikasi di atas, bukan template. Kedua endpoint mengambil file tersimpan terlebih dahulu; font/desainnya tidak otomatis berubah saat deployment. Penerbitan ulang berversi belum dilakukan atau diotorisasi.

Atas permintaan pemilik, file privat `credentials` diberi label **17 akun**: jabatan, role sistem/RBAC, tugas koordinator/Kepala, dan kelas Wali bila ada. Pemetaan diverifikasi lewat GET halaman Karyawan, Jabatan, Penugasan Persetujuan dan Detail Kelas production memakai Superadmin dengan guard hanya-baca yang sama. Tidak menyimpan form/perubahan apa pun. Kepala Sekolah secara teknis memakai role sistem Admin, sedangkan koordinator tetap Guru dengan penugasan tambahan; label membedakan hal tersebut.

Akun 09 adalah Koordinator Agama (Agama + Ummi), sekaligus Wali kelasnya. Akun 16 adalah Koordinator Bahasa Inggris. Petunjuk reproduksi 500 ditulis pada kedua blok akun: **Persetujuan eRapor → Selesai → Tinjau sesi 14 atau 9 → Pratinjau PDF**. Email dan password seluruh akun diverifikasi tetap identik melalui hash pemetaan kredensial; parser lama masih mengenali 17 akun serta password Superadmin yang tetap tepat setelah email. File tetap diabaikan Git dan tidak boleh diunggah ke hosting. Panduan lokal kini merujuk nomor akun/label `pass:`, bukan nomor baris lama.

Bukti aman: [`docs/testing/erapor-postdeploy-89d0df7/`](../docs/testing/erapor-postdeploy-89d0df7/). Kesimpulan: deployment aset/font dan template benar, data rapor tidak berubah, **tetapi perlu diagnosis 500 sebelum hasil deployment dinyatakan sepenuhnya aman**.
