# Spesifikasi Rubrik Penilaian — Rapor Bahasa Inggris
## eRapor Zivana Montessori

Dokumen kelima dari enam. Terjemahan dari `STATEMENT_OF_RESULT.pdf` ke bentuk yang siap dibangun jadi sistem.

Seluruh teks disalin **persis** dari berkas sumber, termasuk salah ketik. Daftarnya di bagian 9.

**Berkas pendamping**: `rubrik_bing_seed.json`.

> **Tidak ada yang menghambat.** `English Teacher` di blok tanda tangan adalah **guru kelas murid itu sendiri**, cuma istilahnya berbeda. Rancangan sesi tidak berubah sedikit pun. Baca bagian 2.

> **Urutan skala sudah ditetapkan `Excellent` di atas `Outstanding`**, dan yang perlu diperbaiki adalah definisinya, bukan urutannya. Baca bagian 4.2.

> **Berkas sumber masih memuat isian murid contoh bernama Naura** di Bagian B. Kalau berkas ini dipakai guru sebagai cetakan dasar, teks itu ikut ke mana-mana. Baca bagian 9.

> **Dokumen ini paling kecil dari kelimanya.** 5 nilai dan 4 kotak teks per periode. Bandingkan RTS yang 175 nilai.

---

## 1. Urutan Enam Dokumen

| No | Dokumen | Status |
|---|---|---|
| 1 | Data Murid | di luar cakupan rubrik |
| 2 | RTS Montessori | sudah, `SPEK_RUBRIK_RTS.md` |
| 3 | Rapor Agama | sudah, `SPEK_RUBRIK_AGAMA.md` |
| 4 | Rapor Ummi | sudah, `SPEK_RUBRIK_UMMI.md` |
| 5 | **Rapor Bahasa Inggris** | **dokumen ini** |
| 6 | Rapor PPI | sudah, `SPEK_RUBRIK_PPI.md` |
| 7 | RAS Rapor Pengembangan | belum, menggantikan RTS di akhir semester |

Dengan ini, **satu-satunya yang belum dispesifikasi tinggal RAS.**

---

## 2. English Teacher Adalah Guru Kelas

Blok tanda tangannya tiga, dan salah satunya berjabatan `English Teacher`:

| Jabatan tercetak | `rubrik_penandatangan.peran` | Sama dengan |
|---|---|---|
| English Coordinator | `KOORDINATOR_BING` | penyetuju urutan 1 di rantai persetujuan |
| **English Teacher** | **`GURU_KELAS`** | **`Guru Kelas` di RTS, Agama, dan PPI** |
| Principal of Zivana Montessori School | `KEPALA_SEKOLAH` | `Kepala TK` di dokumen lain |

**`English Teacher` bukan guru mata pelajaran.** Yang menandatangani adalah guru kelas murid itu sendiri, dan istilahnya saja yang berbeda karena dokumennya berbahasa Inggris. Tidak ada guru Bahasa Inggris tersendiri, sama seperti yang sudah ditegaskan sekolah untuk Rapor Agama.

Akibatnya **tidak ada yang berubah**. Satu sesi tetap diisi satu guru kelas dalam satu duduk, tahap 4 diisi orang yang sama dengan tahap 1, 2, 3, dan 5, dan `SPEK_ALUR_PENGISIAN.md` tetap berlaku utuh.

Ini pantas diperiksa karena kalau jawabannya berbeda, seluruh rancangan sesi harus dibongkar. Nama jabatan yang berbeda di satu blok tanda tangan sudah cukup untuk itu.

### 2.1 Nama penandatangan dibekukan ke sesinya

Berlaku untuk ketiganya. Guru kelas, koordinator, dan kepala sekolah bisa berganti tahun depan, dan rapor yang sudah terbit tidak boleh ikut berubah. Nama, NUPTK, dan gambar tanda tangan diambil dari salinan sesi. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.5.

### 2.2 NUPTK

Berkas sumber memuat baris `NUPT.` di bawah English Teacher, kosong, dan di bawah Principal terisi `7633777678230012`. Angkanya enam belas digit, jadi yang dimaksud **NUPTK**, dan tulisan `NUPT` di berkas kurang huruf `K`.

Tiga hal yang mengikuti:

- NUPTK diambil dari `user.nuptk`, dan **boleh kosong**. Guru di berkas ini belum mengisinya. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.1.
- Barisnya cuma dicetak kalau terisi. Baris `NUPT.` yang menggantung tanpa angka terlihat seperti dokumen yang belum selesai.
- English Coordinator tidak punya baris NUPTK di berkas sumber. Perlu dipastikan apakah memang tidak perlu atau cuma terlewat. Ini pertanyaan 10.3.

RTS juga mencetak NUPTK kepala sekolah dan guru kelas, jadi ini bukan kebutuhan khusus Bahasa Inggris. Tempatnya di **data pegawai**, bukan di rubrik.

---

## 3. Struktur Dokumen

```
Kop         STATEMENT OF RESULT
            ENGLISH CLASS
            ZIVANA MONTESSORI SCHOOL MAKASSAR
            ACADEMIC YEAR {tahun_ajaran}
            (berulang di kedua halaman, logo di kiri)

Identitas   Student's Name, Date of Birth, Class, Term / Semester

A. LEARNING ACHIEVEMENT      tabel 2 kolom, 5 baris nilai
B. TEACHER COMMENTS          4 kotak teks berlabel tetap

── halaman 2 ──
REMARKS                      definisi keempat nilai, teks tetap
Tanda tangan                 tiga, lihat bagian 2
```

### 3.1 Dokumennya berbahasa Inggris seluruhnya

Satu-satunya dari enam dokumen yang begitu, dan itu disengaja karena ini rapor kelas bahasa Inggris.

Simpan penanda `bahasa: "en"` di rubrik. Yang berbahasa Inggris **hanya isi dokumen dan cetakannya**, bukan antarmuka sistemnya. Guru tetap memakai layar berbahasa Indonesia, dengan label kolom yang tetap berbahasa Inggris karena itu yang tercetak.

Jangan menerjemahkan apa pun yang tercetak. `Progress Indicators` tetap `Progress Indicators`, dan `Pronounciation` tetap dengan salah ketiknya sampai sekolah memutuskan lain.

### 3.2 Identitas

| Baris | Diambil dari | Catatan |
|---|---|---|
| Student's Name | `murid.nama` | |
| Date of Birth | `murid.tanggal_lahir` | **tanggal, bukan usia**. PPI memakai usia terhitung, di sini tanggal mentah |
| Class | `murid.kelas.nama` | |
| Term / Semester | `periode` | lihat 3.3 |

### 3.3 `Term / Semester` menanggung dua hal sekaligus

Satu baris untuk dua keterangan, dan berkas contohnya kosong jadi bentuk isinya belum terlihat.

Yang sudah pasti, Bahasa Inggris **muncul di keempat periode**, sesuai yang ditetapkan di `SPEK_ALUR_PENGISIAN.md` bagian 6. Dan tabel nilainya cuma punya **satu kolom**, bukan dua berdampingan seperti RTS, Agama, dan Ummi.

Artinya bentuknya sama dengan PPI, yaitu **satu cetakan satu periode**, empat kali setahun. Jadi `cakupan = 'SEMESTER'` dan `cetak_gabung_periode = FALSE`, dan tiap baris isian memakai `periode_id` konkret. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.3.

Isinya dirangkai dari periode, misalnya `Term 1 / Semester Ganjil`. Susunan katanya perlu dipastikan sekolah, dan itu pertanyaan 10.2.

---

## 4. Skala Penilaian

### 4.1 Empat nilai

| Kode | Label | Definisi di berkas sumber |
|---|---|---|
| `EXCELLENT` | Excellent | *a very good understanding... can communicate independently with little or no assistance* |
| `OUTSTANDING` | Outstanding | *excellent understanding and confidence... independently in various classroom activities* |
| `GOOD` | Good | *a good understanding... with occasional guidance from the teacher* |
| `FAIR` | Fair | *a basic understanding... needs frequent guidance, repetition, and encouragement* |

Bentuk isiannya **dropdown**, mengikuti keputusan yang berlaku untuk seluruh dokumen. Lihat `SPEK_RUBRIK_AGAMA.md` bagian 3.2.

Definisi lengkapnya ada di seed dan dicetak apa adanya di halaman REMARKS.

