# Spesifikasi Rubrik Penilaian — Rapor Tengah Semester (RTS)
## eRapor Zivana Montessori

Dokumen ini adalah terjemahan lengkap dari dokumen fisik `RAPOR_TENGAH_SEMESTER_RTS_1.pdf` (8 halaman, **revisi 23 September 2026**) ke dalam bentuk yang siap dibangun jadi sistem. Isinya struktur data, aturan bisnis, aturan cetak, dan seluruh 175 indikator penilaian beserta aparatusnya.

Dokumen ini ditujukan untuk developer dan untuk AI coding assistant. Seluruh teks indikator dan aparatus mengikuti dokumen sumber versi revisi. **Seluruh salah ketik sudah diperbaiki sekolah**, dan berkas seed sudah mengikutinya. Daftarnya di bagian 10.1. Jangan mengubah teks sepihak.

> **Alur, status, penguncian, dan tabel bersama tidak ada di dokumen ini.** Semuanya di `SPEK_ALUR_PENGISIAN.md`, termasuk tabel `periode`, `rapor`, dan `rapor_sesi`. Dokumen ini hanya memuat isi rubrik RTS, tabel khusus RTS, aturan cetak, dan endpoint isian RTS. Kalau ada yang berbeda, `SPEK_ALUR_PENGISIAN.md` yang menang.

**Berkas pendamping**: `rubrik_rts_seed.json` berisi seluruh data yang sama dalam bentuk terstruktur. Pakai berkas itu untuk seeding, jangan mengetik ulang dari dokumen ini.

> **RTS hanya untuk tengah semester.** Di akhir semester, tahap 1 digantikan **RAS**, Rapor Akhir Semester atau Rapor Pengembangan, yang isinya berbeda dan belum dispesifikasi. Jadi dalam satu tahun ajaran RTS diisi dua kali, yaitu di Tengah Semester Ganjil dan Tengah Semester Genap. Susunan sesinya ada di `SPEK_ALUR_PENGISIAN.md` bagian 6.

---

## 1. Ruang Lingkup

RTS adalah **satu dari beberapa jenis dokumen rapor** di Zivana Montessori. Dokumen ini hanya membahas RTS. Jangan asumsikan struktur di sini berlaku untuk jenis rapor lain. Jenis lainnya punya skema penilaian sendiri, misalnya rubrik Al-Qur'an yang memakai nilai huruf dan bukan simbol, serta **Rapor PPI** untuk murid ABK yang diisi berbarengan dengan RTS pada periode yang sama.

Konsekuensi arsitektur, tabel rubrik harus generik terhadap **jenis dokumen** dan terhadap **jenis skala**. Jangan menanam asumsi "nilai selalu 1 sampai 4" atau "penilaian selalu dua periode" ke dalam skema inti. Satu murid juga harus bisa memegang lebih dari satu dokumen rapor pada tahun ajaran yang sama.

Dokumen rapor lainnya punya spesifikasinya sendiri, yaitu `SPEK_RUBRIK_AGAMA.md`, `SPEK_RUBRIK_UMMI.md`, `SPEK_RUBRIK_BING.md`, dan `SPEK_RUBRIK_PPI.md`.

---

## 2. Anatomi Dokumen

Struktur RTS punya **empat tingkat**, dan tingkat ketiga bersifat opsional.

```
Area                     8 buah    contoh: AREA KETERAMPILAN HIDUP
└── Sub-area (bab)       21 buah   contoh: a. Perawatan Diri
    └── Grup             2 buah    contoh: Huruf Raba          (opsional)
        └── Indikator    175 buah  contoh: Menutup mulut saat batuk dan bersin
```

Tiga hal yang mudah keliru dan harus diperhatikan:

**Tidak semua area punya bab.** Empat area yaitu Matematika, Agama, Sosial Emosional, dan Sikap tidak punya pembagian a/b/c sama sekali. Indikatornya langsung menempel di area. Skema harus mengizinkan ini. Cara paling bersih adalah tetap membuat satu sub-area implisit di belakang layar dengan penanda `implisit = true`, supaya kode aplikasi tidak perlu bercabang, tapi saat dicetak baris judul sub-area itu tidak ditampilkan. Ini sudah disiapkan di berkas seed.

**Ada tingkat grup di dua tempat.** Di `b. Persiapan Membaca` ada baris **Huruf Raba** yang membawahi lima indikator bernomor. Di `b. Zoologi` ada baris **Klasifikasi binatang** yang membawahi lima indikator. Baris grup ini **tidak punya kolom penilaian**, dia hanya judul. Kalau tingkat ini dipaksa masuk ke struktur dua tingkat, cetakannya akan melenceng dari dokumen asli.

**Teks tujuan tidak unik.** Di area Bahasa, teks `Dua suku kata`, `Tiga suku kata`, `Satu suku kata`, `Kata dengan 'ng'`, `Kata dengan 'ny'`, dan `Kata diftong` muncul **dua kali**, yaitu di bab `d. Membangun Kata` dan di bab `e. Membaca Kata`, dengan aparatus yang berbeda. Jadi jangan pernah memakai teks tujuan sebagai kunci. Pakai kode indikator yang sudah diberi awalan area dan bab, seperti yang ada di kolom Kode Indikator pada tabel bagian 4.

### Sebaran indikator

| Area | Jumlah bab | Jumlah indikator |
|---|---|---|
| 1. Area Keterampilan Hidup | 6 | 47 |
| 2. Area Sensorial | 5 | 15 |
| 3. Area Matematika | tanpa bab | 14 |
| 4. Area Bahasa | 5 | 36 |
| 5. Area Budaya | 5 | 31 |
| 6. Area Agama | tanpa bab | 14 |
| 7. Sosial Emosional | tanpa bab | 10 |
| 8. Sikap | tanpa bab | 8 |
| **Total** | **21** | **175** |

---

## 3. Skala Penilaian

Empat tingkat, bersifat **ordinal** dan memakai **simbol**, bukan angka dan bukan teks. Simbolnya progresif, tiap tingkat menambah goresan, dan tingkat tertinggi mengisi penuh bentuknya.

| Nilai | Kode | Label | Simbol | Unicode | Bentuk |
|---|---|---|---|---|---|
| 1 | `BD` | Baru dikenalkan | `/` | U+002F | satu garis miring |
| 2 | `MB` | Mulai Berkembang | `∠` | U+2220 | dua garis membentuk sudut |
| 3 | `BSH` | Berkembang Sesuai Harapan | `△` | U+25B3 | segitiga garis luar |
| 4 | `BSB` | Berkembang Sangat Baik | `▲` | U+25B2 | segitiga terisi penuh |

**Bentuk isiannya dropdown.** Ini keputusan yang berlaku untuk **seluruh dokumen rapor**, bukan hanya RTS, supaya guru mengisi lewat satu bentuk komponen yang sama dari awal sampai akhir.

Jadi guru tidak mengklik simbol, melainkan memilih dari daftar berisi empat pilihan. Simbolnya tetap ditampilkan di dalam daftar itu, berdampingan dengan labelnya, misalnya `△ Berkembang Sesuai Harapan`, supaya guru mengenali keduanya sekaligus.

Cetakannya tidak berubah sama sekali, tetap satu simbol per sel.

**Kosong bukan nilai nol.** Sel yang belum dinilai harus disimpan sebagai `NULL`, dan dicetak sebagai sel kosong. Jangan pernah memetakannya ke nilai 0 atau ke string kosong, karena nanti tidak bisa dibedakan antara "belum dinilai" dan "sudah dinilai tapi rendah". Ini penting untuk validasi kelengkapan sebelum rapor dikunci.

**Simbol digambar sebagai SVG, bukan sebagai teks.** Ini sudah diputuskan. Kolom `simbol` dan `simbol_unicode` di berkas seed hanya untuk rujukan dan pencarian, **jangan** dipakai merender PDF.

Alasannya dua. Pertama, bentuk `/` dan `∠` di dokumen asli adalah goresan dengan proporsi khas, bukan karakter teks biasa, jadi hasilnya lebih setia kalau digambar. Kedua, generator PDF jadi tidak bergantung pada font yang kebetulan terpasang di server, dan itu menghilangkan satu sumber kegagalan yang baru ketahuan saat produksi.

Buat empat berkas SVG dengan ukuran kanvas yang sama, lalu susun agar keempatnya tampak setara secara optis. Segitiga terisi terlihat lebih berat daripada segitiga garis luar pada ukuran yang sama persis, jadi sesuaikan dengan mata, bukan dengan angka. Pakai berkas yang sama untuk tampilan di layar supaya tidak ada selisih antara yang dilihat guru dan yang tercetak.

### Satu dokumen, dua periode

