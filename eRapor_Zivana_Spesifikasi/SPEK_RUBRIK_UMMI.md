# Spesifikasi Rubrik Penilaian — Rapor Al-Quran Metode Ummi
## eRapor Zivana Montessori

Dokumen keempat dari enam. Terjemahan lengkap dari `Rapor_Metode_Ummi_20262027-1.xlsx` ke bentuk yang siap dibangun jadi sistem.

Seluruh teks materi disalin **persis** dari berkas sumber, termasuk salah ketik yang ada di dalamnya. Daftarnya di bagian 9.

**Berkas pendamping**: `rubrik_ummi_seed.json`. Pakai berkas itu untuk seeding, jangan mengetik ulang dari tabel di dokumen ini.

> **Siap di-seed.** Seluruh pertanyaan yang menghambat sudah dijawab sekolah.

> **Rapor ini jarang terisi penuh, dan itu normal.** Kelengkapan ditentukan oleh penanda wajib per bagian, bukan oleh jumlah sel terisi. Baca bagian 8.2 sebelum menyalin logika penerbitan dari RTS.

> **Blok Hafalan di berkas Excel bukan milik dokumen ini, dan tidak di-seed di mana pun.** Lihat bagian 6.3.

> **Alur, status, penguncian, dan tabel bersama ada di `SPEK_ALUR_PENGISIAN.md`.** Guru mengisi seluruh dokumen rapor satu murid dalam satu sesi. Dokumen ini hanya memuat isi dan tabel khusus Ummi.

---

## 1. Urutan Enam Dokumen

Rapor di Zivana terdiri dari enam dokumen. Ummi adalah yang keempat.

| No | Dokumen | Status |
|---|---|---|
| 1 | Data Murid | di luar cakupan rubrik |
| 2 | RTS Montessori | sudah dispesifikasi, lihat `SPEK_RUBRIK_RTS.md` |
| 3 | Rapor Agama | sudah, `SPEK_RUBRIK_AGAMA.md` |
| 4 | **Rapor Ummi** | **dokumen ini** |
| 5 | Rapor Bahasa Inggris | sudah, `SPEK_RUBRIK_BING.md` |
| 6 | Rapor PPI | sudah, `SPEK_RUBRIK_PPI.md`, khusus murid ABK |

Satu murid bisa memegang beberapa dokumen sekaligus pada tahun ajaran yang sama. Karena itu kunci unik tabel `rapor` menyertakan `rubrik_id`. Kunci lengkapnya di `SPEK_ALUR_PENGISIAN.md` bagian 7.4.

---

## 2. Bacaan Wajib Sebelum Menyalin dari RTS

Dokumen Ummi **tidak** memakai pola yang sama dengan RTS. Kalau modelnya disalin mentah-mentah, tujuh hal berikut akan salah.

**Yang sama, dan boleh dipakai ulang apa adanya:** alur empat status, dua titik pengisian dalam satu dokumen, isian titik pertama tetap tersimpan dan tetap terlihat saat mengisi titik kedua, penguncian berdasarkan tanggal, perpanjangan oleh kepala sekolah, sifat final status `SELESAI`, dan jejak perubahan.

**Yang berbeda:**

| | RTS Montessori | Rapor Ummi |
|---|---|---|
| Dokumen per murid per tahun | satu | **dua**, Semester Ganjil dan Semester Genap |
| Dua titik pengisian itu | Tengah Semester **Ganjil** dan Tengah Semester **Genap**, menyeberangi dua semester | Tengah Semester dan Akhir Semester, **di dalam satu semester yang sama** |
| Jenis skala | empat tingkat bersimbol | **dua belas nilai huruf** |
| Semua bagian dinilai? | ya | **tidak**, ada daftar catatan dan teks bebas |
| Bagian yang tercetak dua kolom periode | seluruhnya | **hanya Bagian A**. B dan C tetap disimpan per periode, lihat 3.2 |
| Jumlah baris | tetap, 175 | **berubah-ubah**, Bagian B tumbuh sesuai jumlah tes |
| Bagian yang wajib diisi | seluruhnya | **hanya Catatan Guru** |
| Penandatangan | Kepala Sekolah, Guru Kelas, dan Orang Tua | **Kepala Sekolah dan Koordinator Al-Quran** |

Baris paling berbahaya adalah **bagian yang wajib diisi**. Kalau aturan RTS disalin mentah, sistem akan menolak mengonfirmasi sesi dengan rapor Ummi yang sebenarnya sudah benar. Dibahas di bagian 8.2.

---

## 3. Skala Penilaian

Satu skala saja, yaitu **nilai huruf**, dipakai oleh Bagian A dan Bagian B.

### 3.1 Nilai huruf, dua belas tingkat

Sekolah menetapkan `A` sampai `D`, masing-masing dengan plus dan minus.

