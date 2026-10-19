# Uji lokal PPI menyerupai production — 19 Oktober 2026

## Batas lingkungan dan keselamatan

- Baseline kode: `61ccce9`; perbaikan di bawah ditambahkan sesudahnya, belum di-commit/deploy dalam pengujian ini.
- Menggunakan database **salinan sekali pakai** dan server/browser terpisah, bukan mengubah database lokal utama atau membuka production.
- Semua **17 kredensial** dari file privat berhasil login pada salinan. Hash password hanya disamakan dalam salinan; password database asli tidak diubah.
- PIC, Wali Kelas per kelas, koordinator Quran/English, dan Kepala Sekolah diambil dari **penugasan lokal yang sudah ada**, tanpa memberi semua tahap kepada satu akun. PIC dan Wali pada sesi ABK yang dipilih berbeda.
- Ini bukan salinan database production atau verifikasi ulang pemetaan production terkini. Yang disamakan adalah akun dan aturan otorisasi; penugasan lokal dipertahankan.
- Identitas murid/pegawai, isian PPI dan gambar tanda tangan untuk bukti dibuat sintetis. Nomor kontak uji hanya ditambahkan ke salinan melalui formulir admin, tidak ke production.
- Tanggal periode awal tetap **15 September–10 Oktober 2026**; tidak membuka deadline secara massal. Isian awal disiapkan dengan jam fixture sebelum deadline, lalu seluruh permintaan HTTP menggunakan tanggal server sebenarnya.
- Lokal menggunakan **MySQL 8.4**, bukan MariaDB 11.8. `PDO::ATTR_STRINGIFY_FETCHES=true` dipakai pada seluruh alur HTTP untuk menguji ID numerik bertipe string seperti yang dijumpai di hosting. Ini bukan jaminan paritas seluruh perilaku kedua engine.
- Penyimpanan memakai pengganti R2 berbasis file lokal. Host production, WhatsApp dan Cloudflare storage diblokir di browser; konfigurasi R2 asli dinonaktifkan pada server uji. Tidak mengirim WhatsApp.

## Skenario browser/controller nyata

**76 assertion alur lulus**, di luar pemeriksaan login 17 akun:

| Skenario | Assertion | Hasil |
| --- | ---: | --- |
| PPI lengkap, dikeluarkan saat Finalisasi | 32 | Rapor utama terbit; PIC dapat mengajukan PPI lengkap setelah deadline tanpa izin mengubah nilai. Wali dan Kepala menandatangani secara terpisah. |
| Kepala mengeluarkan PPI sebelum PDF resmi disiapkan | 16 | Pilihan awal dan bukti scope lama tetap tersimpan; tidak ada pengajuan/signature PPI otomatis. PPI bahkan dapat terbit saat rapor utama masih disetujui-belum-diterbitkan. |
| PPI 29/30 setelah deadline | 18 | Rapor utama dapat terbit; PPI tidak bisa diedit/diajukan. Perpanjangan melalui formulir admin membuka PPI saja, bukan rapor utama. Setelah lengkap, alur Wali → Kepala → Terbit berhasil. |
| PPI tetap disertakan | 10 | Default tidak berubah; PPI masuk rapor utama, tanpa pengajuan terpisah dan tanpa akses PDF standalone. |

Pemeriksaan tambahan di dalam skenario:

- Wali tidak dapat mengajukan atas nama PIC; actor buatan ditolak.
- PIC yang memiliki izin persetujuan untuk kelas lain tetap tidak boleh menandatangani sebagai Wali kelas target.
- Kepala tidak dapat melewati persetujuan Wali; koordinator English tidak memperoleh akses PPI terpisah.
- Wali tidak bisa melihat draft PPI terpisah sebelum PIC mengajukannya.
- Setelah PDF utama disiapkan, replay persetujuan Kepala tidak dapat memasukkan kembali PPI.
- Respons autosave sengaja diputus **setelah server menyimpan**; replay pulih dan reload tetap menunjukkan 30/30 nilai.
- Jika ada edit yang membuat PPI kembali belum lengkap ketika modal konfirmasi terbuka, pengajuan dibatalkan sebelum request submit dan kontrol kembali dapat diedit.
- Kontak kosong/satu/dua menghasilkan tindakan berbagi yang tepat. Klik berbagi mencatat audit lokal; navigasi WhatsApp dicegah. Unduhan orang tua mengikuti token dan redirect penyimpanan lokal, dengan byte sama persis seperti PDF PPI resmi.
- Tampilan terpisah pada 1440, 768, 390 dan 375px tidak menambah lebar halaman. Tidak ditemukan exception JavaScript aplikasi pada empat skenario.

## Temuan yang diperbaiki

1. **PPI terbit tanpa kontak orang tua:** sebelumnya tindakan Bagikan hilang tanpa alasan. Sekarang tombol nonaktif dan petunjuk pengisian kontak tampil, dengan `aria-describedby`, memakai komponen/warna yang sudah ada.
2. **Alasan PPI tidak dapat diedit:** sebelumnya pesan kunci rapor utama menutupi alasan deadline PPI. Read model menambahkan `capabilities.ppi_read_only_reason`; kartu PPI membedakan isian belum lengkap yang memerlukan perpanjangan admin dan isian lengkap yang tidak perlu diisi ulang. Kebijakan tenggat/izin tidak dilonggarkan.
3. **Tombol Ajukan setelah autosave:** status native dan kelas visual kini berubah bersama; edit pending langsung menonaktifkan tindakan. Sesudah antrean disimpan, kelengkapan dicek ulang sebelum dispatch agar perubahan di sekitar konfirmasi tidak mengirim pengajuan yang sudah tidak valid atau membekukan input secara keliru.

### Penyempurnaan estetika sesuai feedback

Keterangan panjang pada kartu diganti label pendek: **Tenggat PPI berakhir**, **Isian PPI sudah lengkap**, atau **Kontak belum tersedia**. Rincian disimpan dalam `uiTooltip()` yang sudah ada, di ikon info terpisah dan bisa difokuskan/disentuh—bukan hanya `title` pada tombol disabled. Alur Wali Kelas → Kepala Sekolah tetap terlihat singkat. Jarak antarbaris memakai token komponen yang ada, tanpa margin paragraf ganda.

Uji tambahan **tanpa database**: 16 render sintetis (4 kondisi × 375/390/768/1440px), 28 pemeriksaan tooltip saat fokus, dan 2 pembukaan melalui touch. Semua bubble tetap di dalam viewport. Regresi markup, sharing UI dan autosave lulus kembali. Screenshot `04-compact-deadline.png` dan `05-compact-contact-tooltip.png` memperlihatkan tampilan terbaru; screenshot `01`/`02` adalah bukti sebelum pemadatan teks.

File aplikasi: `app/models/EraporTeacherForm.php`, `app/views/portal-guru/_ppi-workflow.php`, `public/assets/js/erapor-session.js`, `public/assets/css/portal-guru.css`.

**Tidak ada migration baru.** Production tetap menggunakan migration PPI yang sebelumnya sudah terpasang. Database utama lokal tidak dimigrasikan oleh pengujian ini.

## PDF dan regresi

