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
- [ ] Hosting tanpa SSH: gunakan runner CLI-only melalui cPanel Cron sesuai `cookbook/erapor-cpanel-cron.md`; jangan membuat runner SQL publik. Jalankan `--check` dan tinjau output sebelum `--apply-production-schema-only`.

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

Jika gagal setelah sebagian DDL, jangan menghapus ledger atau menandai complete secara manual agar lolos. Hentikan penerapan, periksa tabel terhadap DDL dan backup, lalu susun pemulihan eksplisit. Batch ini tidak menyediakan auto-repair skema drift atau rollback DDL. Untuk hosting tanpa SSH, gunakan runner dan prosedur Cron yang dijelaskan dalam `cookbook/erapor-cpanel-cron.md`; jangan impor berkas migrasi satu per satu melalui phpMyAdmin.

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

Keputusan cakupan 25 September 2026: pilot memakai seed BING V1 apa adanya meskipun teks sumber dan PDF revisi berbeda. Urutan Excellent > Outstanding > Good > Fair sudah tetap; tidak menghitung rata-rata ordinal. Jangan mengubah V1 atau kode `speaking__pronounciation`; koreksi copy di masa depan harus memakai versi rubrik baru dan hanya berlaku untuk publikasi baru.

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

## Fondasi persistensi sesi

Migrasi keenam `20260924_erapor_sessions.sql` menambah empat tabel: `erapor_dokumen`, `erapor_sesi`, `erapor_sesi_dokumen`, `erapor_sesi_log`. Identitas dokumen unik murid/tahun/rubrik/semester (TAHUNAN bukan NULL); sesi unik murid/periode. Penghubung menyimpan dimensi murid/tahun/semester untuk FK komposit dan CHECK, sehingga dokumen milik murid/tahun/semester lain tidak dapat dipasangkan. Penyesuaian cakupan rubrik dan identitas periode masih diperiksa service, bukan FK ke kolom legacy yang belum memiliki composite unique.

`EraporSessionFactory::create(PDO, muridId, periodeId, actorId)` hanya service internal, belum route publik. actorId wajib berasal dari sesi autentikasi server, bukan body request. Pemeriksaan penugasan membaca pivot guru-karyawan-akun serta kelas murid; akun/karyawan/jabatan harus aktif dan berjabatan Guru Kelas/Guru Shadow. Route RBAC tetap wajib sebelum service dipanggil. Pembentukan administratif belum diberikan jalur bypass.

Advisory lock bersama migrasi/seeder diambil sebelum transaksi, sehingga competing factory ditolak untuk dicoba ulang, bukan berjalan bersamaan. Strategi ini sengaja konservatif, belum optimasi throughput per murid. Baris murid, penugasan, periode, tahun, katalog dikunci. Seluruh insert dokumen/sesi/penghubung/log dan perubahan rubrik draft menjadi terkunci berada dalam satu transaksi. Katalog harus memiliki seed history; tidak mengubah teks rubrik atau mengonversi rapor legacy.

Retry menggunakan komposisi tersimpan dan hash, tidak menghitung ulang paket dari kondisi/konfigurasi sekarang. Identitas kalender atau guru/kelas yang berubah menyebabkan penolakan rekonsiliasi; kondisi snapshot tidak ditimpa. Retry sesi final hanya mengembalikan ID, tidak membuka statusnya. Kelengkapan isian, snapshot nama/TTD saat penerimaan, approval dan penerbitan belum ditambahkan.

Tes restore MySQL kini 579 pemeriksaan. Regular mendapat 4 dokumen, ABK 5; dua sesi tengah semester Regular berbagi RTS/Agama tahunan sehingga hanya 6 identitas dokumen. Trigger gagal saat log membuktikan rollback termasuk status rubrik. Tes juga memeriksa lock bersaing, retry tanpa write, paket drift, guru lain, FK lintas murid, UNIQUE, dan RAS hilang tanpa paket parsial. Pemeriksaan belum merupakan E2E HTTP/browser atau stress test proses paralel.

