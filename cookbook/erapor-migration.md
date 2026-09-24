# eRapor: audit gap dan strategi migrasi

24 September 2026. Rancangan transisi; **belum menjalankan migrasi atau mengganti route produksi**.

## Gap terverifikasi dari kode

| Area | Implementasi lama | Target spesifikasi |
|---|---|---|
| Identitas | `rapor` per murid/sesi pembagian/template | Dokumen per murid/tahun/rubrik/semester; sesi per murid/periode |
| Status | `ReportEntry` mengirim langsung ke menunggu persetujuan | Empat status, konfirmasi isi dan penerimaan berbeda |
| Guru | `rapor.guru_id` menunjuk `karyawan.id` | Penanggung jawab sesi menunjuk `users.id`; perlu mapping eksplisit |
| Nilai | `rapor_nilai` memakai semester dan item template lama | Nilai per periode konkret dan rubrik resmi berversi |
| Catatan | `ReportEntry` melakukan trim; catatan per area | Teks mentah, per periode, bentuk berbeda tiap dokumen |
| Persetujuan | Controller menulis satu approval/status langsung | Dua koordinator sesuai cakupan kemudian kepala sekolah |
| Isi | Satu struktur template area/subkategori/item | Lima skema rubrik, RAS belum tersedia |
| Sinkronisasi | `ReportWorkflow::syncPeriod` membuat rapor satu template | Paket dokumen berdasarkan murid/periode; tidak membuat nilai |

`schema.md` dan checklist historis tidak selalu menggambarkan kode terbaru. Audit schema live/fixture sebelum membuat DDL, terutama FK dan tipe ID. Collision nama `rapor` dan `skala_nilai` melarang menjalankan CREATE IF NOT EXISTS dari spesifikasi secara membabi buta.

## Pemeriksaan read-only

Jalankan `php database/audit-erapor.php` pada database lokal dari `.env`. CLI membuka transaksi read-only dengan snapshot konsisten; hanya jumlah agregat yang dicetak, bukan identitas atau kredensial. Deteksi: identitas/tanggal periode tidak valid, slot ganda, rapor murid/periode ganda, relasi periode hilang, guru tanpa akun unik, nilai berbeda semester/template/skala, template kosong.

Hasil tanpa anomali **tidak berarti data siap dikonversi**. Template dummy yang valid secara FK tetap bukan rubrik resmi. Audit tidak dapat menentukan legitimasi kurikulum dari nama template.

## Aturan pemetaan dan perlindungan

1. Pertahankan seluruh tabel lama dan route lama selama fondasi baru dibangun. Tahap awal hanya kode tidak aktif/DDL additive yang diuji pada database terisolasi. Jangan rename tabel yang masih dipakai controller.
2. Sebelum implementasi DDL, tetapkan namespace tabel baru untuk menghindari collision; dokumentasikan mapping nama fisik ke konsep spesifikasi. Tidak membuat sumber tahun/periode ganda tanpa adapter dan aturan cutover.
3. Mapping guru: `rapor.guru_id → karyawan.id → users.karyawan_id → users.id`. NULL atau beberapa akun adalah pengecualian eksplisit; jangan memakai kesamaan angka ID atau menebak akun.
4. Mapping periode memakai tahun + semester + tipe; bukan menebak dari nama/tanggal. Ganda atau identitas tidak lengkap harus dilaporkan, tidak digabung diam-diam.
5. Mapping item lama ke kode resmi membutuhkan bukti kesetaraan tujuan/aparatus/skala dan versi. Tidak memetakan berdasarkan posisi, label mirip, atau ID numerik. Data contoh tetap arsip legacy, bukan nilai resmi.
6. `belum_diisi` lama tidak menjamin paket lengkap/175 item; hanya dapat menjadi calon data kerja setelah pemetaan eksplisit. `menunggu_persetujuan` dan `disetujui` lama tidak memiliki bukti persetujuan koordinator/snapshot baru: pertahankan sebagai legacy, jangan otomatis ubah menjadi `MENUNGGU_TTD`/`SELESAI`.
7. Jangan membangkitkan snapshot tanda tangan, identitas historis, atau approval retroaktif dari data pegawai saat ini. Simpan asal-usul dan mapping ID bila konversi disetujui.

## Gerbang sebelum penerapan

