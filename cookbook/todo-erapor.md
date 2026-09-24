# TODO Implementasi eRapor Zivana

Tanggal: 24 September 2026.

Status: spesifikasi sudah dipelajari; implementasi baru belum dimulai. Dokumen ini menjadi roadmap aktif untuk modul rapor, menggantikan asumsi rapor lama di [todo.md](todo.md). Checkbox selesai harus disertai bukti; pekerjaan versi lama tidak otomatis lulus spesifikasi baru.

## Acuan dan batas pekerjaan

- [README spesifikasi](../eRapor_Zivana_Spesifikasi/README.md) dan [alur pengisian](../eRapor_Zivana_Spesifikasi/SPEK_ALUR_PENGISIAN.md): aturan bersama, sesi, status, penyimpanan, persetujuan, dan penerbitan.
- Spesifikasi RTS, Agama, Ummi, Bahasa Inggris, dan PPI: struktur serta aturan khusus setiap dokumen. Gunakan lima JSON seed sebagai sumber data, bukan transkripsi tabel Markdown.
- PDF, DOCX, dan XLSX dalam folder spesifikasi: acuan dokumen asli. Perbedaan versi harus dicatat dan diselaraskan, bukan diperbaiki sepihak.
- [Design system](design-system.md): komponen UI aplikasi tetap digunakan. Setiap warna harus dicari di `config/colors.php`; tambahkan token jika belum tersedia, jangan menulis hex langsung di halaman/komponen.
- Jangan mengubah data produksi, menghapus nilai lama, atau menjalankan migrasi destruktif tanpa persetujuan. Tidak membuat CRUD editor kurikulum, reminder, delegasi persetujuan, atau fitur salin otomatis yang tidak diminta.

## 0. Pemahaman dan keputusan

- [x] Pelajari 18 berkas: tujuh Markdown, lima JSON, tiga PDF, satu DOCX, satu XLSX (empat sheet), dan satu skrip Python.
- [x] Periksa jumlah seed: RTS 175 indikator/8 area/21 subarea bernama/2 grup; Agama 73 butir (37 Ganjil, 36 Genap)/99 nama; Ummi 27 materi/7 jilid/12 nilai; BING 5 nilai/4 komentar; PPI 5 aspek/30 isian wajib.
- [x] Jalankan `uji_tinjauan_ppi.py` dan enam assertion tambahan pencarian periode dengan SQLite in-memory; tidak menyentuh database aplikasi. Ini bukan tes implementasi PHP.
- [x] Tetapkan paket Regular = RTS atau RAS + Agama + Ummi + BING. Paket ABK = paket Regular + PPI. Identitas otomatis bukan tahap penilaian; RTS ABK tetap 175 indikator yang sama.
- [ ] Catat keputusan penyelarasan versi sebelum seeding final/PDF: PDF BING sudah mengubah `Pronunciation` dan definisi skala, seed belum; Word Agama masih memuat beberapa ejaan lama; aturan Ummi tentang baris tes kosong berbeda antara seed dan spesifikasi.
- [ ] Selaraskan kalimat Agama tentang perubahan narasi rapor lama dengan kewajiban pembekuan rubrik/rapor terbit. Jangan mengubah dokumen historis secara diam-diam.
- [ ] Terima spesifikasi dan seed RAS. **Menahan implementasi lengkap paket akhir semester, bukan fondasi atau rubrik lain.** Jangan menggunakan RTS sebagai pengganti RAS atau menganggap paket tanpa RAS lengkap.

## 1. Audit aplikasi dan rencana migrasi — mulai di sini

- [ ] Petakan tabel, model, controller, route, UI, PDF, dan tes rapor saat ini terhadap spesifikasi baru; catat bagian reusable dan yang harus diganti. Audit model penyimpanan/status dan controller awal ada di [erapor-migration.md](erapor-migration.md); audit UI/PDF/seluruh route masih berlanjut.
- [ ] Inventarisasi data nyata, data dummy, template placeholder, nilai, status, serta relasi guru/murid/kelas. Bedakan `sesi_pembagian_rapor` lama dari sesi baru per murid/periode.
- [x] Tetapkan aturan pemetaan ID/status dan pengecualian legacy di [erapor-migration.md](erapor-migration.md). Ini aturan transisi, bukan backfill yang telah dijalankan; pemetaan data aktual dan persetujuan kasus ambigu masih diperlukan.
- [x] Siapkan cadangan lokal terbaru dan uji restore ke database terisolasi. Batch katalog 24 September: hitungan serta checksum isi seluruh 24 tabel legacy cocok sebelum/sesudah DDL baru, database sumber tidak berubah. Tetap buat cadangan baru sebelum migrasi berikutnya/produksi.
- [ ] Rancang migrasi bertahap, dry-run, pemeriksaan prasyarat, pengulangan aman, dan pemulihan kegagalan. Tidak memakai reset database atau mengimpor seed dengan menimpa nilai lama.
- [ ] Siapkan langkah deployment cPanel tanpa SSH: migrasi terkontrol melalui mekanisme yang sesuai hosting, bukan endpoint publik bebas menjalankan SQL/PHP.

Gerbang: pemetaan data dan strategi pemulihan terbukti pada salinan database sebelum migrasi database pengguna.

## 2. Fondasi data bersama

- [ ] Tahun ajaran memiliki urutan stabil; periode memiliki semester, jenis Tengah/Akhir, urutan 1–4, tanggal mulai/akhir, dan batasan empat kombinasi unik per tahun ajaran.
- [ ] Implementasikan metadata rubrik, bagian wajib, kolom periode cetak, penandatangan, dan konfigurasi alur penyetuju sebagai data.
- [ ] Terapkan versi rubrik; kode tetap sejak seeding pertama, definisi terkunci setelah dipakai, indikator historis tidak dihapus. Revisi berikutnya memakai versi baru.
- [ ] Pisahkan identitas dokumen `rapor` dari `rapor_sesi`: unik murid/tahun/rubrik/semester pada dokumen; unik murid/periode pada sesi. Gunakan `TAHUNAN`, bukan NULL, untuk semester dokumen tahunan.
- [ ] Buat `rapor_sesi_dokumen` dengan urutan dan penanda wajib. Paket Regular/ABK dibentuk dari data, tidak di-hardcode terpisah di setiap halaman.
- [ ] Buat tabel persetujuan, log status, log perubahan isian, dan perpanjangan periode. Salin urutan penyetuju ketika sesi diajukan.
- [ ] Siapkan berkas tanda tangan, persetujuan penggunaan gambar, NUPTK opsional, serta sumber tempat pengesahan dari Data Sekolah. Simpan berkas secara terlindungi.
- [ ] Pasang FK, uniqueness, validasi lintas rubrik/periode/semester, dan transaksi. Setiap isian harus dapat ditelusuri ke satu sesi yang benar.

## 3. Tabel khusus dan seeding rubrik