### 4.2 Urutan sudah ditetapkan, definisinya yang diperbaiki

Kepala kolom menulis urutan `(Excellent, Outstanding, Good, Fair )`, dan **urutan itulah yang dipakai**. `Excellent` tertinggi, lalu `Outstanding`, `Good`, dan `Fair`.

| Kode | Peringkat |
|---|---|
| `EXCELLENT` | 4 |
| `OUTSTANDING` | 3 |
| `GOOD` | 2 |
| `FAIR` | 1 |

Yang bermasalah adalah **definisinya**, karena membaca terbalik dari urutan itu:

| | Kata kunci di definisi sumber |
|---|---|
| EXCELLENT | *a **very good** understanding* |
| OUTSTANDING | ***excellent** understanding and confidence* |

`Excellent` didefinisikan sebagai *very good*, sementara `Outstanding` didefinisikan sebagai *excellent*. Guru yang membaca halaman REMARKS akan menyimpulkan `Outstanding` lebih tinggi, padahal bukan.

Ini bukan cuma soal data. **Kekeliruannya sudah berjalan sekarang, di atas kertas, tanpa sistem.** Dua guru yang menilai anak yang sama bisa memberi hasil berbeda tergantung mereka membaca kepala kolom atau halaman REMARKS.

Definisi yang perlu diperbaiki ada di bagian 9.1, dan sudah disampaikan ke sekolah lewat dokumen catatan perbaikan untuk guru.

**Sampai definisinya diperbaiki sekolah, yang di-seed tetap teks aslinya.** Peringkatnya sudah pasti, jadi seeding tidak tertahan.

### 4.3 Jangan merata-rata

Aturan yang sama dengan Rapor Ummi. Nilai huruf ini **bukan angka**, dan menghitung rata-rata dari lima baris tidak menghasilkan apa pun yang berarti.

Kalau nanti ada permintaan nilai ringkas, itu keputusan sekolah tentang cara membacanya, bukan perhitungan yang boleh diputuskan sendiri oleh sistem.

---

## 5. Bagian A, Learning Achievement

### 5.1 Lima baris nilai, satu baris kepala kelompok

```
┌──────────────────────────────────┬─────────────────────────┐
│ Progress Indicators              │ In Figures              │
│                                  │ (Excellent, Outstanding,│
│                                  │  Good, Fair )           │
├──────────────────────────────────┼─────────────────────────┤
│ Student Attendance               │        ← dropdown       │
│ Written test results             │        ← dropdown       │
├──────────────────────────────────┼─────────────────────────┤
│ Speaking Test Result             │▓▓▓▓ diarsir, bukan sel  │
│   a. Grammar and Vocabulary      │        ← dropdown       │
│   b. Pronounciation              │        ← dropdown       │
│   c. Interactive Communication   │        ← dropdown       │
└──────────────────────────────────┴─────────────────────────┘
```

**`Speaking Test Result` adalah kepala kelompok, bukan butir penilaian.** Di berkas sumber, sel nilainya diarsir kuning sama seperti baris kepala tabel, yang artinya memang tidak untuk diisi. Jangan membuatkan dropdown untuknya, dan jangan menghitungnya sebagai isian yang kurang.

Jadi **5 nilai per periode**, bukan 6.

### 5.2 `Student Attendance` memakai nilai huruf

Kelima baris memakai dropdown yang sama. Kehadiran **tidak** diisi jumlah hari.

Satu hal yang tertinggal dari situ. Kepala kolomnya berbunyi **`In Figures`**, yang berarti *dalam angka*, padahal yang diisi empat kata. Namanya memang tidak tepat, tapi itu teks tetap yang tercetak di tiap rapor dan bukan sesuatu yang perlu diakali sistem. Dicatat di bagian 9.

Sekolah masih akan memastikan ulang soal kehadiran ini. Kalau ternyata berubah jadi angka, yang diperlukan cuma satu kolom tambahan di tabel nilai, dan itu murah dikerjakan belakangan. **Jangan menyiapkan kolomnya sekarang**, karena kolom yang disiapkan untuk kemungkinan selalu berakhir jadi kolom yang tidak ada yang tahu kapan dipakai.

---

## 6. Bagian B, Teacher Comments