- [ ] Backup baru, restore terisolasi, hitungan serta checksum tabel sumber terverifikasi. Backup sebelumnya bukan bukti untuk data yang sudah berubah.
- [ ] DDL additive diuji pada versi MySQL hosting; migration ledger/checksum dan preflight schema mencegah pengulangan merusak. MySQL DDL bukan transaksi rollback biasa; kegagalan parsial perlu recovery eksplisit.
- [ ] Seeder hanya menulis versi baru, idempotent dan tidak mengganti rubrik yang telah dipakai; jumlah resmi dan isi seed divalidasi.
- [ ] Dry-run pemetaan mengeluarkan pengecualian tanpa PII, lalu persetujuan atas mapping ambigu sebelum backfill data nyata.
- [ ] Uji fitur baru di fixture; cutover terkontrol setelah lengkap, dengan jendela penghentian tulis. Jangan dual-write dua model status tanpa strategi konsistensi.
- [ ] Pemulihan tidak menghapus nilai baru yang sudah diinput: backup pasca-cutover dan rencana rekonsiliasi wajib, bukan hanya kembali ke commit lama.
- [ ] Hosting tanpa SSH: siapkan SQL migrasi terurut untuk phpMyAdmin beserta pemeriksaan sebelum/sesudah; jangan membuat runner SQL publik. Deployment ini memerlukan izin terpisah.

## Bukti batch awal

`tests/erapor-legacy-audit.php` menggunakan SQLite in-memory, menguji fixture normal, sembilan kategori anomali dan tidak adanya write. Audit database lokal dicatat terpisah setelah berhasil dijalankan; tidak ada klaim backup/restore/migrasi telah lulus hanya dari tes ini.

Hasil 24 September 2026: tes audit terisolasi lulus. Percobaan audit MySQL lokal gagal kode koneksi 2002; inventaris aktual belum tersedia. `EraporSessionPolicy` ditambahkan sebagai aturan murni yang belum dipanggil route lama: 65 assertion lulus, termasuk dua approval urutan sama yang dapat berjalan paralel. Regresi `report-workflow-regression.php` lulus. Persistence harus melakukan normalisasi tipe hasil PDO, penguncian baris, pemeriksaan actor/cakupan, transaksi, audit, dan pembuatan PDF sebelum memakai policy; policy sendiri tidak memberi otorisasi atau menghasilkan artefak.

Pemeriksaan ulang setelah pengguna menyalakan MySQL: audit read-only berhasil, dengan 57 rapor/1.000 nilai/56 catatan. Status lama: 45 belum diisi, 2 menunggu persetujuan, 10 disetujui. Ada 5 rapor tanpa guru dan 3 template kosong yang harus diinvestigasi, bukan diisi/dihapus otomatis. Pemeriksaan lain pada preflight menghasilkan nol anomali. Backup/restore dan migrasi belum dijalankan. Percobaan ulang staging tetap ditolak pada `.git/index.lock`; akses remote juga gagal melalui proxy lingkungan.

## Batch katalog: hasil lanjutan

Setelah push pengguna `f224283`, audit diperinci: kelima rapor tanpa guru belum diisi, tidak memiliki nilai, dan tidak memiliki penugasan guru saat ini. Template system kosong ID 2 dipakai enam rapor; ID 3/4 belum digunakan. Ini keadaan data yang perlu dipertahankan, bukan dasar untuk membuat guru/nilai secara otomatis.

Cadangan `database/backups/zivana-local-20260924-034137-b72e2a.sql` berhasil dipulihkan ke database acak terisolasi. Tes katalog membandingkan checksum per tabel dari semua nilai baris (termasuk NULL), bukan hanya jumlah baris. Seluruh 24 tabel lama sama sebelum/sesudah migrasi, dan sumber lokal tidak berubah. Cadangan berisi data privat, jangan commit/upload ke document root. Database uji dihapus setelah tes; file backup tetap tersedia.

### Nama fisik dan batas batch

| Konsep spesifikasi | Tabel baru |
|---|---|
| rubrik | erapor_rubrik |
| rubrik_bagian | erapor_rubrik_bagian |
| rubrik_periode (kolom cetakan, bukan kalender) | erapor_rubrik_periode |
| rubrik_penandatangan | erapor_rubrik_penandatangan |

`tahun_ajaran` dan `periode_penilaian` tetap sumber kalender aplikasi; batch ini tidak menambahkan salinan kalender atau mengganti `rapor`/`skala_nilai` lama. Adapter ke istilah periode/urutan spesifikasi akan dibangun sebelum sesi baru diaktifkan. Skema khusus rubrik, nilai, approval, dan sesi belum dibuat dalam batch ini.