Ini titik yang paling sering salah dibaca. RTS **bukan** dua dokumen terpisah.

Satu murid punya **satu dokumen RTS per tahun ajaran**, dan di dalam dokumen itu setiap indikator punya **dua kolom penilaian**, yaitu `TS Ganjil` dan `TS Genap`. Guru mengisi kolom Ganjil sekitar September, lalu kolom Genap sekitar Maret di dokumen yang sama. Halaman terakhir dokumen sumber membuktikan ini, di situ ada **dua blok tanda tangan** yang terpisah, satu untuk Ganjil dan satu untuk Genap, masing-masing dengan tanggalnya sendiri.

**Alasannya bukan efisiensi kertas, tapi supaya perkembangan anak terlihat.** Menaruh kedua periode berdampingan membuat orang tua dan guru bisa langsung membandingkan titik berangkat dengan titik sekarang pada baris yang sama. Ini menentukan perilaku sistem, bukan sekadar tata letak cetakan. Konsekuensinya ada di aturan pengisian, lihat bagian 6.

Artinya:

- Jumlah sel penilaian per murid per tahun ajaran adalah **175 × 2 = 350**.
- Saat rapor Ganjil dicetak, kolom Genap **tetap ikut tercetak** dalam keadaan kosong, dan blok tanda tangan Genap juga tetap ada dalam keadaan kosong. Jangan disembunyikan.
- Saat guru mengisi TS Genap, nilai TS Ganjil **tetap tampil** di sebelahnya dalam keadaan hanya-baca.
- Jangan membuat dua baris rapor terpisah untuk Ganjil dan Genap. Satu baris rapor dengan `semester = 'TAHUNAN'`, dua periode di dalamnya.
- Kedua kolom diisi di **dua sesi berbeda**, yaitu sesi Tengah Semester Ganjil dan sesi Tengah Semester Genap. Tiap sesi punya status dan tanda tangannya sendiri. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 6.
- Kelengkapan untuk penerbitan dihitung **per periode**, yaitu 175 sel, bukan 350.

---

## 4. Tabel Variabel Penilaian

Seluruh 175 indikator, dikelompokkan per area lalu per bab. Kolom Kode Indikator adalah kunci stabil yang dipakai di basis data dan di berkas seed.

Teks pada kolom Tujuan dan Aparatus disalin persis dari dokumen sumber revisi 23 September 2026.

### 1. AREA KETERAMPILAN HIDUP

`keterampilan_hidup` · 47 indikator


**a. Perawatan Diri** · `keterampilan_hidup__a` · 20 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Menutup mulut saat batuk dan bersin | Role model guru, situasi nyata, cermin | `keterampilan_hidup__a__menutup_mulut_saat_batuk_dan_bersin` |
| 2 | Membersihkan hidung | Tisu, tempat sampah, role model guru | `keterampilan_hidup__a__membersihkan_hidung` |
| 3 | Dapat mengelap keringat | Sapu tangan/tisu, role model guru | `keterampilan_hidup__a__dapat_mengelap_keringat` |
| 4 | Menggunakan keran air dengan benar | Keran air nyata, wastafel | `keterampilan_hidup__a__menggunakan_keran_air_dengan_benar` |
| 5 | Mencuci tangan dengan benar | Keran air, sabun cair, handuk kecil/tisu | `keterampilan_hidup__a__mencuci_tangan_dengan_benar` |
| 6 | Memakai dan melepas sepatu dan kaus kaki | Sepatu & kaus kaki anak, kursi kecil | `keterampilan_hidup__a__memakai_dan_melepas_sepatu_dan_kaus_kaki` |
| 7 | Makan sendiri | Peralatan makan anak | `keterampilan_hidup__a__makan_sendiri` |
| 8 | Membuka dan memasang Velcro | Bingkai pakaian velcro | `keterampilan_hidup__a__membuka_dan_memasang_velcro` |
| 9 | Membuka dan menutup resleting | Bingkai pakaian resleting | `keterampilan_hidup__a__membuka_dan_menutup_resleting` |
| 10 | Membuka dan memasang kancing besar | Bingkai pakaian kancing besar | `keterampilan_hidup__a__membuka_dan_memasang_kancing_besar` |
| 11 | Membuka dan memasang kancing kecil | Bingkai pakaian kancing kecil | `keterampilan_hidup__a__membuka_dan_memasang_kancing_kecil` |
| 12 | Membuka dan memasang kancing tekan | Bingkai pakaian kancing tekan | `keterampilan_hidup__a__membuka_dan_memasang_kancing_tekan` |
| 13 | Membuka dan memasang sabuk | Bingkai pakaian sabuk | `keterampilan_hidup__a__membuka_dan_memasang_sabuk` |
| 14 | Membuka dan memasang kancing kait | Bingkai pakaian kancing kait | `keterampilan_hidup__a__membuka_dan_memasang_kancing_kait` |
| 15 | Membuka dan memasang tali sepatu | Bingkai pakaian tali sepatu | `keterampilan_hidup__a__membuka_dan_memasang_tali_sepatu` |
| 16 | Membuka dan memakai jaket | Bingkai pakaian jaket | `keterampilan_hidup__a__membuka_dan_memakai_jaket` |
| 17 | Membuka dan memakai pakaian | Pakaian | `keterampilan_hidup__a__membuka_dan_memakai_pakaian` |
| 18 | Melipat baju | Baju | `keterampilan_hidup__a__melipat_baju` |
| 19 | Mengikat pita | Bingkai pakaian pita | `keterampilan_hidup__a__mengikat_pita` |
| 20 | Dapat menggunakan toilet | Toilet/kamar mandi, role model guru | `keterampilan_hidup__a__dapat_menggunakan_toilet` |


**b. Motorik Halus** · `keterampilan_hidup__b` · 8 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Menyendok objek kering | Menyendok dari satu wadah ke wadah yang berukuran sama | `keterampilan_hidup__b__menyendok_objek_kering` |
| 2 | Menuang objek kering | Menuang dari satu wadah ke wadah yang berukuran sama | `keterampilan_hidup__b__menuang_objek_kering` |
| 3 | Membuka dan menutup botol | Botol | `keterampilan_hidup__b__membuka_dan_menutup_botol` |
| 4 | Menuang objek cair | Menuang cair dari satu wadah ke wadah yang berukuran sama | `keterampilan_hidup__b__menuang_objek_cair` |
| 5 | Menggunting kertas | Gunting dan kertas | `keterampilan_hidup__b__menggunting_kertas` |
| 6 | Menjahit | Bingkai jahit | `keterampilan_hidup__b__menjahit` |
| 7 | Meronce | Manik ronce | `keterampilan_hidup__b__meronce` |
| 8 | Membungkus kado | Pembungkus kado | `keterampilan_hidup__b__membungkus_kado` |


**c. Motorik Kasar** · `keterampilan_hidup__c` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Membawa baki dengan seimbang | Baki/tray, benda konkret ringan | `keterampilan_hidup__c__membawa_baki_dengan_seimbang` |
| 2 | Berjalan mengikuti garis | Garis pada lantai | `keterampilan_hidup__c__berjalan_mengikuti_garis` |
| 3 | Mengangkat kursi dengan benar | Kursi anak, role model guru | `keterampilan_hidup__c__mengangkat_kursi_dengan_benar` |
| 4 | Melempar dan menangkap bola | Bola karet berbagai ukuran | `keterampilan_hidup__c__melempar_dan_menangkap_bola` |
| 5 | Melompat ke depan dan belakang | Matras/evamat, garis lantai sebagai batas | `keterampilan_hidup__c__melompat_ke_depan_dan_belakang` |


**d. Kepedulian Terhadap Lingkungan** · `keterampilan_hidup__d` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Merapikan alas kerja | Alas kerja (work mat), rak penyimpanan, role model guru | `keterampilan_hidup__d__merapikan_alas_kerja` |
| 2 | Membuang sampah pada tempatnya | Tempat sampah kecil, tisu/kertas bekas, role model guru | `keterampilan_hidup__d__membuang_sampah_pada_tempatnya` |
| 3 | Merapikan meja dan kursi | Meja & kursi anak, lingkungan kelas nyata, role model guru | `keterampilan_hidup__d__merapikan_meja_dan_kursi` |
| 4 | Menggunakan sapu kecil dan besar | Sapu besar dan kecil | `keterampilan_hidup__d__menggunakan_sapu_kecil_dan_besar` |
| 5 | Mengepel lantai | Kain pel | `keterampilan_hidup__d__mengepel_lantai` |