### 6.1 Empat kotak teks berlabel tetap

| | Label tercetak |
|---|---|
| a. | **Speaking (grammar, vocabulary, interactive communication)** |
| b. | **Reading** |
| c. | **Listening** |
| d. | **Writing** |

Labelnya tercetak tebal dan tetap, dan teks guru menyambung setelah titik dua. Jadi ini **empat textarea**, bukan satu kotak besar.

Menyimpannya sebagai empat baris, bukan satu teks panjang, membuat labelnya dijamin selalu ada dan urutannya tidak bisa tertukar. Bentuknya sama dengan Bagian VII Rapor Agama, cuma tanpa perangkaian kalimat.

Penyimpanan dan cara mencetaknya mengikuti aturan yang sama dengan PPI, yaitu teks disimpan mentah dan pemecahan jadi butir hanya terjadi di generator. Lihat `SPEK_RUBRIK_PPI.md` bagian 5.1.

### 6.2 Bagian A dan B membagi bahasa Inggris dengan cara berbeda

Ini terlihat begitu keduanya disandingkan, dan bukan kekeliruan.

```
Bagian A menilai           Bagian B menarasikan
─────────────────          ─────────────────────
Student Attendance         (tidak ada)
Written test results       (tidak ada)
Grammar and Vocabulary  ┐
Pronounciation          ├─→ a. Speaking
Interactive Comm.       ┘
(tidak ada)            ←─── b. Reading
(tidak ada)            ←─── c. Listening
(tidak ada)            ←─── d. Writing
```

**Reading, Listening, dan Writing dapat narasi tapi tidak dapat nilai.** **Attendance dan Written test dapat nilai tapi tidak dapat narasi.** Yang bertemu di keduanya cuma Speaking.

Pembagiannya memang begitu, yaitu Bagian A hasil tes dan Bagian B pengamatan menyeluruh. Dicatat di sini supaya tidak ada yang mencoba merapikannya dengan menambah baris nilai untuk Reading, Listening, dan Writing, atau menambah kotak narasi untuk Attendance.

Kalau sekolah memang ingin keduanya sejajar, itu perubahan dokumen yang diputuskan sekolah, bukan perapian yang dilakukan sendiri saat membangun.

---

## 7. Skema Basis Data

Rubrik ini kecil, dan skemanya sengaja mengikuti pola yang sudah dipakai supaya tidak ada bentuk baru untuk dipelajari. Tabel bersama ada di `SPEK_ALUR_PENGISIAN.md` bagian 7.

```sql
rubrik_bing_skala
  id              BIGINT PK
  rubrik_id       FK -> rubrik
  kode            VARCHAR(20)       -- EXCELLENT, OUTSTANDING, GOOD, FAIR
  label           VARCHAR(40)
  peringkat       SMALLINT          -- 4, 3, 2, 1. Lihat 4.2
  urutan_tampil   SMALLINT
  definisi        TEXT              -- dicetak di halaman REMARKS
  UNIQUE (rubrik_id, kode)

rubrik_bing_indikator
  id            BIGINT PK
  rubrik_id     FK -> rubrik
  kode          VARCHAR(60)
  urutan        SMALLINT
  label_cetak   VARCHAR(120)
  grup          VARCHAR(60) NULL    -- 'Speaking Test Result' atau NULL
  penanda_cetak VARCHAR(4) NULL     -- 'a.', 'b.', 'c.'
  wajib         BOOLEAN
  UNIQUE (rubrik_id, kode)

rubrik_bing_komentar
  id            BIGINT PK
  rubrik_id     FK -> rubrik
  kode          VARCHAR(40)         -- speaking, reading, listening, writing
  urutan        SMALLINT
  penanda_cetak VARCHAR(4)
  label_cetak   VARCHAR(160)
  wajib         BOOLEAN
  UNIQUE (rubrik_id, kode)

rapor_bing_nilai
  rapor_id      FK -> rapor
  periode_id    FK -> periode
  indikator_id  FK -> rubrik_bing_indikator
  pilihan_id    FK -> rubrik_bing_skala
  PRIMARY KEY (rapor_id, periode_id, indikator_id)

rapor_bing_komentar
  rapor_id      FK -> rapor
  periode_id    FK -> periode
  komentar_id   FK -> rubrik_bing_komentar
  isi           TEXT NOT NULL
  PRIMARY KEY (rapor_id, periode_id, komentar_id)
```

