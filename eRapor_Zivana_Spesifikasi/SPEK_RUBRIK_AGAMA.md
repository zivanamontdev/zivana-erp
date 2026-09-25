# Spesifikasi Rubrik Penilaian — Rapor Pendidikan Agama Islam
## eRapor Zivana Montessori

Dokumen ketiga dari enam. Terjemahan lengkap dari `RAPOR_AGAMA__RANTING_20262027-2.docx` ke bentuk yang siap dibangun jadi sistem.

Seluruh teks butir disalin dari berkas sumber, **dengan perbaikan yang sudah dilakukan sekolah**. Daftar perbaikannya di bagian 9.1.

**Berkas pendamping**: `rubrik_agama_seed.json`. Pakai berkas itu untuk seeding, jangan mengetik ulang dari tabel di dokumen ini.

> **Siap dibangun.** Seluruh pertanyaan yang menghambat sudah dijawab sekolah.

> **Bentuk isian seluruh rapor adalah dropdown.** Berlaku untuk seluruh dokumen rapor, bukan hanya yang ini. Baca bagian 3.2.

> **Tiap butir terikat ke satu semester.** Hal ini tidak ada di RTS maupun Ummi, dan memisahkan cara mengisi dari cara mencetak. Baca bagian 3.3.

> **Tabel di berkas sumber tidak muat di halamannya sendiri**, lebih lebar 2,88 cm. Perlu dibereskan sebelum generator PDF dibuat. Baca bagian 7.3.

---

## 1. Urutan Enam Dokumen

| No | Dokumen | Status |
|---|---|---|
| 1 | Data Murid | di luar cakupan rubrik |
| 2 | RTS Montessori | sudah, `SPEK_RUBRIK_RTS.md` |
| 3 | **Rapor Agama** | **dokumen ini** |
| 4 | Rapor Ummi | sudah, `SPEK_RUBRIK_UMMI.md` |
| 5 | Rapor Bahasa Inggris | sudah, `SPEK_RUBRIK_BING.md` |
| 6 | Rapor PPI | sudah, `SPEK_RUBRIK_PPI.md`, khusus murid ABK |

Kunci unik tabel `rapor` tetap `(murid_id, tahun_ajaran_id, rubrik_id, semester)` seperti yang ditetapkan di `SPEK_ALUR_PENGISIAN.md` bagian 7.4.

---

## 2. Bacaan Wajib Sebelum Menyalin dari RTS atau Ummi

Tiga hal di dokumen ini belum pernah muncul di dua dokumen sebelumnya.

| | RTS | Ummi | **Agama** |
|---|---|---|---|
| Bentuk isian | dropdown | dropdown | dropdown |
| Isi dropdown | 4 simbol | 12 nilai huruf | **7 pilihan** |
| Kolom nilai per periode **di cetakan** | 1 | 1 | **7** |
| Butir terikat semester? | tidak | tidak | **ya, tiap butir milik Ganjil atau Genap** |
| Catatan guru | tidak ada | satu textarea bebas | **enam textarea, dirangkai sistem** |
| Penandatangan | Kepsek, Guru Kelas, Orang Tua | Kepsek, Koordinator Al-Quran | Kepsek, Guru Kelas, Orang Tua |

Baris ketiga dan keempat yang paling menentukan. Di dokumen ini, **cara mengisi dan cara mencetak sengaja berbeda bentuk**, dan itu disengaja. Dibahas di 3.2 dan 3.3.

---

## 3. Skala Penilaian

### 3.1 Lima tahapan, satu di antaranya bertingkat

Skalanya bukan sekadar bagus atau kurang, melainkan **tahapan proses belajar** anak terhadap satu materi. Definisinya diambil apa adanya dari keterangan di berkas sumber.

| # | Tahapan | Artinya |
|---|---|---|
| 1 | **Teladan** | Anak hanya melihat atau mendengar guru dan orang tua melakukannya, dan belum tergerak untuk ikut melakukan atau mengucapkan |
| 2 | **Talqin** | Anak ikut melafalkan kembali apa yang guru dan orang tua ucapkan, bacakan, dan lakukan |
| 3 | **Tahfizh** | Anak sudah menghafalkannya. Punya tiga sub-tingkat, lihat di bawah |
| 4 | **Tafhim** | Anak sudah mampu memahami, mengartikan, dan menjelaskannya dengan baik |
| 5 | **Ta'dib** | Anak sudah mampu menjunjung tinggi dan mengamalkan nilai-nilai iman dan adab, yaitu tahap pembiasaan dan penyempurnaan |

**Tahfizh** dipecah tiga:

| Kode | Nama | Arti | Kapan dipakai |
|---|---|---|---|
| `D` | Dho'if | Hafalan Lemah | hafalan masih perlu dituntun dan dibantu untuk menyambung ke ayat selanjutnya |
| `J` | Jayyid | Hafalan Baik | hanya terdapat 1 sampai 3 kesalahan |
| `M` | Mumtaaz | Hafalan Istimewa | hafalan fasih, lancar, dan tidak ada kesalahan |

Di cetakan, ini jadi **tujuh kolom** per periode:

```
│ TELADAN │ TALQIN │  TAHFIZH   │ TAFHIM │ TA'DIB │
│         │        │  D │ J │ M │        │        │
```

**Simpan tahapan dan sub-tingkat sebagai dua kolom terpisah**, bukan sebagai tujuh nilai datar seperti `TAHFIZH_D`. Sub-tingkat hanya berlaku untuk Tahfizh, dan memisahkannya membuat aturan itu bisa ditegakkan oleh skema, bukan hanya oleh kode.

### 3.2 Guru memilih satu, lewat dropdown

**Satu butir, satu periode, satu nilai.** Tidak ada centang ganda dan tidak ada penumpukan tahapan.

Bentuk isiannya **dropdown**, dan ini keputusan yang berlaku untuk **seluruh dokumen rapor**, bukan hanya yang ini. Guru mengisi rapor lewat satu bentuk komponen yang sama dari awal sampai akhir, supaya pengalamannya menyatu.

