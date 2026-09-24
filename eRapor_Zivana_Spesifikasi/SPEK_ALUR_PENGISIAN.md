# Spesifikasi Alur Pengisian dan Persetujuan Rapor
## eRapor Zivana Montessori

Dokumen ini **berlaku lintas seluruh dokumen rapor**. Isinya alur pengisian, status, rantai persetujuan, penguncian, penerbitan, tanda tangan, dan **seluruh tabel bersama**.

> **Kalau ada yang berbeda antara dokumen ini dan spesifikasi rubrik mana pun, dokumen ini yang menang.** `SPEK_RUBRIK_RTS.md` ditulis paling awal, saat alurnya masih dianggap berjalan per dokumen. Status, peran, penguncian, perpanjangan, tabel bersama, endpoint perpindahan status, dan kriteria penerimaan alur yang dulu ada di sana sekarang tinggal di sini.

Kelima spesifikasi rubrik, yaitu RTS, Agama, Ummi, Bahasa Inggris, dan PPI, tetap berlaku penuh untuk **isi dan bentuk masing-masing dokumen**.

---

## 1. Temuan Pokok

Rapor **tidak diisi dan tidak disetujui per dokumen**. Yang bergerak adalah **satu paket rapor milik satu murid**.

```
                    SATU SESI = SATU MURID, SATU PERIODE

  Lembar 1  Data Murid              terisi otomatis, bukan tahap
  ──────────────────────────────────────────────────────────────
  Tahap 1   RTS atau RAS        ┐   RTS saat tengah semester
  Tahap 2   Rapor Agama         │   RAS saat akhir semester
  Tahap 3   Rapor Ummi          ├── diisi dalam satu sesi
  Tahap 4   Rapor Bahasa Inggris│    oleh satu guru kelas
  Tahap 5   Rapor PPI           ┘    (hanya untuk murid ABK)
  ──────────────────────────────────────────────────────────────
            satu tombol konfirmasi selesai
            satu rantai persetujuan
            satu tanda tangan kepala sekolah
            terbit serentak
```

**RAS adalah dokumen ketujuh**, yaitu Rapor Akhir Semester atau Rapor Pengembangan. Dia **menggantikan** RTS saat akhir semester, bukan menambahinya. Isinya berbeda dan belum dispesifikasi.

Empat akibat yang menentukan rancangan:

**Konfirmasi selesai ditekan sekali untuk seluruh sesi**, bukan per dokumen.

**Persetujuan bertingkat.** Koordinator bidang menyetujui lebih dulu, yaitu Koordinator Al-Quran untuk Rapor Ummi dan Koordinator Bahasa Inggris untuk Rapor Bahasa Inggris, baru kepala sekolah.

**Kepala sekolah menandatangani sekali untuk seluruh paket.** Empat sampai lima dokumen sekaligus.

**Tidak ada dokumen yang boleh terbit duluan.** Penerbitan serentak atau tidak sama sekali.

---

## 2. Status Ada di Sesi, Bukan di Dokumen

Di spesifikasi RTS, status diletakkan pada `rapor_periode`, yaitu per dokumen per periode. **Itu sudah tidak berlaku.**

| | Sebelumnya | Sekarang |
|---|---|---|
| Status | di tiap dokumen | **di sesi** |
| Konfirmasi selesai | per dokumen | **sekali per sesi** |
| Tanda tangan | per dokumen | **sekali per sesi** |
| Terbit | boleh sebagian | **serentak** |

Dokumen tetap punya barisnya sendiri di `rapor`, isian sendiri, dan **susunan blok tanda tangan sendiri**, karena penandatangannya memang berbeda-beda. Rapor Ummi ditandatangani Kepala TK dan Koordinator Al-Quran, Rapor Agama ditandatangani Kepala TK, Guru Kelas, dan Orang Tua. Susunan itu data di `rubrik_penandatangan`.

Yang tercetak di blok itu, yaitu nama, NUPTK, dan gambar tanda tangan, diambil dari **salinan di sesi**, karena orangnya sama untuk seluruh dokumen dalam satu sesi. Lihat bagian 7.5.

---

## 3. Empat Status

| Step | Status | Label di layar | Cara masuk | Isian bisa diubah? |
|---|---|---|---|---|
| 1 | `BELUM_DIISI` | Belum Diisi | keadaan awal saat sesi dibuka | **ya** |
| 2 | `TELAH_DIISI` | Telah Diisi | guru menekan konfirmasi selesai, seluruh dokumen wajib lengkap | **ya** |
| 3 | `MENUNGGU_TTD` | **Menunggu Disetujui** | guru menekan konfirmasi penerimaan rapor selesai | **tidak** |
| 4 | `SELESAI` | Selesai | kepala sekolah menandatangani | **tidak** |

### 3.1 Step 3 ke atas hanya bisa dibaca

Begitu guru menekan konfirmasi penerimaan rapor selesai, **isian terkunci untuk semua orang**, termasuk guru yang mengisinya.

Alasannya bukan cuma kerapian. Secara logika guru sudah menyerahkan dokumen itu untuk diperiksa, dan dokumen yang sedang diperiksa tidak boleh berubah di tangan orang yang menyerahkannya.

Kalau isian masih bisa diubah di step 3, muncul keadaan yang berbahaya. Koordinator menyetujui versi A, guru mengubah satu nilai, lalu kepala sekolah menandatangani versi B. Persetujuan koordinator jadi melekat pada versi yang tidak pernah dia lihat.

### 3.2 Tidak ada jalan mundur dari step 3

Ini keputusan sekolah, dan disengaja.

**Tidak ada aksi tarik kembali untuk guru, dan tidak ada aksi kembalikan untuk penyetuju.** Begitu sesi masuk step 3, satu-satunya arah adalah maju.

Dasarnya pengalaman sekolah sendiri. Sepanjang catatan mereka, **belum pernah ada kasus koordinator atau kepala sekolah menyuruh revisi rapor**. Membangun mekanisme pengembalian berarti membangun jalur yang tidak pernah dilewati, lengkap dengan urusan turunannya seperti reset persetujuan antar koordinator dan layar alasan pengembalian.

Kalau nanti benar-benar terjadi kekeliruan, sekolah menanganinya di luar sistem.

**Catatan untuk siapa pun yang mengerjakan bagian ini di kemudian hari.** Status yang tidak punya jalan mundur terasa seperti cacat, dan dorongan untuk melengkapinya akan muncul sendiri. Jangan, kecuali sekolah memintanya. Ini sama seperti sifat final status `SELESAI` di `SPEK_RUBRIK_RTS.md` bagian 6.4.1, yaitu bukan kelalaian melainkan memang yang diinginkan.

Penyetuju jadi hanya punya satu tindakan, yaitu menyetujui. Itu memang yang diinginkan.

**Jangan menambahkan pengingat atau layar pemantau untuk sesi yang lama menggantung.** Seluruh guru, koordinator, dan kepala sekolah berada di satu kantor. Kalau ada yang perlu diingatkan, mereka menanyakannya langsung atau lewat chat. Sistem tidak perlu ikut campur.

### 3.3 Yang berubah dari rancangan sebelumnya

Dua mekanisme **dihapus**, bukan diganti.