**e. Tata Krama** · `keterampilan_hidup__e` · 6 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengucap dan menjawab salam | Role model guru, interaksi dengan teman, situasi nyata | `keterampilan_hidup__e__mengucap_dan_menjawab_salam` |
| 2 | Mengucap permisi pada situasi yang tepat | Role model guru, simulasi situasi, lingkungan kelas | `keterampilan_hidup__e__mengucap_permisi_pada_situasi_yang_tepat` |
| 3 | Mengucap terima kasih dan menjawabnya | Role model guru, interaksi sosial nyata | `keterampilan_hidup__e__mengucap_terima_kasih_dan_menjawabnya` |
| 4 | Meminta maaf pada situasi yang tepat | Role model guru, situasi nyata (simulasi/kejadian sehari-hari) | `keterampilan_hidup__e__meminta_maaf_pada_situasi_yang_tepat` |
| 5 | Dapat meminta bantuan jika membutuhkan | Role model guru, kegiatan kelompok, interaksi nyata | `keterampilan_hidup__e__dapat_meminta_bantuan_jika_membutuhkan` |
| 6 | Dapat meminta izin | Role model guru, aturan kelas, situasi nyata | `keterampilan_hidup__e__dapat_meminta_izin` |


**f. Kebiasaan Bekerja** · `keterampilan_hidup__f` · 3 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengembalikan material pada tempatnya | Rak penyimpanan, material/aparatus yang digunakan, role model guru | `keterampilan_hidup__f__mengembalikan_material_pada_tempatnya` |
| 2 | Bekerja secara mandiri | Role model guru, situasi nyata | `keterampilan_hidup__f__bekerja_secara_mandiri` |
| 3 | Bekerja dengan rapi dan teratur | Role model guru, aturan kelas, situasi nyata | `keterampilan_hidup__f__bekerja_dengan_rapi_dan_teratur` |


### 2. AREA SENSORIAL

`sensorial` · 15 indikator


**a. Indra Penglihatan** · `sensorial__a` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal ukuran besar kecil | Pink Tower, Knobbed cylinders, knobless cylinder, broad stair | `sensorial__a__mengenal_ukuran_besar_kecil` |
| 2 | Mengenal ukuran panjang pendek | Long Rods | `sensorial__a__mengenal_ukuran_panjang_pendek` |
| 3 | Mengenal warna primer | Kotak warna 1 | `sensorial__a__mengenal_warna_primer` |
| 4 | Mengenal warna sekunder | Kotak warna 2 | `sensorial__a__mengenal_warna_sekunder` |
| 5 | Mengurutkan gradasi warna | Kotak warna 3 | `sensorial__a__mengurutkan_gradasi_warna` |


**b. Indra Peraba** · `sensorial__b` · 7 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal tekstur kasar halus | Touch board, touch tablet | `sensorial__b__mengenal_tekstur_kasar_halus` |
| 2 | Menyamakan tekstur | Touch fabric, touch tablet | `sensorial__b__menyamakan_tekstur` |
| 3 | Mengenal berat ringan | Baric tablets, Thermic tablet | `sensorial__b__mengenal_berat_ringan` |
| 4 | Mengenal suhu | Thermic tablets, thermic bottles | `sensorial__b__mengenal_suhu` |
| 5 | Mengenal bentuk bangun ruang | Geometry solid, stereognostic bags | `sensorial__b__mengenal_bentuk_bangun_ruang` |
| 6 | Mengenal bentuk bangun datar | Geometry cabinet, constructive triangle, tessellation | `sensorial__b__mengenal_bentuk_bangun_datar` |
| 7 | Mengenal bentuk kubus dan aljabar | Binomial cube, trinomial cube | `sensorial__b__mengenal_bentuk_kubus_dan_aljabar` |


**c. Indra Penciuman** · `sensorial__c` · 1 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal aroma | Smelling bottle | `sensorial__c__mengenal_aroma` |


**d. Indra Perasa** · `sensorial__d` · 1 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal rasa | Tasting solution | `sensorial__d__mengenal_rasa` |


**e. Indra Pendengaran** · `sensorial__e` · 1 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal bunyi | Sound boxes | `sensorial__e__mengenal_bunyi` |


### 3. AREA MATEMATIKA

`matematika` · 14 indikator


Tanpa pembagian bab. Seluruh indikator langsung di bawah area.


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Menghitung objek (1-10) | Spindel box, number rods | `matematika__menghitung_objek_1_10` |
| 2 | Mengenal angka dengan tulisan dan kuantitas (1-10) | Sandpaper numbers, cards & counters, bird's eye view | `matematika__mengenal_angka_dengan_tulisan_dan_kuantitas_1_10` |
| 3 | Mengenal angka dengan tulisan dan kuantitas (11-19) | Seguin board A kombinasi short bead stairs, bird's eye view | `matematika__mengenal_angka_dengan_tulisan_dan_kuantitas_11_19` |
| 4 | Mengenal angka dengan tulisan dan kuantitas (10, 20, 30, …, 90) | Seguin board B kombinasi short bead stairs, bird's eye view | `matematika__mengenal_angka_dengan_tulisan_dan_kuantitas_10_20_30_90` |
| 5 | Mengenal angka dengan tulisan dan kuantitas (100, 200, 300, …, 900) | Manik emas kombinasi number cards, bird's eye view | `matematika__mengenal_angka_dengan_tulisan_dan_kuantitas_100_200_300_900` |
| 6 | Mengenal angka dengan tulisan dan kuantitas (1000, 2000, 3000, …, 9000) | Manik emas kombinasi number cards, bird's eye view | `matematika__mengenal_angka_dengan_tulisan_dan_kuantitas_1000_2000_3000_9000` |
| 7 | Penjumlahan tanpa menyimpan | Addition strip board, Stamp game, small number rods, short bead stairs | `matematika__penjumlahan_tanpa_menyimpan` |
| 8 | Penjumlahan dengan menyimpan | Stamp game | `matematika__penjumlahan_dengan_menyimpan` |
| 9 | Perkalian tanpa menyimpan | Multiplication board, Stamp game, short bead stairs | `matematika__perkalian_tanpa_menyimpan` |
| 10 | Perkalian dengan menyimpan | Stamp game | `matematika__perkalian_dengan_menyimpan` |
| 11 | Pengurangan tanpa menyimpan | Subtraction strip board, Stamp game, small number rods, short bead stairs | `matematika__pengurangan_tanpa_menyimpan` |
| 12 | Pengurangan dengan menyimpan | Stamp game | `matematika__pengurangan_dengan_menyimpan` |
| 13 | Pembagian tanpa menyimpan | Division board, Stamp game | `matematika__pembagian_tanpa_menyimpan` |
| 14 | Pembagian dengan menyimpan | Stamp game | `matematika__pembagian_dengan_menyimpan` |


### 4. AREA BAHASA

`bahasa` · 36 indikator


**a. Komunikasi** · `bahasa__a` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Dapat mengekspresikan keinginan | Role model guru, situasi nyata di kelas, kartu bergambar | `bahasa__a__dapat_mengekspresikan_keinginan` |
| 2 | Berbicara dengan kalimat jelas | Role model guru, interaksi nyata, cermin (untuk melatih artikulasi/opsional) | `bahasa__a__berbicara_dengan_kalimat_jelas` |
| 3 | Dapat menyampaikan pesan | Role model guru, kegiatan kelompok, situasi nyata (misalnya menyampaikan pesan dari guru ke teman) | `bahasa__a__dapat_menyampaikan_pesan` |
| 4 | Dapat bergantian berbicara | Role model guru, circle time, kegiatan kelompok | `bahasa__a__dapat_bergantian_berbicara` |
| 5 | Dapat bercerita | Buku cerita bergambar, kartu bergambar, role model guru | `bahasa__a__dapat_bercerita` |


**b. Persiapan Menulis** · `bahasa__b` · 6 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Menarik garis lurus | Insert design | `bahasa__b__menarik_garis_lurus` |
| 2 | Menarik garis membuat pola | Insert design | `bahasa__b__menarik_garis_membuat_pola` |
| 3 | Menggambar bentuk | Insert design | `bahasa__b__menggambar_bentuk` |
| 4 | Menyalin tulisan orang dewasa | Buku cerita | `bahasa__b__menyalin_tulisan_orang_dewasa` |
| 5 | Menulis nama diri | Buku dan alat tulis | `bahasa__b__menulis_nama_diri` |
| 6 | Ketebalan tulisan | Tracing Cards | `bahasa__b__ketebalan_tulisan` |