Belum diterapkan pada database aplikasi. Sebelum aktivasi, integrasikan guard perubahan/hapus periode, tahun, murid, guru/kelas dan definisi dengan struktur baru. FK RESTRICT sengaja mempertahankan identitas historis; modul CRUD lama perlu menampilkan penolakan yang sesuai. Penandaan status rubrik terkunci bukan perlindungan universal terhadap SQL langsung.

## Penyimpanan RTS dan log isian

Migrasi ketujuh menambahkan `erapor_rts_nilai` dan `erapor_isian_log`. Identitas nilai menyertakan sesi, dokumen dan indikator; sesi sudah unik murid/periode konkret sehingga nilai Ganjil/Genap tidak saling menimpa meski dokumen tahunan sama. FK ke sesi-dokumen menolak dokumen di luar paket. Kesesuaian indikator/skala terhadap rubrik diperiksa service; skema ini belum menolak setiap bentuk SQL langsung lintas rubrik.

`EraporRtsEntry::save` menerima batch `indikator_id, nilai, expected`; nilai/expected harus integer 1–4 atau NULL. Expected adalah nilai terakhir yang dibaca klien, bukan izin menulis. Nilai berubah dengan expected lama ditolak kecuali tujuan sudah sama dengan nilai tersimpan (retry no-op). Ini bukan version counter dan tidak mendeteksi seluruh siklus perubahan kembali ke nilai semula. Jika diperlukan deteksi ABA, tambahkan revision token sebelum mengklaim versioned autosave.

Service mengambil actor dari konteks autentikasi pemanggil dan clock server (default Asia/Makassar). Clock injeksi hanya untuk pengujian tepercaya, bukan request HTTP. Ia memeriksa snapshot guru, penugasan aktif saat ini, identitas periode, hash paket, dokumen RTS tahunan/tengah semester/terkunci, indikator aktif dan skala rubrik. Penyimpanan hanya pada dua status editable dan sampai tanggal akhir periode. Perpanjangan khusus kepala sekolah belum diimplementasikan; service belum boleh dianggap memenuhi seluruh alur tenggat.

Advisory lock dan transaksi mencakup grade + audit. NULL menghapus baris nilai; log tetap mencatat nilai lama/baru beserta status sesi dan aktor. No-op tidak menulis log baru. Clear tidak menurunkan TELAH_DIISI. Hasil kelengkapan hanya untuk RTS sesi tersebut, bukan kelengkapan semua dokumen atau izin transisi status.

Harness restore mencapai 609 pemeriksaan, termasuk audit gagal yang membatalkan nilai, batch gagal di item berikutnya, expected-value conflict, retry, deadline boundary, status terkunci, perubahan paket, kepemilikan dan seluruh 175 nilai wajib. Tidak ada nilai legacy atau database aplikasi yang dimodifikasi. Route RBAC/CSRF, browser autosave, perpanjangan, form load, dokumen lain dan transisi status transaksional masih task berikutnya.

## Penyimpanan BING/PPI

Migrasi kedelapan `20260924_erapor_bing_ppi_values.sql` menambahkan `erapor_bing_nilai`, `erapor_bing_isian`, `erapor_ppi_isian`. Setiap baris mengacu sesi/dokumen dan rubrik. Grade BING memakai kode pilihan dengan FK komposit rubrik/kode ke skala BING; identitas indikator/komentar/aspek/kolom mengacu tabel definisinya. Kesesuaian setiap definisi dengan rubrik dokumen dan larangan Hasil Capaian masih dijaga service, bukan seluruhnya constraint SQL langsung.

`EraporStructuredEntry::save` menerima jenis BING/PPI dan perubahan `key,value,expected`. BING memakai key `nilai:<indikator_id>` atau `komentar:<komentar_id>`; PPI `<aspek_id>:<kolom_id>`. Daftar field diambil dari definisi database. Identifiers SQL berasal dari mapping internal, bukan key request. Seluruh pasangan aspek/kolom PPI di luar sesi ditolak, termasuk operasi hapus. Kondisi ABK memakai snapshot sesi, bukan mengganti paket historis ketika data murid berubah.

Text/expected dinormalisasi hanya untuk deteksi kosong: NULL atau whitespace menjadi tidak ada baris; teks nonkosong dipertahankan byte-for-byte. UTF-8 salah, nilai bukan string/null, dan teks di atas 65.535 byte ditolak sebelum disimpan. Tidak melakukan HTML escaping atau pemecahan bullet saat penyimpanan; UI/PDF mendatang harus melakukan output escaping sesuai konteks. Grade BING harus kode resmi, tidak menerima nilai ordinal bebas atau Ummi A+.

