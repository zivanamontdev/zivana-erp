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