**c. Persiapan Membaca** · `bahasa__c` · 7 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
|  | **Huruf Raba** *(baris grup, tidak dinilai)* |  |  |
| 1 | 1. a, i, u, e, o | Sandpaper letter | `bahasa__c__1_a_i_u_e_o` |
| 2 | 2. m, p, t, r, s | Sandpaper letter | `bahasa__c__2_m_p_t_r_s` |
| 3 | 3. b, k, l, h, n | Sandpaper letter | `bahasa__c__3_b_k_l_h_n` |
| 4 | 4. d, f, g, j, w | Sandpaper letter | `bahasa__c__4_d_f_g_j_w` |
| 5 | 5. c, q, v, x, y, z | Sandpaper letter | `bahasa__c__5_c_q_v_x_y_z` |
| 6 | Mengenal bunyi huruf awal | Fonik | `bahasa__c__mengenal_bunyi_huruf_awal` |
| 7 | Mengenal bunyi huruf akhir | Fonik | `bahasa__c__mengenal_bunyi_huruf_akhir` |


**d. Membangun Kata** · `bahasa__d` · 7 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | LMA, kotak objek, dan gambar | LMA, kotak objek, dan gambar | `bahasa__d__lma_kotak_objek_dan_gambar` |
| 2 | Dua suku kata | LMA, kotak objek, dan gambar | `bahasa__d__dua_suku_kata` |
| 3 | Tiga suku kata | LMA, kotak objek, dan gambar | `bahasa__d__tiga_suku_kata` |
| 4 | Satu suku kata | LMA, kotak objek, dan gambar | `bahasa__d__satu_suku_kata` |
| 5 | Kata dengan 'ng' | LMA, kotak objek, dan gambar | `bahasa__d__kata_dengan_ng` |
| 6 | Kata dengan 'ny' | LMA, kotak objek, dan gambar | `bahasa__d__kata_dengan_ny` |
| 7 | Kata diftong | LMA, kotak objek, dan gambar | `bahasa__d__kata_diftong` |


**e. Membaca Kata** · `bahasa__e` · 11 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Dua suku kata | Buku montessori | `bahasa__e__dua_suku_kata` |
| 2 | Tiga suku kata | Buku montessori | `bahasa__e__tiga_suku_kata` |
| 3 | Satu suku kata | Buku montessori | `bahasa__e__satu_suku_kata` |
| 4 | Kata dengan 'ng' | Blue word list | `bahasa__e__kata_dengan_ng` |
| 5 | Kata dengan 'ny' | Blue word list | `bahasa__e__kata_dengan_ny` |
| 6 | Kata diftong | Green word list | `bahasa__e__kata_diftong` |
| 7 | Daftar kata | Buku montessori | `bahasa__e__daftar_kata` |
| 8 | Buku Kata | Buku montessori | `bahasa__e__buku_kata` |
| 9 | Frasa | Attached sentence strips | `bahasa__e__frasa` |
| 10 | Kalimat | Buku montessori | `bahasa__e__kalimat` |
| 11 | Menulis cerita sederhana | Buku dan alat tulis | `bahasa__e__menulis_cerita_sederhana` |


### 5. AREA BUDAYA

`budaya` · 31 indikator


**a. Botani** · `budaya__a` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal nama buah-buahan | Objek buah & gambar besar buah | `budaya__a__mengenal_nama_buah_buahan` |
| 2 | Mengenal nama sayuran | Objek sayur & gambar besar sayur | `budaya__a__mengenal_nama_sayuran` |
| 3 | Mengenal bagian tumbuhan | Puzzle bagian tumbuhan | `budaya__a__mengenal_bagian_tumbuhan` |
| 4 | Mengenal bagian bunga | Puzzle bagian bunga | `budaya__a__mengenal_bagian_bunga` |
| 5 | Mengenal siklus hidup tumbuhan | Siklus tumbuhan | `budaya__a__mengenal_siklus_hidup_tumbuhan` |


**b. Zoologi** · `budaya__b` · 11 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal binatang liar | Objek binatang liar | `budaya__b__mengenal_binatang_liar` |
| 2 | Mengenal binatang ternak | Objek binatang ternak | `budaya__b__mengenal_binatang_ternak` |
| 3 | Mengenal binatang peliharaan | Objek binatang peliharaan | `budaya__b__mengenal_binatang_peliharaan` |
| 4 | Mengenal binatang laut | Objek binatang laut | `budaya__b__mengenal_binatang_laut` |
| 5 | Mengenal siklus hidup katak | Siklus hidup katak | `budaya__b__mengenal_siklus_hidup_katak` |
| 6 | Mengenal siklus hidup kupu-kupu | Siklus hidup kupu-kupu | `budaya__b__mengenal_siklus_hidup_kupu_kupu` |
|  | **Klasifikasi binatang** *(baris grup, tidak dinilai)* |  |  |
| 7 | Mamalia | Objek binatang mamalia | `budaya__b__mamalia` |
| 8 | Reptil | Objek binatang reptil | `budaya__b__reptil` |
| 9 | Unggas | Objek binatang unggas | `budaya__b__unggas` |
| 10 | Amfibi | Objek binatang amfibi | `budaya__b__amfibi` |
| 11 | Serangga | Objek binatang serangga | `budaya__b__serangga` |


**c. Geografi** · `budaya__c` · 6 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal pentingnya matahari | Permainan kartu rantai makanan | `budaya__c__mengenal_pentingnya_matahari` |
| 2 | Mengenal benda langit | Kartu terminologi planet | `budaya__c__mengenal_benda_langit` |
| 3 | Mengenal 7 benua | Continent globe | `budaya__c__mengenal_7_benua` |
| 4 | Mengenal binatang dari benua | Puzzle benua dan objek | `budaya__c__mengenal_binatang_dari_benua` |
| 5 | Mengenal 3 elemen (darat, laut, udara) | Kotak 3 elemen | `budaya__c__mengenal_3_elemen_darat_laut_udara` |
| 6 | Mengenal jenis perairan dan daratan | Jenis perairan & daratan | `budaya__c__mengenal_jenis_perairan_dan_daratan` |


**d. Sejarah** · `budaya__d` · 5 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mengenal nama hari | Kalender Montessori / kalender harian, kartu nama hari, lagu hari | `budaya__d__mengenal_nama_hari` |
| 2 | Mengenal nama bulan | Kalender Montessori, kartu nama bulan, lagu bulan | `budaya__d__mengenal_nama_bulan` |
| 3 | Mengenal waktu (pagi, siang, malam) | Kartu bergambar aktivitas pagi, siang, malam | `budaya__d__mengenal_waktu_pagi_siang_malam` |
| 4 | Mengenal usia | Permainan Birthday Walking | `budaya__d__mengenal_usia` |
| 5 | Mengenal jam | Jam, kartu aktivitas harian, situasi nyata | `budaya__d__mengenal_jam` |


**e. Ilmu Pengetahuan Alam** · `budaya__e` · 4 indikator


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Mencampur warna | Cat air, palet, kertas | `budaya__e__mencampur_warna` |
| 2 | Benda terapung dan tenggelam | Benda terapung dan tenggelam | `budaya__e__benda_terapung_dan_tenggelam` |
| 3 | Gaya Magnet | Magnet | `budaya__e__gaya_magnet` |
| 4 | Benda hidup dan benda mati | Objek dan lingkungan nyata, role model guru | `budaya__e__benda_hidup_dan_benda_mati` |


### 6. AREA AGAMA

`agama` · 14 indikator


Tanpa pembagian bab. Seluruh indikator langsung di bawah area.


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Sikap berdoa | Situasi nyata, role model guru | `agama__sikap_berdoa` |
| 2 | Mengucapkan basmalah dan hamdalah | Role model guru, situasi nyata (sebelum & sesudah aktivitas) | `agama__mengucapkan_basmalah_dan_hamdalah` |
| 3 | Rukun islam | Puzzle rukun islam, kartu terminologi, audio | `agama__rukun_islam` |
| 4 | Rukun iman | Puzzle rukun iman, kartu terminologi, audio | `agama__rukun_iman` |
| 5 | Idul fitri | Role model guru, simulasi/situasi nyata, kartu terminologi | `agama__idul_fitri` |
| 6 | Idul adha dan tata cara berkurban | Role model guru, simulasi/situasi nyata, kartu terminologi | `agama__idul_adha_dan_tata_cara_berkurban` |
| 7 | Mengenalkan tata cara berwudhu | Role model guru, simulasi/situasi nyata, puzzle gerakan berwudhu | `agama__mengenalkan_tata_cara_berwudhu` |
| 8 | Mengenalkan gerakan shalat | Role model guru, simulasi/situasi nyata, puzzle gerakan sholat | `agama__mengenalkan_gerakan_shalat` |
| 9 | Mengenalkan huruf hijaiyah | Hijaiyyah ronce, huruf raba hijaiyyah, role model guru, kegiatan mengaji setiap hari, pohon hijaiyyah | `agama__mengenalkan_huruf_hijaiyah` |
| 10 | Mengenalkan rukun islam yang kelima | Miniatur ka'bah, kegiatan manasik haji | `agama__mengenalkan_rukun_islam_yang_kelima` |
| 11 | Mengenal malaikat dan tugasnya | Kartu terminologi, puzzle malaikat dan tugasnya | `agama__mengenal_malaikat_dan_tugasnya` |
| 12 | Mengenal bulan hijriah | Kartu terminologi, audio | `agama__mengenal_bulan_hijriah` |
| 13 | Mengenal sejarah islam | Kartu terminologi sejarah islam | `agama__mengenal_sejarah_islam` |
| 14 | Mengenal perbuatan baik dan buruk | Matching cards | `agama__mengenal_perbuatan_baik_dan_buruk` |