| Rancangan lama | Sekarang |
|---|---|
| Mengubah isian di step 3 menurunkan status satu langkah secara otomatis | **isian tidak bisa diubah di step 3** |
| Guru bisa menarik kembali, penyetuju bisa mengembalikan, dan keduanya mereset seluruh persetujuan | **tidak ada keduanya** |

Akibatnya seluruh urusan reset persetujuan hilang. Tidak ada lagi pertanyaan apa yang terjadi pada persetujuan koordinator lain ketika satu koordinator mengembalikan, karena tidak ada yang mengembalikan.

### 3.4 Aturan yang tetap berlaku

- Status tidak pernah kembali ke `BELUM_DIISI` setelah ditinggalkan.
- Status dan kelengkapan adalah dua hal berbeda. Kelengkapan hanya menjaga gerbang perpindahan, tidak pernah mengubah status sendiri.
- Di step 1 dan 2 isian bebas diubah, dan mengosongkan satu isian **tidak** menurunkan status. Yang terjadi hanya tombol konfirmasi jadi tidak aktif sampai diisi lagi.
- `SELESAI` bersifat final dan tidak bisa dibatalkan oleh siapa pun.

Rinciannya di `SPEK_RUBRIK_RTS.md` bagian 6.2.1, 6.2.2, dan 6.4.1, yang bekerja di tingkat sesi.

### 3.5 Menjeda dan melanjutkan sesi

Sesi tidak harus selesai dalam satu duduk. Guru bisa **mengarsipkan** sesi lalu kembali mengisi kapan saja.

- Isian tersimpan otomatis per perubahan, bukan lewat satu tombol simpan di akhir.
- Mengarsipkan cuma menutup wizard dan mencatat `tahap_terakhir`. **Tidak ada status draf**, dan statusnya tidak berubah. Istilah ini tidak ada hubungannya dengan `rubrik.status = 'arsip'`.
- Membuka sesi lagi langsung melanjutkan ke tahap terakhir yang dikerjakan.

---

## 4. Rantai Persetujuan di Dalam Step 3

Inilah bagian yang belum terakomodasi sebelumnya.

Status `MENUNGGU_TTD` ternyata bukan satu keadaan, melainkan **antrean persetujuan**. Kepala sekolah tidak bisa langsung menandatangani sebelum koordinator bidang yang terkait menyetujui.

### 4.1 Jangan menambah status baru

Godaannya adalah memecah `MENUNGGU_TTD` menjadi `MENUNGGU_KOORDINATOR` dan `MENUNGGU_KEPSEK`. **Jangan.**

Kalau nanti ada penyetuju ketiga, misalnya koordinator kurikulum atau wakil kepala sekolah, jumlah statusnya ikut bertambah, dan seluruh kode yang memeriksa status harus disunting ulang. Itu perubahan yang merembet ke mana-mana untuk sesuatu yang sebenarnya cuma penambahan baris data.

Yang benar, **simpan rantai persetujuannya sebagai data terpisah**. Statusnya tetap empat.

```
status = MENUNGGU_TTD
         │
         ├── persetujuan[1]  KOORDINATOR_QURAN   → MENUNGGU | DISETUJUI
         ├── persetujuan[1]  KOORDINATOR_BING    → MENUNGGU | DISETUJUI
         └── persetujuan[2]  KEPALA_SEKOLAH      → MENUNGGU | DISETUJUI
```

Sesi pindah ke `SELESAI` ketika **seluruh baris persetujuan berstatus DISETUJUI**. Bukan ketika satu orang tertentu menekan tombol.

Dengan begitu, menambah penyetuju ketiga cukup menambah satu baris di tabel urutan persetujuan. Tidak ada status baru, tidak ada kode yang perlu disunting.

### 4.2 Tiap penyetuju hanya berwenang atas bidangnya

Ini yang membedakan koordinator dari kepala sekolah.

| Penyetuju | Dokumen yang jadi wewenangnya | Ikut tanda tangan di cetakan |
|---|---|---|
| Koordinator Al-Quran | **hanya Rapor Ummi** | ya, di Rapor Ummi |
| Koordinator Bahasa Inggris | **hanya Rapor Bahasa Inggris** | sudah, `SPEK_RUBRIK_BING.md`. Namanya tercetak di blok tanda tangan dokumen itu |
| Kepala Sekolah | **seluruh dokumen** | ya, di seluruh dokumen |

Jadi koordinator **tidak meninjau paket secara keseluruhan**. Dia hanya membuka dokumen bidangnya, memeriksa, lalu menyetujui. Tidak ada aksi mengembalikan, lihat bagian 3.2.

Simpan wewenang ini sebagai data, bukan percabangan di kode:

Definisi tabelnya, yaitu `rubrik_alur_penyetuju` dengan kolom `cakupan ENUM('SEMUA','TERBATAS')` dan `rubrik_alur_penyetuju_dokumen`, ada di bagian 7.5.

Koordinator Bahasa Inggris masuk dengan cara ini, yaitu satu baris penyetuju dan satu baris dokumen. Penyetuju berikutnya, kalau ada, ditambahkan dengan cara yang sama tanpa menyunting kode.

**Baris persetujuan hanya dibuat kalau dokumennya ada di sesi itu.** Sesi yang tidak memuat Rapor Ummi tidak menghasilkan baris untuk Koordinator Al-Quran, dan tidak menunggu siapa-siapa.

**Di layar koordinator, tampilkan hanya dokumen bidangnya**, dalam keadaan hanya-baca. Menampilkan kelima dokumen hanya akan membuat dia ragu apakah semuanya ikut jadi tanggung jawabnya.

### 4.3 Urutannya mengikat

Penyetuju kedua tidak bisa bertindak sebelum yang pertama selesai.

| Urutan | Peran | Kapan bisa bertindak |
|---|---|---|
| 1 | seluruh koordinator bidang | begitu sesi masuk `MENUNGGU_TTD` |
| 2 | Kepala Sekolah | hanya setelah **seluruh** koordinator berstatus `DISETUJUI` |

Koordinator yang berbeda bidang berada di **urutan yang sama**, jadi mereka bisa bekerja berbarengan tanpa saling menunggu. Yang menunggu hanya kepala sekolah, dan dia menunggu semuanya.

Di layar kepala sekolah, sesi yang koordinatornya belum menyetujui **tidak boleh muncul sebagai bisa ditandatangani**. Kalaupun muncul, tombolnya mati dan keterangannya menyebut sedang menunggu siapa.

Endpoint-nya tetap harus menolak, bukan cuma layarnya.

### 4.4 Persetujuan tidak bisa diwakilkan

Koordinator yang belum menyetujui **harus dia sendiri yang menyetujui**. Tidak ada pendelegasian, dan kepala sekolah tidak bisa menyetujui mewakili koordinator.

Ini mengikuti kebiasaan di dunia nyata. Kalau yang bersangkutan tidak hadir hari itu, tanda tangannya menyusul besok saat orangnya ada. Rapor menunggu orangnya, bukan sebaliknya.

Jangan membangun fitur pendelegasian, dan jangan memberi kepala sekolah jalan pintas untuk melewati koordinator.

Kalau tanda tangan mendesak dan koordinatornya tidak ada, sekolah menanganinya dengan cara mereka sendiri, yaitu orang yang dititipi tanggung jawab membuka akun koordinator tersebut. Ini **lumrah bagi mereka dan sudah diterima**.