| Peringkat | Nilai | | Peringkat | Nilai | | Peringkat | Nilai | | Peringkat | Nilai |
|---|---|---|---|---|---|---|---|---|---|---|
| 12 | `A+` | | 9 | `B+` | | 6 | `C+` | | 3 | `D+` |
| 11 | `A` | | 8 | `B` | | 5 | `C` | | 2 | `D` |
| 10 | `A-` | | 7 | `B-` | | 4 | `C-` | | 1 | `D-` |

**Bentuk isiannya dropdown**, berisi dua belas pilihan di atas, diurutkan dari `A+` turun ke `D-`. Ini keputusan yang berlaku untuk seluruh dokumen rapor, bukan hanya Ummi, supaya guru mengisi lewat satu bentuk komponen yang sama dari awal sampai akhir.

Simpan sebagai **tabel**, bukan enum di kode. Kolom peringkat dipakai untuk mengurutkan pilihan di layar dan untuk rekap di kemudian hari. Skala huruf lebih mungkin berubah daripada skala bersimbol.

Kolom `peringkat` bukan nilai angka dan **jangan dipakai menghitung rata-rata**. Jarak antara `B` dan `B+` belum tentu sama dengan jarak antara `C` dan `C+`. Kalau nanti butuh rekap, rekapnya berupa sebaran, bukan nilai rata-rata.

Kosong berarti belum dinilai. Simpan `NULL`, cetak sebagai sel kosong.

**Catatan.** Di berkas sumber hanya muncul `B` dan `B+` sebagai contoh isian. Daftar lengkap di atas berasal dari keterangan sekolah, bukan dari berkas. Asumsi yang dipakai, keempat huruf sama-sama punya plus dan minus, termasuk `D`. Kalau ternyata `D` tidak bertingkat, kabari supaya tiga baris terbawah dibuang.

### 3.2 Dua titik pengisian, dan setiap bagian ikut periodenya

Di Bagian A, setiap baris materi punya **dua kolom nilai**, yaitu `Tengah Semester` dan `Akhir Semester`.

Bagian B dan C tidak tercetak dalam dua kolom, tapi **isiannya tetap milik periode tempat ditulis**. Alasannya aturan kunci di `SPEK_ALUR_PENGISIAN.md` bagian 7.6, yaitu setiap baris isian harus menunjuk ke tepat satu sesi. Kalau catatan guru tidak punya periode, tidak jelas catatan itu terkunci oleh sesi Tengah atau sesi Akhir.

| Bagian | Disimpan | Dicetak di rapor yang terbit |
|---|---|---|
| A | per periode | kedua kolom, Tengah dan Akhir |
| B | tiap baris tes milik periode tempat dicatat | seluruh baris semester itu sampai periode yang terbit |
| C | satu catatan per periode | catatan periode yang terbit |

Jadi di Akhir Semester guru menulis catatan baru. Catatan Tengah Semester tetap tersimpan dan terlihat hanya-baca, dan guru boleh menyalinnya sendiri kalau masih berlaku.

### 3.3 Letak dua titik itu, di dalam satu semester

Bentuknya sama persis dengan RTS. Satu dokumen, dua titik pengisian, dan isian titik pertama **tetap tersimpan serta tetap terlihat** saat mengisi titik kedua. Implementasinya boleh disalin.

Yang berbeda hanya letak kedua titik itu.

```
RTS      satu dokumen per TAHUN AJARAN
         ├── Tengah Semester Ganjil
         └── Tengah Semester Genap        ← menyeberangi dua semester

Ummi     satu dokumen per SEMESTER
         ├── Tengah Semester
         └── Akhir Semester               ← di dalam satu semester
```

Jadi untuk Semester Ganjil 2025/2026, guru mengisi Tengah Semester Ganjil, rapor diserahkan, lalu di Akhir Semester Ganjil guru mengisi kolom kedua sementara kolom pertama tetap terbaca di sebelahnya.

**Ada dua dokumen Ummi per murid per tahun ajaran**, satu untuk Semester Ganjil dan satu untuk Semester Genap. Sudah dipastikan sekolah.

Isi Bagian A, B, dan C **identik** di keduanya. Yang berbeda hanya label semester pada judul cetak. Jadi **cukup satu rubrik** yang melayani keduanya, jangan dibuat dua.

Yang membedakan kedua dokumen itu ada di tabel `rapor`, lewat kolom `semester`. Rinciannya di bagian 7.

---

## 4. Tabel Variabel Penilaian

Seluruh materi Bagian A, disalin persis dari berkas sumber.

### 4.1 Bagian A. Bacaan Jilid

Tujuh tingkat, 27 materi, seluruhnya dalam **satu rubrik**. Tingkat `PRA TK` ditandai supaya bisa disembunyikan saat dicetak untuk murid yang tidak memulai dari sana.

Seluruh isian boleh kosong. Lihat bagian 8.2.