### 7. SOSIAL EMOSIONAL

`sosial_emosional` · 10 indikator


Tanpa pembagian bab. Seluruh indikator langsung di bawah area.


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Bertutur kata sopan | Role model guru, situasi nyata (percakapan di kelas) | `sosial_emosional__bertutur_kata_sopan` |
| 2 | Dapat berinteraksi dengan teman | Kegiatan kelompok, role model guru, permainan kooperatif | `sosial_emosional__dapat_berinteraksi_dengan_teman` |
| 3 | Dapat berinteraksi dengan orang dewasa | Role model guru, situasi nyata (menyapa tamu/guru lain), simulasi percakapan | `sosial_emosional__dapat_berinteraksi_dengan_orang_dewasa` |
| 4 | Mudah berteman | Role model guru, kegiatan kelompok, permainan kooperatif | `sosial_emosional__mudah_berteman` |
| 5 | Dapat bekerja dalam kelompok | Kegiatan kelompok terstruktur, aparatus untuk kerja berpasangan/kelompok | `sosial_emosional__dapat_bekerja_dalam_kelompok` |
| 6 | Mengungkapkan perasaan dengan tepat | Emotion cards / kartu ekspresi, role model guru, situasi nyata | `sosial_emosional__mengungkapkan_perasaan_dengan_tepat` |
| 7 | Dapat menunggu giliran | Permainan bergiliran, role model guru, circle time | `sosial_emosional__dapat_menunggu_giliran` |
| 8 | Dapat berbagi | Material yang terbatas (1 set alat untuk beberapa anak), role model guru, kegiatan kelompok | `sosial_emosional__dapat_berbagi` |
| 9 | Dapat menyelesaikan masalah | Role model guru, simulasi situasi, diskusi kelompok kecil | `sosial_emosional__dapat_menyelesaikan_masalah` |
| 10 | Dapat memimpin kelompok | Kegiatan kelompok dengan rotasi pemimpin, role model guru, proyek sederhana | `sosial_emosional__dapat_memimpin_kelompok` |


### 8. SIKAP

`sikap` · 8 indikator


Tanpa pembagian bab. Seluruh indikator langsung di bawah area.


| # | Tujuan | Aparatus / Media Pendukung | Kode Indikator |
|---|---|---|---|
| 1 | Bekerja dengan percaya diri | Anak memilih sendiri pekerjaan tanpa dipaksa. | `sikap__bekerja_dengan_percaya_diri` |
| 2 | Memiliki rasa empati | Role model guru, kegiatan berbagi, membaca cerita bergambar tentang empati, atau situasi dalam kelas dengan teman yang beragam | `sikap__memiliki_rasa_empati` |
| 3 | Memiliki rasa ingin tahu | Kegiatan Rabu Eksplorasi, rasa penasaran terhadap aparatus | `sikap__memiliki_rasa_ingin_tahu` |
| 4 | Tertib pada aturan di kelas | Lingkungan terstruktur, aturan kelas yang disepakati. | `sikap__tertib_pada_aturan_di_kelas` |
| 5 | Menghargai pekerjaan teman | Tidak mengganggu pekerjaan teman, menunggu giliran, memberi apresiasi. | `sikap__menghargai_pekerjaan_teman` |
| 6 | Memiliki inisiatif untuk bekerja | Kebebasan memilih dalam batasan setiap material sesuai minat. | `sikap__memiliki_inisiatif_untuk_bekerja` |
| 7 | Merapikan kembali material setelah digunakan | Latihan membawa & mengembalikan aparatus, membersihkan, role model guru | `sikap__merapikan_kembali_material_setelah_digunakan` |
| 8 | Menggunakan material dengan baik | Presentasi dari guru, anak diberi kesempatan mencoba | `sikap__menggunakan_material_dengan_baik` |
---

## 5. Skema Basis Data

Dipisah jadi dua kelompok. Kelompok **definisi rubrik** jarang berubah dan dipakai bersama oleh semua murid. Kelompok **penilaian** tumbuh per murid per periode.

### 5.1 Definisi rubrik

Tabel `rubrik`, `rubrik_periode`, dan `rubrik_penandatangan` didefinisikan di `SPEK_ALUR_PENGISIAN.md` bagian 7.3. Nilai untuk RTS:

| Tabel | Nilai RTS |
|---|---|
| `rubrik` | `kode = 'RTS_MONTESSORI_V1'`, `jenis_dokumen = 'RTS'`, `cakupan = 'TAHUNAN'`, `jenis_periode = 'TENGAH'`, `cetak_gabung_periode = TRUE` |
| `rubrik_periode` | `TS_GANJIL` = (GANJIL, TENGAH), `TS_GENAP` = (GENAP, TENGAH) |
| `rubrik_bagian` | satu baris, `wajib = TRUE`, karena seluruh 175 indikator wajib |
| `rubrik_penandatangan` | Kepala Sekolah, Guru Kelas, Orang Tua Siswa |

Tabel khusus RTS:

```sql
rubrik_area
  id          PK
  rubrik_id   FK -> rubrik
  kode        VARCHAR             -- 'keterampilan_hidup'
  nama        VARCHAR             -- 'AREA KETERAMPILAN HIDUP'
  urutan      INT
  UNIQUE (rubrik_id, kode)

rubrik_sub_area
  id          PK
  area_id     FK -> rubrik_area
  kode        VARCHAR
  huruf       CHAR(1) NULL        -- 'a'..'f', NULL kalau implisit
  nama        VARCHAR NULL        -- NULL kalau implisit
  implisit    BOOLEAN DEFAULT 0   -- true = jangan cetak baris judulnya
  urutan      INT
  UNIQUE (area_id, kode)

rubrik_grup                        -- tingkat opsional, saat ini hanya 2 baris
  id            PK
  sub_area_id   FK -> rubrik_sub_area
  nama          VARCHAR           -- 'Huruf Raba', 'Klasifikasi binatang'
  urutan        INT

rubrik_indikator
  id            PK
  sub_area_id   FK -> rubrik_sub_area
  grup_id       FK -> rubrik_grup NULL
  kode          VARCHAR UNIQUE    -- 'bahasa__e__dua_suku_kata'
  tujuan        TEXT
  aparatus      TEXT NULL
  urutan        INT
  aktif         BOOLEAN DEFAULT 1
  INDEX (sub_area_id, urutan)

skala_nilai
  id          PK
  rubrik_id   FK -> rubrik
  nilai       SMALLINT            -- 1..4
  kode        VARCHAR             -- 'BD','MB','BSH','BSB'
  label       VARCHAR
  simbol      VARCHAR
  urutan      INT
  UNIQUE (rubrik_id, nilai)
```

### 5.2 Penilaian

Tabel `periode`, `periode_perpanjangan`, `rapor`, dan `rapor_sesi` ada di `SPEK_ALUR_PENGISIAN.md` bagian 7. Di sini hanya tabel isian RTS.

```sql
rapor_penilaian
  id            PK
  rapor_id      FK -> rapor
  indikator_id  FK -> rubrik_indikator
  periode_id    FK -> periode       -- periode konkret: Tengah Ganjil atau Tengah Genap
  nilai         SMALLINT NULL       -- NULL = belum dinilai. BUKAN 0.
  diisi_oleh    FK -> user NULL
  diisi_pada    TIMESTAMP NULL
  UNIQUE (rapor_id, indikator_id, periode_id)
  INDEX (rapor_id, periode_id)
```

Jejak perubahan nilai ditulis ke `rapor_isian_log` bersama, lihat bagian 6.5.

**Satu rapor RTS per murid per tahun ajaran**, dengan `semester = 'TAHUNAN'`. Nilai kolom TS Ganjil ditulis dengan `periode_id` Tengah Semester Ganjil, nilai kolom TS Genap dengan `periode_id` Tengah Semester Genap.

