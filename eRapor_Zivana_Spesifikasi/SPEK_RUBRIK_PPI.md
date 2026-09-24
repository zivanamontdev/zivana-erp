# Spesifikasi Rubrik Penilaian — Rapor PPI (Program Pembelajaran Individu)
## eRapor Zivana Montessori

Dokumen keenam dari enam. Terjemahan dari `PPI_Muhammad_Ibrahim_Hanif_2026_JUNI.pdf` ke bentuk yang siap dibangun jadi sistem.

**Berkas pendamping**: `rubrik_ppi_seed.json`.

> **Dokumen ini hampir tidak punya isi tetap.** Yang tetap cuma lima nama aspek. Seluruh isian lainnya teks bebas yang diketik guru. Baca bagian 2.

> **Ini satu-satunya dokumen yang isinya berubah saat penerimaan rapor.** Dokumen lain hanya dibacakan. PPI didiskusikan dan direvisi di depan orang tua. Baca bagian 7.

> **PPI mencetak satu periode saja**, sama seperti Bahasa Inggris. RTS, Agama, dan Ummi menampilkan dua periode berdampingan. Baca bagian 4.

> **Tidak ada lagi yang menghambat.** Yang masih terbuka ada di bagian 11, dan tidak ada yang menahan pembangunan.

> **Skrip uji pencarian tinjauan ada di `uji_tinjauan_ppi.py`.** Jalankan untuk melihat bagian 6.3 bekerja di kalender tiga tahun, termasuk kasus periode yang tidak berjalan.

> **Kolom Hasil Capaian diisi berbulan-bulan setelah rapor terbit**, oleh guru dan orang tua, lewat alur yang terpisah dari sesi. Ini perkecualian terhadap aturan pembekuan yang berlaku di seluruh sistem. Baca bagian 6 sebelum menyentuh skema PPI.

---

## 1. Urutan Enam Dokumen

| No | Dokumen | Status |
|---|---|---|
| 1 | Data Murid | di luar cakupan rubrik |
| 2 | RTS Montessori | sudah, `SPEK_RUBRIK_RTS.md` |
| 3 | Rapor Agama | sudah, `SPEK_RUBRIK_AGAMA.md` |
| 4 | Rapor Ummi | sudah, `SPEK_RUBRIK_UMMI.md` |
| 5 | Rapor Bahasa Inggris | sudah, `SPEK_RUBRIK_BING.md` |
| 6 | **Rapor PPI** | **dokumen ini**, khusus murid ABK |
| 7 | RAS Rapor Pengembangan | belum, menggantikan RTS di akhir semester |

Alur pengisian dan persetujuan ada di `SPEK_ALUR_PENGISIAN.md` dan berlaku penuh di sini.

---

## 2. Yang Tetap Cuma Lima Nama Aspek

Ini perbedaan paling besar dibanding RTS, Agama, dan Ummi, dan perlu disadari sebelum menyalin rancangan apa pun dari sana.

| | RTS | Agama | Ummi | **PPI** |
|---|---|---|---|---|
| Butir penilaian tetap | 175 | 73 | 27 | **tidak ada** |
| Bentuk isian | dropdown | dropdown | dropdown | **textarea** |
| Yang di-seed | 175 indikator | 73 butir + 99 Asmaul Husna | 27 materi | **5 nama aspek** |
| Skala nilai | 4 simbol | 7 pilihan | 12 huruf | **tidak ada** |

Seluruh tabel penilaian di RTS, Agama, dan Ummi adalah **daftar tetap yang disentang guru**. Di PPI, tabelnya cuma kerangka, dan seluruh isinya ditulis guru dari nol untuk tiap anak.

Akibatnya langsung ke rancangan:

**Tidak ada tabel nilai.** Tidak ada `rapor_nilai` dengan kolom `pilihan_id`. Yang ada cuma teks.

**Tidak ada validasi isi.** Sistem tidak bisa dan tidak boleh menilai apakah isian guru masuk akal. Satu-satunya yang bisa dicek adalah kosong atau tidak.

**Tidak ada seeding yang berarti.** Berkas `rubrik_ppi_seed.json` isinya lima nama aspek, definisi kolom, dan lebar cetak. Itu saja.

**Perubahan daftar aspek adalah perubahan data, bukan perubahan kode.** Lihat bagian 3.2.

---

## 3. Struktur Dokumen

### 3.1 Tiga bagian

```
Kop halaman  PROGRAM PEMBELAJARAN INDIVIDU
             Akhir Semester Genap
             Tahun Pelajaran 2025/2026
             TK Zivana Montessori
             (berulang di setiap halaman, dengan logo di kanan)

A. IDENTITAS                    5 baris, terisi otomatis
B. KARAKTERISTIK ANAK           tabel 5 aspek x 2 kolom
   BERDASARKAN HASIL OBSERVASI  Kekuatan, Tantangan
C. PROGRAM PEMBELAJARAN         tabel 5 aspek x 6 kolom
   INDIVIDU                     Tujuan (Panjang, Pendek), Strategi,
                                Media, Hasil Capaian (Guru, Orangtua)

Tanda tangan  Kepala TK Zivana Montessori dan Guru Kelas
```

Bagian A bukan tahap pengisian. Isinya diambil dari Data Murid, sama seperti lembar pertama di dokumen lain.

| Baris | Diambil dari | Catatan |
|---|---|---|
| Nama | `murid.nama` | |
| Usia | dihitung dari `murid.tanggal_lahir` | lihat 3.3 |
| Kelas | `murid.kelas.nama` | contohnya `Mekar Melati` |
| Diagnosa | `murid.diagnosa` | **boleh kosong**, dicetak `-` |
| Sekolah | `sekolah.nama` | |

Kolom `diagnosa` mungkin belum ada di Data Murid. Isinya di contoh adalah `-`, jadi harus boleh kosong dan **jangan dijadikan syarat kelengkapan**. Sekolah tidak selalu punya diagnosa resmi untuk tiap anak ABK.