| Tingkat | # | Materi | Kode |
|---|---|---|---|
| **PRA TK** ⚑ | 1 | Mengenal huruf tunggal berharokat fathah ( A- Ya ) | `ummi__pra_tk__mengenal_huruf_tunggal_berharokat_fathah_a_ya` |
|  | 2 | Membaca 2 huruf tunggal berharokat fathah A - Ya | `ummi__pra_tk__membaca_2_huruf_tunggal_berharokat_fathah_a_ya` |
| **I** | 1 | Mengenal huruf tunggal/ hijaiyah ( Alif – Ya’ ) | `ummi__i__mengenal_huruf_tunggal_hijaiyah_alif_ya` |
|  | 2 | Mengenal huruf tunggal berharokat fathah ( A- Ya ) | `ummi__i__mengenal_huruf_tunggal_berharokat_fathah_a_ya` |
|  | 3 | Membaca 2 – 3 huruf tunggal berharokat fathah A - Ya | `ummi__i__membaca_2_3_huruf_tunggal_berharokat_fathah_a_ya` |
| **II** | 1 | Mengenal harokat kasroh, dlommah, | `ummi__ii__mengenal_harokat_kasroh_dlommah` |
|  | 2 | Mengenal huruf sambung Alif–Ya’ | `ummi__ii__mengenal_huruf_sambung_alif_ya` |
|  | 3 | Mengenal harokat fathatain, kasrotain dan dlommatain. | `ummi__ii__mengenal_harokat_fathatain_kasrotain_dan_dlommatain` |
|  | 4 | Mengenal angka arab 1 - 99 | `ummi__ii__mengenal_angka_arab_1_99` |
| **III** | 1 | Fathah diikuti alif dan fathah panjang | `ummi__iii__fathah_diikuti_alif_dan_fathah_panjang` |
|  | 2 | Kasroh diikuti ya sukun dan kasroh panjang | `ummi__iii__kasroh_diikuti_ya_sukun_dan_kasroh_panjang` |
|  | 3 | Dlommah diikuti waw sukun dan dlommah panjang | `ummi__iii__dlommah_diikuti_waw_sukun_dan_dlommah_panjang` |
|  | 4 | Mengenal tanda baca panjang | `ummi__iii__mengenal_tanda_baca_panjang` |
|  | 5 | Mengenal angka arab 100-500 | `ummi__iii__mengenal_angka_arab_100_500` |
| **IV** | 1 | Mengenal tanda sukun ditekan membacanya | `ummi__iv__mengenal_tanda_sukun_ditekan_membacanya` |
|  | 2 | Mengenal tanda tasydid ditekan membacanya | `ummi__iv__mengenal_tanda_tasydid_ditekan_membacanya` |
|  | 3 | Mengenal angka arab 500-900 | `ummi__iv__mengenal_angka_arab_500_900` |
| **V** | 1 | Mengenal cara membaca waqof atau mewaqofkan | `ummi__v__mengenal_cara_membaca_waqof_atau_mewaqofkan` |
|  | 2 | Mengenal bacaan dengung atau gunnah | `ummi__v__mengenal_bacaan_dengung_atau_gunnah` |
|  | 3 | Mengenal bacaan ikhfa’/samar | `ummi__v__mengenal_bacaan_ikhfa_samar` |
|  | 4 | Mengenal bacaan idghom bighunnah | `ummi__v__mengenal_bacaan_idghom_bighunnah` |
|  | 5 | Mengenal bacaan iqlab | `ummi__v__mengenal_bacaan_iqlab` |
|  | 6 | Mengenal bacaan lafadz Allah | `ummi__v__mengenal_bacaan_lafadz_allah` |
| **VI** | 1 | Mengenal bacaan qolqolah | `ummi__vi__mengenal_bacaan_qolqolah` |
|  | 2 | Mengenal bacaan idghom bilaghunnah | `ummi__vi__mengenal_bacaan_idghom_bilaghunnah` |
|  | 3 | Mengenal bacaan idzhar | `ummi__vi__mengenal_bacaan_idzhar` |
|  | 4 | Mengenal bacaan Ana, Na-nya dibaca pendek | `ummi__vi__mengenal_bacaan_ana_na_nya_dibaca_pendek` |

⚑ Hanya dicetak untuk murid yang memulai dari PRA TK.


## 5. Satu Rubrik, Bukan Dua Varian

Berkas sumber punya dua sheet yang terlihat seperti dua varian dokumen, yaitu `JILID` yang mulai dari jilid I, dan `PRA` yang menambahkan satu tingkat `PRA TK` di atasnya. Isi jilid I sampai VI sama persis di keduanya.

**Keduanya digabung menjadi satu rubrik.** Ini keputusan sekolah, dan alasannya kuat.

Murid belajar Ummi dengan cara **maju**, tidak berhenti di satu jilid. Murid yang baru sampai jilid 1 otomatis meninggalkan jilid 2 sampai 6 dalam keadaan kosong. Jadi rapor yang sebagian besar kosong itu **keadaan normal**, bukan rapor yang belum selesai diisi.