**Kenapa kunci unik `rapor` menyertakan `rubrik_id`.** Satu murid memegang beberapa dokumen rapor di tahun ajaran yang sama, yaitu RTS, Agama, Ummi, Bahasa Inggris, dan PPI untuk murid ABK. Jangan pernah menulis `UNIQUE (murid_id, tahun_ajaran_id)` tanpa `rubrik_id`. Kunci lengkapnya di `SPEK_ALUR_PENGISIAN.md` bagian 7.4.

**Kenapa status tidak ada di tabel ini.** Status ada di `rapor_sesi`, satu per murid per periode. Sesi Tengah Ganjil bisa sudah selesai di Oktober sementara sesi Tengah Genap baru dibuka di Maret, walaupun keduanya mengisi rapor RTS yang sama.

**Kenapa tanggal ada di `periode`, bukan di `rapor`.** Penguncian urusan jadwal sekolah, bukan urusan per murid. Satu tanggal akhir berlaku untuk seluruh murid pada periode itu.

---

## 6. Aturan Bisnis

> **Status, peran, penguncian, dan perpanjangan ada di `SPEK_ALUR_PENGISIAN.md`**, bagian 3, 4, 7.6, dan 7.7. Bagian ini dulu memuatnya dalam bentuk per dokumen, dan sudah dihapus supaya tidak ada dua versi. Yang tersisa di sini adalah aturan yang tetap berlaku, sekarang bekerja di tingkat sesi.

Nomor bagian 6.2.1, 6.2.2, 6.4.1, 6.5, dan 6.6 dipertahankan karena dirujuk dari dokumen lain.

### 6.2.1 Status dan Kelengkapan Adalah Dua Hal Berbeda

Ini pembedaan yang menentukan seluruh perilaku alur, dan mudah sekali tertukar.

| | Apa itu | Sifat |
|---|---|---|
| **Status** | Sejauh mana sesi sudah berjalan | Disimpan di `rapor_sesi`, berubah hanya lewat aksi orang |
| **Kelengkapan** | Apakah seluruh isian wajib sesi ini terisi saat ini juga | Dihitung, tidak disimpan |

Kelengkapan **bukan** status, dan tidak boleh mengubah status. Tugasnya cuma satu, yaitu **menjaga gerbang perpindahan status**.

Kalau guru tidak sengaja mengosongkan satu isian saat sesi di step 2, yang terjadi adalah:

1. Status **tetap** `TELAH_DIISI`.
2. Tombol konfirmasi penerimaan tampil, tapi **dalam keadaan tidak aktif**.
3. Di dekat tombol itu ada keterangan isian mana yang masih kosong, beserta tautan lompat ke sana.
4. Begitu isian tersebut diisi lagi, tombol menyala.

Yang **tidak** boleh terjadi adalah status terjun ke `BELUM_DIISI`. Rapor yang tinggal kurang satu isian karena salah hapus sama sekali bukan hal yang sama dengan rapor yang belum pernah disentuh.

Di step 3 dan 4 keadaan ini tidak mungkin terjadi, karena isian sudah terkunci.

**Konsekuensi yang harus ditangani.** Karena status dan kelengkapan dipisah, status `TELAH_DIISI` **tidak menjamin** isiannya lengkap. Jadi berlaku dua larangan:

- Jangan pernah menyimpulkan kelengkapan dari status. Rekap seperti "20 rapor telah diisi" harus menghitung kelengkapan sungguhan.
- Di layar mana pun yang menampilkan status, tampilkan angka kelengkapan di sebelahnya, misalnya `Telah Diisi · 174/175`.

Gerbangnya tetap dijaga di sisi server. Endpoint konfirmasi wajib menghitung ulang kelengkapan dan menolak kalau belum penuh. Tombol yang tidak aktif di layar itu kenyamanan, bukan pengaman.

### 6.2.2 Definisi "Kosong" Harus Generik

Untuk RTS, kosong berarti `nilai IS NULL` pada `rapor_penilaian`. Sederhana, karena seluruh isiannya berupa skala empat tingkat.

Tapi jangan menanam definisi itu ke dalam logika inti. Rapor Agama, Ummi, Bahasa Inggris, dan PPI punya isian **teks bebas**, bukan skala. Untuk jenis isian itu, kosong berarti:

- `NULL`, **atau**
- string kosong, **atau**
- string yang isinya hanya spasi, tab, dan baris baru

Guru yang menghapus isisan teks biasanya meninggalkan satu spasi atau satu baris kosong tanpa sadar. Kalau pemeriksaannya cuma `IS NOT NULL`, isian seperti itu akan lolos sebagai "terisi", dan rapor bisa terbit dengan kolom yang pada praktiknya kosong.

Jadi buat pemeriksaan kelengkapan sebagai satu fungsi yang bergantung pada **jenis isian**, bukan satu kueri `WHERE nilai IS NULL` yang ditulis langsung di dalam controller. Fungsi yang sama dipakai seluruh dokumen.

### 6.4 Penguncian dan Perpanjangan Periode

Dipindahkan ke `SPEK_ALUR_PENGISIAN.md` bagian 7.6 dan 7.7. Ringkasnya, isian RTS bisa ditulis hanya selama sesinya di step 1 atau 2 dan tanggal akhir periodenya belum lewat. Perpanjangan oleh kepala sekolah hanya membuka kembali sesi step 1 dan 2.

### 6.4.1 Status `SELESAI` Bersifat Final

Rapor yang sudah ditandatangani kepala sekolah **tidak bisa diubah lagi, oleh siapa pun, lewat jalur apa pun**. Kalau ternyata ada salah isi, rapor itu tetap seperti apa adanya.

Ini keputusan sekolah dan **disengaja**, bukan keterbatasan yang menunggu dibereskan. Alasannya, evaluasi atas kesalahan pengisian ditaruh pada guru, bukan pada sistem. Kalau sistem menyediakan jalan keluar yang mudah, ketelitian sebelum tanda tangan akan menurun dan prosesnya jadi diremehkan. Ketegasan di titik ini memang yang diinginkan.

**Catatan untuk siapa pun yang mengerjakan bagian ini di kemudian hari.** Menemukan status yang tidak punya jalan keluar itu terasa seperti cacat rancangan, dan dorongan untuk "melengkapinya" dengan tombol batalkan tanda tangan akan muncul dengan sendirinya. Jangan. Itu bukan kelalaian, itu justru intinya.

Yang harus dipastikan ada di kode:

- Tidak ada endpoint untuk mengembalikan status dari `SELESAI` ke mana pun.
- Tidak ada peran yang bisa melakukannya, termasuk admin dan superadmin. Jangan sediakan jalan pintas "khusus admin".
- Perpanjangan periode tidak membuka sesi berstatus `SELESAI`.
- Penulisan nilai pada sesi `SELESAI` ditolak di lapisan penyimpanan, bukan cuma di controller.

Karena tindakannya tidak bisa dibatalkan, layar harus membuatnya terasa begitu. Sebelum kepala sekolah menekan setujui, tampilkan konfirmasi yang menyebut dengan jelas bahwa setelah ditandatangani rapor tidak bisa diubah lagi, lengkap dengan nama murid dan periodenya. Jangan pakai konfirmasi satu baris yang gampang dilewati. Tindakan yang permanen tidak boleh terasa sama seperti menyimpan draf.

### 6.5 Jejak Perubahan

Setiap perubahan nilai dicatat ke `rapor_isian_log`, tabel log bersama di `SPEK_ALUR_PENGISIAN.md` bagian 7.5, berisi nilai lama, nilai baru, siapa, kapan, dan **status sesi saat perubahan terjadi**.

Kolom status itu yang membuat log ini berguna. Perubahan saat `BELUM_DIISI` adalah pekerjaan normal. Perubahan saat `TELAH_DIISI` artinya ada yang diubah setelah guru menyatakan selesai, misalnya saat diskusi dengan orang tua, dan itu perlu bisa ditelusuri. Tanpa kolom itu, kedua hal tersebut terlihat sama di log.

Data berubah tanpa jejak adalah salah satu keluhan yang melatarbelakangi proyek ini. Log ini bukan fitur tambahan.

### 6.6 Aturan Umum Lainnya

**Rubrik dikunci begitu dipakai.** Setelah ada satu saja baris `rapor_penilaian` yang menunjuk ke sebuah rubrik, definisi rubrik itu tidak boleh diubah lagi. Kalau sekolah mau menambah atau mengubah indikator, buat **versi baru** dan terapkan mulai tahun ajaran berikutnya. Kalau aturan ini dilanggar, rapor yang sudah dicetak tahun lalu bisa berubah isinya sendiri, dan itu masalah serius untuk dokumen resmi sekolah.

**Indikator dinonaktifkan, bukan dihapus.** Pakai kolom `aktif`. Penghapusan baris akan memutus rapor lama.