### 3.2 Lima aspek perkembangan

| # | Kode | Aspek |
|---|---|---|
| 1 | `NAM` | Nilai Agama dan Moral |
| 2 | `FM` | Fisik Motorik |
| 3 | `KOG` | Kognitif |
| 4 | `BHS` | Bahasa |
| 5 | `SOSEM` | Sosial Emosional |

Urutan dan penomorannya sama persis di bagian B dan C, dan itu disengaja. Baris nomor 3 di bagian B dan baris nomor 3 di bagian C membicarakan anak yang sama di aspek yang sama. Lihat bagian 5.2.

**Simpan sebagai baris tabel, jangan sebagai enum di kode.** Alasannya ada di bagian 11.1, yaitu daftar rujukan nasional sebenarnya memuat enam aspek dan dokumen ini memakai lima. Kalau nanti sekolah menambahkan yang keenam, itu harus jadi satu baris baru di basis data, bukan penyisiran kode di layar isian, generator PDF, dan aturan kelengkapan sekaligus.

### 3.3 Usia dihitung dari tanggal lahir

Di contoh tertulis `7 tahun 8 bulan`. Dokumennya bertanggal 26 Juni 2026, jadi anaknya lahir sekitar Oktober 2018.

Hitung dari `murid.tanggal_lahir` ke **tanggal pengesahan sesi**, bukan ke tanggal hari ini. Kalau rapor dicetak ulang setahun kemudian, usianya harus tetap usia saat rapor itu terbit. Kalau tanggal lahir kosong, cetak `-`.

---

## 4. Satu Periode, Satu Cetakan

### 4.1 Empat dokumen PPI per tahun

Kop dokumen contoh menulis **`Akhir Semester Genap`**, satu periode saja. Tidak ada kolom Tengah Semester di sampingnya.

Bandingkan dengan RTS, Agama, dan Ummi, yang menaruh dua periode berdampingan dalam satu lembar supaya orang tua melihat perkembangan anak:

| Dokumen | Isi satu cetakan |
|---|---|
| RTS | kolom TS Ganjil dan TS Genap berdampingan |
| Rapor Agama | kolom Tengah Semester dan Akhir Semester berdampingan |
| Rapor Ummi | kolom Tengah Semester dan Akhir Semester berdampingan |
| Rapor Bahasa Inggris | satu periode saja |
| **Rapor PPI** | **satu periode saja** |

Jadi dalam satu tahun ajaran terbit **empat dokumen PPI**, masing-masing berdiri sendiri.

### 4.2 Tapi penyimpanannya tidak perlu berubah

Godaan pertama adalah membuat satu baris `rapor` untuk tiap periode, yang berarti mengubah kunci unik `rapor`. **Jangan.**

Yang berbeda cuma cara mencetak, bukan cara menyimpan. PPI menyimpan seperti Ummi, yaitu dua baris `rapor` per tahun untuk Ganjil dan Genap. Tiap baris isian memakai `periode_id` konkret, jadi teks Tengah dan Akhir tersimpan terpisah di rapor yang sama.

Pembedanya satu penanda di rubrik, `cetak_gabung_periode = FALSE`, yang sudah ada di tabel `rubrik` bersama. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.3. Generator PDF membaca penanda ini untuk memutuskan berapa kolom nilai yang digambar.

Teks periode sebelumnya **tetap tersimpan** walaupun tidak ikut dicetak, dan bisa dibuka guru saat mengisi periode berikutnya. Lihat 5.4.

---

## 5. Seluruh Isian Berbentuk Textarea

Sekolah minta textarea, bukan daftar butir yang ditambah satu per satu lewat tombol. Alasannya menambah butir jadi lebih cepat, guru tinggal menekan enter.

Permintaan itu diikuti. Tapi ada satu hal yang perlu diselesaikan, karena **cetakannya tetap berbentuk butir**, bukan paragraf.

### 5.1 Simpan mentah, ubah jadi butir saat mencetak

Aturannya satu arah, dan tidak pernah sebaliknya.

```
Disimpan apa adanya         →    Dicetak sebagai butir

Dapat duduk bersila              -  Dapat duduk bersila ketika
ketika sedang berdo'a               sedang berdo'a
Dapat bersalaman kepada          -  Dapat bersalaman kepada orang
orang dewasa secara mandiri         dewasa secara mandiri
```

Langkahnya saat mencetak:

1. Pecah isi per baris baru
2. Buang spasi di pangkal dan ujung tiap baris
3. Buang baris kosong
4. Buang penanda butir di pangkal kalau guru terlanjur mengetiknya sendiri, yaitu `-`, `–`, `—`, `•`, atau `*`, beserta spasi sesudahnya
5. Tiap baris sisa jadi satu butir, dengan indentasi gantung supaya baris sambungannya rata di bawah teks, bukan di bawah tanda hubung

**Jangan pernah merapikan teks saat menyimpan.** Guru yang sedang mengetik akan melihat kursornya melompat, dan baris kosong yang dia buat untuk mengatur napas akan hilang begitu saja. Perapian hanya terjadi di generator PDF.

Langkah 4 penting karena guru yang terbiasa mengetik `-` di depan tiap baris akan tetap melakukannya. Tanpa langkah itu, cetakannya jadi `- - Dapat duduk bersila`.

### 5.2 Tantangan di B jadi Tujuan di C

Ini terlihat jelas saat membandingkan kedua tabel di dokumen contoh, dan pantas dimanfaatkan.

Kelima aspek punya tepat dua Tantangan di bagian B, dan keduanya muncul lagi di bagian C sebagai dua Tujuan, dengan kata pembuka diubah dari `Berlatih untuk` jadi `Mampu`.

| Aspek | Tantangan di B | Jadi di C |
|---|---|---|
| Fisik Motorik | Berlatih untuk kegiatan merayap | Jangka Panjang, `Mampu melakukan kegiatan merayap` |
| Fisik Motorik | Berlatih untuk berjalan zig zag sambil membawa benda | Jangka Pendek, `Mampu untuk berjalan zig zag sambil membawa benda` |