- [ ] RTS: area → subarea eksplisit/implisit → grup opsional → indikator, aparatus, empat skala BD/MB/BSH/BSB. Grup bukan isian; teks tujuan yang sama tidak dijadikan kunci.
- [ ] RTS: satu dokumen tahunan, dua periode Tengah Ganjil/Genap; 175 nilai wajib per sesi, bukan 350. Gunakan SVG penilaian yang sudah tersedia pada UI dan PDF.
- [ ] Agama: enam lingkup, subbagian, 73 butir terikat semester, dan 99 nama Asmaul Husna dalam sepuluh baris penilaian; jangan deduplikasi nama berdasarkan teks.
- [ ] Agama: dropdown tujuh pilihan, penyimpanan tahapan dan subtingkat Tahfizh terpisah; enforce kombinasi sah di database. Enam potongan narasi wajib per periode, bukan satu teks hasil rangkaian.
- [ ] Ummi: satu rubrik tujuh jilid/27 materi, skala A+ sampai D-, bacaan opsional, daftar tes dinamis opsional, catatan guru wajib.
- [ ] Ummi: `mulai_pra_tk` per periode; awal periode Akhir mengikuti Tengah. Menyembunyikan PRA TK tidak menghapus nilai. Jangan seed atau tampilkan blok Hafalan dari Excel.
- [ ] BING: lima indikator dan empat komentar wajib, `Excellent > Outstanding > Good > Fair`; Speaking Test Result hanya judul kelompok. Kehadiran tetap skala sesuai spesifikasi saat ini.
- [ ] PPI: lima aspek berbasis data, enam kolom isian sesi per aspek = 30 textarea wajib. Diagnosa boleh kosong; Hasil Capaian tidak masuk tabel isian sesi atau kelengkapan.
- [ ] Jalankan seeder berurutan sesuai FK dan verifikasi seluruh jumlah, kode unik, urutan, relasi, serta hasil pengulangan. Jangan seed nama/nilai/narasi murid contoh dari dokumen asli.
- [ ] Uji isolasi antarperiode: RTS/Agama tahunan; Ummi/BING/PPI per semester; setiap nilai tetap memakai ID periode konkret. Nilai ordinal tidak dirata-ratakan.

## 4. Layanan pengisian, kelengkapan, dan status

- [ ] Implementasikan empat status sesi: `BELUM_DIISI → TELAH_DIISI → MENUNGGU_TTD → SELESAI`; label UI status ketiga `Menunggu Disetujui`.
- [ ] Buat layanan kelengkapan bersama: NULL, kosong, dan whitespace dianggap kosong; isian kosong dihapus barisnya. Teks nonkosong disimpan mentah tanpa normalisasi isi.
- [ ] Kelengkapan mengikuti bagian/kolom wajib dan semester butir. Ummi kosong pada bagian opsional tidak menjadi peringatan atau menghalangi konfirmasi.
- [ ] Pisahkan status dan kelengkapan: menyunting/mengosongkan isian saat `TELAH_DIISI` tidak menurunkan status, tetapi memblokir konfirmasi penerimaan sampai lengkap kembali.
- [ ] Terapkan guard di lapisan penyimpanan: akses guru, keanggotaan dokumen, periode sesi, status 1/2, dan tenggat. Tolak perubahan periode lewat manipulasi request.
- [ ] Autosave berupa batch perubahan saja; transaksi isian dan audit log harus bersama. Tangani request gagal, request terlambat, retry, dan konflik saat konfirmasi/lock.
- [ ] Arsip menyimpan posisi tahap terakhir dan menjeda sesi tanpa membuat status draft tambahan. Pastikan perubahan tersimpan sebelum navigasi/konfirmasi.
- [ ] Konfirmasi isi dan konfirmasi penerimaan menghitung ulang kelengkapan di server. Periode kedaluwarsa menolak tulis, tetapi tidak menolak transisi bila isian sudah lengkap.
- [ ] Perpanjangan hanya kepala sekolah, wajib alasan, tanggal baru lebih besar dari tanggal lama dan hari ini; audit tercatat. Tidak membuka status 3/4.
- [ ] Tidak menyediakan pembatalan persetujuan, pengembalian status, revisi, atau bypass admin/superadmin untuk membuka nilai terkunci.

## 5. Login, RBAC, dan persetujuan

- [ ] Petakan tugas Koordinator Al-Quran dan Koordinator Bahasa Inggris ke model akun/role yang ada tanpa diam-diam mengubah empat jabatan karyawan. Bedakan jabatan, role, permission, dan cakupan dokumen.
- [ ] Audit akun karyawan → login → role → menu; perubahan permission berlaku setelah login ulang. Pertahankan tes akun nonaktif, perubahan password, dan regresi modul nonrapor.
- [ ] Guru kelas mengisi seluruh paket muridnya; tidak membuat guru mata pelajaran terpisah. Cocokkan kewenangan Guru Shadow dengan penanggung jawab sesi, jangan otomatis memperluas akses.
- [ ] Buat persetujuan koordinator Quran khusus Ummi dan koordinator BING khusus BING secara paralel pada urutan 1; kepala sekolah seluruh paket pada urutan 2 setelah keduanya selesai.
- [ ] Jangan mempertahankan persetujuan langsung Admin dari alur lama atau mengizinkan kepala sekolah melewati koordinator. Terapkan cakupan baca/tulis di backend, bukan hanya tombol.
- [ ] Snapshot guru saat konfirmasi penerimaan; snapshot approver ketika menyetujui: nama, NUPTK, dan referensi gambar tanda tangan. Gambar tidak tersedia boleh nama saja sesuai spesifikasi.
- [ ] Tolak persetujuan salah urutan, lintas cakupan, tanpa izin, CSRF salah, dan request ganda/paralel. Hasil final ditentukan seluruh baris persetujuan, bukan nama role hardcoded.

## 6. UI wizard dan integrasi halaman

- [ ] Integrasikan daftar template dengan jenis dokumen dan periode; PPI tersedia dalam katalog tetapi hanya masuk paket ABK. Hindari placeholder dianggap rubrik resmi/RAS.
- [ ] Dashboard dan Daftar Murid Guru menampilkan sesi milik guru, agenda, progres/status, dan navigasi pengisian/pratinjau yang sesuai. Membuka halaman tidak menciptakan nilai atau status palsu.
- [ ] Wizard empat/lima tahap dengan nama murid tetap, periode, kemajuan per bagian, status autosave, Arsip, konfirmasi isi, dan konfirmasi penerimaan sebagai aksi berbeda.
- [ ] Gunakan komponen select/textarea/card/button/modal/teks yang ada, collapse area/subarea, pilihan panjang yang terbaca, header serta action bar mobile yang telah disepakati.
- [ ] Saat RTS Genap diisi, nilai Ganjil tampil berdampingan read-only tanpa harus pindah tab. Tampilkan nilai/catatan periode terdahulu sesuai aturan tiap dokumen tanpa autofill atau tombol salin khusus.
- [ ] Pesan kelengkapan menunjuk tahap, bagian, jumlah kosong, dan tautan lompat ke isian. Status dan progres ditampilkan terpisah.
- [ ] Rapor Murid admin memakai satu tabel per periode, tanpa card bertumpuk; pratinjau/aksi mengikuti status dan cakupan approver. Konfirmasi final menyebut murid, periode, serta sifat permanennya.
- [ ] Uji desktop/mobile, keyboard/fokus, dropdown panjang, collapse, reload, koneksi terputus, dan navigasi saat autosave belum selesai. Catat jika tes browser belum dapat dijalankan.