| Dokumen | Isi dropdown-nya |
|---|---|
| RTS Montessori | empat simbol segitiga |
| Rapor Ummi | dua belas nilai huruf, `A+` sampai `D-` |
| **Rapor Agama** | **tujuh pilihan di bawah** |

Jangan memakai radio button, dan jangan memakai checkbox. Tabel tujuh kolom di berkas Word itu **bentuk cetakan, bukan bentuk isian**.

#### Tujuh pilihan dropdown

Tahfizh dipecah menjadi tiga pilihan yang berdiri sendiri, supaya guru tidak perlu memilih dua kali.

| # | Yang dilihat guru | Disimpan sebagai | Kolom di cetakan |
|---|---|---|---|
| 1 | Teladan | `TELADAN` | TELADAN |
| 2 | Talqin | `TALQIN` | TALQIN |
| 3 | **Tahfizh Dho'if** | `TAHFIZH` + `D` | TAHFIZH, sub-kolom D |
| 4 | **Tahfizh Jayyid** | `TAHFIZH` + `J` | TAHFIZH, sub-kolom J |
| 5 | **Tahfizh Mumtaaz** | `TAHFIZH` + `M` | TAHFIZH, sub-kolom M |
| 6 | Tafhim | `TAFHIM` | TAFHIM |
| 7 | Ta'dib | `TADIB` | TA'DIB |

**Cetakannya tidak berubah.** Tetap tujuh kolom seperti berkas aslinya, dan yang dipilih ditandai di kolomnya masing-masing. Yang berbeda hanya cara guru memasukkannya.

Kolom `kolom_cetak` di berkas seed memetakan tiap pilihan ke kolom cetakannya, jadi pemetaan ini data, bukan percabangan `if` di kode generator PDF.

#### Akibatnya di data

| | |
|---|---|
| Nilai per murid per tahun | **146**, yaitu 73 butir × 2 periode |
| Baris per butir per periode | tepat satu, atau tidak ada sama sekali kalau belum dinilai |

Bandingkan dengan kalau tadinya dipilih model centang ganda, yang bisa sampai 1.022 nilai. Selisihnya tujuh kali lipat, dan keputusan ini membuat datanya jauh lebih ringan sekaligus menghilangkan keraguan arti kolom kosong. **Kosong berarti belum dinilai**, titik.

### 3.3 Tiap butir terikat ke satu semester

Ini yang belum pernah ada di RTS maupun Ummi.

Butir di dokumen ini **tidak semuanya dinilai sepanjang tahun**. Tiap butir ditandai milik Semester Ganjil atau Semester Genap, mengikuti kapan materinya diajarkan.

```
I. AQIDAH TAUHID
   ── CAPAIAN SEMESTER GANJIL
      1. Rukun Iman
      2. Rukun Islam
      3. Syahadat
      4. Mengenal Al-Qur'an
   ── CAPAIAN SEMESTER GENAP
      5. Nabi dan Rasul
      6. Hari Kiamat
```

Sebarannya hampir berimbang, yaitu **37 butir Ganjil** dan **36 butir Genap**, dari total 73.

Artinya `semester` bukan sekadar penanda dokumen, tapi **atribut milik butir itu sendiri**. Kolomnya ada di `rubrik_agama_item`, bukan cuma di `rapor`.

#### Mengisi disaring, mencetak tidak

Ini pemisahan yang penting dan sudah disetujui sekolah.

| | Yang terjadi |
|---|---|
| **Layar pengisian** | hanya menampilkan butir milik semester yang berjalan, yaitu 37 butir saat Ganjil dan 36 butir saat Genap |
| **Cetakan** | menampilkan **seluruh 73 butir**, karena di dokumen fisik memang digabung |

Alasan menyaring di layar sederhana, materinya memang belum diajarkan, jadi menampilkannya hanya akan membuat guru ragu apakah ada yang terlewat.

Alasan tidak menyaring di cetakan juga sederhana, dokumennya satu dan utuh, dan orang tua melihat perjalanan anak sepanjang tahun dalam satu lembar.

**Artinya hasil semester sebelumnya ikut terbawa ke cetakan berikutnya.** Saat rapor Semester Genap dicetak, butir Ganjil tetap muncul lengkap dengan nilainya. Tidak dikosongkan dan tidak disembunyikan.

Aturan turunannya untuk generator PDF, jangan pernah menyaring baris berdasarkan semester yang sedang berjalan. Ambil seluruh 73 butir, lalu isikan nilai yang ada.

### 3.4 Dua titik pengisian, sama seperti Ummi

Setiap butir punya dua kolom penilaian, yaitu `Tengah Semester` dan `Akhir Semester`, keduanya di dalam semester yang sama. Bentuknya sama persis dengan Rapor Ummi dan implementasinya boleh disalin.

Isian Tengah Semester tetap tersimpan dan tetap terlihat saat mengisi Akhir Semester.

---

## 4. Tabel Variabel Penilaian

Seluruh 73 baris nilai, dikelompokkan per ruang lingkup. Kolom Semester menunjukkan kapan butir itu dinilai dan ditampilkan di layar pengisian.

Teks disalin persis dari berkas sumber.

### I. AQIDAH TAUHID

`i_aqidah_tauhid` · 6 baris nilai

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Rukun Iman | `i_aqidah_tauhid__rukun_iman` |
| Ganjil | 2 | Rukun Islam | `i_aqidah_tauhid__rukun_islam` |
| Ganjil | 3 | Syahadat | `i_aqidah_tauhid__syahadat` |
| Ganjil | 4 | Mengenal Al-Qur’an | `i_aqidah_tauhid__mengenal_al_quran` |
| Genap | 5 | Nabi dan Rasul | `i_aqidah_tauhid__nabi_dan_rasul` |
| Genap | 6 | Hari Kiamat | `i_aqidah_tauhid__hari_kiamat` |


### II. FIQIH/IBADAH