Transaksi mencakup semua perubahan dan audit JSON lama/baru. No-op tidak membuat audit; konflik expected menolak perubahan kecuali nilai tujuan sudah tersimpan. Batas expected-value/ABA sama seperti RTS, bukan revision-token concurrency. Kelengkapan hanya menghitung field wajib dalam dokumen/sesi itu: 9 untuk BING, 30 untuk PPI V1. Clear tidak menurunkan TELAH_DIISI, dan status terkunci/tenggat tetap dijaga.

Harness restore 715 pemeriksaan lulus: teks Unicode/multibaris/markup tetap identik, whitespace Unicode clear, invalid UTF-8/ukuran, audit rollback, batch gagal di field berikutnya, grade update/clear, outcome PPI ditolak, Regular tidak bisa mengisi PPI, stale write/retry/locks, dua semester terpisah, serta FK/unique. Regresi sebelumnya lulus. Database aplikasi dan tabel legacy tidak berubah; ini belum uji HTTP/browser/PDF atau aktivasi UI.

## Penyimpanan Agama

Migrasi kesembilan menambahkan `erapor_agama_nilai` dan `erapor_agama_catatan`. Grade menyimpan tahap dan subtingkat secara terpisah, bukan satu nilai datar atau tujuh flag. FK komposit mengikat rubrik/tahapan dan tahapan/subtingkat beserta kode yang cocok; CHECK dengan IS NOT NULL mewajibkan subtingkat pada Tahfizh saja. Catatan adalah satu potongan teks per lingkup/sesi; tidak menyimpan narasi hasil rangkaian.

`EraporAgamaEntry::save` menerima key `nilai:<item_id>` atau `catatan:<lingkup_id>`, value dan expected string/null. Tujuh pilihan memakai kode kolom cetak dari seed (contoh TAHFIZH/D), kemudian service memetakannya ke kolom tahap/subtingkat. Kode yang tidak tersedia ditolak, termasuk TAHFIZH tanpa subtingkat atau TAFHIM/D. Mapping grade hanya milik rubrik dokumen itu. Pemilihan item dibatasi semester sesi dari database, bukan input klien.

Transaksi, advisory lock, guard guru/kalender/paket/status/tenggat, expected-value check, retry no-op, preservasi UTF-8, clear whitespace dan audit mengikuti service sebelumnya. Perubahan dari Tahfizh ke tahap biasa mengosongkan kolom subtingkat. Hasil completeness hanya dokumen Agama sesi itu: 43 isian Ganjil dan 42 Genap pada V1, tanpa memasukkan butir semester lain.

Harness restore mencapai 818 pemeriksaan. Dua sesi semester pada dokumen tahunan yang sama mempertahankan seluruh 73 nilai dan 12 catatan secara terpisah. Seluruh pilihan serta constraint pasangan tahap/subtingkat diuji, termasuk rollback audit dan batch, raw text, clear/refill, invalid grade/semester, tenggat/status/guru, idempotensi dan lock. Tidak mengubah data legacy/database aplikasi; belum menguji HTTP/browser/PDF atau mengaktifkan narasi/approval. Ummi masih task storage berikutnya.

## Penyimpanan Ummi

Migrasi kesepuluh `20260924_erapor_ummi_values.sql` menambah bacaan, catatan, periode (flag PRA TK), dan tes. Identitas semua baris terikat sesi/dokumen; nilai bacaan/tes memiliki FK kode skala terhadap rubrik Ummi. Keanggotaan materi pada rubrik diperiksa service. Tidak ada tabel Hafalan.

`EraporUmmiEntry::save` menerima key `catatan`, `mulai_pra_tk`, `bacaan:<materi_id>`, atau `tes:<32 hex lowercase>`, dengan value dan expected. Token tes harus dibuat acak oleh klien/server untuk tiap kejadian dan dipakai ulang saat retry. Payload tes: urutan integer positif, tanggal_tes valid, jilid teks nonkosong maksimal 150 karakter, nilai kode huruf. Nilai NULL menghapus bacaan/catatan/tes, tetapi flag harus boolean dan tidak bisa dihapus melalui service. Urutan tes bukan kunci unik sehingga pengurutan ulang dapat dilakukan tanpa benturan; pembaca perlu urutan lalu token sebagai tie-breaker.