### Menjalankan pemeriksaan

```powershell
php database/plan-erapor-migration.php
php tests/erapor-catalog-mysql.php --run --backup=zivana-local-20260924-034137-b72e2a.sql
```

Perintah pertama hanya menampilkan empat tabel dan checksum migrasi. Perintah kedua membuat database `zivana_erapor_test_<acak>`, memulihkan cadangan, menguji migrasi/constraint/kegagalan, lalu menghapus database uji miliknya. Jika sumber lokal telah berubah sejak backup, tes berhenti karena checksum berbeda; buat backup baru, jangan menonaktifkan pengecekannya.

DDL `20260924_erapor_catalog.sql` **belum diterapkan ke database aplikasi**. Runner menulis ledger `erapor_migrations` dengan checksum stabil LF/CRLF dan status `applying/complete`, serta memakai MySQL advisory lock. Pengulangan `complete` tidak mengulang DDL; checksum berubah, tabel hilang, collision tak tercatat, atau status parsial menyebabkan penolakan. Ia hanya mendukung file DDL CREATE TABLE sederhana yang direview, bukan parser SQL umum.

Jika gagal setelah sebagian DDL, jangan menghapus ledger atau menandai complete secara manual agar lolos. Hentikan penerapan, periksa tabel terhadap DDL dan backup, lalu susun pemulihan eksplisit. Batch ini tidak menyediakan auto-repair skema drift atau rollback DDL. Panduan penerapan phpMyAdmin beserta ledger yang konsisten masih perlu diselesaikan sebelum deploy tanpa SSH.

Hasil uji: 17 pemeriksaan MySQL lulus (restore, checksum legacy, idempotensi, lock bersamaan, FK/unique/check, checksum migrasi berubah, parsial, tabel hilang, collision); 65 assertion policy dan tes audit/workflow lama lulus. Belum menguji UI atau versi MySQL hosting.

## Batch RTS: definisi dan seeder

`20260924_erapor_rts.sql` bergantung pada katalog dan menambahkan enam tabel: `erapor_rubrik_area`, `erapor_rubrik_sub_area`, `erapor_rubrik_grup`, `erapor_rubrik_indikator`, `erapor_skala_nilai`, `erapor_seed_history`. Relasi indikator ke grup memakai FK komposit agar grup selalu berasal dari subarea yang sama. Kelompok judul tidak menjadi indikator. Tidak ada tabel nilai murid baru dalam batch ini.

`EraporRtsSeed` membaca JSON RTS resmi tanpa menyalin teks dari PDF. Import hanya INSERT untuk rubrik yang belum ada; rubrik existing tanpa ledger ditolak, sumber berubah ditolak, isi tersimpan drift ditolak. Retry rubrik sama (termasuk status terkunci) tidak mengubah baris atau timestamp. Hash sumber dihitung dari hasil parse JSON sehingga whitespace/LF/CRLF file tidak memengaruhi hasil. Hash isi tidak memasukkan status dan timestamp rubrik karena perubahan status merupakan proses terpisah.

Seeder ini khusus struktur **RTS V1**, bukan importer arbitrer atau editor kurikulum. Versi baru memerlukan seeder/migrasi baru yang direview. Guard database/service yang mengunci definisi setelah dipakai nilai belum dikerjakan; menolak overwrite melalui seeder tidak berarti seluruh jalur tulis sudah terkunci.

Perintah read-only tambahan: `php database/validate-erapor-rts.php`. Rencana migrasi kini menampilkan dua file (katalog → RTS). Tidak ada CLI apply ke database aplikasi atau endpoint publik yang ditambahkan.

Pengujian restore MySQL diperluas menjadi **214 pemeriksaan**, termasuk kesamaan teks 175 indikator, jumlah struktur, judul kembar yang tetap terpisah, rollback seluruh impor saat trigger uji menolak insert, retry tanpa perubahan, penolakan sumber berubah/drift, dan FK lintas subarea. Validator mandiri **11 skenario lulus**. Checksum seluruh tabel legacy tetap sama pada database restore dan sumber; database disposable dibersihkan setelah pengujian. Migrasi dan seed belum diterapkan ke database pengguna/hosting, belum ada tes UI baru.

## Batch Ummi/PPI: definisi saja