`ii_fiqih_ibadah` · 9 baris nilai

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Bersuci | `ii_fiqih_ibadah__bersuci` |
| Ganjil | 2 | Wudhu | `ii_fiqih_ibadah__wudhu` |
| Ganjil | 3 | Adzan dan Iqamah | `ii_fiqih_ibadah__adzan_dan_iqamah` |
| Ganjil | 4 | Gerakan Shalat dan Bacaan Shalat | `ii_fiqih_ibadah__gerakan_shalat_dan_bacaan_shalat` |
| Genap | 5 | Puasa Ramadhan | `ii_fiqih_ibadah__puasa_ramadhan` |
| Genap | 6 | Zakat/Infaq/Shodaqoh/Hadiah | `ii_fiqih_ibadah__zakat_infaq_shodaqoh_hadiah` |
| Genap | 7 | Haji | `ii_fiqih_ibadah__haji` |
| Genap | 8 | Hari Raya Islam | `ii_fiqih_ibadah__hari_raya_islam` |
| Genap | 9 | Kalimat Toyyibah | `ii_fiqih_ibadah__kalimat_toyyibah` |


### III. AKHLAQ

`iii_akhlaq` · 10 baris nilai

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Adab Belajar | `iii_akhlaq__adab_belajar` |
| Ganjil | 2 | Adab Makan dan Minum | `iii_akhlaq__adab_makan_dan_minum` |
| Ganjil | 3 | Adab Tidur | `iii_akhlaq__adab_tidur` |
| Ganjil | 4 | Adab Kebersihan | `iii_akhlaq__adab_kebersihan` |
| Ganjil | 5 | Adab Terhadap Orang Tua | `iii_akhlaq__adab_terhadap_orang_tua` |
| Genap | 6 | Adab Pergaulan | `iii_akhlaq__adab_pergaulan` |
| Genap | 7 | Adab Silaturrahmi | `iii_akhlaq__adab_silaturrahmi` |
| Genap | 8 | Adab Berbicara | `iii_akhlaq__adab_berbicara` |
| Genap | 9 | Adab Terhadap Orang Yang Terkena Musibah | `iii_akhlaq__adab_terhadap_orang_yang_terkena_musibah` |
| Genap | 10 | Sifat Mahmudah dan Madzmumah | `iii_akhlaq__sifat_mahmudah_dan_madzmumah` |


### IV. ALQUR’AN DAN HADITS

`iv_alquran_dan_hadits` · 30 baris nilai