## 7. Pratinjau, PDF, dan penerbitan

- [ ] Rancang renderer per dokumen sejak skema, implementasikan setelah penyimpanan dan alur stabil. Pratinjau dan PDF memakai sumber data yang sama; jangan hardcode identitas, tanggal, kota, atau nilai.
- [ ] RTS: A4 tegak, acuan delapan halaman, pagination mengalir; kop/identitas/header tabel berulang, legenda hanya halaman pertama, dua blok tanda tangan sesuai sesi masing-masing.
- [ ] Agama: cetak seluruh 73 butir dengan nilai semester sebelumnya, tujuh kolom per periode, header bertingkat, dan enam catatan yang dirangkai untuk periode terbit.
- [ ] Ummi: dua kolom Tengah/Akhir pada bacaan; daftar tes kumulatif semester sampai periode terbit; catatan periode terbit; blok PRA mengikuti sakelar periode; tanpa Hafalan.
- [ ] BING: satu periode per cetakan, lima nilai, empat komentar, remarks dan penandatangan sesuai versi teks yang diselaraskan.
- [ ] PPI: A4 dengan margin/lebar kolom dari seed, tabel berulang, teks mentah diubah menjadi butir hanya saat render. Usia dihitung pada tanggal pengesahan, bukan tanggal cetak ulang. Hasil Capaian kosong saat pilot.
- [ ] Prefinal hanya pratinjau bertanda draft tanpa cap tanda tangan; unduhan final per dokumen/paket hanya setelah `SELESAI`. Cegah nilai periode mendatang bocor ke cetak ulang periode lama.
- [ ] Terbitkan paket secara atomik: kegagalan salah satu PDF tidak memfinalkan sebagian paket. Simpan artefak final, snapshot, tempat, dan tanggal pengesahan konsisten; retry tidak menggandakan persetujuan/penerbitan.
- [ ] Siapkan pengiriman PDF melalui email dan WhatsApp sesuai spesifikasi; tentukan integrasi dan konfigurasi yang tersedia, catat kegagalan/retry tanpa membuka kembali nilai. Jangan mengklaim kanal aktif sebelum diuji; pengujian tidak mengirim ke orang tua nyata.
- [ ] Periksa visual PDF, simbol SVG, font, pemenggalan tabel, watermark, ukuran berkas, cetak ulang historis, dan kompatibilitas shared hosting.

## 8. Gerbang pengujian dan rilis

- [ ] Buat fixture terisolasi berurutan: tahun/periode → akun/role → kelas/murid/penugasan → rubrik → sesi/paket → nilai → persetujuan → artefak. Sertakan Regular, ABK, guru kosong, dan beberapa guru/kelas.
- [ ] Uji CRUD/validasi tiap jenis isian, clear whitespace, skala tidak sah, lintas rubrik/semester, dan log lama/baru beserta aktor/status. Uji semua jumlah kelengkapan tepat.
- [ ] E2E Regular dan ABK: autosave parsial → lanjut → konfirmasi isi → sunting saat diskusi → konfirmasi penerimaan → koordinator paralel → kepala sekolah → PDF paket.
- [ ] Uji empat periode; E2E akhir semester baru dapat dinyatakan lengkap setelah RAS tersedia. Nilai/rubrik dummy bukan pengganti validasi RAS resmi.
- [ ] Uji tenggat/perpanjangan, nilai Ganjil tetap ketika Genap diisi, pergantian guru/kepala sekolah, rubrik berversi, dan cetak ulang tanpa perubahan historis.
- [ ] Uji request bersamaan: autosave vs lock, dua konfirmasi, dua persetujuan, serta kegagalan PDF di tengah paket. Pastikan rollback, idempotensi, dan penolakan tidak mengubah data.
- [ ] Uji matriks izin setiap menu/aksi melalui UI dan HTTP langsung, termasuk akses lintas guru/murid/dokumen dan pembatasan approver.
- [ ] Jalankan regresi modul Sekolah, Karyawan, Jabatan, Guru, Murid, Kelas, Periode, login, dan RBAC. Periksa navigasi/sidebar serta design system tetap konsisten.
- [ ] Bedakan bukti lint/unit, integrasi database, HTTP, browser visual, dan PDF visual. Checkbox hanya ditutup untuk pengujian yang benar-benar dijalankan.
- [ ] Uji restore+migrasi pada salinan data, backup produksi, rencana pemulihan, konfigurasi hosting, dan smoke test setelah deploy dengan izin pengguna.
- [ ] Perbarui schema/architecture, design system untuk komponen baru, panduan guru/approver, deployment, serta laporan hasil pengujian dan batas cakupan.

## 9. Setelah pilot — bukan syarat rilis awal

- [ ] Implementasikan tinjauan PPI: jangka pendek dari periode tepat sebelumnya; jangka panjang dari slot yang sama pada tahun ajaran sebelumnya, bukan mundur empat baris.
- [ ] Simpan Hasil Capaian terpisah di `ppi_capaian`, per aspek/horizon/pengisi. Tidak membuka rencana atau mengubah status sesi final; tetap diaudit.
- [ ] Tentukan UI guru, kanal/pemicu pengiriman tautan orang tua, masa berlaku, kebijakan tidak diisi, dan cara mengambil PDF PPI terbaru.
- [ ] Tautan orang tua tanpa akun dibatasi satu murid/rencana/horizon, token acak kedaluwarsa dan sekali pakai setelah submit; tidak membuka dokumen lain atau isian guru.
- [ ] Uji pergantian tahun/guru, periode tanpa rencana, murid baru ABK, token salah/kedaluwarsa/dipakai ulang, dan versi PDF setelah capaian diperbarui.

## Langkah pengerjaan berikutnya

Mulai **Fase 1: audit gap implementasi dan rencana migrasi**, lalu Fase 2–3 sebelum mengubah form guru. Pemetaan RBAC/persetujuan dapat dirancang bersamaan dengan fondasi; implementasi UI bergantung pada layanan data yang sudah teruji. Belum ada instruksi untuk menjalankan migrasi atau deployment melalui TODO ini.

### Hasil batch awal implementasi (24 September 2026)