**Kode indikator dibekukan sejak seeding pertama.** Kode diturunkan dari teks **tujuan**, bukan aparatus. Contohnya `matematika__pengurangan_tanpa_menyimpan` berasal dari tujuan `Pengurangan tanpa menyimpan`. Setelah di-seed, kode **tidak boleh ikut berubah** walau teks tujuannya nanti diperbaiki. Yang berubah hanya kolom `tujuan` atau `aparatus`, sedangkan `kode` tetap. Kode yang ikut berubah akan memutus seluruh nilai yang sudah menunjuk padanya.

Sebelum seeding pertama, kode boleh dibangkitkan ulang sesuka hati. Sesudahnya, tidak pernah lagi.

**Pengisian bertahap dan simpan otomatis.** 175 indikator tidak akan diisi dalam satu duduk. Guru akan mengisi berhari-hari. Jadi tidak boleh ada tombol simpan tunggal yang kalau gagal semua isian hilang. Simpan per perubahan, tampilkan status tersimpan, dan pastikan halaman bisa ditutup kapan saja tanpa kehilangan isian.

**Jangan 350 permintaan.** Satu layar pengisian memuat ratusan sel. Sediakan endpoint upsert massal yang menerima kumpulan perubahan sekaligus. Kirim hanya sel yang berubah, jangan seluruh isi formulir.

**Kelengkapan bersifat memblokir, dan dihitung per periode.** Ini sudah diputuskan sekolah. Sesi **tidak bisa dikonfirmasi** kalau masih ada sel RTS kosong pada periode sesi itu. Jadi:

- Di sesi Tengah Ganjil, seluruh **175** sel kolom Ganjil wajib terisi. Sel kolom Genap boleh kosong seluruhnya dan itu normal.
- Di sesi Tengah Genap, seluruh **175** sel kolom Genap wajib terisi.
- Hitungannya per periode, **tidak pernah** 350 sekaligus.

Validasinya dijalankan di sisi server, bukan cuma di sisi klien. Tombol konfirmasi yang tidak aktif di layar bukan pengaman, itu cuma kenyamanan. Endpoint tetap harus menolak.

Saat validasi gagal, jangan cuma bilang "masih ada yang kosong". Tampilkan jumlahnya, lalu rincian per area beserta tautan lompat ke area itu. Dengan 175 indikator di 8 area, guru yang cuma dikasih pesan galat tanpa penunjuk arah akan menghabiskan waktu lama mencari satu sel yang terlewat.

**Periode berjalan sendiri-sendiri.** Sesi Tengah Ganjil yang sudah `SELESAI` tidak boleh ikut menutup kolom TS Genap. Keduanya sesi yang berbeda, dengan status, tanggal, dan persetujuan masing-masing.

**Nilai Ganjil tetap tampil saat mengisi Genap.** Ini disengaja dan jadi inti gunanya dokumen. Menyatukan kedua periode dalam satu dokumen tujuannya memperlihatkan **perkembangan** anak, bukan sekadar menghemat kertas. Maka di layar pengisian TS Genap, kolom TS Ganjil harus tetap terlihat berdampingan dalam keadaan hanya-baca, supaya guru menilai dengan melihat titik berangkat anak tersebut. Jangan disembunyikan, jangan ditaruh di tab terpisah, dan jangan perlu diklik dulu baru muncul.

**Riwayat perubahan.** Lihat bagian 6.5.

---

## 7. Aturan Cetak PDF

Dokumen sumber adalah **A4 tegak, 8 halaman**. Bangun generator PDF dengan asumsi multi halaman sejak awal, jangan satu halaman lalu ditambal.

**Berulang di setiap halaman**

- Judul `LAPORAN PERKEMBANGAN TENGAH SEMESTER` dan baris tahun ajaran
- Blok identitas, yaitu `Nama Siswa`, `Kelas`, `NISN`
- Baris kepala tabel, yaitu `Tujuan` | `Aparatus/Media Pendukung` | `Pencapaian`, dengan `Pencapaian` terbagi dua menjadi `TS Ganjil` dan `TS Genap`
- Logo sekolah di kanan atas

**Hanya di halaman 1**

- Kotak keterangan berisi empat simbol beserta labelnya

**Jenis baris di dalam tabel**

| Jenis baris | Perilaku |
|---|---|
| Judul area | Satu sel melebar penuh, huruf tebal, latar abu |
| Judul sub-area | Satu sel melebar penuh, format `a. Perawatan Diri`. Dilewati kalau `implisit = true` |
| Judul grup | Hanya kolom Tujuan yang terisi, kolom penilaian kosong dan tidak bisa diisi |
| Indikator | Empat kolom terisi normal |

**Pemenggalan halaman.** Biarkan mengalir mengikuti isi, jangan menanam nomor halaman secara kaku. Tapi pasang aturan jangan pernah memisahkan baris judul area atau judul sub-area dari baris pertama di bawahnya. Kalau tidak muat, dorong keduanya ke halaman berikutnya.

**Halaman terakhir.** Berisi dua blok tanda tangan yang tersusun ke bawah:

1. Judul `Tengah Semester Ganjil`, lalu baris tempat dan tanggal rata kanan, lalu tiga kolom tanda tangan yaitu Kepala Sekolah, Guru Kelas `{nama kelas}`, dan Orang Tua Siswa. Dua kolom pertama mencantumkan nama dan NUPTK. Kolom orang tua berisi garis kosong.
2. Hal yang sama untuk `Tengah Semester Genap`.

Tiap blok diisi dari salinan sesi periodenya sendiri, yaitu nama, NUPTK, gambar tanda tangan, tempat, dan tanggal. Caranya di `SPEK_ALUR_PENGISIAN.md` bagian 7.5. Kalau sesi Genap belum terbit, blok tanda tangannya **tetap dicetak** dengan tempat dan tanggal kosong.

---

## 8. Kontrak API Minimal

Endpoint perpindahan status, persetujuan, perpanjangan periode, dan cetak paket ada di `SPEK_ALUR_PENGISIAN.md` bagian 7.9. Di sini hanya endpoint isian RTS.

```
GET  /api/rubrik/{kode}
     Mengembalikan pohon rubrik lengkap, yaitu area, sub-area, grup, indikator,
     skala, dan kolom periode. Layar pengisian memuat ini sekali lalu menyimpannya
     di sisi klien. Jangan panggil per area.

GET  /api/rapor/{rapor_id}/penilaian
     Seluruh nilai yang sudah ada untuk rapor tersebut, kedua kolom sekaligus.
     Bentuk ringkas: { "indikator_kode": { "TS_GANJIL": 3, "TS_GENAP": null } }

PATCH /api/sesi/{sesi_id}/rapor/{rapor_id}/penilaian
     Upsert massal. Badan permintaan berisi hanya sel yang berubah.
     { "perubahan": [ { "indikator": "bahasa__e__frasa", "nilai": 2 } ] }
     periode_id diambil dari sesi, bukan dari badan permintaan.
     Tolak kalau syarat penulisan di SPEK_ALUR_PENGISIAN.md bagian 7.6 tidak terpenuhi.
     Tolak nilai di luar 1..4 dan selain null.

GET  /api/sesi/{sesi_id}/rapor/{rapor_id}/kelengkapan
     Dipanggil layar pengisian untuk menampilkan sisa pekerjaan secara langsung.
     { "total": 175, "terisi": 168, "kosong": 7,
       "per_area": [ { "kode": "bahasa", "nama": "AREA BAHASA", "kosong": 7 } ] }

GET  /api/rapor/{rapor_id}/cetak?sesi={sesi_id}
     PDF RTS saja, 8 halaman. Pratinjau bertanda draf kalau sesinya belum SELESAI.
```

---

## 9. Kriteria Penerimaan

Fitur dianggap selesai kalau seluruh butir ini terbukti, bukan sekadar terlihat jalan.

- [ ] Seeding menghasilkan **tepat 175** baris `rubrik_indikator`, **21** sub-area bernama, **8** area, dan **2** grup.
- [ ] Empat area tanpa bab tercetak tanpa baris judul sub-area kosong.
- [ ] Baris `Huruf Raba` dan `Klasifikasi binatang` tercetak sebagai judul tanpa kolom penilaian.
- [ ] Enam teks tujuan kembar di area Bahasa tersimpan sebagai indikator yang berbeda, dan mengubah nilai salah satunya tidak ikut mengubah pasangannya.
- [ ] Sel yang belum diisi tersimpan `NULL` dan tercetak kosong, bukan nol.
- [ ] Satu murid satu tahun ajaran menghasilkan **satu** baris `rapor` RTS dengan `semester = 'TAHUNAN'`, menampung **350** sel penilaian.
- [ ] Sesi Tengah Ganjil yang sudah `SELESAI` tidak mengunci kolom TS Genap.
- [ ] Konfirmasi isi sesi Tengah Ganjil dengan **1 sel RTS kosong** ditolak, dan pesannya menyebut area mana yang kurang.
- [ ] Konfirmasi isi sesi Tengah Ganjil saat seluruh 175 sel Ganjil terisi **berhasil**, walaupun seluruh 175 sel Genap masih kosong.