**A. Surah-surah Pendek** · 10 baris

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Surah Al-Fatihah | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_fatihah` |
| Ganjil | 2 | Surah An-Nas | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_an_nas` |
| Ganjil | 3 | Surah Al-Falaq | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_falaq` |
| Ganjil | 4 | Surah Al-Ikhlas | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_ikhlas` |
| Ganjil | 5 | Surah Al-Lahab | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_lahab` |
| Genap | 6 | Surah An-Nasr | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_an_nasr` |
| Genap | 7 | Surah Al-Kafirun | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_kafirun` |
| Genap | 8 | Surah Al-Kausar | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_kausar` |
| Genap | 9 | Surah Al-Ma’un | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_al_maun` |
| Genap | 10 | Surah Quraisy | `iv_alquran_dan_hadits__a_surah_surah_pendek__surah_quraisy` |


**B. Hadits** · 10 baris

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Kasih Sayang | `iv_alquran_dan_hadits__b_hadits__kasih_sayang` |
| Ganjil | 2 | Keindahan | `iv_alquran_dan_hadits__b_hadits__keindahan` |
| Ganjil | 3 | Menyebarkan Salam | `iv_alquran_dan_hadits__b_hadits__menyebarkan_salam` |
| Ganjil | 4 | Jangan Suka Marah | `iv_alquran_dan_hadits__b_hadits__jangan_suka_marah` |
| Ganjil | 5 | Sesama Muslim Bersaudara | `iv_alquran_dan_hadits__b_hadits__sesama_muslim_bersaudara` |
| Genap | 6 | Menutup Aurat | `iv_alquran_dan_hadits__b_hadits__menutup_aurat` |
| Genap | 7 | Shalat Tepat Waktu | `iv_alquran_dan_hadits__b_hadits__shalat_tepat_waktu` |
| Genap | 8 | Berbuat Baik | `iv_alquran_dan_hadits__b_hadits__berbuat_baik` |
| Genap | 9 | Memberi Hadiah | `iv_alquran_dan_hadits__b_hadits__memberi_hadiah` |
| Genap | 10 | Wajib Menuntut Ilmu | `iv_alquran_dan_hadits__b_hadits__wajib_menuntut_ilmu` |


**C. Doa-doa Harian** · 10 baris

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Sebelum Belajar dan Pembuka Hati | `iv_alquran_dan_hadits__c_doa_doa_harian__sebelum_belajar_dan_pembuka_hati` |
| Ganjil | 2 | Bepergian | `iv_alquran_dan_hadits__c_doa_doa_harian__bepergian` |
| Ganjil | 3 | Naik Kendaraan | `iv_alquran_dan_hadits__c_doa_doa_harian__naik_kendaraan` |
| Ganjil | 4 | Kedua Orang Tua | `iv_alquran_dan_hadits__c_doa_doa_harian__kedua_orang_tua` |
| Ganjil | 5 | Penutup Majelis | `iv_alquran_dan_hadits__c_doa_doa_harian__penutup_majelis` |
| Genap | 6 | Sebelum dan Setelah Tidur | `iv_alquran_dan_hadits__c_doa_doa_harian__sebelum_dan_setelah_tidur` |
| Genap | 7 | Masuk dan Keluar WC | `iv_alquran_dan_hadits__c_doa_doa_harian__masuk_dan_keluar_wc` |
| Genap | 8 | Melepas Pakaian | `iv_alquran_dan_hadits__c_doa_doa_harian__melepas_pakaian` |
| Genap | 9 | Bercermin | `iv_alquran_dan_hadits__c_doa_doa_harian__bercermin` |
| Genap | 10 | Berpakaian | `iv_alquran_dan_hadits__c_doa_doa_harian__berpakaian` |


### V. ASMAUL HUSNA

`v_asmaul_husna` · 10 baris nilai

Setiap baris memuat sepuluh nama dan **dinilai sebagai satu baris**, bukan per nama.

| Semester | Baris | Nama yang tercakup |
|---|---|---|
| Ganjil | 1 | Ar-Rahman, Ar-Rahim, Al-Malik, Al-Quddus, As-Salam, Al-Mu'min, Al-Muhaimin, Al-'Aziz, Al-Jabbar, Al-Mutakabbir |
| Ganjil | 2 | Al-Khaliq, Al-Bari', Al-Musawwir, Al-Ghaffar, Al-Qahhar, Al-Wahhab, Ar-Razzaq, Al-Fattah, Al-'Alim, Al-Qabidh |
| Ganjil | 3 | Al-Basith, Al-Khafidh, Ar-Rafi', Al-Mu'iz, Al-Mudzill, As-Sami', Al-Bashir, Al-Hakam, Al-'Adl, Al-Lathif |
| Ganjil | 4 | Al-Khabir, Al-Halim, Al-'Azhim, Al-Ghafur, Asy-Syakur, Al-'Aliyy, Al-Kabir, Al-Hafizh, Al-Muqit, Al-Hasib |
| Ganjil | 5 | Al-Jalil, Al-Karim, Ar-Raqib, Al-Mujib, Al-Wasi', Al-Hakim, Al-Wadud, Al-Majid, Al-Ba'its, Asy-Syahid |
| Genap | 6 | Al-Haqq, Al-Wakil, Al-Qawiyy, Al-Matin, Al-Waliyy, Al-Hamid, Al-Muhshi, Al-Mubdi', Al-Mu'id, Al-Muhyi |
| Genap | 7 | Al-Mumit, Al-Hayy, Al-Qayyum, Al-Wajid, Al-Majid, Al-Wahid, Al-Ahad, Ash-Shamad, Al-Qadir, Al-Muqtadir |
| Genap | 8 | Al-Muqaddim, Al-Mu'akhkhir, Al-Awwal, Al-Akhir, Azh-Zhahir, Al-Bathin, Al-Wali, Al-Muta'ali, Al-Barr, At-Tawwab |
| Genap | 9 | Al-Muntaqim, Al-'Afuww, Ar-Ra'uf, Malikul Mulk, Dzul Jalali wal Ikram, Al-Muqsith, Al-Jami', Al-Ghaniyy, Al-Mughni, Al-Mani' |
| Genap | 10 | Ad-Dharr, An-Nafi', An-Nur, Al-Hadi, Al-Badi', Al-Baqi, Al-Warits, Ar-Rasyid, Ash-Shabur |


### VI. KISAH SAHABAT RASULULLAH

`vi_kisah_sahabat_rasulullah` · 8 baris nilai

| Semester | # | Butir | Kode |
|---|---|---|---|
| Ganjil | 1 | Abu Bakar Ash-Shiddiq | `vi_kisah_sahabat_rasulullah__abu_bakar_ash_shiddiq` |
| Ganjil | 2 | Umar bin Khattab | `vi_kisah_sahabat_rasulullah__umar_bin_khattab` |
| Ganjil | 3 | Utsman bin Affan | `vi_kisah_sahabat_rasulullah__utsman_bin_affan` |
| Ganjil | 4 | Ali bin Abi Thalib | `vi_kisah_sahabat_rasulullah__ali_bin_abi_thalib` |
| Genap | 5 | Khadijah binti Khuwailid | `vi_kisah_sahabat_rasulullah__khadijah_binti_khuwailid` |
| Genap | 6 | Fatimah binti Rasulullah | `vi_kisah_sahabat_rasulullah__fatimah_binti_rasulullah` |
| Genap | 7 | Asiyah binti Muzahim | `vi_kisah_sahabat_rasulullah__asiyah_binti_muzahim` |
| Genap | 8 | Maryam binti Imran | `vi_kisah_sahabat_rasulullah__maryam_binti_imran` |
---

## 5. Struktur Dokumen

Enam ruang lingkup, satu di antaranya punya tiga sub-bagian, lalu satu bagian catatan.

| No | Ruang Lingkup | Sub | Baris nilai | Ganjil | Genap |
|---|---|---|---|---|---|
| I | Aqidah Tauhid | — | 6 | 4 | 2 |
| II | Fiqih/Ibadah | — | 9 | 4 | 5 |
| III | Akhlaq | — | 10 | 5 | 5 |
| IV | Al-Qur'an dan Hadits | A, B, C | 30 | 15 | 15 |
| V | Asmaul Husna | — | 10 | 5 | 5 |
| VI | Kisah Sahabat Rasulullah | — | 8 | 4 | 4 |
| | **Total** | | **73** | **37** | **36** |
| VII | Laporan Perkembangan Agama | — | teks | | |

### 5.1 Asmaul Husna dinilai per baris, bukan per nama

Ruang lingkup V memuat **99 nama**, tapi hanya punya **10 baris nilai**. Tiap baris memuat sepuluh nama sekaligus, kecuali baris terakhir yang memuat sembilan.

```
Baris 1  Ar-Rahman, Ar-Rahim, Al-Malik, Al-Quddus, As-Salam,
         Al-Mu'min, Al-Muhaimin, Al-'Aziz, Al-Jabbar, Al-Mutakabbir