- **11 hasil unduhan PDF** diperiksa: tidak ada kata di luar batas halaman.
- Tiga pasangan PDF utama sebelum/sesudah proses PPI berbeda skenario **identik byte-for-byte**.
- Fixture ini menghasilkan 13 halaman utama tanpa PPI, 16 halaman bila PPI disertakan, dan 3 halaman PPI standalone. Jumlah halaman bergantung isi, bukan ketentuan tetap.
- Halaman akhir PPI diperiksa visual: watermark di belakang isi, slot Guru Kelas dan Kepala memakai bukti peran yang benar, tanda tangan sintetis tidak terpotong.
- Regresi backend: `php tests/erapor-ppi-workflow-mysql.php --run` **67** pemeriksaan; `php tests/erapor-save-recovery-mysql.php --run` **13** pemeriksaan.
- `php tests/erapor-ppi-share-ui.php`: **9** pemeriksaan baru untuk kontak kosong/satu/dua, aksesibilitas, escaping dan pembatasan sebelum publikasi.
- `php tests/erapor-session-ui.php` dan `node tests/erapor-autosave-ui.js` diperluas untuk deadline PPI, retry PPI, serta status/warna tombol setelah autosave.
- API adapter **37**, RBAC **91**, PDF renderer **24**, PDF artifact **24**, filename **10**, signer snapshot **16**, publication signer **7**, overview **13**, approval UI dan account/RBAC regression lulus.
- PHP lint, JS syntax dan `git diff --check` lulus.

## Bukti aman dan cleanup

Bukti: [`docs/testing/erapor-ppi-production-like-20261019/`](../docs/testing/erapor-ppi-production-like-20261019/). Hanya screenshot sintetis serta daftar assertion/hash, tanpa kredensial, token unduhan, atau laporan utama mentah.

Cleanup terverifikasi: fingerprint isi **82 tabel asli tidak berubah**, database salinan dihapus, server/browser uji dihentikan, konfigurasi/kuki/sesi privat dan artefak sementara dibersihkan. File `credentials` tetap diabaikan Git.

## Lingkungan manual yang disiapkan kembali untuk pengguna

Atas permintaan pengguna, dibuat **salinan baru yang sengaja dibiarkan aktif** di `http://127.0.0.1:8013/__local-test`. Ini bukan database pengujian sebelumnya yang sudah dihapus, dan bukan aplikasi utama di `:8000`.

- Runtime/panduan akun disimpan dalam `.local-ppi-test/`, dikecualikan melalui `.git/info/exclude`. Folder tersebut berisi konfigurasi privat; **jangan commit, zip untuk deployment, atau unggah ke hosting**. Username ada di `AKSES-LOKAL.md`; password tidak disalin, tetap mengacu pada file `credentials`.
- Empat murid sintetis baru memakai PIC dan pemetaan Wali kelas yang sama dengan penugasan lokal terpilih: PPI 29/30 siap dilengkapi, PPI terbit tanpa kontak, rapor sebelum Finalisasi, dan PPI terbit dengan dua kontak sintetis untuk simulasi berbagi. Draft kasus pertama belum diajukan dan tidak terlihat oleh Wali/Kepala.
- Kalender **hanya di salinan** dibuka sampai **2 November 2026** agar pengguna dapat mengetik. Panduan privat menjelaskan pengujian deadline melalui Admin; kalender asli tidak diubah.
- Cookie `ZIVANA_PPI_TEST` terpisah dari `PHPSESSID` aplikasi utama. R2 memakai file lokal, SMTP dimatikan, dan URL WhatsApp pada HTML/JSON dialihkan ke halaman simulasi lokal. Klik berbagi tidak membuka WhatsApp nyata.
- `MULAI-UJI.cmd` dan `HENTIKAN-UJI.cmd` dalam folder privat menyalakan/menghentikan server tanpa menghapus database atau hasil uji. Restart sudah diperiksa; server tetap bind ke loopback saja.
- **33 smoke assertion** lulus, termasuk login 17 akun, empat kondisi awal, pemisahan cookie, akses Wali/Kepala dan pengalihan WhatsApp. Pemeriksaan ulang menunjukkan isi **82 tabel asli tetap tidak berubah**. Aplikasi utama `:8000` dan server uji `:8013` sama-sama merespons HTTP 200.
- Browser audit/CDP ditutup; hanya server HTTP manual yang sengaja dibiarkan berjalan. Data dan lingkungan uji tidak dihapus otomatis.