- [x] Tambahkan `EraporLegacyAudit` dan CLI `database/audit-erapor.php`: agregat read-only, validasi relasi/identitas legacy, tanpa konversi otomatis.
- [x] Tambahkan fondasi aturan murni `EraporSessionPolicy`: empat status, tanggal/tenggat, teks mentah, identitas periode, urutan approval paralel, dan gerbang finalisasi. **Belum terhubung ke route/storage; bukan pengganti guard transaksi/RBAC.**
- [x] `php tests/erapor-legacy-audit.php` lulus pada SQLite in-memory.
- [x] `php tests/erapor-session-policy.php` lulus 65 assertion.
- [x] `php tests/report-workflow-regression.php` lulus; lint ketiga file PHP implementasi lulus.
- [x] Audit agregat database lokal setelah MySQL dinyalakan: 57 rapor, 1.000 nilai, 56 catatan; 45 belum diisi, 2 menunggu persetujuan, 10 disetujui. Ditemukan 5 rapor tanpa guru dan 3 template kosong; pengecekan identitas/duplikasi/tanggal periode serta relasi nilai yang tersedia tidak menemukan anomali. Ini bukan izin konversi otomatis; investigasi pengecualian, backup/restore dan mapping rubrik masih diperlukan.
- [ ] Commit/push: dicoba sesuai instruksi pengguna, tetapi `.git/index.lock` ditolak permission dan koneksi GitHub gagal melalui proxy lingkungan. Belum ada commit atau push berhasil; jangan mengakali proteksi filesystem/proxy.

Lanjutan terdekat: investigasi pengecualian preflight dan verifikasi backup/restore; tetapkan DDL additive tanpa collision, lalu seeder rubrik resmi dan tes integrasi. Aturan murni baru digunakan oleh layanan penyimpanan transaksional sebelum UI baru diaktifkan. Folder spesifikasi milik pengguna tetap belum dimasukkan ke staging secara massal; berisi contoh data murid.

### Batch katalog dan verifikasi restore (24 September 2026)

- [x] Verifikasi push pengguna: working tree awal bersih pada `f224283`.
- [x] Perinci pengecualian tanpa menampilkan identitas: lima rapor tanpa guru adalah `belum_diisi`, tanpa nilai dan tanpa penugasan murid saat ini. Tiga template system kosong: ID 2 dipakai enam rapor, ID 3/4 belum dipakai. Tidak diperbaiki dengan penghapusan atau penugasan tebakan.
- [x] Backup lokal baru berhasil diekspor dan direstore: `database/backups/zivana-local-20260924-034137-b72e2a.sql` (beserta gzip/manifest), tetap diabaikan Git.
- [x] Tambahkan DDL empat tabel katalog `erapor_rubrik`, `erapor_rubrik_bagian`, `erapor_rubrik_periode`, `erapor_rubrik_penandatangan`; prefix mencegah collision dengan struktur lama. Ini baru bagian katalog dari Fase 2, bukan seluruh fondasi data sesi.
- [x] Tambahkan migration runner dengan ledger checksum, advisory lock, pengulangan aman, dan penolakan migrasi parsial/collision. Tidak melakukan rollback DDL semu atau mengadopsi tabel asing.
- [x] Tambahkan CLI rencana read-only `php database/plan-erapor-migration.php`, tanpa koneksi database; tidak ada endpoint migrasi publik.
- [x] Tes MySQL `tests/erapor-catalog-mysql.php --run --backup=...sql` lulus 17 pemeriksaan pada database disposable. Restore dibandingkan isi (bukan hanya jumlah); seluruh 24 tabel legacy dan sumber lokal tidak berubah.
- [x] Ulangi tes policy (65 assertion), audit legacy, serta workflow lama: lulus. Database disposable dibersihkan setelah tes; backup tetap disimpan.
- [ ] Terapkan katalog ke database aplikasi: **belum dilakukan**. Belum ada seed resmi, perubahan UI, cutover route, atau deployment hosting.

Lanjutan: tabel rubrik khusus beserta validasi seed, adapter periode dari sumber kalender yang sama, kemudian data dokumen/sesi dan guard transaksional. Perbedaan teks sumber/seed BING/Agama/Ummi tetap dicatat sebelum seeding final; tidak mengubah teks sepihak.

### Batch struktur dan seeder RTS (24 September 2026)

- [x] Tambahkan migrasi additive `20260924_erapor_rts.sql`: area, subarea, grup opsional, indikator, skala, dan seed history. FK komposit menolak grup dari subarea berbeda; kode indikator unik, teks tujuan tidak wajib unik.
- [x] Implementasikan `EraporRtsSeed` khusus RTS V1: validasi struktur/jumlah sebelum tulis, transaksi seluruh impor, lock bersama runner migrasi, checksum sumber dan isi tersimpan, retry no-op, tanpa update/upsert rubrik lama.
- [x] Seed resmi di database uji menghasilkan 175 indikator, 8 area, 21 subarea bernama + 4 implisit, 2 grup, dan 4 skala. Setiap tujuan/aparatus dibandingkan dengan sumber, bukan hanya hitungan total.
- [x] Uji rollback impor gagal di tengah, sumber berubah, drift data, retry rubrik terkunci, FK grup salah, serta teks tujuan sama dengan kode berbeda. `tests/erapor-catalog-mysql.php` kini lulus 214 pemeriksaan; validator RTS mandiri lulus 11 skenario.
- [x] Tambahkan CLI `database/validate-erapor-rts.php` tanpa akses DB dan perluas rencana migrasi untuk katalog + RTS.
- [ ] Seed/migrasi RTS pada database aplikasi belum dijalankan. Guard perubahan saat penilaian pertama, tabel nilai, API, serta penggunaan pada UI belum selesai; checksum seeder bukan pengganti guard penyimpanan penilaian.

Lanjutan batch berikutnya: struktur/seeder Ummi dan PPI, kemudian Agama/BING sambil menyelaraskan perbedaan versi sumber. RAS tetap menunggu spesifikasi. Tidak ada kurikulum atau nilai legacy yang ditimpa.

### Batch struktur dan seeder Ummi/PPI (24 September 2026)

- [x] Tambahkan enam tabel definisi melalui `20260924_erapor_ummi_ppi.sql`, setelah katalog dan RTS (seed history bersama). Tidak membuat tabel nilai atau mengubah tabel legacy.
- [x] `EraporUmmiPpiSeed`: validasi V1, transaksi impor, advisory lock bersama, retry no-op, penolakan perubahan sumber/drift, termasuk rubrik berstatus terkunci.
- [x] Ummi: tujuh jilid, 27 materi persis seed resmi, 12 skala A+ sampai D-, A/B opsional dan C wajib. Urutan bagian mengikuti posisi array resmi; PRA TK hanya penanda definisi, belum sakelar penilaian per periode. Tidak mengimpor Hafalan.
- [x] PPI: lima aspek, delapan kolom cetak; enam kolom sesi menghasilkan 30 isian wajib. Dua kolom Hasil Capaian tetap di luar sesi. Simpan label multiline, grup, lebar kolom, dan snapshot sumber untuk metadata cetak/identitas.
- [x] Validator mandiri lulus 20 skenario. Integrasi MySQL kini lulus 291 pemeriksaan: rollback, lock, constraint, teks/metadata sumber, retry, drift, dan checksum legacy/sumber lokal tidak berubah. Regresi policy (65), RTS (11), audit dan workflow lama lulus.
- [x] CLI read-only `database/validate-erapor-ummi-ppi.php`; rencana migrasi kini katalog → RTS → Ummi/PPI.
- [ ] Migrasi/seed database aplikasi, penyimpanan nilai, kelengkapan sesi, UI/PDF dan tinjauan PPI belum diterapkan. Perbedaan ketentuan cetak baris tes Ummi kosong masih menunggu penyelarasan; tidak diputuskan oleh seeder ini.