Tidak ada yang perlu dibangun untuk itu, dan tidak perlu ada kolom penanda siapa yang sebenarnya menekan. Cukup diketahui saja supaya tidak dianggap celah yang perlu ditambal.

## 5. Penerbitan Serentak

Tidak ada dokumen yang boleh terbit duluan.

Saat kepala sekolah menyetujui, **sesi berpindah ke `SELESAI` dan seluruh dokumennya terbit dalam satu transaksi**. Kalau PDF satu dokumen gagal dibuat, semuanya dibatalkan, tidak ada yang terbit, dan status tetap `MENUNGGU_TTD`.

Ini bukan sekadar kerapian. Rapor diserahkan ke orang tua sebagai satu bundel, dan bundel yang isinya sebagian bertanda tangan dan sebagian belum adalah dokumen yang cacat.

Tiga aturan turunannya:

- Cetak seluruh paket **tidak tersedia** sebelum status `SELESAI`.
- Cetak per dokumen juga tidak tersedia sebelum `SELESAI`. Pratinjau boleh, dengan tanda jelas bahwa itu draf.
- Tanggal pengesahan **sama untuk seluruh dokumen** dalam satu sesi, yaitu tanggal kepala sekolah menyetujui. Disimpan sekali di `rapor_sesi.tanggal_pengesahan`.

### 5.1 Terbit berarti terkirim, bukan tercetak

**Dokumen fisik ditiadakan sepenuhnya.** Yang terbit adalah PDF per murid, dikirim lewat surel dan WhatsApp.

**PDF yang sudah terkirim tidak bisa ditarik dan tidak bisa diperbarui.** Ini mempertegas sifat final status `SELESAI`. Di zaman kertas, rapor keliru masih bisa dicetak ulang sebelum diserahkan. Sekarang begitu terkirim, salinannya sudah ada di ponsel orang tua dan di luar jangkauan sistem.

Satu perkecualian, yaitu **Rapor PPI**. Kolom Hasil Capaian-nya baru terisi berbulan-bulan setelah rapor terbit, jadi PDF PPI memang berubah setelah `SELESAI`. Itu tidak membatalkan apa pun di bagian ini, karena yang membeku tetap rencananya. Lihat `SPEK_RUBRIK_PPI.md` bagian 6.

### 5.2 Tanda tangan dibubuhkan sistem

Tidak ada tanda tangan basah, jadi blok tanda tangan diisi sistem.

**Gambar tanda tangan disimpan lebih dulu** di `user_tanda_tangan`, untuk guru, koordinator, dan kepala sekolah, masing-masing atas persetujuan yang bersangkutan. Definisi tabelnya di bagian 7.1.

Kapan gambarnya diambil:

| Siapa | Diambil saat | Disimpan di |
|---|---|---|
| Guru kelas | konfirmasi penerimaan, step 2 ke 3 | `rapor_sesi.guru_ttd_berkas_id` |
| Koordinator dan kepala sekolah | menyetujui | `rapor_sesi_persetujuan.ttd_berkas_id` |

Guru dianggap menandatangani saat menyerahkan rapor untuk disetujui, karena secara logika itulah saat guru melepas dokumennya.

Empat aturan:

- **Gambar dibekukan bersama sesi.** Simpan rujukan gambar yang dipakai di kolom salinan di atas, jangan mengambilnya dari `user_tanda_tangan` saat mencetak. Orang yang mengganti gambar tanda tangannya tahun depan tidak boleh mengubah rapor yang sudah terbit. Ini aturan yang sama dengan nama kepala sekolah di `SPEK_RUBRIK_UMMI.md`.
- **Tanpa gambar, rapor tetap bisa terbit.** Yang tercetak cuma namanya. Persetujuan tetap sah karena yang mengesahkan adalah catatan di `rapor_sesi_persetujuan`, bukan gambarnya.
- **Persetujuan pemilik dicatat.** Gambar tanda tangan adalah data pribadi, dan penyimpanannya perlu jejak kapan yang bersangkutan mengizinkan.
- **Jangan pernah menampilkan gambarnya di pratinjau draf.** Pratinjau sebelum `SELESAI` menampilkan nama saja, supaya tidak ada berkas beredar yang terlihat sudah ditandatangani padahal belum.

Apakah nanti perlu tanda tangan elektronik yang tersertifikasi belum diputuskan sekolah, dan itu keputusan di luar teknis. Rancangan di atas tidak menghalangi kalau nanti diperlukan.

---

## 6. Dokumen Mana Saja di Dalam Satu Sesi

Sudah dipastikan sekolah. Ada **empat sesi per murid per tahun ajaran**, dan isinya selalu empat dokumen, atau lima untuk murid ABK.

| # | Periode | Tahap 1 | Tahap 2 | Tahap 3 | Tahap 4 | Tahap 5 |
|---|---|---|---|---|---|---|
| 1 | Tengah Semester Ganjil | **RTS** | Agama | Ummi | Bhs Inggris | PPI |
| 2 | Akhir Semester Ganjil | **RAS** | Agama | Ummi | Bhs Inggris | PPI |
| 3 | Tengah Semester Genap | **RTS** | Agama | Ummi | Bhs Inggris | PPI |
| 4 | Akhir Semester Genap | **RAS** | Agama | Ummi | Bhs Inggris | PPI |

Hanya tahap 1 yang berganti, yaitu **RTS saat tengah semester dan RAS saat akhir semester**. Tiga dokumen lainnya muncul di keempat sesi. Tahap 5 hanya ada untuk murid ABK.

### 6.1 Empat sesi, tapi bukan empat dokumen baru

Yang bertambah adalah sesinya, bukan dokumennya. Tiap dokumen tetap punya dua titik pengisian di dalam dirinya, dan keempat sesi itu memetakan ke titik-titik tersebut.

```
RTS      satu dokumen per TAHUN AJARAN
         sesi 1 → kolom TS Ganjil
         sesi 3 → kolom TS Genap

RAS      satu dokumen per TAHUN AJARAN
         sesi 2 → kolom Akhir Semester Ganjil
         sesi 4 → kolom Akhir Semester Genap

Agama    satu dokumen per TAHUN AJARAN
         sesi 1 → Tengah Semester        sesi 3 → Tengah Semester
         sesi 2 → Akhir Semester         sesi 4 → Akhir Semester

Ummi     satu dokumen per SEMESTER
         Ganjil:  sesi 1 → Tengah,  sesi 2 → Akhir
         Genap:   sesi 3 → Tengah,  sesi 4 → Akhir

B.Ing    satu dokumen per SEMESTER, tapi mencetak SATU periode
         tiap sesi → satu kolom, yaitu periode sesi itu

PPI      sama dengan Bahasa Inggris, hanya untuk murid ABK
```

Perhatikan RTS dan RAS. Keduanya **diisi dua kali setahun tapi di sesi yang berselang-seling**, bukan berurutan. RTS diisi di sesi 1 dan 3, RAS di sesi 2 dan 4.

### 6.2 Tetap simpan isinya sebagai data

Walaupun susunannya sekarang sudah pasti, jangan menanam daftar dokumen di kode:

Tabelnya `rapor_sesi_dokumen`, didefinisikan di bagian 7.5. Kolom `urutan` menentukan urutan tahap di wizard, dan `wajib` menentukan apakah dokumen itu ikut dihitung kelengkapannya. Seluruh dokumen yang ada di sesi saat ini wajib, termasuk PPI untuk murid ABK.

Dua alasannya. Pertama, tahap 5 memang berubah-ubah tergantung murid ABK atau bukan. Kedua, pergantian RTS dan RAS di tahap 1 lebih bersih diselesaikan saat sesi dibuat daripada lewat percabangan di setiap layar yang menampilkan daftar tahap.

Indikator kemajuan wizard menghitung dari tabel ini, bukan dari angka tetap. Guru murid biasa melihat `Tahap 2 dari 4`, guru murid ABK melihat `Tahap 2 dari 5`.

---

## 7. Skema Basis Data Bersama

> **Ini satu-satunya tempat tabel bersama didefinisikan.** Tiap spesifikasi rubrik hanya mendefinisikan tabel khusus dokumennya sendiri, lalu merujuk ke sini untuk periode, rapor, sesi, persetujuan, dan tanda tangan. Kalau ada definisi tabel di spesifikasi rubrik yang berbeda dari bagian ini, **bagian ini yang menang**.

Tabel pengguna disebut `user` di seluruh berkas. Setiap `FK -> user` merujuk ke tabel yang sama.

### 7.1 Berkas dan tanda tangan

```sql
berkas                        -- seluruh unggahan: gambar tanda tangan, logo, dsb.
  id               PK
  path             VARCHAR
  mime             VARCHAR
  ukuran_byte      INT
  diunggah_oleh    FK -> user
  diunggah_pada    TIMESTAMP

user_tanda_tangan             -- lihat bagian 5.2
  user_id          FK -> user  PK
  berkas_id        FK -> berkas   -- gambar, latar transparan
  disetujui_pada   TIMESTAMP      -- persetujuan pemilik menyimpan gambarnya
  diperbarui_pada  TIMESTAMP
```

NUPTK disimpan di data pegawai, yaitu kolom `user.nuptk VARCHAR NULL`, dan **boleh kosong**. Dicetak hanya kalau terisi.

### 7.2 Tahun ajaran dan periode

**Periode hanya punya satu bentuk di seluruh sistem**, yaitu tabel `periode` dengan tepat empat baris per tahun ajaran. Tidak ada `ENUM('TENGAH','AKHIR')` sebagai pengganti periode di tabel isian mana pun, dan tidak ada tabel jadwal periode kedua.

```sql
tahun_ajaran
  id               PK
  nama             VARCHAR       -- '2026/2027'
  urutan           INT UNIQUE    -- untuk mencari tahun sebelumnya tanpa mengurai teks
  tanggal_mulai    DATE
  tanggal_selesai  DATE

periode                         -- tepat 4 baris per tahun ajaran
  id               PK
  tahun_ajaran_id  FK -> tahun_ajaran
  urutan           SMALLINT      -- 1..4
  semester         ENUM('GANJIL','GENAP')
  jenis            ENUM('TENGAH','AKHIR')
  nama             VARCHAR       -- 'Tengah Semester Ganjil 2026/2027'
  tanggal_mulai    DATE
  tanggal_akhir    DATE          -- gerbang penguncian isian, lihat 7.6
  UNIQUE (tahun_ajaran_id, urutan)
  UNIQUE (tahun_ajaran_id, semester, jenis)

periode_perpanjangan            -- wajib, satu baris tiap perpanjangan
  id                  PK
  periode_id          FK -> periode
  tanggal_akhir_lama  DATE
  tanggal_akhir_baru  DATE
  alasan              TEXT NOT NULL
  diperpanjang_oleh   FK -> user     -- harus kepala sekolah
  diperpanjang_pada   TIMESTAMP
```

| urutan | semester | jenis | Nama |
|---|---|---|---|
| 1 | GANJIL | TENGAH | Tengah Semester Ganjil |
| 2 | GANJIL | AKHIR | Akhir Semester Ganjil |
| 3 | GENAP | TENGAH | Tengah Semester Genap |
| 4 | GENAP | AKHIR | Akhir Semester Genap |

**Setiap baris isian di tabel rubrik mana pun memakai `periode_id FK -> periode`**, yaitu periode konkret tempat isian itu ditulis. Jangan memakai kode kolom rubrik seperti `TS_GANJIL` atau `TENGAH_SEMESTER` sebagai pengganti periode di tabel isian. Kode itu cuma nama kolom cetakan, lihat 7.3.

### 7.3 Rubrik

```sql
rubrik                          -- satu baris per jenis dokumen per versi
  id                    PK
  kode                  VARCHAR UNIQUE  -- 'RTS_MONTESSORI_V1', 'AGAMA_V1', 'UMMI_V1', 'BING_V1', 'PPI_V1'
  jenis_dokumen         ENUM('RTS','RAS','AGAMA','UMMI','BING','PPI')
  nama                  VARCHAR
  judul_cetak           VARCHAR
  versi                 INT
  status                ENUM('draft','terkunci','arsip')
  cakupan               ENUM('TAHUNAN','SEMESTER')   -- satu dokumen per tahun atau per semester
  jenis_periode         ENUM('TENGAH','AKHIR') NULL  -- RTS: TENGAH, RAS: AKHIR, lainnya NULL = keempat periode
  cetak_gabung_periode  BOOLEAN   -- TRUE: beberapa periode berdampingan di satu cetakan
  khusus_abk            BOOLEAN   -- TRUE hanya untuk PPI
  created_at, updated_at

rubrik_periode                  -- kolom nilai di cetakan, hanya untuk cetak_gabung_periode = TRUE
  id          PK
  rubrik_id   FK -> rubrik
  kode        VARCHAR          -- 'TS_GANJIL', 'TENGAH_SEMESTER', dst. Nama kolom, bukan periode
  label       VARCHAR          -- teks kepala kolom di cetakan
  urutan      INT
  semester    ENUM('GANJIL','GENAP') NULL   -- NULL = ikut semester butir atau rapor
  jenis       ENUM('TENGAH','AKHIR')
  UNIQUE (rubrik_id, kode)

rubrik_bagian                   -- bagian dokumen dan penanda wajibnya, gerbang kelengkapan
  id          PK
  rubrik_id   FK -> rubrik
  kode        VARCHAR          -- 'A', 'B', 'C', 'I-VI', 'VII', dst.
  judul       VARCHAR
  jenis       ENUM('identitas','rubrik','matriks','daftar_catatan','teks_bebas')
  wajib       BOOLEAN          -- inilah gerbangnya
  urutan      INT

rubrik_penandatangan            -- siapa yang tercetak di blok tanda tangan dokumen itu
  rubrik_id      FK -> rubrik
  urutan         INT
  peran          ENUM('GURU_KELAS','KEPALA_SEKOLAH','KOORDINATOR_QURAN','KOORDINATOR_BING','ORANG_TUA')
  jabatan_cetak  VARCHAR        -- 'Kepala TK Zivana Montessori', 'English Teacher', dst.
  prefiks        VARCHAR NULL   -- teks di atas jabatan, misalnya 'Mengetahui,'
  cetak_nuptk    BOOLEAN DEFAULT 1
  posisi_cetak   VARCHAR NULL   -- 'kiri' | 'kanan' | 'tengah', kalau tata letaknya tidak berurutan biasa
  PRIMARY KEY (rubrik_id, urutan)
```