**Tapi pemetaannya bukan urutan, dan ini yang menentukan rancangan.**

Di empat aspek, Tantangan pertama jadi Jangka Panjang dan yang kedua jadi Jangka Pendek. Di aspek Nilai Agama dan Moral, keduanya **tertukar**. Tantangan pertama di B adalah `konsisten tidak menyakiti orang lain`, dan itu jadi Jangka **Pendek**, sementara Tantangan kedua jadi Jangka Panjang.

Artinya guru yang memutuskan mana yang butuh setahun dan mana yang tiga bulan, dan itu memang penilaian profesional yang tidak bisa ditebak sistem.

Jadi yang perlu diketahui pengembang cuma ini, dan berhenti di sini:

**Kedua tabel membicarakan hal yang sama, dan guru sendiri yang menyalinnya.** Tidak ada pengisian otomatis, tidak ada tebakan mana yang panjang mana yang pendek, dan tidak ada tombol salin khusus. Guru menyorot teksnya lalu menyalin seperti biasa.

Satu hal yang tidak perlu ditangani sistem tapi layak diketahui. Di berkas contoh, satu sel lolos tanpa diubah kata pembukanya, yaitu Jangka Panjang aspek pertama yang masih berbunyi `Berlatih untuk mengikuti gerakan shalat tanpa bantuan` padahal seluruh sel lain memakai `Mampu`. Itu akibat wajar dari menyalin dengan tangan, dan bukan sesuatu yang pantas dijawab dengan fitur.

### 5.3 Kolom Hasil Capaian punya alur sendiri

Kesepuluh sel `Hasil Capaian` kosong di berkas contoh, dan itu memang bentuk yang benar saat rapor terbit. Kolom itu baru terisi berbulan-bulan sesudahnya, oleh guru dan orang tua.

Artinya kolom ini **bukan bagian dari sesi pengisian rapor**, tidak masuk hitungan kelengkapan, dan tidak boleh ikut terkunci saat sesi selesai.

Alurnya dibahas utuh di bagian 6.

### 5.4 Tidak ada pengisian otomatis dari periode sebelumnya

Rencana di bagian C bertenggat tiga bulan dan satu tahun, keduanya melewati batas satu periode. Jadi saat guru mengisi periode berikutnya, sebagian besar isian kemungkinan masih berlaku apa adanya.

**Jangan mengisi kotaknya secara otomatis, dan jangan membuat tombol salin.** Guru menyalin sendiri seperti biasa.

Kotak yang terisi sendiri membuat guru merasa sudah selesai, dan rapor yang seharusnya menggambarkan perkembangan anak jadi berhenti bergerak. Anak yang sudah bisa merayap tetap tertulis sedang berlatih merayap, karena tidak ada yang memaksa siapa pun membacanya ulang.

Yang perlu dipastikan cuma **isian periode sebelumnya bisa dibuka guru saat mengisi**, karena kalau tidak, satu-satunya jalan menyalin adalah membuka PDF lama. Datanya sudah tersimpan per periode, jadi ini soal layar, bukan soal data.

---

## 6. Hasil Capaian: Alur Terpisah Setelah Rapor Selesai

Kolom `Hasil Capaian` **tetap ada di tabel bagian C dan tetap dicetak**. Yang berbeda cuma waktu dan cara mengisinya.

Ini alur yang belum pernah ada di dokumen mana pun, dan perlu dibaca utuh sebelum menyentuh skema PPI.

### 6.1 Dua zona di satu dokumen

Seluruh dokumen lain membeku sekaligus saat sesi berstatus `SELESAI`. PPI tidak.

```
┌─ Bagian A, B, dan C (rencana) ────────────────────────┐
│  diisi guru sebelum penerimaan rapor                  │
│  TERKUNCI sejak step 3, sama seperti dokumen lain     │
└───────────────────────────────────────────────────────┘
┌─ Kolom Hasil Capaian ─────────────────────────────────┐
│  KOSONG saat rapor terbit, dan itu wajar              │
│  diisi guru dan orang tua BERBULAN-BULAN SETELAHNYA   │
│  tidak terikat status sesi sama sekali                │
└───────────────────────────────────────────────────────┘
```

Ini perkecualian terhadap aturan yang berlaku di seluruh sistem, jadi perlu ditulis terang-terangan.

**Kunci sesi membekukan rencananya, bukan Hasil Capaian.** Pengembang yang membaca `SPEK_ALUR_PENGISIAN.md` bagian 3.1 akan mengunci seluruh isian PPI begitu sesi masuk step 3, dan itu mengunci kolom yang justru baru boleh diisi setelah titik itu.

Aturan penjagaannya:

- Menulis Hasil Capaian **tidak menyentuh status sesi**. Sesi tetap `SELESAI`.
- Menulis Hasil Capaian **tidak membuka kembali** bagian A, B, atau C.
- Kelengkapan sesi **tidak pernah** menghitung Hasil Capaian. Lihat bagian 9.
- Hasil Capaian yang kosong **bukan cacat**. Anak bisa pindah sekolah, orang tua bisa tidak membalas, dan periode pertama sistem berjalan jelas belum punya rencana lama.

### 6.2 Satu lembar PPI hidup selama satu tahun

Rencana punya dua tenggat, dan masing-masing ditinjau satu kali di penerimaan rapor yang berbeda.

| Tenggat | Ditinjau di |
|---|---|
| Jangka Pendek (3 Bulan) | penerimaan rapor **berikutnya** |
| Jangka Panjang (1 Tahun) | penerimaan rapor **satu tahun berikutnya** |

Akibatnya satu lembar PPI tidak selesai saat diterbitkan. Dia tetap terbuka selama setahun, menerima dua isian di dua waktu yang berbeda, lalu baru benar-benar tutup.

Dan akibat lanjutannya, **di tiap penerimaan rapor ada dua lembar lama yang sedang ditinjau sekaligus**, selain rencana baru yang sedang ditulis:

```
Penerimaan rapor periode T

  tinjau jangka pendek   ← rencana periode T-1   (3 bulan lalu)
  tinjau jangka panjang  ← rencana periode T-4   (1 tahun lalu)
  tulis rencana baru     ← periode T
```

Tinjauan jangka panjang selalu menyeberang tahun ajaran. Saat itu tiba, anaknya bisa sudah naik kelas dan gurunya berganti. Jadi **tinjauan menunjuk ke rencananya, bukan ke sesi, kelas, atau guru yang menulisnya.**

### 6.3 Tidak ada umur, tidak ada tahap

Kelihatannya sistem perlu tahu sebuah rencana sudah berumur berapa lama dan sedang di tahap mana. **Tidak perlu, dan justru jangan disimpan.**

Umur dan tahap kalau disimpan jadi keadaan yang harus dimajukan oleh sesuatu, entah tugas terjadwal atau pemicu tanggal. Begitu ada satu periode yang tanggalnya digeser, satu murid yang absen, atau satu tahun ajaran yang susunannya berubah, keadaan itu jadi salah dan tidak ada yang tahu. Itu masalah yang lahir sendiri dari keputusan menyimpannya.

Gantinya, **hitung saat dibutuhkan**. Yang dibutuhkan cuma dua pencarian, dijalankan saat guru membuka sesi:

| Tenggat | Rencana mana | Cara mencarinya |
|---|---|---|
| Jangka Pendek | periode tepat sebelumnya | periode dengan `tanggal_mulai` terbesar yang lebih kecil dari periode ini |
| Jangka Panjang | slot yang sama, tahun ajaran sebelumnya | `urutan` sama, `tahun_ajaran` tepat sebelumnya |

Selesai. Tidak ada kolom umur, tidak ada kolom tahap, tidak ada tugas terjadwal, dan tidak ada yang mengawasi tanggal.

**Pencarian jangka panjang mencocokkan slot, bukan menghitung mundur empat periode.** Ini bukan pilihan gaya. Kalau ada satu periode yang tidak berjalan, hitung mundur empat akan mendarat di periode yang salah dan diam-diam meninjau rencana yang keliru. Mencocokkan slot akan mendarat di periode yang benar, atau tidak menemukan apa-apa sama sekali, dan keduanya jawaban yang jujur.

#### Hasil uji

Dijalankan di kalender tiga tahun ajaran, dengan **Tengah Genap 25/26 sengaja dikosongkan** untuk meniru periode yang tidak berjalan, dan sistem dianggap baru berjalan sampai Akhir Ganjil 27/28.

```
Sesi di periode        Rencana yang perlu ditinjau
─────────────────────  ────────────────────────────────────────────
25/26 Tengah Ganjil    — tidak ada —
25/26 Akhir Ganjil     3 bulan ← Tengah Ganjil
25/26 Tengah Genap     3 bulan ← Akhir Ganjil
25/26 Akhir Genap      — tidak ada —
26/27 Tengah Ganjil    3 bulan ← Akhir Genap, 1 tahun ← Tengah Ganjil
26/27 Akhir Ganjil     3 bulan ← Tengah Ganjil, 1 tahun ← Akhir Ganjil
26/27 Tengah Genap     3 bulan ← Akhir Ganjil
26/27 Akhir Genap      3 bulan ← Tengah Genap, 1 tahun ← Akhir Genap
27/28 Tengah Ganjil    3 bulan ← Akhir Genap, 1 tahun ← Tengah Ganjil
27/28 Akhir Ganjil     3 bulan ← Tengah Ganjil, 1 tahun ← Akhir Ganjil
```

Empat baris yang membuktikan aturannya bekerja:

| Baris | Yang diuji |
|---|---|
| `25/26 Tengah Ganjil` kosong | sistem baru menyala, belum ada rencana lama. Bukan galat |
| `25/26 Akhir Genap` kosong | periode sebelumnya tidak berjalan, jadi tidak ada yang ditinjau |
| `26/27 Tengah Genap` tanpa jangka panjang | slot yang sama tahun lalu adalah periode yang tidak berjalan itu, jadi tidak ditemukan |
| `26/27 Tengah Ganjil` | tinjauan jangka pendek menyeberang tahun ajaran, dan tetap ketemu |

Dua keadaan lain yang ikut terjawab tanpa penanganan khusus, yaitu murid yang baru ditetapkan ABK sehingga belum punya rencana lama, dan murid yang absen satu periode. Keduanya menghasilkan daftar kosong, dan daftar kosong adalah jawaban yang benar.

#### Satu syarat, dan itu saja

`periode` harus tabel betulan dengan `urutan`, `semester`, `jenis`, dan `tanggal_mulai`, dan `tahun_ajaran` harus punya `urutan` sendiri supaya tahun sebelumnya bisa dicari tanpa mengurai teks `2025/2026`. Keduanya sudah didefinisikan di `SPEK_ALUR_PENGISIAN.md` bagian 7.2. PPI cuma ikut memakainya.

#### Akibatnya di pemicu

Ini yang paling menentukan apakah bagian ini murah atau mahal.

Karena daftarnya dihitung saat sesi dibuka, **tidak ada yang perlu berjalan di latar belakang**. Tidak ada tugas harian yang memeriksa rencana jatuh tempo, tidak ada antrean, dan tidak ada pengingat yang bisa terlambat atau terkirim dua kali.

Guru membuka sesi periode berjalan, dan daftar tinjauan sudah ada di situ. Dari situ juga dia menerbitkan tautan ke orang tua. Momennya sama dengan momen yang sudah dia lakukan, yaitu mengisi rapor.

Kalau satu periode terlewat tanpa ada yang meninjau, tinjauan itu memang hilang dan tidak dikejar. Sesuai dengan yang sudah diputuskan di bagian 6.1, yaitu Hasil Capaian yang kosong bukan cacat.