Batch kosong adalah operasi inisialisasi eksplisit yang tetap memerlukan otorisasi, status editable dan tenggat; bukan GET read-only. Pemanggil UI mendatang harus menginisialisasi saat sesi dibuka, lalu membaca flag tersimpan. Flag awal Tengah false; Akhir mengambil Tengah pada dokumen/tahun/semester sama satu kali, tanpa menyalin bacaan, tes atau catatan. Pembuatan flag diaudit dan ikut rollback. Mematikan PRA hanya mengubah flag, tidak menghapus atau memutasi bacaan.

Kelengkapan Ummi selalu required=1, yakni catatan periode tersebut. Nilai bacaan/tes tidak dihitung sebagai kekurangan. Batas 200 perubahan per batch adalah batas ukuran request internal, bukan batas jumlah baris tes per semester. Konflik expected/retry dan batas ABA sama seperti service lain. Semua SQL identifiers berasal dari mapping internal, bukan key request.

Harness restore kini 884 pemeriksaan. Uji mencakup nol/lebih dua tes, token retry, edit/hapus event, 12 skala, optional clear, raw notes, UTF-8/date/flag validation, initialization/update audit rollback, batch rollback, status/tenggat/guru/lock, FK/unique, dan semester berbeda. Pewarisan Akhir menggunakan fixture sesi Ummi-only sintetis yang jelas bukan paket lengkap; tidak membuat RAS palsu atau melewati guard factory aplikasi. Belum aktivasi DB/HTTP/UI/PDF, dan perbedaan ketentuan cetak baris tes kosong tetap belum diputuskan.

## Fondasi konfigurasi dan snapshot alur persetujuan

Migrasi `20260924_erapor_approval_flow.sql` ditambahkan setelah migrasi nilai Ummi di CLI plan. Lima tabel menyimpan alur, mapping rubrik terbatas, penugasan user eksplisit, salinan alur sesi, dan cakupan dokumen sesi. Migrasi hanya DDL, tidak mengisi akun penyetuju atau mengubah data pegawai. Akun admin tidak otomatis menjadi koordinator/kepala sekolah.

`EraporApprovalPlan::build(documents, flows, scopeRows)` merupakan validasi/proyeksi murni dari konfigurasi tepercaya: Quran hanya Ummi, BING hanya BING, keduanya urutan 1, kepala sekolah urutan 2 seluruh paket termasuk PPI untuk ABK. Flow wajib tidak aktif atau mapping hilang ditolak. Pemanggil tetap wajib memvalidasi paket/kelengkapan, mengotorisasi aktor, dan menyimpan proyeksi dalam transaksi penerimaan. Planner ini tidak melakukan ketiganya sendiri.

Snapshot cakupan terikat pasangan sesi/dokumen dan sesi/penyetuju melalui composite foreign keys. Perubahan konfigurasi tidak merambat ke snapshot lama. Snapshot ini baru konfigurasi alur, bukan identitas penandatangan; belum ada nama/NUPTK/berkas TTD/aktor/waktu persetujuan. Jangan mengaktifkan transisi penerimaan atau approval hanya berdasarkan tabel ini.

Uji planner 16 pemeriksaan dan harness restore 927 pemeriksaan lulus. Pengujian persistensi snapshot memakai fixture SQL khusus, bukan service penerimaan produksi. Uji meliputi retry migrasi, penugasan awal kosong, cakupan Regular/ABK, perubahan konfigurasi, duplicate/FK dan relasi lintas sesi. Database aplikasi tetap tidak dimigrasikan.

## Konfirmasi penerimaan dan snapshot guru