Berikutnya: struktur/seeder Agama dan BING, dengan mempertahankan catatan perbedaan versi sumber. Setelah definisi siap, lanjut identitas dokumen/sesi dan penyimpanan penilaian transaksional. RAS tetap membutuhkan spesifikasi resmi.

### Batch struktur dan seeder BING (24 September 2026)

- [x] Tambahkan tiga tabel definisi BING: skala, indikator, komentar; tidak menambah tabel nilai murid atau mengubah tabel lama.
- [x] Seeder BING V1 mempertahankan teks/kode seed resmi, termasuk perbedaan ejaan dan definisi dengan PDF revisi. Snapshot menyimpan identitas, grup, teks tetap dan metadata cetak. Tidak mengimpor narasi murid contoh.
- [x] Validasi 5 indikator nilai, 4 komentar wajib, 4 skala berurutan Excellent > Outstanding > Good > Fair. Kehadiran tetap skala, bukan angka; Speaking Test Result hanya kepala kelompok.
- [x] Uji validator 19 skenario; harness MySQL kini 328 pemeriksaan lulus (rollback impor, lock, retry, perubahan sumber, drift isi, status terkunci, FK/unique/check, teks persis sumber). Regresi policy, audit, RTS, Ummi/PPI dan workflow lama lulus.
- [x] Rencana migrasi read-only ditambah BING setelah Ummi/PPI; tersedia CLI validasi tanpa DB `database/validate-erapor-bing.php`.
- [ ] Aktivasi BING ke aplikasi/PDF tetap belum dilakukan. Selaraskan teks PDF revisi dan seed sebelum seeding final; jangan mengubah kode indikator yang dibekukan.

Berikutnya: struktur/seeder Agama (73 butir terikat semester, tujuh pilihan tahapan, 99 nama Asmaul Husna dalam 10 kelompok, enam catatan wajib). Setelah itu lanjut fondasi dokumen/sesi dan penyimpanan nilai. Batch BING belum mengubah UI atau database aplikasi.

### Batch struktur dan seeder Agama (24 September 2026)

- [x] Tambahkan tujuh tabel definisi Agama: lingkup, sub, item, nama dalam kelompok, tahapan, subtingkat, dan pemetaan pilihan dropdown ke kolom cetak.
- [x] Seeder V1 memvalidasi 73 butir (37 Ganjil/36 Genap), 6 lingkup, 8 sub (3 bernama/5 implisit), 99 nama dalam 10 butir Asmaul Husna, 5 tahapan, 3 subtingkat, 7 pilihan. Teks resmi dan kode tidak diubah.
- [x] Tahapan/subtingkat disimpan terpisah. FK komposit dan CHECK menolak subtingkat di luar Tahfizh, Tahfizh tanpa subtingkat, kode subtingkat yang tidak cocok, dan referensi lintas rubrik.
- [x] Keenam lingkup memiliki penanda catatan wajib (berdasarkan spesifikasi bagian 5.2/7.2); belum menyimpan catatan murid atau merangkai paragraf narasi.
- [x] Validator 22 skenario lulus; harness MySQL 514 pemeriksaan lulus termasuk rollback impor, lock, retry, perubahan sumber/drift, rubrik terkunci, seluruh teks butir/semester/nama kelompok, dan constraint relasi. Checksum database asli/legacy tetap identik.
- [x] CLI validasi read-only `database/validate-erapor-agama.php`; rencana migrasi mencakup kelima batch katalog/rubrik.
- [ ] Integrasi penilaian Agama, kelengkapan sesi, narasi/cetakan, dan penerapan ke database aplikasi belum dilakukan. Konflik perbaikan narasi historis versus pembekuan dokumen final tetap dicatat; tidak mengubah rapor lama.

Berikutnya: adapter kalender dari tahun_ajaran/periode_penilaian yang sudah ada, identitas dokumen dan sesi/paket Regular–ABK, kemudian penyimpanan penilaian transaksional. Kelima seeder definisi sudah diuji; ini belum berarti alur rapor baru aktif. RAS dan penyelarasan teks BING/aturan cetak Ummi tetap terbuka.

### Batch preflight kalender dan paket (24 September 2026)

- [x] Adapter read-only `EraporCalendar` memakai kalender lama tanpa tabel duplikat; normalisasi empat slot, validasi tahun/tanggal, penolakan semester kosong dan slot duplikat. Slot yang belum ada dilaporkan, tidak dibuat atau ditebak.
- [x] Komposisi awal berversi ditetapkan dalam `config/erapor-package.php`; binding RAS sengaja kosong. Ini data bootstrap internal, belum aktivasi atau pengganti snapshot sesi-dokumen.
- [x] `EraporPackagePlan` menghasilkan rencana identitas sesi murid/periode dan dokumen murid/tahun/rubrik/semester. RTS/Agama memakai TAHUNAN, Ummi/BING/PPI semester konkret. PPI hanya untuk ABK.
- [x] Rubrik hilang menyebabkan daftar dokumen kosong dan daftar blocker eksplisit; tidak mengembalikan paket parsial yang bisa dianggap lengkap. Rubrik arsip, metadata tidak cocok, kode duplikat, kondisi murid tidak dikenal ditolak.
- [x] 29 pemeriksaan unit/SQLite serta total 552 pemeriksaan MySQL lulus. Kalender/paket dibaca dari salinan database nyata, checksum tidak berubah. Policy 65 assertion dan regresi workflow lama juga lulus.
- [ ] Persistensi dokumen/sesi, unique constraint antiduplikasi, snapshot paket/penugasan, transaksi dan izin pembuatan belum diimplementasikan. Rencana deterministik bukan pengganti constraint database atau guard concurrency.

Berikutnya: DDL additive dokumen/sesi/sesi-dokumen dan layanan pembentukan transaksional memakai preflight ini. Belum ada route UI baru atau sesi rapor yang dibuat pada database aplikasi.

### Batch persistensi identitas dan pembentukan sesi (24 September 2026)