Begitu kekosongan diterima sebagai hal biasa, memisahkan dua varian hanya demi dua baris `PRA TK` jadi tidak ada gunanya. Dua rubrik yang isinya 90 persen sama juga berarti setiap perbaikan teks materi harus dikerjakan dua kali, dan cepat atau lambat keduanya akan berbeda tanpa ada yang sadar.

Maka strukturnya:

- **Satu rubrik**, tujuh tingkat, `PRA TK` lalu `I` sampai `VI`, total 27 materi.
- **Seluruh isian boleh kosong.** Tidak ada materi yang wajib.
- Tingkat `PRA TK` ditandai `hanya_pra_tk = true` di berkas seed.

### 5.1 Satu sakelar menggantikan varian

Varian tetap dibutuhkan untuk satu hal, yaitu menentukan apakah blok `PRA TK` muncul. Cukup satu kolom, **disimpan per periode** supaya ikut aturan kunci sesi:

```sql
rapor_ummi_periode
  rapor_id       FK -> rapor
  periode_id     FK -> periode
  mulai_pra_tk   BOOLEAN DEFAULT 0
  PRIMARY KEY (rapor_id, periode_id)
```

Saat sesi Akhir Semester dibuka, nilainya diawali dari periode Tengah Semester di rapor yang sama. Kalau disimpan di tingkat rapor, mengubahnya di sesi Akhir akan ikut mengubah cetakan Tengah Semester yang sudah terbit.

**Siapa yang menentukan.** Guru, lewat sakelar di layar pengisian. Keputusannya sendiri diambil di luar sistem, yaitu kesepakatan antar guru soal apakah anak itu masuk kategori PRA atau tidak. Sistem tidak perlu menebak dari usia, kelas, atau apa pun. Sediakan sakelarnya, sisanya urusan guru.

**Pengaruhnya di dua tempat:**

| | `false` (default) | `true` |
|---|---|---|
| Layar pengisian | blok PRA TK tidak muncul | blok PRA TK muncul di paling atas |
| Cetakan | blok PRA TK dilewati | blok PRA TK ikut tercetak |

Hasilnya sama persis dengan dua varian di berkas asli, tapi tanpa dua rubrik terpisah.

Sakelarnya bisa dinyalakan dan dimatikan selama sesinya di step 1 atau 2. Kalau dimatikan sementara blok PRA TK sudah terisi, **jangan hapus isiannya**, cukup sembunyikan. Guru yang salah pencet lalu membetulkan tidak boleh kehilangan data.

### 5.2 Jangan menghitung kekosongan sebagai kesalahan

Konsekuensi yang paling sering terlewat. Karena kekosongan itu normal:

- Jangan memberi tanda peringatan pada tingkat yang kosong.
- Jangan menampilkan kemajuan seperti `6/27` dengan nada seolah kurang. Untuk Ummi angka itu tidak berarti apa-apa.
- Jangan menghalangi penerbitan karena masih ada yang kosong. Lihat bagian 8.2.

---

## 6. Tiga Bagian Dokumen

| Bagian | Judul | Bentuk | Jumlah baris | Dua titik pengisian | Wajib diisi |
|---|---|---|---|---|---|
| A | Bacaan Jilid | rubrik berjenjang | tetap 27, **sebagian besar sengaja kosong** | **ya** | tidak |
| B | Nilai Tes Kenaikan Jilid | daftar catatan | **berubah-ubah, boleh nol** | tiap baris milik satu periode | tidak |
| C | Catatan Guru | textarea | satu per periode | ya, lihat 3.2 | **ya** |

Berkas Excel sebenarnya memuat satu blok lagi, yaitu Hafalan. Blok itu **bukan milik dokumen ini**. Lihat 6.3.

### 6.1 Bagian B bukan rubrik

Ini jebakan utamanya. Bagian B terlihat seperti tabel penilaian, padahal isinya **catatan kejadian**. Tiap baris mencatat satu kali tes kenaikan jilid, dengan kolom nomor urut, tanggal tes beserta jilidnya, dan nilai.

**Bagian ini opsional dan defaultnya kosong.** Guru menambahkan baris hanya kalau memang ada tes yang perlu dicatat. Rapor tanpa satu pun baris di Bagian B adalah rapor yang sah, dan tidak boleh menghalangi penerbitan.

Di berkas sumber hanya disediakan dua baris kosong, tapi itu keterbatasan kertas, bukan aturan. Seorang murid bisa saja ikut tes lebih dari dua kali dalam satu semester, atau tidak sama sekali.

Jadi jangan membuat dua kolom tetap bernama `tes_1` dan `tes_2`. Buat tabel tersendiri dengan baris yang bisa ditambah dan dihapus:

```sql
rapor_ummi_tes
  id              PK
  rapor_id        FK -> rapor
  periode_id      FK -> periode        -- periode tempat tes dicatat, lihat 3.2
  urutan          INT
  tanggal_tes     DATE
  jilid           VARCHAR              -- jilid yang diteskan
  skala_huruf_id  FK -> skala_huruf    -- sama dengan Bagian A
  dicatat_oleh    FK -> user
  dicatat_pada    TIMESTAMP
  INDEX (rapor_id, periode_id, urutan)
```

Di cetakan, kalau barisnya kurang dari dua, tetap gambar dua baris supaya bentuknya sama seperti dokumen aslinya. Sel kosong diberi placeholder singkat `—`; placeholder hanya untuk cetakan dan bukan data tes. Kalau lebih dari dua, gambar semua baris yang ada.

Kolom nilai memakai **skala huruf yang sama dengan Bagian A**, yaitu dua belas tingkat dari `A+` sampai `D-`. Jadi komponen isiannya sama, dan `skala_huruf_id` menunjuk ke tabel `skala_huruf` yang sama.

### 6.2 Bagian C, Catatan Guru — satu-satunya yang wajib

Satu **textarea**. Redaksinya sepenuhnya milik guru, tidak ada templat dan tidak ada kalimat yang disiapkan sistem.

**Ini satu-satunya bagian yang wajib diisi di seluruh Rapor Ummi**, di setiap periode. Seluruh Bagian A boleh kosong, Bagian B boleh nol baris, tapi sesi tidak bisa dikonfirmasi tanpa catatan guru periode itu.

Masuk akal kalau dilihat dari sisi orang tua. Rapor Ummi yang sebagian besar kosong hampir tidak berarti apa-apa tanpa kalimat yang menjelaskan posisi anak. Contoh di berkas sumber melakukan persis itu, yaitu menyebut anak sedang di jilid berapa dan halaman berapa. Kolom inilah yang membawa maknanya.

Berlaku definisi kosong yang sudah ditulis di spesifikasi RTS bagian 6.2.2, yaitu `NULL`, string kosong, dan string berisi spasi saja semuanya dihitung kosong. Untuk kolom ini definisi itu bukan sekadar kerapian, tapi memang yang menentukan rapor boleh terbit atau tidak.

Di berkas sumber kolom ini berisi contoh tulisan tentang murid bernama Shabir dari kelas Mekar Masamba. Itu sisa contoh dari dokumen lain dan **jangan ikut di-seed**. Lihat bagian 9.2.

### 6.3 Hafalan bukan milik dokumen ini

Berkas Excel memuat satu blok berjudul `C. HAFALAN` di antara Bagian B dan Catatan Guru, berisi 21 butir dalam tiga kelompok, yaitu Surah Pendek, Do'a Harian, dan Hadits.

**Blok itu bukan milik Rapor Ummi.** Sempat diduga milik Rapor Agama, tapi setelah berkas Rapor Agama diterima, **daftarnya ternyata tidak sama** dengan daftar hafalan yang dipakai Rapor Agama. Rincian perbandingannya di `SPEK_RUBRIK_AGAMA.md` bagian 8.

Konsekuensinya:

- **Jangan** membuat tabel `rubrik_ummi_hafalan` maupun `rapor_ummi_hafalan`.
- **Jangan** menampilkan blok hafalan di layar pengisian Ummi.
- **Jangan** mencetaknya di rapor Ummi.
- **Jangan** men-seed ke-21 butir itu ke rubrik mana pun. Hafalan yang berlaku ada di `rubrik_agama_seed.json`.

---

## 7. Skema Basis Data

Tabel bersama, yaitu `rubrik`, `rubrik_bagian`, `periode`, `rapor`, dan `rapor_sesi`, didefinisikan di `SPEK_ALUR_PENGISIAN.md` bagian 7. Di sini hanya tabel khusus Ummi.

Nilai untuk Ummi di tabel bersama:

| Tabel | Nilai Ummi |
|---|---|
| `rubrik` | `kode = 'UMMI_V1'`, `cakupan = 'SEMESTER'`, `jenis_periode = NULL`, `cetak_gabung_periode = TRUE` |
| `rubrik_periode` | `TENGAH_SEMESTER` = (semester rapor, TENGAH), `AKHIR_SEMESTER` = (semester rapor, AKHIR) |
| `rapor` | dua per murid per tahun ajaran, `semester = 'GANJIL'` dan `'GENAP'` |

```sql
-- definisi rubrik
rubrik_ummi_jilid          -- tujuh tingkat: PRA TK, I .. VI. Tidak ada tabel varian.
  id            PK
  rubrik_id     FK -> rubrik
  kode          VARCHAR
  nama          VARCHAR      -- 'PRA TK', 'I', 'II', ...
  urutan        INT
  hanya_pra_tk  BOOLEAN DEFAULT 0   -- true hanya untuk tingkat PRA TK

rubrik_ummi_materi
  id          PK
  jilid_id    FK -> rubrik_ummi_jilid
  kode        VARCHAR UNIQUE
  teks        TEXT
  urutan      INT
  aktif       BOOLEAN DEFAULT 1

-- dua belas nilai huruf, dipakai Bagian A dan Bagian B. Lihat bagian 3.1.
skala_huruf
  id          PK
  rubrik_id   FK -> rubrik
  kode        VARCHAR      -- 'A+' .. 'D-'
  label       VARCHAR
  peringkat   INT          -- 12 = A+ (tertinggi), 1 = D- (terendah)
  UNIQUE (rubrik_id, kode)
```