Migrasi `20260924_erapor_reception.sql` mengikuti approval flow. `erapor_profil_penandatangan` memperluas akun pegawai secara additive (user_id FK ke akun karyawan), sedangkan `erapor_sesi_penerimaan` menyimpan nama/NUPTK/gambar/consent/hash dan waktu penerimaan per sesi. Adaptasi penyimpanan: gambar PNG disimpan sebagai bytes privat di database, bukan referensi file mutable. Salinan tidak mengikuti penggantian profil dan tidak memiliki URL publik. Tidak ada unggah atau pemberian consent otomatis pada batch ini. Endpoint unggah mendatang wajib mengautentikasi pemilik, mencatat consent nyata, memvalidasi/decode dan menormalisasi gambar sebelum menyimpan; validator snapshot bukan pengganti sanitasi upload.

`EraporConfirmReception::confirm(PDO, sessionId, actorId)` merupakan service internal dengan actor dari autentikasi tepercaya; RBAC/CSRF wajib di route mendatang. Memakai advisory lock bersama dan row locks, memeriksa kepemilikan/penugasan aktif, status TELAH_DIISI, jejak KONFIRMASI_ISI, identitas kalender dan kelengkapan paket. Isian kosong mengembalikan incomplete tanpa menulis. Snapshot guru + alur/cakupan approval + status MENUNGGU_TTD + audit KONFIRMASI_PENERIMAAN tersimpan satu transaksi. Tenggat hanya membatasi edit, bukan konfirmasi lengkap.

Retry MENUNGGU_TTD memerlukan guru yang masih berwenang serta snapshot/jejak/alur tersimpan; tidak mengambil ulang profil atau konfigurasi terbaru. SELESAI dan BELUM_DIISI ditolak. NUPTK/gambar kosong sah; gambar yang tersedia harus PNG dengan metadata dimensi valid dan consent tersimpan. Tidak ada signing/approval/PDF pada operasi ini. Akun approver belum harus dipilih saat reception, namun approval berikutnya wajib memakai penugasan eksplisit dan akun aktif, tanpa bypass admin.

Harness restore 956 pemeriksaan lulus, unit snapshot 16 pemeriksaan, regresi sebelumnya lulus. Fixture mencakup NUPTK invalid, gambar/consent invalid di unit test, opsional kosong, snapshot signature/nama tidak berubah, audit failure rollback, retry, inactive actor, incomplete no-op, konfigurasi tidak aktif, lock dan autosave terkunci. Semua perubahan database terjadi hanya di database disposable; database aplikasi tidak dimigrasikan. UI dan uji visual belum termasuk batch ini.

## Aksi persetujuan dengan snapshot penyetuju

Migrasi `20260924_erapor_approval_actions.sql` setelah reception menambahkan `erapor_persetujuan_snapshot`. Snapshot terikat pasangan approval/sesi, user, serta satu log unik. Nama, NUPTK, PNG privat, SHA-256, waktu consent dan persetujuan disalin saat aksi, tidak dibaca hidup saat pencetakan. Tidak mengubah tabel legacy.

`EraporApprove::approve(PDO, sessionId, approvalId, actorId)` memakai koneksi MySQL khusus, advisory lock bersama dan transaksi. Actor harus berasal dari autentikasi; endpoint mendatang wajib RBAC/CSRF. Penugasan eksplisit `erapor_penyetuju_user.aktif` dan akun/pegawai/jabatan aktif selalu diperiksa, termasuk retry. Aksi kepala sekolah juga mensyaratkan jabatan Kepala Sekolah; admin tidak otomatis berwenang. Tidak menerapkan larangan satu akun memiliki beberapa assignment karena spesifikasi belum menetapkannya.

Validasi alur memakai snapshot sesi: perubahan konfigurasi flow tidak mengubah urutan/cakupan historis, tetapi pencabutan assignment akun tetap berlaku. Layanan memeriksa hash paket, jejak penerimaan dan kesesuaian cakupan tiap approval. Koordinator urutan 1 paralel; kepala sekolah urutan 2 menunggu seluruh koordinator. Flag DISETUJUI tanpa bukti snapshot/log tidak dapat dipakai sebagai prasyarat approval berikutnya.

Log SETUJUI, snapshot dan update status approval atomik. Pengulangan sah oleh aktor yang sama mengembalikan already_approved tanpa log/snapshot baru. Akun lain tidak mengambil alih persetujuan yang sudah diberikan. Tidak ada pembatalan persetujuan, pembukaan isian, ataupun perubahan status sesi ke SELESAI. Return all_approved hanya sinyal kesiapan untuk layanan publikasi mendatang, bukan bukti PDF sudah tersedia.