- [x] Migrasi additive `20260924_erapor_sessions.sql`: dokumen tanpa status, sesi, penghubung sesi-dokumen, log sesi. Identitas dokumen/sesi memiliki UNIQUE; FK komposit penghubung menolak murid/tahun/semester yang tidak sesuai.
- [x] `EraporSessionFactory` memakai transaksi dan lock bersama migrasi/seeder. Membaca penugasan/kalender/katalog dari database; hanya guru aktif yang ditugaskan di kelas murid dapat membentuk sesi. Katalog tanpa seed history tidak dipilih.
- [x] Snapshot komposisi, kondisi, kelas, dan akun guru saat pembuatan; hash komposisi memeriksa drift ketika diulang. Dokumen tahunan dipakai ulang; semester konkret menghasilkan identitas berbeda. Referensi pertama menandai rubrik terkunci.
- [x] Pembuatan gagal di tengah membatalkan dokumen, sesi, penghubung, perubahan status rubrik dan log. Retry tidak menulis log/timestamp baru, tidak membuka sesi SELESAI, dan tidak merekonstruksi paket historis dari konfigurasi terbaru.
- [x] Total 579 pemeriksaan MySQL lulus: Regular/ABK, dua semester, rollback lewat trigger log, competing advisory lock, unique constraint, relasi lintas murid, guru tidak ditugaskan, paket drift, dan RAS hilang. Regresi kalender/policy/seed/audit/workflow lama lulus.
- [ ] Belum dipasang ke route/UI/database aplikasi. Route harus memakai actor dari autentikasi dan memeriksa permission RBAC; factory bukan endpoint dan tidak mengizinkan admin membypass kepemilikan.
- [ ] Sebelum aktivasi: guard edit/hapus kalender/penugasan legacy terhadap sesi baru, seluruh guard edit definisi, snapshot penerimaan/approval/signature, audit nilai, serta layanan pengisian/status masih diperlukan. FK RESTRICT baru dapat memengaruhi penghapusan murid/kelas/periode; tampilkan pesan penolakan yang sesuai saat integrasi.

Berikutnya: fondasi penyimpanan nilai per periode dan jejak perubahan secara transaksional, dengan guard kepemilikan/sesi/tenggat dan kelengkapan. Belum ada nilai, approval atau PDF baru yang diaktifkan pada aplikasi.

### Batch penyimpanan dan audit nilai RTS (24 September 2026)

- [x] Migrasi `20260924_erapor_rts_values.sql`: nilai RTS per sesi/dokumen/indikator serta log isian lama/baru, aktor, waktu dan status sesi. FK penghubung memastikan dokumen berada di sesi yang dituju.
- [x] Service internal `EraporRtsEntry` menyimpan batch secara atomik dengan lock, validasi guru snapshot + penugasan aktif, konsistensi kalender/paket, dokumen RTS dan indikator/skala rubrik tersebut.
- [x] Guard BELUM_DIISI/TELAH_DIISI dan tenggat memakai policy. MENUNGGU_TTD/SELESAI ditolak; clear menjadi DELETE tanpa menurunkan status. Tanggal server Asia/Makassar; clock khusus pengujian tidak boleh berasal dari request.
- [x] Expected-value check menolak autosave basi yang berbeda; retry nilai identik no-op tanpa log/timestamp baru. Batch dengan satu item tidak sah atau audit gagal dirollback seluruhnya.
- [x] Kelengkapan RTS dihitung dari indikator aktif rubrik (175 pada V1); nilai sesi/semester lain tidak terhitung atau ditimpa. Tidak menghitung rata-rata.
- [x] Total 609 pemeriksaan integrasi MySQL lulus, termasuk update/clear, 175 nilai lengkap, tenggat, sesi terkunci, guru lain, dokumen lain, paket drift, audit rollback, expected-value conflict, lock, UNIQUE/FK dan isolasi semester. Regresi policy/kalender/seed/audit/workflow lama serta lint lulus.
- [ ] Belum terhubung HTTP/UI/database aplikasi. Permission RBAC/CSRF di route, pembacaan form, perpanjangan tenggat teraudit, penilaian dokumen lain, kelengkapan paket dan transisi status transaksional masih perlu dikerjakan.

Berikutnya: storage nilai/teks BING dan PPI dengan preservasi teks mentah, lalu Ummi/Agama, sebelum penggabungan kelengkapan paket dan integrasi portal guru.

### Batch penyimpanan BING/PPI (24 September 2026)

- [x] Migrasi additive tiga tabel: nilai BING, komentar BING, isian PPI; seluruh isian terikat sesi/dokumen, tidak memodifikasi nilai legacy.
- [x] Service internal `EraporStructuredEntry` memakai transaksi, lock, kepemilikan guru/penugasan aktif, kalender, hash paket, status dan tenggat. Actor/clock hanya dari konteks server tepercaya; belum route publik.
- [x] BING memakai kode skala resmi (bukan angka kehadiran); 5 nilai + 4 komentar wajib. PPI memakai 5 aspek × 6 kolom sesi = 30 isian; Hasil Capaian ditolak, termasuk permintaan clear.
- [x] Teks UTF-8 disimpan persis (spasi, CRLF/newline, tanda baca, aksara Arab, emoji). Whitespace-only dihapus; tipe salah, UTF-8 tidak sah dan teks melampaui kapasitas TEXT ditolak. Audit nilai lama/baru, actor, waktu dan status mengikuti transaksi.
- [x] Expected-value conflict, retry no-op, clear tanpa downgrade, rollback audit/batch, deadline, sesi terkunci, lock bersaing, kondisi Regular vs ABK, FK/unique dan isolasi dua semester teruji. Kelengkapan tidak menghitung nilai/teks sesi lain.
- [x] Total 715 pemeriksaan integrasi MySQL lulus; seluruh regresi kalender/policy/seed/audit/workflow lama lulus. Checksum database asli dan tabel legacy tetap identik.
- [ ] Belum menerapkan migrasi pada database aplikasi atau menghubungkan UI/HTTP. Pembacaan form, RBAC/CSRF route, perpanjangan tenggat, kelengkapan paket/transisi, PDF dan tinjauan PPI tetap belum aktif.

Berikutnya: penyimpanan Ummi (bacaan, tes dinamis, catatan, sakelar PRA TK) dan Agama (tahapan/subtingkat dan catatan per lingkup), kemudian kelengkapan paket sebelum integrasi portal guru.

### Batch penyimpanan Agama (24 September 2026)

- [x] Migrasi `20260924_erapor_agama_values.sql`: nilai dengan tahapan/subtingkat terpisah dan catatan per lingkup, terikat sesi/dokumen. CHECK/FK menolak Tahfizh tanpa subtingkat, subtingkat pada tahap lain, dan pasangan kode/ID yang tidak cocok.
- [x] Service internal `EraporAgamaEntry` menyimpan batch nilai + catatan + audit secara atomik, memakai guard kepemilikan/penugasan, kalender/paket, status dan tenggat seperti service sebelumnya.
- [x] Pilihan dropdown dipetakan dari data definisi, bukan tujuh boolean. Nilai hanya diterima untuk butir aktif semester sesi; enam catatan wajib disimpan mentah per sesi, bukan satu paragraf hasil rangkaian.
- [x] Kelengkapan V1: Ganjil 37 nilai + 6 catatan = 43; Genap 36 + 6 = 42. Dokumen tahunan tetap sama, nilai/catatan periode berbeda tidak saling menimpa.
- [x] Total 818 pemeriksaan MySQL lulus: seluruh pilihan, update keluar/masuk Tahfizh, clear/refill tanpa downgrade, whitespace Unicode, teks utuh, stale write/retry, audit/batch rollback, deadline/status/actor, semester salah, lock, FK/unique dan isolasi Ganjil/Genap.
- [ ] Belum diterapkan ke database aplikasi/HTTP/UI. Perangkaian narasi, print seluruh butir, approval, perpanjangan, dan kelengkapan paket tetap belum aktif.