### 6.4 Dua pengisi, dua jalur

| Kolom | Diisi oleh | Lewat apa |
|---|---|---|
| `Hasil Capaian - Orangtua` | orang tua | tautan yang dikirim ke ponselnya, dibuka tanpa akun |
| `Hasil Capaian - Guru` | guru | belum ditentukan bentuknya |

**Orang tua tidak punya akun dan tidak perlu dibuatkan.** Yang dikirim cuma tautan berisi token, mengarah ke satu formulir kecil berisi kolom miliknya saja.

Syarat tautannya, karena isinya data seorang anak berkebutuhan khusus:

| | |
|---|---|
| Cakupan token | satu murid, satu rencana, satu tenggat. Bukan satu token untuk seluruh rapor |
| Yang terlihat | rencana yang ditinjau, sebagai konteks, dan satu kotak isian miliknya |
| Yang tidak terlihat | dokumen rapor lain, murid lain, dan kolom milik guru |
| Umur token | terbatas, dan sekali pakai setelah dikirim |
| Kalau kedaluwarsa | guru bisa menerbitkan tautan baru |

**Sisi guru belum ditentukan.** Yang jelas cuma kebutuhannya, yaitu guru perlu satu tempat untuk membuka rencana yang sudah waktunya ditinjau, karena rencana itu ditulis tiga bulan sampai setahun sebelumnya dan tidak ada di layar mana pun yang sekarang dirancang. Bentuknya diputuskan saat mengerjakan bagian ini.

### 6.5 PDF-nya tidak lagi sekali jadi

Ini akibat yang paling mudah terlewat.

PDF PPI dikirim saat rapor terbit, dengan Hasil Capaian masih kosong. Tiga bulan kemudian kolom itu terisi, dan **PDF yang sudah ada di ponsel orang tua jadi usang**. Berkasnya tidak bisa ditarik dan tidak bisa diperbarui dari jauh.

Jadi PPI butuh sesuatu yang tidak dibutuhkan dokumen lain, yaitu **cara mengambil versi terbaru**. Pilihannya ada di bagian 6.7.

Untuk dokumen lain ini tidak jadi soal, karena isinya memang final saat terbit.

### 6.6 Bentuk data

```sql
ppi_capaian
  id                BIGINT PK
  rapor_id          FK -> rapor              -- rapor PPI yang memuat rencananya
  periode_id        FK -> periode            -- periode rencana yang ditinjau
  aspek_id          FK -> rubrik_ppi_aspek
  horizon           ENUM('3_BULAN','1_TAHUN')
  pengisi           ENUM('GURU','ORANGTUA')
  isi               TEXT NOT NULL
  diisi_pada        TIMESTAMP
  diisi_oleh_id     FK -> user NULL          -- NULL kalau lewat tautan orang tua
  UNIQUE (rapor_id, periode_id, aspek_id, horizon, pengisi)
```

```sql
ppi_tautan_orangtua
  id                BIGINT PK
  rapor_id          FK -> rapor
  periode_id        FK -> periode            -- periode rencana yang ditinjau
  horizon           ENUM('3_BULAN','1_TAHUN')
  token             CHAR(43) UNIQUE          -- acak, bukan urutan
  berlaku_sampai    TIMESTAMP
  dipakai_pada      TIMESTAMP NULL
  diterbitkan_oleh  FK -> user
```

Empat hal yang menentukan bentuk ini:

**Terpisah dari `rapor_ppi_isian`, dan hanya di sini.** Keduanya punya siklus hidup yang berbeda, satu terkunci sejak step 3 dan satu baru hidup setelah `SELESAI`. Hasil Capaian **tidak pernah** ditulis ke `rapor_ppi_isian`, walaupun kolomnya terdaftar di `rubrik_ppi_kolom` untuk keperluan cetak.

**Satu capaian per aspek, bukan per kolom rencana.** Mengikuti berkas aslinya, di mana Hasil Capaian adalah satu sel di baris aspek. Paling banyak 20 baris per rencana, yaitu 5 aspek dikali 2 tenggat dikali 2 pengisi.

**Dua tenggat, satu sel cetak.** Di cetakan hanya ada dua kolom, yaitu Guru dan Orangtua. Tiap sel memuat kedua tenggat bertumpuk dengan label, `3 bulan:` lalu `1 tahun:`, dan tenggat yang belum diisi tidak dicetak.

**`diisi_oleh_id` boleh kosong.** Isian dari tautan orang tua tidak punya pengguna. Jejaknya cukup lewat token yang dipakai.

### 6.7 Yang perlu diputuskan sebelum bagian ini dikerjakan

| # | Pertanyaan | Kenapa perlu |
|---|---|---|
| 1 | Tautan dikirim lewat apa, dan siapa yang menekan kirimnya? | WhatsApp dan surel butuh penanganan berbeda. Kalau guru yang menekan, dia butuh tombolnya. Kalau otomatis, sistem butuh tahu kapan tenggatnya tiba |
| 2 | Orang tua mengambil PDF terbaru dari mana? | pilihannya antara tautan tetap yang selalu menampilkan versi terkini, atau PDF baru dikirim ulang tiap kali ada tinjauan masuk |
| 3 | Kalau orang tua tidak pernah mengisi, apa yang terjadi? | jawaban paling murah adalah tidak terjadi apa-apa dan kolomnya tetap kosong. Perlu dipastikan sekolah menerima itu |

### 6.8 Untuk pilot

Rapor PPI terbit dengan kolom Hasil Capaian **tercetak dan kosong**, persis seperti berkas contoh. Itu bentuk yang benar, bukan kekurangan.

Seluruh alur di bagian ini dikerjakan setelah pilot. Tinjauan pertama baru jatuh satu periode setelah rapor pertama terbit, jadi waktunya memang belum tiba.

Yang perlu disiapkan sekarang cuma satu, yaitu **jangan mengunci `ppi_capaian` berdasarkan status sesi**. Isian rencana di `rapor_ppi_isian` tetap terkunci seperti dokumen lain. Lihat 6.1. Membetulkan itu belakangan jauh lebih mahal daripada menghindarinya sekarang.