```sql
-- penilaian
rapor_ummi_bacaan          -- Bagian A
  id              PK
  rapor_id        FK -> rapor
  materi_id       FK -> rubrik_ummi_materi
  periode_id      FK -> periode
  skala_huruf_id  FK -> skala_huruf NULL   -- NULL = belum dinilai
  diisi_oleh      FK -> user NULL
  diisi_pada      TIMESTAMP NULL
  UNIQUE (rapor_id, materi_id, periode_id)

rapor_ummi_tes             -- Bagian B, lihat 6.1

rapor_ummi_catatan         -- Bagian C, WAJIB terisi di tiap periode. Lihat 8.2
  id            PK
  rapor_id      FK -> rapor
  periode_id    FK -> periode
  isi           TEXT NOT NULL
  diisi_oleh    FK -> user NULL
  diisi_pada    TIMESTAMP NULL
  UNIQUE (rapor_id, periode_id)
```

Tiga hal yang disengaja dan mudah dikira salah:

- **Ketiga tabel isian punya `periode_id`.** Bagian B dan C juga, walaupun tidak tercetak dua kolom. Lihat bagian 3.2.
- Tidak ada tabel varian dan tidak ada tabel hafalan. Sesuai bagian 5 dan 6.3.
- Satu rubrik melayani Semester Ganjil dan Genap sekaligus. Yang membedakan hanya kolom `semester` pada `rapor`.

Catatan guru yang dikosongkan dihapus barisnya, bukan disimpan sebagai string kosong. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 8.

---

## 8. Aturan Bisnis dan Cetak

### 8.1 Yang dipakai ulang dari RTS

Alur empat status di tingkat sesi, pemisahan status dan kelengkapan, penguncian, perpanjangan oleh kepala sekolah, sifat final status `SELESAI`, dan jejak perubahan lewat `rapor_isian_log`. Semuanya berlaku sama. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 3 dan 7, serta `SPEK_RUBRIK_RTS.md` bagian 6.2.1 dan 6.4.1.

### 8.2 Kelengkapan ditentukan penanda wajib, bukan jumlah sel

Aturan kelengkapan RTS **tidak dibatalkan**, hanya digeneralisasi. Bentuk umumnya:

> Sesi boleh dikonfirmasi kalau seluruh bagian yang **ditandai wajib** sudah terisi untuk periode sesi itu.

RTS kebetulan menandai semuanya wajib, sehingga terbaca seperti "175 sel harus penuh". Ummi menandai hampir semuanya opsional. Aturannya satu dan sama, isinya saja yang berbeda.

Karena itu penanda wajib harus jadi **data**, bukan kondisi yang ditulis di kode per dokumen. Pasang di tingkat bagian:

Tabelnya `rubrik_bagian`, didefinisikan di `SPEK_ALUR_PENGISIAN.md` bagian 7.3. Kolom `wajib` di tabel itu adalah gerbangnya. Seluruh dokumen lain memakai tabel yang sama.

Untuk Rapor Ummi:

| Bagian | Wajib | Alasan |
|---|---|---|
| A. Bacaan Jilid | tidak | kosong itu normal, tergantung sejauh mana murid sudah maju |
| B. Nilai Tes | tidak | opsional, boleh nol baris |
| C. Catatan Guru | **ya** | satu-satunya gerbang penerbitan |

Jadi syarat Ummi di sebuah sesi hanya satu, yaitu **Catatan Guru periode itu tidak kosong**.

Untuk Bagian A, tetap tampilkan hitungan terisi di layar sebagai informasi, misalnya `terisi 6 dari 27`. Tapi jangan diberi warna peringatan, jangan dipakai menghalangi tombol, dan jangan disebut belum lengkap.

### 8.3 Penandatangan dan data sekolah

Yang menandatangani adalah **Kepala TK** dan **Koordinator Al-Quran**. Tidak ada guru kelas.

**Yang mengisi adalah guru kelas**, bukan Koordinator Al-Quran. Zivana tidak punya guru agama atau guru mengaji tersendiri, semua pengajarnya guru TK umum, dan satu guru kelas mengisi seluruh dokumen rapor muridnya dalam satu sesi. Lihat bagian 8.5.

Koordinator Al-Quran adalah jabatan yang **ikut menandatangani** dokumen ini, bukan orang yang mengisinya. Jadi jangan membuat peran `GURU_QURAN` di RBAC, dan jangan mengunci pengisian Rapor Ummi ke peran selain guru kelas.