Berikutnya: penyimpanan Ummi beserta tes dinamis dan sakelar PRA TK, lalu agregasi kelengkapan paket/transisi status transaksional sebelum integrasi portal guru.

### Batch penyimpanan Ummi (24 September 2026)

- [x] Migrasi empat tabel: bacaan per materi, catatan, flag PRA TK per sesi, dan kejadian tes dinamis. Skala bacaan/tes memakai dua belas kode resmi; tidak menambahkan Hafalan.
- [x] Service internal `EraporUmmiEntry` menangani batch atomik, expected-value conflict, retry no-op, audit, kepemilikan/penugasan, kalender/paket, status dan tenggat.
- [x] Catatan Guru satu-satunya wajib. Bacaan boleh kosong dan tes boleh nol atau lebih dari dua. Teks mentah dipertahankan; whitespace-only dihapus tanpa menurunkan status.
- [x] Flag default false, tidak ditebak dari umur/kelas. Inisialisasi eksplisit saat membuka sesi memakai write yang diaudit; Akhir mengambil flag Tengah pada dokumen/semester sama sekali saja. Perubahan berikutnya tidak merambat; mematikan flag tidak menghapus nilai PRA.
- [x] Baris tes memiliki token retry, tanggal, jilid, skala dan urutan. Tambah/ubah/hapus teraudit, termasuk payload lama/baru; token dan isi yang sama tidak menghasilkan baris ganda.
- [x] Total 884 pemeriksaan MySQL lulus, termasuk seluruh 12 skala, optional completion, raw text, dynamic tests, audit/batch rollback, FK/unique, lock, status/tenggat/kepemilikan, isolasi semester dan pewarisan flag. Seluruh regresi terdahulu lulus.
- [ ] Pewarisan Akhir diuji dengan fixture Ummi-only khusus, bukan paket akhir lengkap. Factory produksi masih menolak AKHIR tanpa RAS. Belum diterapkan ke database aplikasi/HTTP/UI.

Berikutnya: agregasi kelengkapan semua dokumen dan perpindahan status transaksional. Pembacaan form, perpanjangan, snapshot penerimaan/approval, dan integrasi portal guru tetap belum aktif.

### Batch kelengkapan paket dan konfirmasi isi (24 September 2026)

- [x] `EraporCompleteness` membaca nilai tersimpan seluruh dokumen wajib dan mengembalikan required/filled/complete serta key/label isian kosong per dokumen. Tidak menerima boolean lengkap dari klien atau memanggil autosave untuk menghitung.
- [x] Memeriksa hash/komposisi paket, metadata dokumen dan definisi kosong. Regular empat dokumen, ABK lima; paket Ummi-only sintetis tidak dapat lolos sebagai paket akhir lengkap. RAS belum didukung.
- [x] `EraporConfirmFilled` melakukan BELUM_DIISI → TELAH_DIISI dengan kepemilikan/penugasan aktif, konsistensi kalender, advisory lock dan transaksi status + audit. Tidak memblokir konfirmasi lengkap setelah tenggat.
- [x] Retry sah TELAH_DIISI memerlukan jejak KONFIRMASI_ISI dan tidak membuat log ganda. Clear saat diskusi dapat membuat completeness false tanpa menurunkan status. Sesi MENUNGGU_TTD/SELESAI tidak bisa dibuka oleh operasi ini.
- [x] Total 913 pemeriksaan MySQL lulus: paket Regular/ABK, missing field labels, optional Ummi, PPI tanpa outcomes, rollback audit, lock, actor salah, retry, deadline lewat, clear/refill, drift dan paket parsial. Seluruh regresi sebelumnya lulus.
- [ ] Belum aktivasi database aplikasi/HTTP/UI. Konfirmasi penerimaan TELAH_DIISI → MENUNGGU_TTD harus menambahkan snapshot guru/TTD dan pembentukan alur persetujuan; belum tersedia dalam service ini.

Berikutnya: snapshot penerimaan dan alur approver per sesi, lalu perpanjangan tenggat serta endpoint pembacaan/form sebelum integrasi portal guru.

### Batch fondasi alur persetujuan (24 September 2026)

- [x] Migrasi lima tabel konfigurasi alur/cakupan, penugasan user eksplisit, dan salinan alur/cakupan dokumen per sesi. Tidak menunjuk akun atau mengubah jabatan secara otomatis.
- [x] `EraporApprovalPlan` memvalidasi koordinator Quran hanya Ummi, koordinator BING hanya BING (paralel urutan 1), kepala sekolah seluruh paket (urutan 2). Konfigurasi wajib tidak aktif/tidak lengkap ditolak.
- [x] Relasi komposit mencegah cakupan dokumen lintas sesi. Salinan label/urutan/cakupan tidak mengikuti perubahan konfigurasi selanjutnya.
- [x] 16 pemeriksaan planner dan total 927 pemeriksaan MySQL lulus; seluruh regresi sebelumnya lulus. Fixture approval hanya di database disposable; data aplikasi tidak berubah.
- [ ] Belum konfirmasi penerimaan, snapshot identitas/NUPTK/TTD guru/penyetuju, layanan approval, maupun UI penugasan. Planner bukan otorisasi dan tidak memeriksa kelengkapan nilai sendiri.

Berikutnya: lengkapi sumber profil tanda tangan dan snapshot penerimaan transaksional sebelum mengaktifkan perpindahan TELAH_DIISI ke MENUNGGU_TTD. Penugasan koordinator harus eksplisit, bukan disimpulkan dari akun admin/guru yang tersedia.

### Batch konfirmasi penerimaan (24 September 2026)

- [x] Migrasi additive profil penandatangan dan snapshot penerimaan. Profil memperluas akun pegawai tanpa mengubah tabel legacy; NUPTK dan tanda tangan boleh kosong.
- [x] `EraporSignerSnapshot`: nama UTF-8, NUPTK opsional 16 digit (nol awal dipertahankan), gambar PNG privat maksimal 2 MB/4096 px dan waktu persetujuan pemilik; salinan bytes dan SHA-256 tidak bergantung pada URL/path yang berubah.
- [x] `EraporConfirmReception`: guru aktif dan masih ditugaskan, status TELAH_DIISI dengan jejak konfirmasi isi, kalender/paket valid dan seluruh nilai wajib lengkap. Snapshot guru, tiga alur/cakupan persetujuan, status MENUNGGU_TTD dan audit disimpan atomik.
- [x] Tenggat tidak menghalangi konfirmasi lengkap. Retry tidak mengambil ulang profil/konfigurasi; tidak membuka penilaian yang sudah terkunci. Tidak memilih akun koordinator secara otomatis.
- [x] 956 pemeriksaan MySQL, 16 pemeriksaan snapshot serta regresi sebelumnya lulus. Termasuk rollback audit, isian kosong, guru salah/nonaktif, perubahan nama/profil, konfigurasi tidak aktif, lock, retry, batas status dan penguncian autosave.
- [ ] Belum diterapkan ke database aplikasi/route/UI. Form profil/unggah PNG dan persetujuan pemilik belum tersedia; data gambar pada pengujian hanya fixture. Layanan persetujuan koordinator/kepala sekolah dan PDF belum aktif.