---

## 7. PPI di Dalam Alur Sesi

### 7.1 Alurnya sudah tertampung, tidak ada yang perlu ditambah

Sekolah menerangkan urutannya begini:

```
Guru mengisi Kekuatan, Tantangan, dan rencana di bagian C
        ↓
Penerimaan rapor. Guru mendiskusikan PPI bersama orang tua.
Tantangan, Kekuatan, dan rencana bisa berubah atau bertambah
        ↓
Diskusi selesai. Guru menekan konfirmasi penerimaan rapor selesai
        ↓
Menunggu Disetujui
```

Urutan itu **persis** yang sudah dirancang di `SPEK_ALUR_PENGISIAN.md` bagian 3. Tidak ada status baru, tidak ada tombol baru, dan tidak ada perubahan skema.

| Yang terjadi | Di alur yang sudah ada |
|---|---|
| Guru selesai mengisi seluruh dokumen | tekan konfirmasi selesai, sesi jadi `TELAH_DIISI` (step 2) |
| Diskusi dengan orang tua, isian PPI berubah | **step 2 memang masih bisa diubah** |
| Diskusi selesai | tekan konfirmasi penerimaan rapor selesai, sesi jadi `MENUNGGU_TTD` (step 3) |
| Kepala sekolah menandatangani | `SELESAI` |

Satu hal yang perlu ditegaskan ke pengembang, karena mudah disalahpahami sebagai cacat. Menyunting isian di step 2 **tidak menurunkan status** dan tidak mewajibkan guru menekan konfirmasi selesai untuk kedua kalinya. Aturan itu sudah tertulis di `SPEK_ALUR_PENGISIAN.md` bagian 3.4. Jadi setelah diskusi, guru cukup menekan **satu** tombol, yaitu tombol step 3.

Yang tetap dijaga hanya gerbang kelengkapan. Kalau di tengah diskusi guru mengosongkan satu kotak, tombol step 3 mati sampai kotak itu terisi lagi. Statusnya tidak bergerak ke mana-mana.

### 7.2 PPI adalah alasan step 2 ada

Layak dicatat, karena step 2 gampang terlihat mubazir.

Untuk RTS, Agama, dan Ummi, jarak antara `TELAH_DIISI` dan `MENUNGGU_TTD` cuma ruang tunggu. Isinya sudah final sejak guru menekan konfirmasi, dan penerimaan rapor cuma membacakannya.

Untuk PPI, jarak itu **ruang kerja**. Di situlah orang tua ikut menyusun rencana untuk anaknya, dan di situlah isian dokumen benar-benar berubah.

Pengembang yang melihat empat status dan tergoda menggabungkan step 1 dan 2 jadi satu akan menghapus satu-satunya tempat di mana orang tua punya pengaruh terhadap isi rapor. **Jangan digabung.**

### 7.3 Orang tua tidak ikut di dalam sesi

Diskusinya berlangsung tatap muka saat penerimaan rapor. Yang mengetik tetap guru, di perangkatnya sendiri, sambil berbicara dengan orang tua.

Tidak ada akun orang tua, tidak ada layar khusus untuk sesi diskusi, dan tidak ada penanda bahwa suatu suntingan berasal dari diskusi. Jejaknya sudah cukup lewat `rapor_isian_log`, yang mencatat setiap perubahan beserta status sesinya. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.5.

Orang tua memang punya satu jalur masuk ke sistem, tapi itu **di luar sesi dan jauh sesudahnya**, yaitu tautan untuk mengisi Hasil Capaian. Lihat bagian 6.4.

---

## 8. Skema Basis Data

### 8.1 Tabel rubrik

```sql
rubrik_ppi_aspek
  id          BIGINT PK
  rubrik_id   FK -> rubrik
  kode        VARCHAR(10)      -- NAM, FM, KOG, BHS, SOSEM
  urutan      SMALLINT         -- 1..5, sekaligus nomor di cetakan
  nama        VARCHAR(100)
  aktif       BOOLEAN DEFAULT TRUE
  UNIQUE (rubrik_id, kode)

rubrik_ppi_kolom
  id              BIGINT PK
  rubrik_id       FK -> rubrik
  bagian          CHAR(1)          -- 'B' atau 'C'
  kode            VARCHAR(40)      -- kekuatan, tujuan_jangka_panjang, ...
  urutan          SMALLINT
  label_cetak     VARCHAR(60)
  grup_cetak      VARCHAR(40) NULL -- 'Tujuan', 'Hasil Capaian', atau NULL
  wajib           BOOLEAN
  diisi_di_sesi   BOOLEAN          -- FALSE untuk kedua kolom Hasil Capaian
  lebar_cetak_cm  DECIMAL(3,1)
  UNIQUE (rubrik_id, bagian, kode)
```

`grup_cetak` yang membentuk kepala tabel dua tingkat di cetakan. Kolom dengan `grup_cetak` yang sama dan berurutan digabung di bawah satu sel induk. Kolom bernilai `NULL` berdiri sendiri setinggi dua baris. Dengan begitu bentuk kepala tabel jadi data, bukan gambar yang ditanam di kode generator.

### 8.2 Tabel isian

```sql
rapor_ppi_isian
  id          BIGINT PK
  rapor_id    FK -> rapor
  periode_id  FK -> periode
  aspek_id    FK -> rubrik_ppi_aspek
  kolom_id    FK -> rubrik_ppi_kolom      -- hanya kolom dengan diisi_di_sesi = TRUE
  isi         TEXT NOT NULL
  UNIQUE (rapor_id, periode_id, aspek_id, kolom_id)
```

Paling banyak **30 baris per periode**, yaitu 5 aspek dikali 6 kolom yang diisi di sesi. Tolak penulisan untuk kolom dengan `diisi_di_sesi = FALSE`. Hasil Capaian hanya ada di `ppi_capaian`, lihat bagian 6.6.