Urutan migrasi sekarang katalog → RTS → `20260924_erapor_ummi_ppi.sql`. Migrasi ketiga menambah `erapor_ummi_jilid`, `erapor_ummi_materi`, `erapor_skala_huruf`, `erapor_ppi_aspek`, `erapor_ppi_kolom`, dan `erapor_rubrik_sumber`. Tabel terakhir menyimpan snapshot JSON sumber lengkap supaya metadata cetak, mapping identitas, catatan bagian dan label tidak hilang saat normalisasi. Snapshot adalah data, bukan ekspresi executable; renderer mendatang harus memetakan sumber identitas secara eksplisit, bukan mengevaluasi string. Tidak ada data penilaian murid contoh yang diimpor.

`EraporUmmiPpiSeed` khusus V1 memakai seed history dari migrasi RTS, transaksi dan advisory lock bersama runner. Sumber maupun isi tabel tersimpan difingerprint; retry identik tidak menulis ulang, perubahan membutuhkan versi yang direview. Snapshot turut masuk fingerprint. Status rubrik boleh berubah tanpa dianggap drift. Perlindungan seeder ini belum merupakan guard seluruh jalur edit definisi saat penilaian mulai digunakan.

Ummi menyimpan 7 jilid/27 materi/12 skala; hanya bagian C wajib. Urutan bagian A/B/C berasal dari urutan array karena JSON Ummi tidak memiliki field urutan bagian. PPI menyimpan 5 aspek/8 kolom, hanya 6 kolom diisi dalam sesi (30 textarea). Hasil Capaian tetap tidak wajib dan tidak diisi di sesi. Metadata print/identitas dipertahankan, tetapi renderer, snapshot usia, sakelar PRA TK, tes dinamis, dan nilai per periode belum dibangun. Konflik aturan cetak baris kosong Ummi belum diselesaikan di batch ini.

Pemeriksaan tanpa DB: `php database/validate-erapor-ummi-ppi.php`; unit: `php tests/erapor-ummi-ppi-seed.php` (20 skenario). Harness restore yang sama kini **291 pemeriksaan lulus**, termasuk rollback seluruh impor kedua dokumen, advisory lock, hash sumber/isi, status terkunci, constraint PPI lintas bagian, 27 teks Ummi dan label multiline/lebar kolom PPI. Checksum tabel legacy dan database sumber tetap sama; hanya database disposable milik tes dihapus sesudahnya. Belum diterapkan ke database aplikasi/hosting; tidak ada perubahan tampilan atau klaim uji browser/PDF.

## Batch BING: definisi saja

`20260924_erapor_bing.sql` berjalan setelah katalog, RTS, dan Ummi/PPI karena memakai seed history serta snapshot sumber bersama. Tiga tabelnya: `erapor_bing_skala`, `erapor_bing_indikator`, `erapor_bing_komentar`. Kode/order unik per rubrik; rank skala 1–4. Lima indikator semuanya SKALA (termasuk attendance); empat komentar TEXTAREA. Judul kelompok Speaking Test Result tidak menjadi indikator keenam. Definisi tidak menyimpan nilai atau komentar murid contoh.

`EraporBingSeed` khusus V1 memvalidasi cakupan semester, cetakan satu periode, bahasa Inggris, dua bagian wajib, tiga penandatangan, urutan skala dan identitas tanggal lahir (bukan usia). Transaksi, advisory lock, hash sumber/isi, retry no-op, dan penolakan overwrite mengikuti seeder sebelumnya. Teks tetap, kelompok dan metadata cetak disimpan di snapshot sumber. Ini bukan renderer atau guard penyimpanan nilai.

Seed BING dan PDF revisi belum selaras: ejaan label Pronounciation, definisi skala serta kalimat REMARKS perlu dicocokkan sebelum aktivasi. Batch ini tidak mengoreksi sumber secara sepihak. Kode `speaking__pronounciation` tidak boleh ikut diganti saat label diperbaiki. Urutan Excellent > Outstanding > Good > Fair sudah tetap; tidak menghitung rata-rata ordinal.

CLI read-only: `php database/validate-erapor-bing.php`. Validator `tests/erapor-bing-seed.php` lulus 19 skenario; harness restore MySQL lulus 328 pemeriksaan total. Seluruh data legacy/database lokal sumber tetap identik, database disposable telah dibersihkan, backup tetap tersedia. Regresi policy/audit/workflow lama dan validator rubrik terdahulu lulus. Belum ada migrasi database aplikasi/hosting atau perubahan UI/PDF.