## Perapian kartu, tombol dan pengingat di daftar

Tindak lanjut feedback saat pengguna mencoba lingkungan manual:

- Judul/progres, status, keterangan dan tindakan PPI sekarang berada dalam **satu kartu intro**. Partial workflow hanya menghasilkan isi, bukan kartu tersendiri. Progres hanya tampil sekali; label "Terpisah" tidak diulang pada setiap baris.
- Margin bawaan tautan PDF sebelumnya ikut dihitung oleh flex stretch dan membuat tombol native lebih tinggi. Area tindakan PPI kini memakai margin nol dan alignment yang sama, tanpa ukuran tinggi hardcoded. Browser mengukur PDF/Ajukan sama-sama **39px** pada 375/390/768px dan **41.3125px** pada 1440px (mengikuti skala tema).
- Dashboard Guru dan Rapor Murid Superadmin mendapat badge **PPI belum diajukan** untuk sesi ABK yang sudah Finalisasi, efektif dipisahkan, dan pengajuannya masih `BELUM_DIAJUKAN`. Status rapor utama dan tindakan PDF tetap dipertahankan. PPI yang disertakan, draft sebelum Finalisasi, serta PPI yang sudah diajukan tidak diberi pengingat tersebut. Pilihan pemisahan oleh Kepala juga tercakup.
- Join status PPI hanya dipakai ketika schema tersedia. Fallback sebelum migration tetap bisa membaca daftar tanpa mengakses kolom/tabel yang belum ada; hasil numerik bertipe string juga diuji.
- **76 pemeriksaan backend PPI**, **13 recovery**, **11 sharing/layout markup**, **7 list badge UI**, dan regresi lain lulus. Audit browser hanya-baca menghasilkan **73 assertion** pada 1440/768/390/375px, termasuk pengukuran tombol, satu kartu, badge kedua daftar, dan tidak ada overflow/exception JS. Data uji manual tidak di-reset atau disubmit oleh audit ini.
- Bukti terbaru: `06-merged-ppi-mobile.png`, `07-ppi-list-badge-mobile.png`, `08-ppi-overview-badge-desktop.png` beserta hasil pengukuran. Screenshot sebelumnya disimpan sebagai riwayat, bukan desain terbaru.

### Penempatan tindakan di bawah progres

Sesuai feedback lanjutan, header PPI terpisah menggunakan dua kolom pada desktop/tablet: judul/status/alur di kiri, indikator progres dan tombol tepat di bawahnya di kanan. Di HP urutannya judul → progres → tombol → informasi alur, sehingga tombol tidak kembali berada di bawah alur dan urutan fokus sesuai tampilan. Tetap satu kartu dan tinggi tombol tetap sama.

Audit posisi hanya-baca: **20 kombinasi** (empat kondisi × 1440/1280/768/390/375px), memeriksa posisi, jarak, tinggi, tooltip, overflow dan pembukaan menu berbagi. Menu PPI dua kontak juga diperbaiki agar mengikuti atribut `data-action-menu` komponen bersama, tanpa `hidden` permanen. Tidak ada pesan dikirim atau nilai diubah oleh audit. Screenshot `09-actions-under-progress-desktop.png` dan `10-actions-under-progress-mobile.png` menunjukkan posisi terbaru.

Tidak ada migration tambahan, perubahan aturan persetujuan, atau penulisan ulang PDF resmi. Server manual tetap aktif pada `:8013`.

## Yang belum dibuktikan oleh uji ini

- Unduhan melalui R2 production sebenarnya dan kontak orang tua sah; pengganti penyimpanan lokal bukan uji integrasi R2 nyata.
- Mutasi alur PPI pada data aman khusus production, Safari/iOS fisik, dan beban konkurensi production.
- Smoke test setelah deploy terhadap tampilan/pesan baru.
- Tidak menerbitkan ulang atau mengganti PDF historis. Tidak commit, push, atau deploy otomatis.