Harness restore mencapai 985 pemeriksaan dan seluruh unit/regresi sebelumnya lulus. Fixture menguji BING sebelum Quran, kepala sekolah prematur, penugasan/akun tidak aktif, rollback kegagalan snapshot, cakupan tidak lengkap, retry, tanda tangan kosong/tersimpan, profil berubah, FK dan sesi tetap MENUNGGU_TTD. Fixture sementara mengganti jabatan satu akun lalu memulihkannya di database disposable saja; tidak memilih pejabat produksi. Uji lock bukan stress test paralel multiproses. Database aplikasi, UI, upload consent, dan PDF belum diaktifkan.

## Perpanjangan tenggat periode

Migrasi `20260924_erapor_extensions.sql` menambahkan audit perpanjangan dengan FK ke kalender `periode_penilaian` yang sudah ada. Tidak ada kalender alternatif. `EraporExtendPeriod::extend` memperbarui `akhir_periode` dan menambahkan audit tanggal lama/baru, alasan mentah, aktor dan waktu dalam satu transaksi. Kegagalan audit membatalkan update tanggal. Layanan memakai advisory lock yang sama dengan autosave/migrasi dan row lock periode/aktor.

Actor harus berasal dari autentikasi dan memiliki akun, pegawai serta jabatan Kepala Sekolah aktif. Route mendatang tetap wajib RBAC/CSRF. Tanggal baru harus lebih besar dari maksimum tenggat lama dan hari ini dalam zona Asia/Makassar. Alasan wajib UTF-8 non-whitespace, maksimal 65535 byte. `expectedDate` berasal dari periode yang ditampilkan; bila tenggat telah berubah, request ditolak agar kepala sekolah memuat ulang. Retry identik berdasarkan catatan terakhir, tanggal, alasan dan aktor mengembalikan already_extended tanpa menulis ulang; retry tidak memberi waktu tambahan walaupun dilakukan hari berikutnya.

`preview` adalah pembacaan berotorisasi tanpa mutasi untuk menampilkan jumlah sesi per status dan jumlah yang dapat diisi/tetap terkunci. Angka preview bukan reservasi; saat menyimpan dampak dihitung ulang di transaksi. Perpanjangan berlaku untuk semua murid dalam periode dan tidak mengubah status sesi. Layanan autosave yang sudah membaca kalender langsung otomatis memakai tenggat baru, tetapi status MENUNGGU_TTD/SELESAI tetap menolak penulisan.

Harness restore mencapai 1015 pemeriksaan dan regresi sebelumnya lulus. Kalender legacy pada database disposable dipulihkan setelah pengujian sehingga pemeriksaan checksum tetap berlaku; database aplikasi asli tidak dimodifikasi. Endpoint/UI perpanjangan belum diaktifkan. Jalur edit kalender legacy perlu ditinjau/dibatasi sebelum aktivasi workflow baru agar tidak menjadi jalan pintas tanpa audit.

## Read model form portal guru

`EraporTeacherForm::read(PDO, sessionId, actorId, clock)` memakai koneksi MySQL khusus di luar transaksi lain. Ia memulai transaksi READ ONLY dengan isolasi REPEATABLE READ, sehingga seluruh SELECT melihat snapshot yang sama tanpa mengambil row lock penulisan. Ini berbeda dari pemanggil guard paket pada jalur write: konsistensi reader dijamin transaksi snapshot, bukan FOR UPDATE. Tidak ada pembuatan sesi, default tersimpan, tanda tangan, atau audit pada operasi baca.

Otorisasi memerlukan guru pemilik sesi yang masih aktif dan ditugaskan pada murid/kelas terkait. Identitas kalender, hash paket, metadata dan komposisi rubrik diperiksa. Guru lain/admin tidak mendapat bypass. Respons memuat identitas minimal murid/kelas, periode, status/kondisi sesi, dokumen berurutan, definisi dan nilai per dokumen, kelengkapan, serta capabilities. Capabilities merupakan petunjuk status/kelengkapan/tenggat untuk UI, bukan pengganti validasi server atas konfigurasi approval, jejak, profil, RBAC, atau kondisi yang berubah sebelum save.