| Rubrik | `cakupan` | `jenis_periode` | `cetak_gabung_periode` | Kolom di cetakan |
|---|---|---|---|---|
| RTS | TAHUNAN | TENGAH | TRUE | TS Ganjil = (GANJIL, TENGAH), TS Genap = (GENAP, TENGAH) |
| RAS | TAHUNAN | AKHIR | TRUE | belum dispesifikasi |
| Agama | TAHUNAN | NULL | TRUE | Tengah = (semester butir, TENGAH), Akhir = (semester butir, AKHIR) |
| Ummi | SEMESTER | NULL | TRUE | Tengah = (semester rapor, TENGAH), Akhir = (semester rapor, AKHIR) |
| Bahasa Inggris | SEMESTER | NULL | FALSE | satu kolom, yaitu periode sesi yang terbit |
| PPI | SEMESTER | NULL | FALSE | satu kolom, yaitu periode sesi yang terbit |

**Cara generator menemukan nilai sebuah kolom.** Periode konkretnya adalah baris `periode` dengan `tahun_ajaran_id` milik rapor, `semester` = kolom, atau kalau kosong semester butir, atau kalau kosong semester rapor, dan `jenis` = kolom. Satu aturan, berlaku untuk seluruh rubrik.

Rubrik yang tidak punya bagian bernama, seperti RTS, cukup satu baris `rubrik_bagian` dengan `wajib = TRUE`.

### 7.4 Rapor

```sql
rapor                           -- identitas dokumen, tanpa status
  id               PK
  murid_id         FK
  tahun_ajaran_id  FK -> tahun_ajaran
  rubrik_id        FK -> rubrik
  semester         ENUM('GANJIL','GENAP','TAHUNAN') NOT NULL
                   -- 'TAHUNAN' jika dan hanya jika rubrik.cakupan = 'TAHUNAN'
  created_at, updated_at
  UNIQUE (murid_id, tahun_ajaran_id, rubrik_id, semester)
```

**Rapor tidak punya status.** Status hanya ada di `rapor_sesi`. Satu rapor bisa dipakai lebih dari satu sesi, misalnya satu rapor RTS dipakai sesi Tengah Ganjil dan sesi Tengah Genap, dan tiap sesi punya statusnya sendiri.

Jangan memakai `NULL` untuk semester rapor tahunan. Batasan `UNIQUE` di kebanyakan basis data menganggap dua `NULL` berbeda, sehingga satu murid bisa mendapat dua rapor RTS di tahun yang sama tanpa ditolak.

### 7.5 Sesi, persetujuan, dan jejak

```sql
rapor_sesi                      -- satu per murid per periode
  id               PK
  murid_id         FK
  periode_id       FK -> periode
  kelas_id         FK
  guru_kelas_id    FK -> user
  status           ENUM('BELUM_DIISI','TELAH_DIISI','MENUNGGU_TTD','SELESAI')
                                -- default 'BELUM_DIISI'
  tahap_terakhir   SMALLINT NULL   -- untuk melanjutkan setelah diarsipkan, lihat 3.5
  diisi_selesai_oleh  FK -> user NULL
  diisi_selesai_pada  TIMESTAMP NULL
  penerimaan_oleh     FK -> user NULL
  penerimaan_pada     TIMESTAMP NULL
  -- salinan guru, diambil saat konfirmasi penerimaan (step 2 ke 3)
  guru_nama        VARCHAR NULL
  guru_nuptk       VARCHAR NULL
  guru_ttd_berkas_id  FK -> berkas NULL
  -- pengesahan, diisi saat sesi menjadi SELESAI
  tempat           VARCHAR NULL    -- dari data sekolah, bukan ditulis di templat
  tanggal_pengesahan  DATE NULL
  terbit_pada      TIMESTAMP NULL
  created_at, updated_at
  UNIQUE (murid_id, periode_id)

rapor_sesi_dokumen              -- lihat bagian 6
  sesi_id   FK -> rapor_sesi
  rapor_id  FK -> rapor
  urutan    INT
  wajib     BOOLEAN DEFAULT 1
  PRIMARY KEY (sesi_id, rapor_id)

rubrik_alur_penyetuju           -- urutan persetujuan, data bukan kode
  id        PK
  kode      VARCHAR     -- 'KOORDINATOR_QURAN' | 'KOORDINATOR_BING' | 'KEPALA_SEKOLAH'
  label     VARCHAR
  urutan    INT         -- koordinator bidang = 1, kepala sekolah = 2
  cakupan   ENUM('SEMUA','TERBATAS')
  aktif     BOOLEAN DEFAULT 1
  UNIQUE (kode)

rubrik_alur_penyetuju_dokumen   -- hanya untuk cakupan TERBATAS, lihat 4.2
  penyetuju_id  FK -> rubrik_alur_penyetuju
  rubrik_id     FK -> rubrik
  PRIMARY KEY (penyetuju_id, rubrik_id)

rapor_sesi_persetujuan          -- satu baris per penyetuju per sesi
  id            PK
  sesi_id       FK -> rapor_sesi
  penyetuju_id  FK -> rubrik_alur_penyetuju
  urutan        INT                           -- disalin saat baris dibuat, lihat di bawah
  status        ENUM('MENUNGGU','DISETUJUI')  -- default 'MENUNGGU'
  disetujui_oleh  FK -> user NULL
  disetujui_pada  TIMESTAMP NULL
  -- salinan penandatangan, diambil saat menyetujui
  nama          VARCHAR NULL
  nuptk         VARCHAR NULL
  ttd_berkas_id FK -> berkas NULL
  UNIQUE (sesi_id, penyetuju_id)
  INDEX (sesi_id, urutan)

rapor_sesi_log                  -- jejak perpindahan status dan persetujuan
  id             PK
  sesi_id        FK -> rapor_sesi
  aksi           VARCHAR   -- 'KONFIRMASI_ISI','KONFIRMASI_PENERIMAAN','SETUJUI','TERBIT','ARSIPKAN'
  status_sebelum VARCHAR NULL
  status_sesudah VARCHAR NULL
  oleh           FK -> user
  pada           TIMESTAMP

rapor_isian_log                 -- jejak perubahan isian, SELURUH dokumen
  id                    PK
  sesi_id               FK -> rapor_sesi
  rapor_id              FK -> rapor
  tabel                 VARCHAR       -- 'rapor_penilaian', 'rapor_ummi_catatan', dst.
  baris_kunci           VARCHAR       -- kunci baris yang berubah, misalnya kode butir
  nilai_lama            TEXT NULL
  nilai_baru            TEXT NULL     -- NULL = dikosongkan
  status_sesi_saat_itu  VARCHAR
  oleh                  FK -> user
  pada                  TIMESTAMP
  INDEX (sesi_id, pada)
```

**Satu log isian untuk seluruh dokumen.** Setiap penulisan ke tabel isian dokumen mana pun, termasuk menghapus baris karena dikosongkan, menulis satu baris ke `rapor_isian_log`. Kolom `status_sesi_saat_itu` yang membuat log ini berguna, karena perubahan saat `TELAH_DIISI` artinya ada yang diubah setelah guru menyatakan selesai, misalnya saat diskusi dengan orang tua. Data berubah tanpa jejak adalah salah satu keluhan yang melatarbelakangi proyek ini.