**Isian yang kosong tidak disimpan.** Kalau guru mengosongkan sebuah kotak, atau isinya hanya spasi, hapus barisnya. Jadi **tidak ada baris berarti belum diisi**, sama seperti dokumen lain. Definisi kosongnya di `SPEK_ALUR_PENGISIAN.md` bagian 8.

---

## 9. Kelengkapan

Aturannya sederhana, dan sudah ditetapkan sekolah.

| Kolom | Wajib? |
|---|---|
| Kekuatan | **ya**, kelima aspek |
| Tantangan | **ya**, kelima aspek |
| Jangka Panjang, Jangka Pendek, Strategi, Media | **ya**, kelima aspek |
| Hasil Capaian Guru dan Orangtua | **tidak pernah**, lihat bagian 6 |

Jadi **30 kotak teks wajib terisi**, yaitu 5 aspek dikali 6 kolom.

Alasan sekolah pantas dicatat, karena menjelaskan kenapa tidak ada perkecualian. Rapor PPI disusun untuk dibahas bersama orang tua, dan **guru harus menyediakan bahan bahasannya lebih dulu**. Aspek yang kosong berarti ada bagian dari anak itu yang tidak dibicarakan sama sekali di pertemuan.

Tidak ada aturan bersyarat di kode. Kolom `wajib` di `rubrik_ppi_kolom` sudah menyimpan seluruhnya, dan perhitungan kelengkapan cukup menghitung baris yang terisi untuk kolom yang ditandai wajib.

Pesan galatnya mengikuti bentuk di `SPEK_ALUR_PENGISIAN.md` bagian 8, yaitu menunjuk sampai ke dalam:

```
Belum bisa dikonfirmasi. Masih ada 3 isian yang kosong.

  Tahap 5  Rapor PPI    3 kosong
           Bagian B  Kognitif          Kekuatan
           Bagian C  Fisik Motorik     Strategi, Media
```

---

## 10. Cetakan

Dokumen fisik ditiadakan sepenuhnya. Yang terbit cuma PDF per murid, dikirim lewat surel dan WhatsApp.

Itu menghapus kekhawatiran soal pencetak memotong tepi kertas, tapi tidak menghapus kebutuhan menentukan lebar kolom, karena generator tetap harus memilih angka. Yang berubah cuma alasannya. **Yang membaca dokumen ini sekarang orang tua di layar ponsel**, bukan orang tua memegang kertas.

### 10.1 Ukuran di berkas sumber

Diukur langsung dari PDF-nya:

| | Lebar tabel | Margin kiri | Margin kanan |
|---|---|---|---|
| Bagian B | 16,00 cm | 3,29 cm | 1,71 cm |
| Bagian C | 20,01 cm | 0,53 cm | 0,46 cm |

Kertasnya A4 tegak, lebar 21 cm. Kedua tabel tidak sejajar, dan di dokumen yang sama itu terlihat seperti dua dokumen berbeda yang tergabung. Samakan.

### 10.2 Lebar yang dipakai generator

Margin 2 cm kiri dan kanan, lebar cetak 17 cm, sama untuk kedua tabel.

**Bagian B**, empat kolom:

| Kolom | Lebar |
|---|---|
| No | 1,0 cm |
| Aspek Perkembangan | 3,0 cm |
| Kekuatan | 7,0 cm |
| Tantangan | 6,0 cm |

**Bagian C**, delapan kolom. Kedua kolom `Hasil Capaian` tetap dicetak dan nanti akan berisi kalimat, bukan tanda centang, jadi lebarnya tidak boleh dibuat sekadar cukup untuk kepala kolomnya:

| Kolom | Lebar |
|---|---|
| No | 0,8 cm |
| Aspek Perkembangan | 2,4 cm |
| Tujuan, Jangka Panjang | 2,4 cm |
| Tujuan, Jangka Pendek | 2,4 cm |
| Strategi | 3,0 cm |
| Media | 1,8 cm |
| Hasil Capaian, Guru | 2,1 cm |
| Hasil Capaian, Orangtua | 2,1 cm |

Angkanya ada di `rubrik_ppi_seed.json` supaya bisa disetel tanpa menyentuh kode.

Delapan kolom di kertas tegak memang sempit, dan barisnya akan jadi tinggi. Itu masih wajar untuk dokumen rencana. Kalau nanti terasa terlalu sesak setelah Hasil Capaian benar-benar terisi, jalan keluarnya mencetak bagian C melintang sambil bagian B tetap tegak, yang memberi lebar 25,7 cm.

### 10.3 Kepala tabel harus berulang tiap halaman

Di berkas sumber, **kepala tabel cuma muncul sekali**.

| Bagian | Halaman | Kepala tabel muncul di |
|---|---|---|
| B | 1 sampai 6 | halaman 1 saja |
| C | 7 sampai 10 | halaman 7 saja |

Di kertas ini menyusahkan. **Di ponsel ini lebih parah**, karena orang tua menggeser halaman satu per satu tanpa pernah bisa melihat kepala tabel dan isinya sekaligus. Yang terbaca di halaman empat cuma dua kolom teks tanpa keterangan mana Kekuatan dan mana Tantangan.

Setel baris kepala sebagai baris berulang. Di `python-docx` ini `tblHeader` pada baris pertama, dan di `reportlab` ini `repeatRows=1`, atau `2` untuk bagian C yang kepalanya dua tingkat.

### 10.4 Dibaca di ponsel

Tiga hal yang layak dijaga karena tidak ada lagi versi kertasnya:

**Ukuran huruf jangan diturunkan untuk mengejar muat.** Godaan mengecilkan huruf supaya kolom cukup akan muncul sendiri. Di kertas huruf kecil masih terbaca, di ponsel tidak. Kalau sesak, tambah tinggi baris, bukan kurangi ukuran huruf.

**Tanda air logo terlalu pekat di berkas sumber**, sampai teks di atasnya agak sulit dibaca. Di layar dengan kecerahan rendah ini makin terasa. Turunkan kepekatannya.