`documents[].form.definitions` berisi data rubrik terurut untuk rendering. `values` memetakan key ke nilai tersimpan; key absent berarti NULL. RTS memakai `nilai:<indikator_id>` dengan integer; adapter autosave RTS harus mengubah key itu ke `indikator_id`, bukan mengirim format structured langsung. BING memakai nilai/komentar, Agama nilai/catatan, PPI aspek:kolom; Ummi memakai bacaan/catatan/mulai_pra_tk/tes:<token>. Teks tidak di-trim; UI wajib meng-escape ketika merender. Nilai tes Ummi berbentuk urutan/tanggal_tes/jilid/nilai dan diurutkan urutan lalu token.

Flag Ummi yang belum diinisialisasi dikembalikan NULL dengan initialization_required=true, bukan ditebak false atau diwariskan saat GET. Inisialisasi eksplisit tetap melalui layanan write yang diaudit; UI tidak boleh memperlakukannya sebagai save otomatis pada sesi read-only. Materi PRA dan nilainya tetap tersedia dengan metadata hanya_pra_tk agar toggle dapat menyembunyikan tanpa menghapus. PPI hanya mengirim kolom diisi_di_sesi; Agama membatasi definisi/nilai/nama anggota kelompok ke semester sesi. Nilai sesi lain tidak dikirim, termasuk dokumen tahunan yang sama. Riwayat/kolom periode sebelumnya memerlukan reader terpisah berikutnya.

Tidak ada migrasi baru. Harness restore mencapai 1080 pemeriksaan; unit/regresi sebelumnya lulus. GET tidak mengubah hash data/timestamp, tidak membawa signature bytes, dan nilai yang dibaca bisa menjadi expected-value pada autosave tanpa menulis ulang. Belum endpoint/HTTP/UI atau pengujian visual. Tidak ada aktivasi database aplikasi.

## Kelengkapan paket dan transisi konfirmasi isi

`EraporCompleteness::inspect(PDO, session)` adalah pembacaan internal di dalam transaksi dengan session lock dan otorisasi milik pemanggil. Ia memeriksa hash paket serta komposisi dokumen wajib sebelum menghitung. Missing fields membawa key dan label definisi, termasuk lingkup catatan Agama. Nilai hanya dihitung jika pasangan skala/rubrik valid; teks NULL/whitespace tidak lengkap. Paket parsial atau definisi wajib kosong ditolak, bukan dianggap 100%. RAS tidak mendapat implementasi dummy.

`EraporConfirmFilled::confirm` mengambil actor dari konteks autentikasi tepercaya (bukan body request), memeriksa guru snapshot dan penugasan aktif, kemudian kalender dan nilai tersimpan. Return incomplete tidak menulis apa pun; return confirmed melakukan update status dan insert KONFIRMASI_ISI di transaksi yang sama. Jejak berisi actor, waktu, status lama/baru. Retry TELAH_DIISI sah hanya jika event konfirmasi tersedia, dan tidak mengubah timestamp atau menulis event baru. Incomplete sesudah clear tetap TELAH_DIISI, bukan downgrade.

Tenggat membatasi perubahan isian, bukan konfirmasi pekerjaan yang telah lengkap, sehingga operasi ini sengaja tidak memanggil guard deadline. MENUNGGU_TTD/SELESAI tetap ditolak. Lock yang sama dengan layanan autosave mencegah save interleaving selama pemeriksaan/transisi melalui service resmi; belum merupakan stress test multiproses.

Tidak ada DDL baru pada batch ini. Harness restore mencapai 913 pemeriksaan: rollback status saat audit gagal, incomplete no-op, retry, label kekurangan, Regular/ABK, deadline lampau, sesi terkunci, whitespace tersimpan, paket parsial dan drift. Database asli/legacy tidak berubah. Route RBAC/CSRF, endpoint baca, visual UI, snapshot guru/penerimaan, persetujuan koordinator/kepala sekolah, perpanjangan dan PDF belum aktif. Step 2 → 3 tidak boleh ditambahkan hanya dengan mengganti status tanpa snapshot/approvals.