Berikutnya: layanan approval dengan otorisasi penugasan eksplisit, urutan paralel/sekuensial dan snapshot penyetuju; kemudian perpanjangan tenggat serta endpoint/UI portal guru.

### Batch aksi persetujuan (24 September 2026)

- [x] `EraporApprove::approve`: akun/pegawai/jabatan aktif dan penugasan penyetuju eksplisit; jabatan Kepala Sekolah diperlukan untuk persetujuan kepala sekolah. Tidak ada bypass admin.
- [x] Memakai alur dan cakupan yang disalin saat penerimaan, bukan konfigurasi flow terbaru. Mapping kepala sekolah harus memuat seluruh paket; mapping koordinator hanya bidangnya. Hash paket dan jejak penerimaan diperiksa.
- [x] Koordinator paralel, kepala sekolah setelah seluruh koordinator. Status DISETUJUI sebelumnya wajib memiliki snapshot dan jejak audit, bukan flag saja.
- [x] Snapshot nama/NUPTK/PNG/hash/consent, waktu dan aktor persetujuan serta relasi log disimpan atomik. Retry aktor yang sama tidak menandatangani ulang; penugasan yang dicabut tetap ditolak.
- [x] 985 pemeriksaan MySQL dan seluruh regresi sebelumnya lulus: otorisasi, urutan, cakupan, lock, rollback snapshot/audit, tanda tangan opsional, retry, profil berubah, FK dan tidak terbit prematur.
- [ ] Belum aktivasi DB aplikasi/HTTP/UI. Setelah semua menyetujui, sesi tetap MENUNGGU_TTD sampai seluruh PDF siap diterbitkan atomik. Belum layanan publikasi/PDF atau pengelolaan assignment/profil.

Berikutnya: perpanjangan tenggat dengan audit dan kewenangan kepala sekolah; kemudian pembacaan sesi/endpoint portal guru dan integrasi UI. Publikasi PDF tetap tahap terpisah.

### Batch perpanjangan tenggat (24 September 2026)

- [x] `EraporExtendPeriod`: hanya akun/pegawai/jabatan Kepala Sekolah aktif, alasan UTF-8 wajib, tanggal baru setelah maksimum tenggat lama dan hari ini (Asia/Makassar).
- [x] Tanggal akhir pada kalender yang sudah ada dan audit `erapor_periode_perpanjangan` diubah atomik. Tidak membuat kalender kedua atau membuka kunci per murid.
- [x] Preview read-only jumlah sesi per status; simpan memeriksa expected deadline untuk menolak perubahan berbenturan. Retry identik oleh aktor yang sama tidak membuat audit ganda.
- [x] Perpanjangan tidak mengubah status sesi. Autosave dapat kembali bekerja untuk step 1/2, tetap menolak step 3/4.
- [x] Total 1015 pemeriksaan MySQL dan regresi sebelumnya lulus: otorisasi/nonaktif, alasan/tanggal invalid, stale write, rollback audit, lock, retry, preview dan penguncian seluruh status.
- [ ] Belum diterapkan ke database aplikasi/HTTP/UI. Pengujian hanya mengubah kalender pada database disposable lalu memulihkannya; checksum database lokal asli tidak berubah.

Berikutnya: pembacaan sesi dan data form portal guru dengan otorisasi, lalu endpoint dan integrasi UI. Penyiapan/publikasi seluruh PDF secara atomik tetap belum tersedia.

### Batch pembacaan form guru (24 September 2026)

- [x] `EraporTeacherForm::read`: kepemilikan sesi, penugasan guru/kelas dan akun/pegawai/jabatan aktif; validasi identitas kalender serta paket. Tidak memberi akses otomatis kepada admin atau guru lain.
- [x] Transaksi MySQL READ ONLY + REPEATABLE READ untuk snapshot konsisten tanpa mutasi. Membaca form tidak membuat sesi, inisialisasi Ummi, atau audit.
- [x] Definisi terurut, pilihan nilai dan nilai tersimpan RTS/Agama/Ummi/BING/PPI per sesi. Agama hanya semester terkait; PPI hanya ABK dan tanpa Hasil Capaian; dokumen tahunan tidak mencampur nilai sesi lain.
- [x] Status kemampuan edit/konfirmasi dan alasan read-only, kelengkapan serta label isian kosong. Tidak mengirim PNG tanda tangan atau kredensial ke payload form.
- [x] Total 1080 pemeriksaan MySQL dan regresi sebelumnya lulus, termasuk nilai teks utuh, read/save no-op, akses ditolak, batas waktu/status, drift paket, dan GET tanpa inisialisasi flag.
- [ ] Belum HTTP/UI atau migrasi database aplikasi. Riwayat nilai periode lain, pembacaan approver, aktivasi schema dan rendering PDF belum termasuk reader ini.

Berikutnya: adapter endpoint dengan autentikasi/RBAC/CSRF dan kontrak error/payload yang konsisten; integrasikan secara bertahap ke komponen UI portal guru tanpa mengubah rute legacy sebelum kesiapan migrasi diperiksa.

### Batch adapter API guru (24 September 2026)

- [x] Empat route JSON terpisah untuk baca sesi, simpan dokumen, konfirmasi isi, konfirmasi penerimaan. Tidak memakai ID/rute legacy.
- [x] Session aktif, RoleMiddleware lihat/edit/kirim, header CSRF sekali pakai dengan token berikutnya di respons, no-store, validasi JSON/ukuran/field/ID. Actor dan jenis rubrik tidak diterima dari klien.
- [x] Feature flag `ERAPOR_API_ENABLED` default false; tidak memigrasikan/mengaktifkan database aplikasi. Semua route legacy tetap dipertahankan.
- [x] 25 skenario adapter terisolasi, 57 route RBAC, 1092 pemeriksaan MySQL backend serta regresi akun/portal/workflow lulus. Loopback HTTP menguji login, read, autosave, CSRF, kepemilikan lintas guru dan RBAC write.
- [ ] Belum uji browser visual/UI. Endpoint pembuatan/list sesi, approval, perpanjangan, profil dan PDF terpisah dari adapter guru.

Berikutnya: buat read model dashboard/periode dan provisioning sesi untuk halaman portal guru, kemudian integrasikan editor bertahap dengan antrean CSRF/autosave serial.