`ppi_capaian` juga ikut tercatat, dengan `sesi_id` sesi rencananya.

**Kapan baris persetujuan dibuat.** Saat konfirmasi penerimaan, yaitu step 2 ke 3, satu baris untuk tiap penyetuju yang berwenang atas dokumen di sesi itu. `urutan` disalin saat itu juga, supaya sesi lama tidak ikut berubah kalau urutan penyetuju diubah tahun depan.

**Kenapa nama, NUPTK, dan gambar tanda tangan disalin.** Rapor yang sudah terbit tidak boleh berubah karena guru pindah kelas, kepala sekolah berganti, atau seseorang mengganti gambar tanda tangannya. Salinan diambil tepat saat orang itu bertindak, yaitu guru saat konfirmasi penerimaan dan penyetuju saat menyetujui.

**Cara generator mengisi blok tanda tangan.** Untuk tiap baris `rubrik_penandatangan`, ambil salinan dari sesi yang terbit. `GURU_KELAS` dari kolom `guru_*` di `rapor_sesi`. `KEPALA_SEKOLAH` dan koordinator dari baris `rapor_sesi_persetujuan` dengan kode penyetuju yang sama. `ORANG_TUA` dicetak sebagai jabatan dan garis kosong saja. Untuk dokumen yang mencetak dua periode berdampingan, seperti RTS dengan dua blok tanda tangan, tiap blok diambil dari sesi periodenya masing-masing, dan blok periode yang belum terbit dicetak kosong.

### 7.6 Batasan yang harus dipasang

**Setiap baris isian menunjuk ke tepat satu sesi.** Sesinya adalah `rapor_sesi` dengan `murid_id` milik rapor dan `periode_id` milik baris itu. Seluruh aturan kunci diturunkan dari sini, jadi tidak ada data yang kuncinya tidak jelas.

Sebuah penulisan isian **hanya diterima kalau keempat syarat ini terpenuhi**, dan diperiksa di lapisan penyimpanan, bukan di controller:

1. Status sesinya `BELUM_DIISI` atau `TELAH_DIISI`
2. Hari ini belum melewati `periode.tanggal_akhir`
3. Rapornya tercatat di `rapor_sesi_dokumen` sesi itu
4. `periode_id` baris yang ditulis sama dengan periode sesi yang sedang dibuka

Syarat keempat yang membuat nilai periode sebelumnya aman. Saat mengisi TS Genap, nilai TS Ganjil tampil di layar, tapi baris itu milik sesi Tengah Ganjil yang sudah terkunci, jadi tidak bisa ikut tertulis.

**Satu perkecualian**, yaitu kolom Hasil Capaian Rapor PPI di tabel `ppi_capaian`. Tabel itu diisi setelah sesi `SELESAI` dan tidak terikat status sesi. Lihat `SPEK_RUBRIK_PPI.md` bagian 6.

Batasan lainnya:

- **Kunci tanggal hanya menahan penulisan isian**, bukan perpindahan status. Guru yang isiannya sudah lengkap tetap bisa menekan konfirmasi setelah tanggal akhir lewat.
- **Tidak ada jalur yang bisa menurunkan status.** Tidak dari `MENUNGGU_TTD`, tidak dari `SELESAI`, dan tidak ada peran apa pun termasuk admin yang bisa melakukannya. Lihat bagian 3.2.
- Sesi hanya boleh pindah ke `SELESAI` kalau **seluruh** baris `rapor_sesi_persetujuan` berstatus `DISETUJUI`.
- Penyetuju urutan ke-N hanya boleh bertindak kalau seluruh baris urutan sebelumnya sudah `DISETUJUI`.
- Penyetuju hanya boleh bertindak atas sesi yang memuat dokumen bidangnya.
- Kepala sekolah **tidak bisa** menyetujui mewakili koordinator. Lihat bagian 4.4.
- Perpindahan sesi ke `SELESAI` dan pembuatan PDF seluruh dokumennya terjadi dalam satu transaksi. Kalau satu PDF gagal dibuat, tidak ada yang terbit.

### 7.7 Perpanjangan periode

Kepala sekolah bisa memperpanjang periode dengan **memajukan** `tanggal_akhir`. Ini satu-satunya jalan kalau periode sudah lewat tapi ada sesi yang belum selesai diisi.

- **Hanya maju.** Tanggal baru harus lebih besar dari `MAX(tanggal_akhir_lama, hari_ini)`. Memakai `> tanggal_akhir_lama` saja tidak cukup, karena tanggal yang maju tapi sudah lewat tetap tidak membuka apa pun.
- **Hanya kepala sekolah.**
- **Alasan wajib diisi.**
- **Selalu menulis satu baris** ke `periode_perpanjangan`. Jangan cuma menimpa tanggal.
- **Yang terbuka kembali hanya sesi di step 1 dan 2** pada periode itu. Sesi di step 3 dan 4 sudah terkunci oleh statusnya, dan perpanjangan tidak mengubah itu.

Perpanjangan berlaku untuk seluruh murid di periode itu, bukan per murid. Diterima sekolah apa adanya. Tidak perlu membangun buka kunci per rapor.

### 7.8 Yang dibuang dari rancangan lama

Kalau pengembang sudah sempat membangun dari versi awal `SPEK_RUBRIK_RTS.md`, tiga hal ini perlu dibongkar:

| Rancangan lama | Sekarang |
|---|---|
| `periode_penilaian`, satu baris per kolom rubrik per tahun | tabel `periode`, empat baris per tahun. Lihat 7.2 |
| `rapor_periode` berisi status dan salinan penandatangan per dokumen | **dihapus**. Status di `rapor_sesi`, salinan penandatangan di `rapor_sesi` dan `rapor_sesi_persetujuan` |
| `UNIQUE (murid_id, tahun_ajaran_id, rubrik_id)` pada `rapor` | ditambah `semester`. Lihat 7.4 |

Alasan `periode_penilaian` dibuang lebih dari sekadar kerapian. Rapor Agama dan Ummi punya kolom `Tengah Semester` yang dipakai di dua semester. Dengan satu baris jadwal per kolom per tahun, Tengah Ganjil dan Tengah Genap terpaksa berbagi satu tanggal akhir, dan catatan Bagian VII Agama semester Genap akan menimpa catatan semester Ganjil.

### 7.9 Kontrak API sesi

Endpoint isian per dokumen ada di spesifikasi rubrik masing-masing. Yang di bawah ini berlaku untuk seluruh sesi.