```

Jadi yang dinilai adalah **penguasaan satu kelompok sepuluh nama**, bukan tiap nama satu per satu. Jangan memecahnya jadi 99 baris nilai, karena cetakannya akan melenceng jauh dan pekerjaan guru jadi sepuluh kali lipat.

Ke-99 namanya tetap disimpan di berkas seed pada kolom `nama` tiap baris, supaya bisa ditampilkan lengkap di layar dan di cetakan. Yang disimpan sebagai nilai hanya sepuluh baris itu.

### 5.2 Bagian VII berupa template, bukan teks bebas

Berbeda dengan Rapor Ummi yang catatannya kosong melompong, di sini sudah ada kalimat jadi dengan **enam lubang isian**, satu untuk tiap ruang lingkup.

> Pencapaian perkembangan Ananda `{nama}` di semester ganjil ini secara umum berkembang sesuai harapan. Kini Ananda `{nama}` telah menunjukkan kemajuan, seperti aqidah tauhid yaitu **(isi sendiri)**. Fiqih ibadah yaitu **(isi sendiri)**. Akhlaq yaitu **(isi sendiri)**. Al-Qur'an dan hadits yaitu **(isi sendiri)**. Asmaul husna yaitu **(isi sendiri)** dan kisah sahabat yaitu **(isi sendiri)**.
>
> Secara keseluruhan, Ananda mulai memahami nilai-nilai islam yang terkandung di dalamnya dan berusaha menerapkannya dalam kegiatan sehari-hari. Dengan dukungan dari guru dan orang tua, Ananda diharapkan semakin tumbuh menjadi anak yang mengenal dan mencintai Allah, mencintai Rasulullah ﷺ, serta terbiasa menjalankan ajaran Islam dengan penuh kesadaran dan kegembiraan.
>
> Semangat, Ananda `{nama}`!

**Sudah disetujui sekolah.** Sediakan **enam textarea pendek**, masing-masing diberi label ruang lingkupnya, lalu sistem yang merangkai kalimat utuhnya. Guru cukup menulis enam potong kalimat, bukan mengarang satu paragraf panjang dari nol setiap kali.

Nama murid disisipkan otomatis di tiga tempat, dan label semester mengikuti semester rapornya.

Simpan **enam potongan itu**, bukan hasil rangkaiannya. Narasi yang dicetak menjadi bagian dari snapshot rapor saat pengesahan. Jika redaksi pengantar diperbaiki, naikkan versi template dan gunakan hanya untuk rapor yang disahkan sesudah perubahan; rapor yang sudah terbit tidak boleh berubah. Artefak PDF terbit dan manifest-nya menyimpan versi template yang dipakai.

Label keenam textarea mengikuti urutan kalimatnya, yaitu Aqidah Tauhid, Fiqih Ibadah, Akhlaq, Al-Qur'an dan Hadits, Asmaul Husna, lalu Kisah Sahabat.

---

## 6. Skema Basis Data

Tabel bersama, yaitu `rubrik`, `periode`, `rapor`, dan `rapor_sesi`, didefinisikan di `SPEK_ALUR_PENGISIAN.md` bagian 7. Di sini hanya tabel khusus Agama.

Nilai untuk Agama di tabel bersama:

| Tabel | Nilai Agama |
|---|---|
| `rubrik` | `kode = 'AGAMA_V1'`, `cakupan = 'TAHUNAN'`, `jenis_periode = NULL`, `cetak_gabung_periode = TRUE` |
| `rubrik_periode` | `TENGAH_SEMESTER` = (semester butir, TENGAH), `AKHIR_SEMESTER` = (semester butir, AKHIR) |
| `rapor` | satu per murid per tahun ajaran, `semester = 'TAHUNAN'` |

Rapor Agama bersifat tahunan karena cetakannya memuat seluruh 73 butir sepanjang tahun, lihat bagian 3.3. Keempat sesi dalam setahun menulis ke rapor yang sama, masing-masing dengan `periode_id` konkretnya sendiri.

```sql
-- definisi rubrik
rubrik_agama_lingkup        -- I .. VI
  id           PK
  rubrik_id    FK -> rubrik
  kode         VARCHAR
  nomor_romawi VARCHAR      -- 'I' .. 'VI'
  nama         VARCHAR      -- 'AQIDAH TAUHID'
  urutan       INT

rubrik_agama_sub            -- hanya ruang lingkup IV yang punya isi
  id           PK
  lingkup_id   FK -> rubrik_agama_lingkup
  huruf        CHAR(1) NULL -- 'A','B','C', NULL kalau implisit
  nama         VARCHAR NULL
  implisit     BOOLEAN DEFAULT 0
  urutan       INT

rubrik_agama_item
  id           PK
  sub_id       FK -> rubrik_agama_sub
  kode         VARCHAR UNIQUE
  teks         TEXT
  semester     ENUM('GANJIL','GENAP')   -- lihat bagian 3.3
  urutan       INT
  aktif        BOOLEAN DEFAULT 1
  INDEX (sub_id, semester, urutan)

rubrik_agama_item_nama      -- hanya untuk Asmaul Husna
  id           PK
  item_id      FK -> rubrik_agama_item
  nama         VARCHAR      -- 'Ar-Rahman'
  urutan       INT

rubrik_agama_tahapan
  id           PK
  rubrik_id    FK -> rubrik
  kode         VARCHAR      -- 'TELADAN','TALQIN','TAHFIZH','TAFHIM','TADIB'
  label        VARCHAR
  definisi     TEXT
  urutan       INT
  UNIQUE (rubrik_id, kode)

rubrik_agama_subtingkat     -- hanya milik TAHFIZH
  id           PK
  tahapan_id   FK -> rubrik_agama_tahapan
  kode         CHAR(1)      -- 'D','J','M'
  label        VARCHAR      -- 'Dho''if','Jayyid','Mumtaaz'
  arti         VARCHAR      -- 'Hafalan Lemah', dst
  definisi     TEXT
  urutan       INT
```

```sql
-- penilaian
rapor_agama_nilai           -- SATU baris per butir per periode
  id            PK
  rapor_id      FK -> rapor
  item_id       FK -> rubrik_agama_item
  periode_id    FK -> periode
  tahapan_id    FK -> rubrik_agama_tahapan NULL      -- NULL = belum dinilai
  subtingkat_id FK -> rubrik_agama_subtingkat NULL
                -- WAJIB diisi kalau tahapannya TAHFIZH
                -- HARUS NULL kalau tahapannya selain itu
  diisi_oleh    FK -> user NULL
  diisi_pada    TIMESTAMP NULL
  UNIQUE (rapor_id, item_id, periode_id)
  INDEX (rapor_id, periode_id)