Isian yang kosong tidak disimpan. Nilai yang dikosongkan dan komentar yang dikosongkan atau hanya berisi spasi dihapus barisnya. **Tidak ada baris berarti belum diisi.** Definisi kosongnya di `SPEK_ALUR_PENGISIAN.md` bagian 8.

Rapor Bahasa Inggris dua per murid per tahun ajaran, `semester = 'GANJIL'` dan `'GENAP'`. Kunci uniknya di `SPEK_ALUR_PENGISIAN.md` bagian 7.4.

---

## 8. Kelengkapan

| Bagian | Syarat |
|---|---|
| A, Learning Achievement | kelima baris nilai terisi |
| B, Teacher Comments | keempat kotak terisi |

Jadi **9 isian wajib per periode**, yaitu 5 nilai dan 4 teks.

`Speaking Test Result` tidak dihitung karena bukan butir penilaian. Lihat 5.1.

Dasarnya sama dengan Rapor Agama Bagian VII dan Rapor PPI, yaitu dokumen ini dibacakan ke orang tua saat penerimaan rapor, dan bagian yang kosong berarti ada yang tidak dibicarakan.

Pesan galatnya mengikuti bentuk di `SPEK_ALUR_PENGISIAN.md` bagian 8.

---

## 9. Temuan di Berkas Sumber

Seluruhnya teks tetap yang akan disalin ke basis data lalu tercetak di tiap rapor selamanya, jadi **perlu diputuskan sekolah**, tidak seperti PPI.

| # | Tertulis | Seharusnya |
|---|---|---|
| 1 | `Pronounciation` | `Pronunciation` |
| 2 | `The most detail Indicators  for Students :` | lihat 9.2 |
| 3 | `NUPT.` | `NUPTK.` |
| 4 | definisi `EXCELLENT` dan `OUTSTANDING` | lihat 9.1 |
| 5 | `In Figures` untuk nilai berupa kata | nama kolomnya tidak tepat, tapi sekolah boleh membiarkannya |

**Memperbaikinya sekarang gratis.** Rubrik ini belum dipakai rapor mana pun. Setelah dipakai, perbaikan sekecil apa pun berarti membuat versi rubrik baru.

Aturan pembekuan kode tetap berlaku, sama seperti RTS. Kode `speaking__pronounciation` **tidak ikut berubah** walaupun teksnya nanti dibetulkan, karena kode yang berubah memutus seluruh nilai yang sudah menunjuk padanya.

### 9.1 Definisi `Excellent` dan `Outstanding`

Urutannya sudah pasti, `Excellent` di atas `Outstanding`. Yang perlu diubah cuma tiga kata di halaman REMARKS supaya definisinya ikut menurun.

| Tingkat | Tertulis | Seharusnya |
|---|---|---|
| EXCELLENT | Student demonstrates **a very good** understanding of the English material... | Student demonstrates **an exceptional** understanding of the English material... |
| OUTSTANDING | Student demonstrates **excellent** understanding and confidence... | Student demonstrates **a very good** understanding and confidence... |
| OUTSTANDING | ...independently in **various** classroom activities. | ...independently in **most** classroom activities. |

Setelah diperbaiki, keempatnya menurun rapi di dua hal sekaligus:

| Tingkat | Pemahaman | Kemandirian |
|---|---|---|
| Excellent | exceptional | little or no assistance |
| Outstanding | very good | independent in most activities |
| Good | good | occasional guidance |
| Fair | basic | frequent guidance |

Perubahan ketiga yang paling mudah terlewat. Tanpa itu, `Excellent` dan `Outstanding` sama-sama berbunyi *independently* dan guru tetap tidak punya pegangan membedakan keduanya.

### 9.2 `The most detail Indicators  for Students :`

Kalimat ini rusak di tiga lapis sekaligus.

**Tata bahasanya salah.** `the most` menuntut kata sifat, sementara `detail` itu kata benda. Yang benar `detailed`. Bentuk sekarang setara dengan menulis *the most beauty indicators*.