```
GET   /api/sesi/{sesi_id}
      Status, daftar tahap dari rapor_sesi_dokumen, kelengkapan per tahap,
      dan baris persetujuan beserta siapa yang sedang ditunggu.

GET   /api/sesi/{sesi_id}/kelengkapan
      Rincian per tahap dan per bagian, bentuknya seperti contoh di bagian 8.

POST  /api/sesi/{sesi_id}/konfirmasi-isi
      Step 1 ke 2. Hanya guru kelas sesi itu.
      Menolak 422 beserta rincian kelengkapan kalau ada isian wajib yang kosong.

POST  /api/sesi/{sesi_id}/konfirmasi-penerimaan
      Step 2 ke 3. Hanya guru kelas. Menolak kalau status bukan TELAH_DIISI,
      dan menolak 422 kalau kelengkapan tidak penuh.
      Menyalin nama, NUPTK, dan gambar tanda tangan guru.
      Membuat baris rapor_sesi_persetujuan untuk penyetuju yang relevan.

POST  /api/sesi/{sesi_id}/setujui
      Penyetuju yang sedang mendapat giliran dan berwenang atas sesi itu.
      Menyalin nama, NUPTK, dan gambar tanda tangannya.
      Kalau ini baris terakhir, sesi menjadi SELESAI dan seluruh dokumen terbit.

POST  /api/sesi/{sesi_id}/arsipkan
      Menutup wizard dan menyimpan tahap_terakhir. Status tidak berubah.

PATCH /api/periode/{periode_id}/perpanjang
      Hanya kepala sekolah. { "tanggal_akhir_baru": "...", "alasan": "..." }
      Mengembalikan jumlah sesi step 1 dan 2 yang ikut terbuka kembali,
      supaya kepala sekolah tahu dampaknya sebelum menyimpan.

GET   /api/sesi/{sesi_id}/cetak
      Paket PDF seluruh dokumen sesi. Sebelum SELESAI hanya pratinjau
      bertanda draf, tanpa gambar tanda tangan.
```

Tidak ada endpoint untuk menurunkan status, menarik kembali, mengembalikan ke guru, atau membatalkan persetujuan. Ketiadaannya disengaja.

---

## 8. Kelengkapan Sesi

Sesi dianggap lengkap kalau **seluruh dokumen wajib di dalamnya lengkap**, masing-masing menurut aturannya sendiri.

| Dokumen | Syarat lengkapnya |
|---|---|
| RTS Montessori | seluruh 175 sel periode itu terisi |
| Rapor Agama | seluruh butir semester berjalan terisi, dan keenam isian Bagian VII terisi |
| Rapor Ummi | Catatan Guru tidak kosong. Bagian A dan B boleh kosong |
| Rapor Bahasa Inggris | kelima baris nilai dan keempat kotak komentar terisi. Lihat `SPEK_RUBRIK_BING.md` bagian 8 |
| Rapor PPI | seluruh 30 isian terisi, yaitu 5 aspek dikali Kekuatan, Tantangan, Jangka Panjang, Jangka Pendek, Strategi, dan Media. Hasil Capaian tidak pernah dihitung. Lihat `SPEK_RUBRIK_PPI.md` bagian 9 |

Aturan tiap dokumen tetap milik spesifikasi rubriknya masing-masing. Yang dilakukan di sini hanya menjumlahkan.

**Satu definisi kosong untuk seluruh sistem.** Isian dianggap kosong kalau `NULL`, string kosong, atau string yang isinya hanya spasi, tab, dan baris baru. Buat sebagai satu fungsi yang dipakai semua dokumen, lihat `SPEK_RUBRIK_RTS.md` bagian 6.2.2. Teks disimpan apa adanya tanpa dirapikan, tapi kalau isinya kosong menurut definisi ini, barisnya dihapus.

**Pesan galat harus menunjuk sampai ke dalam.** Dengan empat sampai lima dokumen, pesan seperti "masih ada yang belum lengkap" tidak berguna sama sekali. Yang dibutuhkan guru adalah tahap mana, bagian mana, dan berapa yang kurang.

```
Belum bisa dikonfirmasi. Masih ada 3 isian yang kosong.

  Tahap 1  RTS Montessori      2 kosong  → Area Bahasa, Area Sikap
  Tahap 2  Rapor Agama         1 kosong  → Bagian VII, Asmaul Husna
  Tahap 3  Rapor Ummi          lengkap
  Tahap 4  Rapor Bhs Inggris   lengkap
```

Tiap baris jadi tautan yang melompat langsung ke tempatnya.

---

## 9. Keputusan dan Sisa Pertanyaan

### 9.1 Sudah diputuskan

**Isian terkunci begitu masuk step 3**, untuk semua orang termasuk guru yang mengisinya.

**Tidak ada jalan mundur dari step 3.** Tidak ada tarik kembali dan tidak ada pengembalian oleh penyetuju. Sepanjang catatan sekolah belum pernah ada kasus rapor disuruh revisi, jadi jalur itu tidak dibangun. Kalau nanti terjadi, ditangani di luar sistem.

**Persetujuan tidak bisa diwakilkan.** Koordinator yang belum menyetujui harus dia sendiri yang menyetujui. Kepala sekolah tidak punya jalan pintas.

**Label step 3 adalah "Menunggu Disetujui".** Kode statusnya tetap `MENUNGGU_TTD`.

**Tiap koordinator hanya berwenang atas dokumen bidangnya.** Koordinator Al-Quran hanya Rapor Ummi, dan hanya ikut menandatangani cetakan Rapor Ummi.

**Dokumen yang diisi berbeda antara tengah dan akhir semester.** RTS hanya untuk tengah semester, dan digantikan **RAS** pada akhir semester.

**Ada empat sesi per murid per tahun ajaran**, dan isinya selalu empat dokumen atau lima untuk murid ABK. Hanya tahap 1 yang berganti antara RTS dan RAS. Lihat bagian 6.

**Rapor terbit sebagai PDF, tidak dicetak.** Dokumen fisik ditiadakan sepenuhnya. Lihat bagian 5.1.

**Tanda tangan dibubuhkan sistem dari gambar yang disimpan**, bukan ditandatangani tangan. Lihat bagian 5.2.

**NUPTK disimpan di data pegawai dan boleh kosong.** Dicetak hanya kalau terisi. Lihat bagian 7.1.

**Tidak ada pengingat atau layar pemantau.** Seluruh pihak berada di satu kantor dan saling mengingatkan langsung. Lihat bagian 3.2.

### 9.2 Tidak ada lagi yang menghambat

Satu hal yang perlu diputuskan sekolah tapi tidak menahan pembangunan, yaitu **apakah tanda tangan perlu tersertifikasi secara elektronik**. Untuk sekarang gambar tanda tangan yang disimpan sudah cukup. Lihat bagian 5.2.

Seluruh pertanyaan alur sudah terjawab. Yang tersisa hanya spesifikasi isi satu dokumen yang belum dibuat, yaitu **RAS**.

Rapor Bahasa Inggris sempat terlihat menuntut perubahan karena blok tanda tangannya menyebut `English Teacher`, tapi itu **guru kelas murid itu sendiri** dengan istilah yang berbeda. Tidak ada guru mata pelajaran di sekolah ini. Satu sesi tetap diisi satu guru. Lihat `SPEK_RUBRIK_BING.md` bagian 2.

Rapor PPI sudah dispesifikasi di `SPEK_RUBRIK_PPI.md` dan **tidak menuntut perubahan apa pun pada alur**. Sesi diskusi dengan orang tua yang mengubah isian PPI berlangsung di step 2, yang memang masih bisa diubah. Lihat bagian 7 dokumen itu. Satu perkecualian terhadap penguncian ada di sana, yaitu Hasil Capaian yang diisi setelah sesi selesai.

### 9.3 Sengaja ditunda

Keduanya bukan penghalang, dan sekolah sudah memutuskan untuk tidak membangunnya di pilot. Dicatat di sini supaya kalau nanti dibutuhkan, alasannya sudah jelas dan tidak perlu dibahas dari nol.