**Sumber nama.** Nama kepala sekolah dan tempat pengesahan diambil dari modul **Data Sekolah**. Untuk pilot nilainya cukup diisi sekali dan tidak perlu ada layar pengaturannya, tapi **kolomnya harus sudah ada sejak sekarang**. Yang dilarang adalah menulis nama atau kota langsung di dalam templat cetak.

**Nama disalin saat menyetujui, bukan di-join hidup.** Salinannya ada di `rapor_sesi_persetujuan`, lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.5. Sekolah membenarkan aturan ini. Kalau kepala sekolah berganti, rapor yang sudah ditandatangani **tidak berubah**. Nama baru hanya berlaku untuk rapor semester berikutnya.

Berkas sumber kebetulan menyediakan contoh nyatanya. Sheet utama memakai nama kepala sekolah periode lalu, sheet Redaksi memakai nama yang berlaku sekarang. Lihat bagian 9.3.

### 8.4 Cetak

Satu halaman A4 tegak, jauh lebih pendek daripada RTS.

Susunannya dari atas ke bawah: judul beserta label semester dan tahun pelajaran, blok identitas berisi Unit Sekolah, Nama, NISN, dan Kelas, lalu Bagian A sampai C berurutan, lalu blok tanda tangan dua kolom.

Beberapa hal yang perlu diperhatikan:

- Kolom jilid di Bagian A **digabung ke bawah** sepanjang jumlah materinya. Jilid V punya enam materi, jadi sel jilidnya menutupi enam baris.
- Bagian A punya satu judul kolom `CAPAIAN SEMESTER GANJIL` yang membentang di atas dua sub-kolom `TENGAH SEMESTER` dan `AKHIR SEMESTER`.
- Tahun pelajaran dan tanggal pengesahan diambil dari basis data, jangan ditanam di templat.

---

### 8.5 Pengisian lintas dokumen

Bagian ini **sudah dipindahkan ke `SPEK_ALUR_PENGISIAN.md`**, karena ternyata berlaku untuk seluruh dokumen rapor dan isinya berkembang jauh melebihi satu bagian.

Ringkasnya, guru mengisi seluruh dokumen rapor satu murid dalam **satu sesi** berbentuk wizard, lalu satu konfirmasi selesai, satu rantai persetujuan yang dimulai dari Koordinator Al-Quran lalu kepala sekolah, dan satu tanda tangan untuk seluruh paket. Tidak ada dokumen yang bisa terbit duluan.

Rinciannya, termasuk skema tabel sesi dan persetujuannya, ada di dokumen tersebut.

---

## 9. Temuan di Berkas Sumber

### 9.1 Salah ketik dan penomoran

| Lokasi | Tertulis | Catatan |
|---|---|---|
| Judul bagian | **C.** HAFALAN dan **C.** CATATAN GURU | Dua bagian diberi huruf yang sama |
| Blok Hafalan, kelompok Hadits | **Hadist** Senyum, **Hadist** Kasih Sayang | Dua butir lain memakai ejaan **Hadits** |

Karena blok Hafalan tidak ikut di dokumen ini, kedua temuan itu **tidak berakibat apa pun ke sistem**. Seed memakai tiga bagian, yaitu A. Bacaan Jilid, B. Nilai Tes Kenaikan Jilid, dan C. Catatan Guru.

### 9.2 Isi contoh yang tertinggal

Kolom Catatan Guru berisi tulisan tentang **"Ananda Shabir"** di **"Kelas Mekar Masamba"**. Itu bukan murid dan bukan kelas Zivana. Kelas Zivana bernama seperti "Ranting Akasia" berdasarkan dokumen RTS.

Berkas ini jelas disalin dari template sekolah lain dan sebagian isinya belum dibersihkan. **Jangan ikut di-seed.**

Kolom kelancaran di Bagian C juga sudah terisi centang, dan itu pun contoh. Berkas seed tidak memuat centang tersebut.

### 9.3 Dua sheet Redaksi, dan nama Kepala TK yang benar

`Redaksi JILID` dan `redaksi PRA` berisi dokumen yang sama tapi berbeda di dua hal:

| | Sheet utama | Sheet Redaksi |
|---|---|---|
| Kolom nilai Bagian A | `CAPAIAN SEMESTER GANJIL`, terbagi Tengah dan Akhir Semester | satu kolom `NILAI` saja |
| Kepala TK | Adilah Wina Fitria, S.T., M.Pd., LCPC., Dipl. Mont | Atira Dwianti, S.Pd., Gr., Dipl. Mont., Dipl.SNT., NUPTK 7633777678230012 |

**Keduanya diambil dari sheet yang berbeda**, dan itu bukan kelalaian:

- **Struktur Bagian A** mengikuti **sheet utama**, karena bentuk dua periodenya yang berlaku sekarang.
- **Kepala TK** yang benar adalah **Atira Dwianti**, sesuai sheet Redaksi. Sudah dipastikan sekolah. Adilah Wina Fitria adalah kepala sekolah **periode semester lalu**, dan namanya tertinggal di sheet utama.