**Logikanya tidak jalan.** Walaupun dibetulkan jadi `the most detailed`, bentuk superlatif berarti membandingkan dengan kumpulan indikator lain yang kurang rinci. Di dokumen ini cuma ada satu kumpulan, jadi tidak ada pembandingnya.

**Sasarannya keliru.** `for Students` menandakan indikator itu milik atau untuk murid, padahal yang didaftar di bawahnya adalah **arti tiap nilai**, dan yang membacanya orang tua.

Ditambah tiga hal kecil, yaitu `Indicators` berhuruf besar di tengah kalimat tanpa alasan, spasi ganda antara `Indicators` dan `for`, serta spasi sebelum titik dua. Dalam bahasa Inggris titik dua menempel di kata sebelumnya.

| | |
|---|---|
| Tertulis | `The most detail Indicators  for Students :` |
| Perbaikan seperlunya | `Detailed indicators:` |
| Kalau mau sekalian lebih jelas | `What each result means:` |

Pilihan kedua lebih menolong, karena yang membaca halaman itu orang tua yang ingin tahu arti nilai anaknya, bukan guru yang mencari daftar indikator.

### 9.3 Berkas sumber masih memuat isian murid contoh

Bagian B di berkas ini **tidak kosong**. Isinya empat paragraf tentang seorang murid bernama Naura, lengkap dengan kata ganti `she`.

Kalau berkas ini yang dipakai guru sebagai cetakan dasar, teks Naura ikut ke rapor murid lain, dan yang paling mungkin terjadi adalah namanya diganti tapi kalimatnya tidak. Sekolah sebaiknya menyimpan satu berkas kosong terpisah dari contoh terisi.

Untuk sistem sendiri ini tidak berakibat apa-apa, karena Bagian B memang kotak kosong yang diketik guru. Teks Naura **tidak ikut di-seed**.

---

## 10. Pertanyaan

Tidak ada yang menahan pembangunan. Keempatnya bisa dijawab sambil jalan.

### 10.1 `Student Attendance` dipastikan ulang

Untuk sekarang kelimanya memakai nilai huruf. Kalau ternyata kehadiran diisi jumlah hari, yang diperlukan cuma satu kolom tambahan. Lihat 5.2.

### 10.2 `Term / Semester` diisi apa persisnya?

Satu baris untuk dua keterangan, dan berkas contohnya kosong. Perlu contoh tulisannya, misalnya `Term 1 / Semester Ganjil`.

### 10.3 English Coordinator perlu baris NUPTK?

Di berkas sumber cuma English Teacher dan Principal yang punya. Lihat 2.2.

### 10.4 Perbaikan teks di bagian 9 sudah disepakati

Sudah disampaikan ke sekolah dan siap diperbaiki. **Seed sengaja masih memuat teks asli** sampai berkas revisinya diterima, lalu dicocokkan ulang seperti RTS. Alasannya satu, kalimat pembuka halaman REMARKS punya dua pilihan perbaikan dan belum diketahui mana yang dipakai.

Nomor 5, yaitu `In Figures`, sengaja tidak disampaikan karena cuma nama kolom yang kurang tepat dan tidak menyesatkan siapa pun.

---

## 11. Hubungan dengan Spesifikasi Lain

| Berkas | Yang diambil dari sana |
|---|---|
| `SPEK_ALUR_PENGISIAN.md` | seluruh urusan status, sesi, kelengkapan, dan persetujuan. Koordinator Bahasa Inggris sudah tercatat di bagian 4 sana, dan menambahkannya cukup dua baris data |
| `SPEK_RUBRIK_PPI.md` | cara menyimpan teks mentah dan memecahnya jadi butir saat mencetak |
| `SPEK_RUBRIK_AGAMA.md` | keputusan bahwa seluruh isian berbentuk dropdown, dan pemisahan bentuk isian dari bentuk cetakan |
| `SPEK_RUBRIK_UMMI.md` | larangan merata-rata nilai huruf, dan pembekuan nama penandatangan, yang sekarang disalin ke sesi |
| `SPEK_RUBRIK_RTS.md` | pembekuan kode indikator sejak seeding pertama |

Tabel bersama, alur, dan penguncian ada di `SPEK_ALUR_PENGISIAN.md`. NUPTK penandatangan ada di data pegawai, lihat 2.2.