## Batch Agama: definisi saja

`20260924_erapor_agama.sql` menambah tujuh tabel `erapor_agama_*`: lingkup, sub, item, item_nama, tahapan, subtingkat, pilihan. Bergantung pada katalog, seed history RTS dan snapshot sumber Ummi/PPI; rencana CLI menjalankannya setelah BING. Tidak membuat tabel nilai murid.

`EraporAgamaSeed` mempertahankan seluruh teks resmi JSON yang sudah mencakup koreksi sekolah. Enam lingkup/73 butir terikat semester (37 Ganjil, 36 Genap); delapan sub mencakup tiga bernama dan lima implisit. Asmaul Husna menyimpan 99 nama di bawah 10 butir, bukan 99 penilaian. Nomor cetak opsional dipertahankan. Urutan bagian serta subtingkat diambil dari posisi array resmi bila tidak memiliki field urutan.

Lima tahapan dan tiga subtingkat terpisah dari tujuh pilihan dropdown. FK komposit memastikan pilihan merujuk tahapan dari rubrik yang sama dan subtingkat dari tahapan/kode yang sesuai. CHECK mewajibkan subtingkat untuk Tahfizh saja, termasuk pemeriksaan IS NOT NULL agar aturan tidak lolos lewat hasil SQL UNKNOWN. Label dan kolom cetak berasal dari seed, definisi lengkap turut disimpan. Kelak tabel nilai harus menerapkan aturan yang sama; constraint definisi ini belum menggantikan validasi penyimpanan nilai.

`lingkup.catatan_wajib=1` mewakili enam textarea Bagian VII sesuai spesifikasi 5.2/7.2. Tidak ada catatan contoh yang diisi. Template narasi belum diimplementasikan; konflik spesifikasi tentang perubahan narasi rapor lama versus snapshot final masih perlu diputuskan sebelum renderer aktif.

CLI tanpa DB: `php database/validate-erapor-agama.php`. Validator 22 skenario lulus; harness restore mencapai 514 pemeriksaan, termasuk teks/semester seluruh 73 butir dan daftar nama berurutan, transaksi gagal di tengah impor, lock, retry, sumber berubah, drift, status terkunci serta penolakan relasi Tahfizh yang tidak sah. Data asli/legacy tidak berubah dan database disposable dibersihkan. Seluruh batch tetap belum diterapkan ke database aplikasi/hosting atau UI.

## Preflight kalender dan identitas paket

`EraporCalendar::year(PDO, id)` membaca `tahun_ajaran` dan `periode_penilaian` yang sudah ada. Adapter menormalisasi semester lower-case dan tipe legacy Tengah Semester/Akhir Semester menjadi identitas internal, dengan urutan 1–4 sesuai policy. Tidak memakai urutan ID sebagai urutan waktu, tidak menebak semester dari tanggal, dan tidak menuntut empat slot lengkap untuk membaca tahun. Slot hilang dilaporkan; duplikat, tanggal/tahun tidak valid ditolak.

`EraporPackagePlan::build` adalah perhitungan murni dari murid, periode, katalog dan konfigurasi terpercaya. Daftar bootstrap ada di `config/erapor-package.php`; hanya versi rubrik yang terikat eksplisit yang dipilih, bukan versi terbaru secara otomatis. RAS belum terikat sehingga paket akhir semester ditolak tanpa mengembalikan sebagian dokumen. Rencana draft bukan persetujuan aktivasi; penyelarasan seed/PDF tetap diperlukan.

Identitas dokumen tahunan memakai literal TAHUNAN, bukan NULL; identitas sesi selalu murid/periode konkret. Paket tengah Regular berisi empat dokumen, ABK lima. Kelak pembentukan sesi harus menyimpan hasil komposisi sebagai baris sesi-dokumen, dan UI membacanya dari sana, bukan menghitung ulang konfigurasi tiap request. Perubahan kondisi/penugasan murid pada sesi historis belum ditangani oleh preflight ini.

Unit/SQLite: `php tests/erapor-package-plan.php` lulus 29 pemeriksaan. Harness MySQL kini 552 pemeriksaan; seluruh periode dari salinan database dibaca tanpa perubahan checksum, paket akhir tertahan, Regular/ABK sesuai. Persistensi, unique constraint, concurrency, otorisasi dan audit pembuatan sesi masih task berikutnya. Tidak ada migrasi tambahan atau write ke database aplikasi pada batch ini.
