# Verifikasi production sesudah push `833420c`

Audit browser dilakukan atas permintaan pengguna, hanya-baca terhadap data rapor. Baseline Git saat audit: `833420c` (`fixing and rearrage content`). Tidak ada perbaikan kode aplikasi yang diperlukan dari pemeriksaan ini.

## Pengamanan

- Chrome Headless terpisah, masing-masing akun memakai konteks/kuki sendiri; kredensial dibaca dari file privat `credentials`, tanpa menyalin password ke laporan.
- Intersepsi jaringan hanya mengizinkan GET/HEAD/OPTIONS pada aplikasi dan POST `/login` saat autentikasi. POST simpan/finalisasi/ajukan/setujui/terbitkan/bagikan, logout, serta GET unduhan orang tua diblokir sebelum terkirim.
- Tidak menggunakan Remember Me, tidak logout untuk mencabut token pengguna, tidak menjalankan migrasi/reset, dan tidak menambahkan kontak/murid/sesi production.
- Script telemetry eksternal Cloudflare Insights diblokir sengaja. Tidak ada percobaan request mutasi aplikasi, unduhan orang tua, atau tujuan eksternal lain yang terdeteksi.
- Nilai dan bukti persetujuan hanya ditahan dalam memori untuk perbandingan. Tidak menyimpan screenshot, PDF mentah, nama murid, isian, gambar tanda tangan, username atau token production ke repository.
- Konteks autentikasi/browser ditutup dan profil audit dihapus setelah selesai; server lokal uji pengguna tidak dihentikan.

## Hasil

**118 assertion lulus.**

| Pemeriksaan | Hasil |
| --- | --- |
| Aset production versus Git `833420c` | JS editor, CSS Portal Guru, CSS komponen, dan JS action menu cocok berdasarkan SHA-256 dengan normalisasi CRLF/LF. |
| Login | Seluruh 17 akun dari daftar privat berhasil. |
| Data rapor yang ditinjau | 9 sesi: 8 terbit, 1 disetujui-belum-diterbitkan; 5 sesi ABK. |
| Badge daftar Guru/Superadmin | Sesuai status PPI sebenarnya; tidak memberi alarm pengajuan terpisah pada PPI yang masih disertakan. Markup grup badge baru tersedia pada ringkasan Superadmin. |
| Responsif | Ringkasan Superadmin, daftar PIC, dan sampel editor ABK/PPI tidak memperlebar halaman pada 1440/768/390/375px. |
| Kunci pengisian | Seluruh rapor terkunci tetap readonly; PPI included yang sudah Finalisasi tidak menawarkan pemisahan/pengajuan ulang. |
| Antrean Kepala | Rapor yang telah disetujui namun belum diterbitkan tetap terlihat pada antrean, dengan tindakan Terbitkan tanpa mengulangi tanda tangan. Tidak diklik. |
| Antrean PPI | Dapat dibuka Kepala dan Superadmin; tidak ada tugas PPI terpisah dalam data yang diperiksa. |
| PDF resmi | Tiga sampel diambil melalui endpoint terautentikasi dua kali; masing-masing identik byte-for-byte dan cocok dengan hash audit sebelum deployment. Tidak menulis ulang PDF lama. |
| Template PPI | Endpoint template production berhasil menghasilkan PDF. |
| Pembatasan akses | PIC lain ditolak membaca API/PDF sampel; API tamu mendapat 401. |
| Alat maintenance | `/sistem/migrasi-erapor` dan `/sistem/reset-data` sama-sama 404, termasuk dalam konteks Superadmin. |
| JavaScript | Tidak ditemukan exception aplikasi pada halaman yang diaudit. Error pemblokiran telemetry eksternal oleh pengaman bukan error aplikasi. |
| Data sebelum/sesudah | Hash kanonis nilai dokumen, status sesi, state/flag PPI, dan persetujuan kesembilan sesi sama dengan baseline awal audit. |

Tidak ada request tulis selain login yang diizinkan. Ini merupakan perbandingan melalui API, **bukan fingerprint seluruh database production**, karena audit tidak memiliki akses SQL production. Autentikasi sendiri tetap membuat sesi login sebagaimana normalnya.

## Batas verifikasi khusus PPI terpisah

Kelima sesi ABK masih **menyertakan PPI** pada rapor utama; tidak ditemukan sesi PPI terpisah dalam periode yang ditinjau. Karena itu:

- Tidak membuat/memisahkan rapor nyata hanya agar tombol Ajukan dan badge positif muncul.
- Posisi kanan tombol di bawah progres, tingginya, serta transisi badge sesudah pengajuan **belum bisa diamati pada sesi PPI terpisah nyata di production**.
- Verifikasi positif sebelumnya tetap berasal dari lingkungan lokal/salinan; aset layout production kini cocok dengan kode yang diuji tersebut. Pemeriksaan aset bukan checksum seluruh file PHP server.
- Tidak membuka tautan unduh orang tua atau mengirim WhatsApp. Keberhasilan PDF terautentikasi tidak dianggap pengujian integrasi unduhan publik melalui R2.

Tidak ada migrasi atau perubahan data production yang dilakukan. Tindak lanjut positif memerlukan sesi production yang memang sah untuk PPI terpisah, bukan modifikasi artefak resmi lama.