rapor_agama_catatan         -- Bagian VII, enam potongan kalimat
  id            PK
  rapor_id      FK -> rapor
  periode_id    FK -> periode
  lingkup_id    FK -> rubrik_agama_lingkup
  isi           TEXT NULL
  UNIQUE (rapor_id, periode_id, lingkup_id)
```

`periode_id` di kedua tabel adalah periode konkret, satu dari empat per tahun. Karena itu catatan Bagian VII Tengah Ganjil dan Tengah Genap tersimpan di baris berbeda dan tidak saling menimpa.

**Kenapa bukan tujuh kolom boolean.** Karena guru hanya memilih satu. Tujuh kolom boolean akan mengizinkan keadaan yang tidak sah, misalnya `TELADAN` dan `TADIB` sama-sama bernilai benar, dan skemanya sendiri tidak bisa menolaknya. Satu baris dengan kunci unik `(rapor_id, item_id, periode_id)` membuat pilihan ganda jadi mustahil.

**Kenapa sub-tingkat dipisah dari tahapan.** Sub-tingkat hanya milik Tahfizh. Dipisah begini, aturan itu bisa ditegakkan lewat batasan basis data. Kalau disimpan sebagai tujuh nilai datar seperti `TAHFIZH_D`, tidak ada yang mencegah `TAFHIM_D` masuk ke data.

Perhatikan bahwa **di layar guru tetap melihat tujuh pilihan datar**. Pemecahan jadi dua kolom terjadi di belakang layar saat menyimpan. Lihat bagian 3.2.

**Batasan yang harus dipasang di basis data**, bukan diserahkan ke kode aplikasi saja:

- `subtingkat_id` wajib terisi ketika `tahapan_id` menunjuk Tahfizh
- `subtingkat_id` wajib `NULL` untuk tahapan lainnya

---

## 7. Aturan Bisnis dan Cetak

### 7.1 Yang dipakai ulang

**Alur pengisian dan persetujuan ada di `SPEK_ALUR_PENGISIAN.md`**, dan berlaku lintas seluruh dokumen rapor. Isinya alur empat status di tingkat sesi, rantai persetujuan, penerbitan serentak, penguncian, dan tabel bersama. **Rapor Agama hanya disetujui kepala sekolah.** Tidak ada koordinator bidang untuk dokumen ini.

Yang perlu diingat di sini, Rapor Agama **tidak disetujui sendirian**. Dia bergerak bersama seluruh dokumen rapor murid yang sama, dan tidak bisa terbit duluan.

Kedua bagian Agama wajib, dan di seed ditandai `wajib: true`.

### 7.2 Kelengkapan dihitung dari butir semester yang berjalan

Yang dihitung **hanya butir milik semester yang sedang dikerjakan**, bukan seluruh 73.

| Sesi di semester | Butir yang dihitung |
|---|---|
| Ganjil | **37** |
| Genap | **36** |

Butir dari semester lain tidak masuk hitungan sama sekali, karena materinya memang belum atau sudah lewat diajarkan. Butir itu juga tidak muncul di layar pengisian, jadi guru tidak akan bertanya-tanya kenapa ada yang kosong.

Satu butir dianggap terisi kalau ada satu baris `rapor_agama_nilai` untuk periode sesi itu dengan `tahapan_id` yang tidak `NULL`. Tidak ada keraguan, karena pilihannya memang cuma satu.

**Bagian VII wajib, dan keenam isiannya harus terisi semua.**

Jadi syarat terbit rapor Agama ada dua, dan keduanya harus terpenuhi:

1. Seluruh butir semester yang berjalan sudah dinilai, yaitu 37 saat Ganjil atau 36 saat Genap.
2. Keenam isian Bagian VII sudah terisi, dan tidak satu pun yang hanya berisi spasi.

Alasan Bagian VII dibuat wajib bukan soal kerapian data. **Guru mempresentasikan bagian ini langsung ke orang tua saat penerimaan rapor.** Kalau ada yang kosong, yang terjadi bukan data tidak lengkap, melainkan guru berdiri di depan orang tua tanpa bahan untuk satu ruang lingkup.

Karena itu validasinya harus menyebut **ruang lingkup mana** yang belum diisi, bukan sekadar mengatakan catatan guru belum lengkap. Dengan enam isian, pesan yang tidak menunjuk akan membuat guru menebak-nebak.

Berlaku definisi kosong yang sudah ditetapkan di spesifikasi RTS bagian 6.2.2, yaitu `NULL`, string kosong, dan string berisi spasi saja semuanya dihitung kosong.

### 7.3 Cetak

**A4 tegak**, sama seperti RTS dan Ummi. Sudah dipastikan dari berkas Word aslinya, yang memakai ukuran 21 × 29,7 cm tegak.

#### Tabelnya lebih lebar daripada halamannya

Ini temuan yang perlu diselesaikan sebelum generator PDF dibuat.

| | Ukuran |
|---|---|
| Lebar tabel di berkas Word | **18,8 cm** |
| Lebar area cetak, dengan margin 2,54 cm | **15,92 cm** |
| Selisih | **2,88 cm** |

Jadi tabelnya memang tidak muat di halamannya sendiri. Di Word hal ini biasanya tertutupi karena tabel dikecilkan otomatis saat dicetak, tapi generator PDF tidak akan melakukan itu sendiri kalau tidak disuruh.

Tiga cara mengatasinya, dan sebaiknya dipakai berbarengan:

1. **Kecilkan margin** menjadi sekitar 1,2 cm kiri dan kanan. Area cetak jadi 18,6 cm.
2. **Persempit kolom Ruang Lingkup** dari 4,03 cm. Teks butir boleh turun ke baris kedua, dan itu memang sudah terjadi di beberapa baris.
3. **Seragamkan lebar empat belas kolom nilai.** Di berkas asli lebarnya tidak konsisten, ada yang 1,49 cm dan ada yang 0,42 cm, padahal isinya sama-sama satu tanda centang. Samakan semuanya di sekitar 0,8 cm.

Poin ketiga sekaligus memperbaiki tampilannya. Kolom nilai yang lebarnya berbeda-beda membuat tabel terlihat miring padahal isinya seragam.

#### Susunan halaman

Dari atas ke bawah, yaitu blok identitas murid, keterangan lima tahapan beserta definisinya, tabel ruang lingkup I sampai VI, Bagian VII berisi catatan guru, lalu blok tanda tangan tiga kolom.

Yang perlu diperhatikan:

- **Kepala tabel bertingkat tiga.** Baris pertama `CAPAIAN SEMESTER {GANJIL|GENAP}`, baris kedua `TENGAH SEMESTER` dan `AKHIR SEMESTER`, baris ketiga tujuh nama tahapan dengan `TAHFIZH` membentang di atas `D`, `J`, dan `M`. Ketiganya harus ikut berulang di tiap halaman.
- **Baris judul ruang lingkup dan sub-bagian** membentang penuh tanpa kolom nilai.
- **Baris `CAPAIAN SEMESTER GANJIL` dan `CAPAIAN SEMESTER GENAP` di dalam tabel** adalah pemisah kelompok, bukan baris nilai. Keduanya **selalu ikut tercetak**, karena cetakan memuat seluruh 73 butir. Lihat bagian 3.3.
- **Cetakan tidak menyaring per semester.** Ambil seluruh 73 butir apa adanya, lalu isikan nilai yang sudah ada. Nilai semester sebelumnya ikut terbawa dan tidak boleh dikosongkan.
- **Baris Asmaul Husna** memuat sepuluh nama dalam satu sel, dipisah koma.
- **Bagian VII dicetak sebagai kalimat utuh**, bukan enam potongan terpisah. Sistem yang merangkainya. Lihat bagian 5.2.
- **Bagian VII yang tercetak adalah catatan periode sesi yang terbit.** Rapor Agama tahunan menampung sampai empat set catatan, satu per periode, dan hanya satu yang tercetak. Catatan periode sebelumnya tetap tersimpan.
- Keterangan lima tahapan sebaiknya ikut tercetak, karena orang tua tidak akan paham arti Talqin atau Tahfizh tanpa penjelasan.

### 7.4 Penandatangan

Tiga kolom, yaitu **Kepala TK**, **Guru Kelas**, dan **Orang Tua Siswa**. Sama seperti RTS, berbeda dengan Ummi yang memakai Koordinator Al-Quran.

**Yang mengisi adalah guru kelas.** Zivana tidak punya guru agama tersendiri, semua pengajarnya guru TK umum. Jadi tidak ada pemetaan peran khusus untuk dokumen ini, dan tidak perlu ada peran `GURU_AGAMA` di RBAC.

Ini berlaku untuk seluruh dokumen rapor. Satu guru kelas mengisi seluruhnya dalam satu sesi. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 3.

Nama, NUPTK, dan gambar tanda tangan diambil dari salinan sesi, bukan di-join hidup. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 7.5. Baris Orang Tua Siswa dicetak sebagai jabatan dan garis kosong.

---

## 8. Hubungan dengan Blok Hafalan di Berkas Ummi

Berkas Rapor Ummi memuat blok Hafalan 21 butir yang sebelumnya diduga milik Rapor Agama.

**Setelah berkas Rapor Agama yang sebenarnya dibaca, dugaan itu ternyata tidak tepat.** Daftarnya mirip tapi **bukan daftar yang sama**.

| Kelompok | Di berkas Ummi | Di Rapor Agama | Beririsan |
|---|---|---|---|
| Surah Pendek | 9 butir | 10 butir | 8 butir |
| Hadits | 4 butir | 10 butir | 1 butir |
| Do'a Harian | 8 butir | 10 butir | sebagian, dengan penamaan berbeda |

Contoh selisihnya. Berkas Ummi memuat `Al Asr` yang tidak ada di Rapor Agama. Rapor Agama memuat `Al-Ma'un` dan `Quraisy` yang tidak ada di berkas Ummi. Untuk Hadits, hanya `Kasih Sayang` yang sama, sisanya berbeda seluruhnya.