Kriteria status, penguncian, perpanjangan, persetujuan, dan penerbitan ada di `SPEK_ALUR_PENGISIAN.md` bagian 12, dan berlaku juga untuk RTS.

**Khusus RTS**

- [ ] Perubahan nilai di step 2 menghasilkan baris log yang mencatat status sesi `TELAH_DIISI`.
- [ ] Saat mengisi TS Genap, nilai TS Ganjil tampil berdampingan dalam keadaan hanya-baca tanpa perlu diklik atau pindah tab.
- [ ] Satu murid bisa punya beberapa baris `rapor` pada tahun ajaran yang sama dengan `rubrik_id` berbeda, tanpa melanggar batasan unik.
- [ ] Mengisi 20 sel lalu menutup halaman secara paksa, dan saat dibuka lagi 20 sel itu masih ada.
- [ ] PDF yang dihasilkan **8 halaman**, kepala tabel dan identitas murid muncul di setiap halaman, keterangan simbol hanya di halaman 1.
- [ ] Keempat simbol tampil benar di PDF pada mesin yang tidak punya font tambahan terpasang.
- [ ] Rapor yang terbit di sesi Tengah Ganjil tetap menampilkan kolom TS Genap dan blok tanda tangan Genap dalam keadaan kosong.
- [ ] Rubrik yang sudah dipakai menolak perubahan definisi.

---

## 10. Temuan dan Keputusan

### 10.1 Salah ketik yang sudah diperbaiki sekolah

Seluruh sepuluh temuan ejaan di dokumen asli **sudah diperbaiki**, dan `rubrik_rts_seed.json` sudah memakai ejaan yang benar. Tabel ini dipertahankan sebagai catatan apa yang berubah.

| Lokasi | Tertulis di dokumen asli | Di seed |
|---|---|---|
| Keterampilan Hidup a | Bingklai pakaian kancing kait | Bingkai pakaian kancing kait |
| Keterampilan Hidup f | Mengemballikan material | Mengembalikan material |
| Sensorial a | knobbles cylinder | knobless cylinder |
| Sensorial b | stereognostig bags | stereognostic bags |
| Matematika | Seguind board B | Seguin board B |
| Matematika, kuantitas 11-19 | Seguid board A | Seguin board A |
| Matematika, pengurangan tanpa menyimpan | Substraction strip board | Subtraction strip board |
| Bahasa b | Trassing Cards | Tracing Cards |
| Budaya d | Permainan Birtthday Walking | Permainan Birthday Walking |
| Agama | Mengucapkan baslamah | Mengucapkan basmalah |

Seluruh perbaikan ada di kolom tujuan atau aparatus yang tidak dipakai menurunkan kode, jadi **tidak ada kode indikator yang berubah**.

### 10.2 Judul area halaman 5 sudah benar

Halaman 5 sebelumnya berjudul `AREA SENSORIAL` untuk bagian yang berisi Botani, Zoologi, dan Geografi. **Sudah dibetulkan menjadi `AREA BUDAYA`**, sesuai konfirmasi sekolah.

Berkas seed sudah memakai nama itu sejak awal, jadi sekarang dokumen fisik dan sistem sudah sama.

### 10.3 Tahun ajaran dan tanggal sudah konsisten

Ketiga penanggalan yang sebelumnya bertabrakan sudah dirapikan.

| | Sebelumnya | Sekarang |
|---|---|---|
| Judul di badan halaman | T.P 2025/2026 | **T.P 2026/2027** |
| Tanda tangan TS Ganjil | 26 September 2026 | **02 Oktober 2026** |
| Tanda tangan TS Genap | 16 Maret 2026 | **Maret 2027** |

Kepala halaman dan judul sekarang sama-sama menyebut 2026/2027, dan TS Genap sudah jatuh di tahun yang benar.

Tanggal TS Genap sengaja hanya berisi bulan dan tahun, karena harinya memang belum ditentukan. Untuk sistem ini tidak jadi soal, karena tanggal pengesahan diambil dari basis data saat rapor ditandatangani.

### 10.4 Keputusan yang sudah diambil

**Kelengkapan memblokir konfirmasi, dihitung per periode.** Sesi tidak bisa dikonfirmasi selama masih ada sel RTS kosong di periode sesi itu. Rinciannya di bagian 6.6.

**Seluruh murid memakai 175 indikator yang sama.** Tidak ada penyaringan indikator per kelompok usia, per kelas, atau per kondisi murid. Jangan membangun tabel penghubung kelas ke indikator, jangan membuat kolom penanda opsional pada indikator, dan jangan membuat mekanisme sembunyikan indikator. Semuanya hanya menambah kerumitan yang tidak dipakai.

Murid ABK **tidak** memakai versi RTS yang dipangkas. Mereka mengisi RTS yang sama persis, ditambah dokumen terpisah bernama **Rapor PPI** di sesi yang sama. Spesifikasinya di `SPEK_RUBRIK_PPI.md`.

**Alur status dan pembagian peran sudah ditetapkan**, dan tinggal di `SPEK_ALUR_PENGISIAN.md`.

**Status dan kelengkapan dipisah.** Status tidak pernah turun karena isian dikosongkan, dan tidak pernah kembali ke step 1. Kelengkapan hanya menjaga gerbang perpindahan. Rinciannya di bagian 6.2.1.

**Orang tua tidak punya akun.** Rapor dibahas di pertemuan penerimaan rapor, lalu terbit sebagai PDF yang dikirim lewat surel dan WhatsApp. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 5.1.

**Status `SELESAI` tidak bisa dibatalkan.** Disengaja, dengan alasan yang ditulis lengkap di bagian 6.4.1. Jangan disediakan jalan keluarnya.

**Perpanjangan periode berlaku untuk seluruh murid pada periode itu.** Diterima apa adanya. Tidak perlu membangun buka kunci per rapor.

**Tidak ada perlakuan khusus untuk murid ABK pada alur status.** Rapor PPI cuma satu tahap tambahan di sesi yang sama. Jangan membuat percabangan berdasarkan status ABK murid di mana pun dalam alur.

**Simbol dirender sebagai SVG.** Lihat bagian 3.

**Tempat pengesahan dibaca dari data sekolah.** Untuk pilot nilainya cukup diisi sekali dengan `Makassar`, tapi **wadahnya harus ada sejak sekarang**, yaitu satu kolom pada modul Data Sekolah. Templat cetak membaca kolom itu. Yang dilarang adalah menulis string `Makassar` langsung di dalam templat, karena kalau begitu nanti mencarinya harus menyisir kode.

### 10.5 Tidak ada lagi yang perlu diputuskan

Seluruh salah ketik sudah diperbaiki sekolah dan sudah diikuti seed. Pertanyaan soal lapisan sesi juga sudah terjawab, dan seluruh alur pengisian serta persetujuan sekarang tinggal di `SPEK_ALUR_PENGISIAN.md`.

## 11. Cara Memakai Berkas Ini

Untuk AI coding assistant, urutan yang disarankan:

1. Baca bagian 2, 3, dan 5 dulu. Di situ letak seluruh keputusan struktur.
2. Bangun tabel bersama dari `SPEK_ALUR_PENGISIAN.md` bagian 7 lebih dulu, lalu tabel khusus RTS dari bagian 5.1 dan 5.2.
3. Seed dari `rubrik_rts_seed.json`, **jangan** mengetik ulang dari tabel di bagian 4. Tabel di bagian 4 untuk dibaca manusia, berkas JSON untuk dieksekusi mesin. Keduanya dihasilkan dari sumber yang sama.
4. Setelah seeding, jalankan pemeriksaan pada bagian 9 butir pertama sebelum lanjut.
5. Bangun layar pengisian, lalu generator PDF. Kerjakan PDF paling akhir tapi rancang skemanya sejak awal dengan asumsi 8 halaman.

Struktur `rubrik_rts_seed.json`:

```
rubrik            metadata, skala, kolom periode, statistik
area[]            8 area
  sub_area[]      berisi huruf, nama, implisit
    indikator[]   berisi kode, tujuan, aparatus, grup, urutan
```

Developer juga akan diberi akses ke dokumen PDF aslinya. Kalau ada yang berbeda antara dokumen ini dan PDF, **PDF yang menang**, dan tolong kabari supaya dokumen ini diperbarui.