Nama Atira Dwianti juga yang muncul di dokumen RTS, lengkap dengan NUPTK yang sama.

**Ini justru contoh nyata kenapa nama penandatangan disalin saat pengesahan.** Sekolah baru saja berganti kepala. Kalau nama penandatangan di-join hidup ke tabel pegawai, seluruh rapor semester lalu yang ditandatangani Adilah akan berubah sendiri menjadi Atira begitu datanya diperbarui. Rapor yang sudah ditandatangani tidak boleh berubah isinya. Aturannya ada di `SPEK_ALUR_PENGISIAN.md` bagian 7.5 dan berlaku penuh di sini.

### 9.4 Tanggal tidak sesuai tahun pelajaran

| Tertulis | Masalah |
|---|---|
| `TAHUN PELAJARAN 2026/2027` | di kepala dokumen |
| `Makassar, 26 September 2025` | di blok tanda tangan sheet utama, **dua tahun lebih awal** |
| `Makassar, … 2026` | di sheet Redaksi, masih berupa titik-titik |

Pola yang sama muncul di dokumen RTS. Setelah memakai sistem, tanggal pengesahan diambil dari basis data dan masalah ini hilang dengan sendirinya.

---

## 10. Keputusan dan Sisa Pertanyaan

### 10.1 Sudah diputuskan

**Kelengkapan memakai penanda wajib per bagian.** Untuk Ummi, satu-satunya bagian wajib adalah Catatan Guru. Lihat bagian 8.2.

**Bagian A seluruhnya opsional.** Kosong itu keadaan normal.

**Bagian B opsional**, boleh nol baris, dan nilainya memakai skala huruf yang sama dengan Bagian A.

**Bagian C berupa textarea dan wajib diisi.**

**Nilai huruf ada dua belas**, dari `A+` sampai `D-`. Lihat bagian 3.1.

**Tidak ada varian dokumen.** Tujuh tingkat dalam satu rubrik, dengan sakelar `mulai_pra_tk` per periode yang dikendalikan guru. Lihat bagian 5.

**Ummi terbit dua kali setahun**, Semester Ganjil dan Genap, isinya identik. Satu rubrik melayani keduanya, dibedakan kolom `semester` pada `rapor`. Lihat bagian 3.3.

**Blok Hafalan bukan milik dokumen ini**, dan tidak di-seed di mana pun. Lihat bagian 6.3.

**Pengisian berbentuk wizard lintas dokumen**, bisa dijeda dengan mengarsipkan sesi. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 3.5.

**Bagian B dan C disimpan per periode.** Lihat bagian 3.2.

**Nama kepala sekolah dan tempat dari Data Sekolah.** Pergantian kepala sekolah tidak mengubah rapor yang sudah ditandatangani.

**Yang mengisi adalah guru kelas.** Koordinator Al-Quran hanya ikut menandatangani. Lihat bagian 8.3.

### 10.2 Tidak ada yang tersisa

Tidak ada lagi yang menghambat pembangunan Rapor Ummi itu sendiri.

Pertanyaan soal lapisan sesi sudah terjawab, yaitu **memang ada**, dan sudah dipindahkan ke `SPEK_ALUR_PENGISIAN.md`.

Pertanyaan turunannya juga sudah terjawab. **Rapor Ummi memang diisi di keempat periode**, sementara RTS hanya di dua periode tengah semester dan digantikan RAS di akhir semester. Jadi memang ada periode di mana Ummi diisi tapi RTS tidak. Susunan sesinya ada di `SPEK_ALUR_PENGISIAN.md` bagian 6.

## 11. Cara Memakai Berkas Ini

1. Baca bagian 2 lebih dulu. Isinya perbedaan dengan RTS, dan di situ letak sebagian besar kesalahan yang mungkin terjadi.
2. Bangun tabel bersama dari `SPEK_ALUR_PENGISIAN.md` bagian 7 lebih dulu, lalu tabel khusus Ummi dari bagian 7 dokumen ini.
3. Seed dari `rubrik_ummi_seed.json`. Jangan mengetik ulang dari tabel di bagian 4.
4. Setelah seeding, pastikan hasilnya tepat 7 tingkat, 27 materi, 12 nilai huruf, dan **tidak ada** tabel hafalan.

Struktur `rubrik_ummi_seed.json`:

```
rubrik      metadata, skala huruf 12 tingkat, kolom periode, tiga bagian
            beserta penanda wajib, penandatangan, statistik
jilid[]     7 tingkat, masing-masing berisi materi[] dan penanda hanya_pra_tk
```

Tidak ada berkas hafalan yang perlu di-seed. Lihat bagian 6.3.

Developer juga diberi akses ke berkas Excel aslinya. Kalau ada yang berbeda antara dokumen ini dan berkas itu, **berkas Excel yang menang**, dan tolong kabari supaya dokumen ini diperbarui.