**Sudah dipastikan sekolah, yang berlaku adalah daftar dari berkas Rapor Agama ini.** Blok dari berkas Ummi **tidak di-seed di mana pun**. Hafalan yang berlaku sudah ada di `rubrik_agama_seed.json`, ruang lingkup IV.

Asal-usul blok di berkas Ummi masih belum jelas, kemungkinan versi lama atau tertinggal dari templat sekolah lain. Tidak perlu dikejar, kecuali sekolah menyebutnya lagi.

---

## 9. Temuan di Berkas Sumber

### 9.1 Salah ketik dan penulisan tidak seragam

**Seluruhnya sudah diperbaiki sekolah**, dan `rubrik_agama_seed.json` sudah mengikuti. Tabel di bawah dipertahankan sebagai catatan apa yang berubah dari berkas sumber.

Karena rubrik belum pernah di-seed, **kode empat butir ikut diturunkan ulang** dari teks yang sudah benar, misalnya `...__quraisy` jadi `...__surah_quraisy`. Setelah seeding pertama, kode dibekukan dan aturan ini tidak berlaku lagi.

Dua perbaikan punya lebih dari satu arah yang mungkin, dan seed memakai **bentuk yang paling banyak dipakai di dokumen itu sendiri**:

| Butir | Dipakai | Alasannya |
|---|---|---|
| IV.C butir 4 | `Kedua Orang Tua` | III.5 dan baris tanda tangan sama-sama menulis `Orang Tua` terpisah |
| IV.B butir 7 | `Shalat Tepat Waktu` | II.4 menulis `Shalat` dua kali |

Keduanya dicocokkan lagi saat contoh Rapor Agama yang sudah terisi diterima. Kalau sekolah ternyata memilih arah sebaliknya, cukup ganti teksnya, dan selama belum di-seed kodenya ikut diganti.

**Jelas salah ketik**

| Lokasi | Tertulis | Seharusnya |
|---|---|---|
| Keterangan tahapan Tahfizh | Hafalan **Istiwewa** | Hafalan Istimewa |
| VI. Kisah Sahabat, butir 6 | Fatimah binti **Rasululullah** | Fatimah binti Rasulullah |