| Hal | Kenapa ditunda | Kapan jadi perlu |
|---|---|---|
| **Pengembalian rapor untuk direvisi** | belum pernah terjadi sepanjang catatan sekolah | kalau mulai ada rapor yang benar-benar perlu ditarik setelah diserahkan |
| **Pembatalan tanda tangan kepala sekolah** | sudah diputuskan final sejak awal | lihat `SPEK_RUBRIK_RTS.md` bagian 6.4.1 |

---

## 10. Dokumen RAS Belum Dispesifikasi

RAS, yaitu Rapor Akhir Semester atau Rapor Pengembangan, adalah dokumen yang menggantikan RTS pada akhir semester.

Dari contoh yang sudah dilihat sekilas, bentuknya mirip RTS, yaitu tabel `Lingkup Perkembangan` dan `Kompetensi` dengan kolom capaian untuk Akhir Semester Ganjil dan Akhir Semester Genap, memakai **empat simbol yang sama dengan RTS**. Isinya berbeda.

Belum dianalisis lebih jauh atas permintaan sekolah, dan akan dikerjakan belakangan. Yang perlu disiapkan sekarang hanya satu, yaitu **jangan menanam asumsi bahwa RTS selalu ada di tiap sesi**. Tabel `rapor_sesi_dokumen` di bagian 6 sudah menanganinya.

---

## 11. Hubungan dengan Spesifikasi Lain

| Dokumen | Yang berlaku dari sana | Yang tinggal di dokumen ini |
|---|---|---|
| `SPEK_RUBRIK_RTS.md` | isi rubrik, tabel khusus RTS, 6.2.1, 6.2.2, 6.4.1, 6.5, 6.6, cetak, endpoint isian | status, peran, penguncian, perpanjangan, tabel bersama, endpoint sesi, kriteria alur |
| `SPEK_RUBRIK_AGAMA.md` | seluruh isi dan tabel khusus Agama | alur, tabel bersama |
| `SPEK_RUBRIK_UMMI.md` | seluruh isi dan tabel khusus Ummi | alur, tabel bersama |
| `SPEK_RUBRIK_BING.md` | seluruh isi dan tabel khusus Bahasa Inggris | alur, tabel bersama |
| `SPEK_RUBRIK_PPI.md` | seluruh isi, tabel khusus PPI, dan alur Hasil Capaian | alur sesi, tabel bersama |

---

## 12. Kriteria Penerimaan Alur

Berlaku untuk seluruh dokumen. Kriteria khusus isi tiap dokumen ada di spesifikasi rubriknya masing-masing.

**Status dan kelengkapan**

- [ ] Sesi yang RTS-nya terisi 170 dari 175 berstatus `BELUM_DIISI`, dan layarnya tetap menampilkan `170/175`.
- [ ] Konfirmasi isi dengan satu isian wajib kosong ditolak, dan pesannya menyebut tahap, bagian, dan jumlah yang kurang.
- [ ] Penolakan tetap terjadi kalau permintaan dikirim langsung ke endpoint tanpa lewat layar.
- [ ] Di step 2, mengosongkan satu isian **tidak** mengubah status. Tombol konfirmasi penerimaan tampil tapi tidak aktif, dan keterangannya menunjuk isian yang kosong.
- [ ] Setelah isian itu diisi lagi, konfirmasi penerimaan berhasil dan sesi menjadi `MENUNGGU_TTD`.
- [ ] Tidak ada satu pun jalur yang mengembalikan status ke `BELUM_DIISI` setelah ditinggalkan.
- [ ] Isian teks yang hanya berisi spasi, tab, atau baris baru dihitung kosong.
- [ ] Rekap di tingkat kelas menghitung kelengkapan sungguhan, bukan menghitung sesi berstatus `TELAH_DIISI`.

**Penguncian**

- [ ] Di step 3 dan 4, setiap penulisan isian ditolak di lapisan penyimpanan, termasuk oleh guru yang mengisinya, admin, dan superadmin.
- [ ] Tidak ada endpoint yang bisa menurunkan status dari `MENUNGGU_TTD` atau `SELESAI`. Diuji dengan akun admin dan superadmin.
- [ ] Sesi Tengah Ganjil yang sudah `SELESAI` tidak mengunci isian sesi Tengah Genap, walaupun keduanya memakai rapor RTS yang sama.
- [ ] Saat mengisi sesi Tengah Genap, nilai Tengah Ganjil tampil hanya-baca, dan permintaan langsung untuk menulis baris Tengah Ganjil ditolak.
- [ ] Sehari setelah `tanggal_akhir`, penulisan isian sesi step 1 dan 2 di periode itu ditolak.
- [ ] Setelah diperpanjang, sesi step 1 dan 2 bisa diisi lagi, sementara sesi step 3 dan 4 tetap menolak.
- [ ] Perpanjangan dengan tanggal yang tidak lebih besar dari `MAX(tanggal_akhir_lama, hari_ini)` ditolak.
- [ ] Perpanjangan tanpa alasan ditolak, perpanjangan oleh guru ditolak, dan setiap perpanjangan meninggalkan satu baris di `periode_perpanjangan`.

**Persetujuan**

- [ ] Sesi tanpa Rapor Ummi tidak punya baris persetujuan Koordinator Al-Quran. Hal yang sama untuk Koordinator Bahasa Inggris.
- [ ] Koordinator hanya bisa membuka dokumen bidangnya, dalam keadaan hanya-baca.
- [ ] Kepala sekolah tidak bisa menyetujui selama masih ada koordinator urutan 1 yang `MENUNGGU`, termasuk lewat endpoint langsung.
- [ ] Kepala sekolah tidak bisa menyetujui atas nama koordinator.
- [ ] Guru kelas tidak bisa memanggil endpoint persetujuan.
- [ ] Sebelum kepala sekolah menyetujui, layar menampilkan konfirmasi yang menyebut nama murid, periodenya, dan bahwa tindakan itu tidak bisa dibatalkan.

**Penerbitan dan tanda tangan**

- [ ] Persetujuan terakhir membuat sesi `SELESAI` dan seluruh dokumennya terbit dalam satu transaksi. Kalau PDF satu dokumen gagal dibuat, tidak ada yang terbit dan status tetap `MENUNGGU_TTD`.
- [ ] Gambar tanda tangan yang tercetak adalah gambar saat orang itu bertindak. Mengganti gambar sesudahnya tidak mengubah PDF rapor yang sudah terbit, termasuk kalau PDF itu dibuat ulang.
- [ ] Penandatangan tanpa gambar tanda tangan tidak menghalangi penerbitan. Cetakannya menampilkan nama saja.
- [ ] Pratinjau sebelum `SELESAI` bertanda draf dan tidak menampilkan gambar tanda tangan.
- [ ] Tanggal pengesahan sama untuk seluruh dokumen dalam satu sesi.

**Menjeda sesi**

- [ ] Guru mengisi sebagian, menekan arsipkan, lalu membuka lagi. Wizard melanjutkan ke tahap terakhir, seluruh isian utuh, dan status tidak berubah.
- [ ] Menutup halaman secara paksa di tengah pengisian tidak menghilangkan isian yang sudah terlihat tersimpan.