**Jumlah halaman jadi ukuran berkas.** PPI saja 10 halaman di berkas sumber, dan satu murid ABK membawa lima dokumen sekaligus. Kalau seluruhnya digabung jadi satu PDF, ukurannya perlu dijaga supaya masih wajar dikirim lewat WhatsApp. Kompres gambar logo dan tanda air, dan sematkan fontnya sebagai subset.

### 10.5 Kop dan tanda tangan

Kop berulang di tiap halaman, empat baris di kiri dan logo sekolah di kanan:

```
PROGRAM PEMBELAJARAN INDIVIDU
{label periode}                    ← "Akhir Semester Genap"
Tahun Pelajaran {tahun_ajaran}     ← "2025/2026"
{nama_sekolah}
```

Blok tanda tangan ada dua:

```
                                   Makassar, {tanggal terbit}
Mengetahui,
Kepala TK Zivana Montessori        Guru Kelas

   {gambar ttd kepsek}                {gambar ttd guru}

{nama kepala sekolah}              {nama guru kelas}
```

**Tanda tangan dibubuhkan sistem, bukan dibubuhkan tangan.** Gambar tanda tangan guru, koordinator, dan kepala sekolah disimpan lebih dulu atas persetujuan masing-masing, lalu dicap otomatis saat yang bersangkutan menyetujui. Rinciannya di `SPEK_ALUR_PENGISIAN.md` bagian 5.2, karena berlaku untuk seluruh dokumen.

Nama kepala sekolah diambil dari data sekolah, sama seperti Rapor Ummi. Di berkas contoh masih tertulis kepala sekolah periode lalu.

---

## 11. Yang Masih Terbuka

Tidak ada yang menahan pembangunan.

### 11.1 Aspek `Seni` belum ada

Dokumen ini memakai lima aspek. Daftar rujukan yang umum dipakai di PAUD memuat enam, dengan `Seni` sebagai yang keenam. **Sekolah sengaja tidak memakainya.** Belum tentu ditambahkan, dan kalau pun ada perubahan, itu baru dibahas setelah pilot.

Karena itu daftar aspek disimpan sebagai baris tabel, bukan enum di kode. Menambahkan aspek keenam cukup satu baris di `rubrik_ppi_aspek`, tanpa menyentuh layar isian, generator PDF, maupun aturan kelengkapan. Lihat bagian 3.2.

### 11.2 Tiga hal di alur Hasil Capaian

Bukan untuk pilot, dan daftarnya ada di bagian 6.7. Ringkasnya, tautan ke orang tua dikirim lewat apa, orang tua mengambil PDF terbaru dari mana, dan apa yang terjadi kalau orang tua tidak pernah mengisi.

### 11.3 Keabsahan tanda tangan elektronik

Untuk sekarang, gambar tanda tangan disimpan lalu dicap otomatis saat disetujui. Apakah nanti perlu tanda tangan elektronik yang tersertifikasi belum diputuskan, dan itu keputusan di luar teknis. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 5.2.

---

## 12. Temuan di Berkas Sumber

### 12.1 Sekolah tidak perlu memperbaiki berkas apa pun

Di RTS, Agama, dan Ummi, salah ketik itu gawat karena teksnya **disalin ke basis data** lalu muncul di ratusan rapor selamanya. Satu huruf salah harus diperbaiki sebelum seeding.

Di PPI tidak ada teks yang disalin. Yang di-seed cuma lima nama aspek dan label kolom, dan ketiganya bersih. Seluruh isi lainnya diketik ulang guru lewat sistem.

Jadi **tidak ada tambahan untuk dokumen catatan perbaikan yang dipegang guru**, dan tidak ada berkas yang perlu dikirim balik ke sekolah. Salah ketik yang ada di berkas contoh adalah salah ketik guru di dokumen satu murid, dan akan hilang sendiri begitu diketik ulang di sistem.

### 12.2 Ada baris tabel yang pecah di bagian C

Temuan yang paling nyata. Di antara baris 3 `Kognitif` dan baris 4 `Bahasa`, ada satu baris tambahan yang kolom No, Aspek, dan Tujuan-nya kosong seluruhnya. Baris itu memotong dua kalimat milik baris 4 tepat di tengah:

| Kolom | Di baris kosong | Sambungannya di baris 4 |
|---|---|---|
| Strategi | `- Mengajak anak keruangan` | `tenang dan perlihatkan gambar anggota keluarga...` |
| Media | `- Role model` | `orang tua dan guru` |

Jadi yang seharusnya berbunyi `Mengajak anak ke ruangan tenang dan perlihatkan gambar anggota keluarga` dan `Role model orang tua dan guru` terbelah jadi dua baris tabel.

Di cetakan hasil sistem, kekeliruan macam ini tidak mungkin terjadi karena barisnya dibuat dari data, satu baris per aspek. Dicatat supaya tidak ada yang mengira baris kosong itu bentuk yang harus ditiru.

---

## 13. Hubungan dengan Spesifikasi Lain

| Berkas | Yang diambil dari sana |
|---|---|
| `SPEK_ALUR_PENGISIAN.md` | seluruh urusan status, sesi, kelengkapan, persetujuan, dan tabel bersama. Bagian 7 dokumen ini hanya menunjukkan bahwa PPI sudah tertampung |
| `SPEK_RUBRIK_UMMI.md` | kunci unik `rapor` dan cara mengambil nama kepala sekolah dari data sekolah |
| `SPEK_RUBRIK_AGAMA.md` | pemisahan antara bentuk isian dan bentuk cetakan |
| `SPEK_RUBRIK_RTS.md` | sifat final status `SELESAI` |

Satu perkecualian dari dokumen ini terhadap aturan kunci bersama, yaitu `ppi_capaian` yang ditulis setelah sesi `SELESAI`. Sudah tercatat di `SPEK_ALUR_PENGISIAN.md` bagian 7.6.