Yang pertama ada di blok keterangan, bukan di tabel, tapi ikut tercetak dan terbaca orang tua.

**Penulisan tidak seragam**

| Lokasi | Tertulis | Bandingkan dengan |
|---|---|---|
| III. Akhlaq, butir 1 | Adab **belajar** | sembilan butir Adab lainnya memakai huruf besar, misalnya `Adab Makan dan Minum` |
| IV.C, butir 1 | Sebelum Belajar dan **pembuka** Hati | huruf kecil di tengah judul |
| IV.C, butir 4 | **kedua Orangtua** | huruf kecil di awal, dan `Orangtua` disambung sementara III.5 menulis `Orang Tua` |
| IV.A, butir 10 | **Quraisy** | sembilan butir lain diawali kata `Surah`, misalnya `Surah Al-Ma'un` |
| IV.B, butir 7 | **Sholat** Tepat Waktu | II.4 menulis `Gerakan Shalat dan Bacaan Shalat` |

**Bukan salah ketik, tapi perlu diketahui**

`Al-Majid` muncul **dua kali** di daftar Asmaul Husna, yaitu di baris 5 dan baris 7. Ini **bukan kekeliruan**. Dalam daftar 99 nama memang ada dua nama berbeda dalam bahasa Arab, yaitu al-Majīd dan al-Mājid, yang lazim dialihaksarakan sama persis ke huruf latin.

Jumlahnya tetap 99 dan urutannya benar. Cukup dipastikan sistem tidak memperlakukan kedua baris itu sebagai data kembar yang perlu dibuang.

### 9.2 Isi contoh yang tertinggal

Bagian VII memuat nama **"Ananda Zahid"** dan kolom Guru Kelas menyebut **"Ranting Mahoni"**. Keduanya sisa contoh dan **jangan ikut di-seed**.

Nama berkas menyebut `RANTING` tanpa keterangan kelas, jadi berkas ini kemungkinan dipakai untuk beberapa kelas dengan nama guru yang diganti manual. Di sistem hal ini hilang dengan sendirinya, karena nama kelas dan guru diambil dari data murid.

### 9.3 Judul semester di kepala tabel

Kepala tabel berbunyi `CAPAIAN SEMESTER GANJIL`, tapi badan tabelnya memuat butir Ganjil **dan** Genap sekaligus. **Sudah terjawab**, yaitu cetakan memang memuat seluruh 73 butir, dan judul semesternya diambil dari periode sesi yang terbit. Lihat bagian 3.3.

---

## 10. Keputusan

Seluruh pertanyaan sudah dijawab sekolah. Tidak ada lagi yang menghambat.

**Guru memilih satu nilai per butir per periode, lewat dropdown.** Berlaku untuk seluruh dokumen rapor. Tahfizh dipecah jadi tiga pilihan terpisah di daftar dropdown, tapi cetakannya tetap tujuh kolom seperti aslinya. Lihat bagian 3.2.

**Tiap butir terikat ke satu semester.** Layar pengisian menyaring menjadi 37 butir saat Ganjil dan 36 butir saat Genap. **Cetakan tidak menyaring**, seluruh 73 butir ikut tercetak, dan nilai semester sebelumnya terbawa. Lihat bagian 3.3.

**Bagian VII memakai enam textarea yang dirangkai sistem, wajib diisi, dan keenamnya harus terisi semua.** Alasannya guru mempresentasikan bagian ini langsung ke orang tua saat penerimaan rapor. Lihat bagian 5.2 dan 7.2.

**Yang mengisi adalah guru kelas.** Tidak ada guru agama tersendiri di Zivana. Lihat bagian 7.4.

**Cetakannya A4 tegak**, sama seperti RTS dan Ummi. Tapi tabel di berkas sumber 2,88 cm lebih lebar daripada area cetaknya, dan itu perlu dibereskan di generator PDF. Lihat bagian 7.3.

**Daftar hafalan yang berlaku adalah milik dokumen ini**, bukan yang ada di berkas Rapor Ummi. Lihat bagian 8.

**Koordinator Al-Quran tidak menyetujui dan tidak menandatangani Rapor Agama.** Wewenangnya hanya Rapor Ummi. Lihat `SPEK_ALUR_PENGISIAN.md` bagian 4.2.

**Tidak ada mode teks bebas untuk Bagian VII.** Yang ada hanya enam textarea yang dirangkai sistem.

## 11. Cara Memakai Berkas Ini

1. Baca bagian 2 dan 3 lebih dulu. Skala bertingkat dan keterikatan butir ke semester adalah dua hal yang tidak ada di dokumen lain.
2. Ingat bahwa bentuk isian di layar berbeda dari bentuk tabel di cetakan. Bagian 3.2 menjelaskan pemetaannya.
3. Bangun tabel bersama dari `SPEK_ALUR_PENGISIAN.md` bagian 7 lebih dulu, lalu tabel khusus Agama dari bagian 6.
4. Seed dari `rubrik_agama_seed.json`. Jangan mengetik ulang dari tabel bagian 4.
5. Setelah seeding, pastikan hasilnya tepat 6 ruang lingkup, 73 baris nilai dengan sebaran 37 Ganjil dan 36 Genap, 99 nama Asmaul Husna, 5 tahapan, 3 sub-tingkat, dan 7 pilihan dropdown.

Struktur `rubrik_agama_seed.json`:

```
rubrik            metadata, 5 tahapan beserta definisi dan sub-tingkat,
                  7 pilihan dropdown beserta pemetaan ke kolom cetak,
                  periode, bagian, penandatangan, statistik
ruang_lingkup[]   6 ruang lingkup
  sub[]           sub-bagian, hanya ruang lingkup IV yang punya isi
    item[]        butir beserta penanda semester
                  butir Asmaul Husna membawa daftar nama lengkapnya
```

Developer juga diberi akses ke berkas Word aslinya. Kalau ada yang berbeda antara dokumen ini dan berkas itu, **berkas Word yang menang**, dan tolong kabari supaya dokumen ini diperbarui.
