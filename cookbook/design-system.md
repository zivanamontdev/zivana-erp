# eRapor Zivana Montessori
## Design Specification untuk Developer (Versi Lengkap — hasil crawling)

> Dokumen ini adalah gabungan dari `design-erapor.md` (dokumen asli, tidak diubah nilainya) **plus** temuan tambahan hasil crawling visual seluruh 52 screenshot di `assets/ss/`. Bagian **BAGIAN 1–6 di bawah adalah salinan persis dari `design-erapor.md`** (sumber kebenaran token/komponen). **BAGIAN 7 (Lampiran Temuan Crawling)** adalah tambahan baru — isi konkret per halaman yang tidak tercakup di dokumen desain asli.

---

# BAGIAN ASLI (dari design-erapor.md)

Dokumen ini adalah acuan tunggal untuk membangun UI aplikasi eRapor. Semua nilai di sini diambil langsung dari file Figma, bukan perkiraan. Kalau ada yang tidak tercantum di sini, tanyakan dulu sebelum mengarang sendiri.

**File Figma**
- Design System (token dan komponen) ada di page "Design System"
- Semua layar UI ada di page "UI"

**Prinsip utama yang harus dijaga**
1. Semua warna wajib memakai token, bukan hex mentah di dalam kode komponen
2. Semua teks wajib memakai type scale yang sudah didefinisikan
3. Light mode saja. Dark mode tidak ada di scope ini
4. Desktop first di lebar kanvas 1280px. Responsive belum dirancang, jangan berimprovisasi tanpa konfirmasi

---

## 1. Design Token

### 1.1 Warna

Ada empat keluarga warna. Ramp dibangun dengan metode perceptual lightness, jadi jangan menambah atau mengganti step sendiri pakai fungsi lighten atau darken biasa.

**Neutral** dipakai untuk 90 persen UI, mencakup background, surface, border, dan teks.

| Token | Hex |
|---|---|
| neutral-50 | `#FCFCFD` |
| neutral-75 | `#E4E3E5` |
| neutral-100 | `#CBCBCD` |
| neutral-150 | `#B4B3B6` |
| neutral-200 | `#9D9C9F` |
| neutral-300 | `#868689` |
| neutral-400 | `#717074` |
| neutral-500 | `#5C5B5F` |
| neutral-600 | `#47474B` |
| neutral-700 | `#343338` |
| neutral-800 | `#222126` |
| neutral-900 | `#111015` |
| neutral-black | `#040404` |
| neutral-white | `#FFFFFF` |

**Red** adalah satu satunya warna brand. Dipakai terbatas untuk aksi utama, nav aktif, dan status destruktif.

| Token | Hex |
|---|---|
| red-50 | `#FFF2F0` |
| red-100 | `#FED2CD` |
| red-200 | `#F8B3AC` |
| red-300 | `#EE958D` |
| red-400 | `#E3766E` |
| red-500 | `#D7554F` |
| red-600 | `#C92C2F` |
| red-700 | `#910A16` |
| red-800 | `#570107` |
| red-900 | `#240000` |

**Orange** hanya untuk status peringatan atau menunggu.

| Token | Hex |
|---|---|
| orange-50 | `#FFF3E6` |
| orange-100 | `#FFE6CA` |
| orange-200 | `#FFD9AE` |
| orange-300 | `#FECC91` |
| orange-400 | `#FDBF72` |
| orange-500 | `#FCB14E` |
| orange-600 | `#FAA30F` |
| orange-700 | `#A56B0A` |
| orange-800 | `#583703` |
| orange-900 | `#180900` |

**Green** hanya untuk status positif atau selesai.

| Token | Hex |
|---|---|
| green-50 | `#ECF9F0` |
| green-100 | `#CEEAD6` |
| green-200 | `#B0DBBD` |
| green-300 | `#92CBA4` |
| green-400 | `#72BC8C` |
| green-500 | `#4FAC74` |
| green-600 | `#1F9D5C` |
| green-700 | `#006B3A` |
| green-800 | `#003C1E` |
| green-900 | `#001305` |

**Contoh implementasi CSS variable**

```css
:root {
  --color-neutral-50: #FCFCFD;
  --color-neutral-75: #E4E3E5;
  --color-neutral-100: #CBCBCD;
  --color-neutral-150: #B4B3B6;
  --color-neutral-200: #9D9C9F;
  --color-neutral-300: #868689;
  --color-neutral-400: #717074;
  --color-neutral-500: #5C5B5F;
  --color-neutral-600: #47474B;
  --color-neutral-700: #343338;
  --color-neutral-800: #222126;
  --color-neutral-900: #111015;
  --color-neutral-black: #040404;
  --color-neutral-white: #FFFFFF;

  --color-red-50: #FFF2F0;
  --color-red-100: #FED2CD;
  --color-red-200: #F8B3AC;
  --color-red-300: #EE958D;
  --color-red-400: #E3766E;
  --color-red-500: #D7554F;
  --color-red-600: #C92C2F;
  --color-red-700: #910A16;
  --color-red-800: #570107;
  --color-red-900: #240000;

  --color-orange-50: #FFF3E6;
  --color-orange-600: #FAA30F;

  --color-green-50: #ECF9F0;
  --color-green-600: #1F9D5C;
}
```

### 1.2 Tipografi

**Typeface** Plus Jakarta Sans. Hanya dua weight yang dipakai, Regular (400) dan Bold (700). Ambil dari Google Fonts.

Semua line height sudah fix dalam pixel dan letter spacing 0.

| Style | Size | Line height | Weight tersedia |
|---|---|---|---|
| Display/XXL | 52px | 78px | Regular, Bold |
| Display/XL | 48px | 72px | Regular, Bold |
| Display/LG | 44px | 66px | Regular, Bold |
| Display/MD | 40px | 60px | Regular, Bold |
| Display/SM | 36px | 54px | Regular, Bold |
| Display/XS | 32px | 48px | Regular, Bold |
| Headline/LG | 32px | 48px | Regular, Bold |
| Headline/MD | 28px | 42px | Regular, Bold |
| Headline/SM | 24px | 36px | Regular, Bold |
| Body/LG | 20px | 30px | Regular, Bold |
| Body/MD | 16px | 24px | Regular, Bold |
| Body/SM | 14px | 21px | Regular, Bold |
| Caption/LG | 14px | 21px | Regular, Bold |
| Caption/MD | 12px | 18px | Regular, Bold |
| Caption/SM | 10px | 14px | Regular, Bold |

**Pemakaian umum di aplikasi ini**
- Judul halaman memakai Headline/Bold/SM (24px)
- Judul modal memakai Headline/Bold/SM (24px)
- Isi tabel, label form, dan teks utama memakai Body/Regular/SM (14px)
- Label input dan meta info memakai Caption/Regular/MD (12px)

Catatan, Body/SM dan Caption/LG punya ukuran yang sama yaitu 14/21. Untuk teks isi konten pakai Body/SM. Caption/LG disediakan untuk keterangan pendamping.

**Setup Google Fonts (untuk implementasi native PHP tanpa build step):**
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;700&display=swap" rel="stylesheet">
```
```css
body { font-family: 'Plus Jakarta Sans', sans-serif; }
```

### 1.3 Spacing dan Radius

Basis spacing 4px.

| Nilai | Dipakai untuk |
|---|---|
| 4px | Gap antar elemen rapat, gap label ke field |
| 8px | Gap antar nav item, gap antar tombol kecil |
| 12px | Gap dalam komponen, padding vertikal page header |
| 16px | Gap grid, padding sel tabel, padding sidebar |
| 20px | Gap antar section dalam modal |
| 24px | Padding horizontal konten, padding modal |

**Radius**

| Nilai | Dipakai untuk |
|---|---|
| 4px | Input, tombol, badge status |
| 8px | Container tabel, nav item, card kecil |
| 12px | Modal |
| 20px | Panel brand di halaman Login |

---

## 2. App Shell

Semua layar aplikasi selain Login memakai shell yang sama. Bangun ini sekali sebagai layout component, jangan diulang per halaman.

```
Canvas 1280px
├── Sidebar 236px (fixed, full height)
└── Content 1044px
    ├── Page Header 1 (toolbar, tinggi 61px)
    ├── Page Header 2 (breadcrumb, tinggi 45px)
    ├── Page Header 3 (judul dan aksi, tinggi 61px)
    └── Body atau Table area
```

### 2.1 Sidebar

| Properti | Nilai |
|---|---|
| Lebar | 236px (collapsed: **93px**, dikonfirmasi dari `sidebar_collapse.svg`) |
| Background | `red-50` |
| Border kanan | `neutral-100`, 1px |
| Padding | 16px atas, 16px kiri kanan, 32px bawah |
| Gap antar blok | 16px |

**Sidebar Header** berisi logo dan tombol collapse. Tinggi 36px, padding kiri 12px, radius 8px, gap 12px. Tombol collapse berukuran 36x36 dengan padding 8px dan radius 4px.

**Nav Menu** disusun dalam 5 grup yang dipisah garis horizontal. Gap antar item 8px.

**Nav Item**

| Properti | Nilai |
|---|---|
| Tinggi | 45px |
| Padding horizontal | 12px |
| Gap ikon ke label | 16px |
| Radius | 8px |
| Background aktif | `red-600` |
| Teks aktif | `neutral-50` |
| Background non aktif | transparan |
| Teks non aktif | `neutral-600` |

Hover state belum dirancang di Figma. Saran implementasi, pakai `neutral-75` sebagai background hover untuk item non aktif. Konfirmasi dulu ke designer sebelum difinalkan.

**Submenu (dikonfirmasi dari `sidebar_submenu.svg` dan `sidebar_submenu2.svg`):** grup nav dengan children (Kurikulum, Karyawan) bisa di-expand — header grup dapat background merah saat terbuka, children ditampilkan dengan indent + garis vertikal penghubung, item child aktif tetap pakai warna merah solid, child non-aktif abu-abu. Pola ini dipakai konsisten untuk semua nav group yang punya sub-menu.

### 2.2 Struktur Navigasi

```
Sekolah
├── Data Sekolah
└── Kurikulum
    ├── Manajemen Template
    └── Periode Penilaian
Human Capital
└── Karyawan
    ├── Daftar Karyawan
    ├── Jabatan
    └── Manajemen Guru
Murid
├── Manajemen Murid
├── Manajemen Kelas
└── Rapor Murid
Portal Guru
├── Dashboard
└── Daftar Murid
Sistem
└── RBAC
```

Prototype di Figma sudah dihubungkan antar halaman lewat nav ini, jadi alurnya bisa ditelusuri langsung dari mode Present.

### 2.3 Page Header

Ada tiga baris header yang bertumpuk. Semuanya memakai padding 12px vertikal dan 24px horizontal, dengan gap 16px.

| Baris | Tinggi | Isi |
|---|---|---|
| Toolbar | 61px | Search field lebar 167px di kiri, info user dan action icon di kanan |
| Breadcrumb | 45px | Path lokasi halaman |
| Judul | 61px | Judul section di kiri, tombol aksi di kanan |

Background header `neutral-50`, border bawah `neutral-100`. Baris breadcrumb hanya muncul di halaman yang punya parent, misalnya submenu Kurikulum dan Karyawan.

**Catatan hasil crawling:** halaman top-level seperti Data Sekolah, Manajemen Guru, Manajemen Murid, Manajemen Kelas, Dashboard Portal Guru **tidak** menampilkan baris breadcrumb (langsung toolbar → judul). Breadcrumb baru muncul di halaman yang benar-benar 2 level ke bawah (mis. "Kurikulum > Manajemen Template", "Karyawan > Daftar Karyawan", "Manajemen Murid > Detail Murid", "Rapor Murid > Pratinjau Rapor Murid").

### 2.4 Area Konten

Wrapper konten memakai padding 16px vertikal dan 24px horizontal, dengan gap 20px antar section. Lebar konten efektif 996px.

---

## 3. Komponen

### 3.1 Input

Ada 80 varian dari kombinasi properti berikut.

| Properti | Opsi |
|---|---|
| Type | textfield, textarea |
| Label | true, false |
| State | default, active, filled, viewonly, negative |
| Icon | default, left-icon, right-icon, double-icon |

**Spesifikasi container**

| Properti | Nilai |
|---|---|
| Tinggi | 37px untuk textfield |
| Padding | 8px vertikal, 12px horizontal |
| Radius | 4px |
| Gap ke ikon | 12px |
| Gap label ke field | 4px |

**Warna per state**

| State | Background | Border | Teks isi |
|---|---|---|---|
| default | `neutral-white` | `neutral-75` | placeholder `neutral-100` |
| active | `neutral-white` | `neutral-75` | placeholder `neutral-100` |
| filled | `neutral-white` | `neutral-75` | `neutral-900` |
| viewonly | `neutral-75` | `neutral-150` | `neutral-600` |
| negative | `neutral-50` | `red-300` | placeholder `neutral-100`, pesan error `red-600` |

Label field selalu `neutral-500`. State viewonly juga dipakai untuk field disabled, tidak ada varian disabled terpisah.

Catatan penting, di Figma state active dan default warnanya identik. Untuk implementasi, tambahkan focus ring yang jelas pada state active, misalnya border `red-600` atau outline 2px, lalu konfirmasi ke designer. Tanpa itu user tidak punya indikasi fokus yang memadai dan ini masalah aksesibilitas.

**Varian tambahan yang dikonfirmasi dari crawling:**
- **Dropdown/select**: memakai style container sama dengan textfield, icon chevron-down di kanan (right-icon)
- **Date picker**: style container sama, kemungkinan pakai native `<input type="date">` atau komponen kalender kustom `[ASUMSI — tidak terlihat state terbuka kalender di screenshot]`
- **Password field**: right-icon toggle show/hide
- **Field read-only computed** (contoh: "Umur" di form Murid, auto-calculated dari Tanggal Lahir): pakai visual state `viewonly` walau berada di form yang sedang mode edit — bukan field yang benar-benar bisa diketik user
- **Field repeatable row** (contoh: Media Sosial di Data Sekolah, baris murid di modal assign): field dalam satu baris + tombol hapus (icon trash) di ujung kanan, tombol "+ Tambah [X]" di atas list untuk menambah baris baru

### 3.2 Buttons

Ada 45 varian dari kombinasi berikut.

| Properti | Opsi |
|---|---|
| State | Regular, Pressed, Disable |
| Level | Primary, Secondary, Tertiary |
| Icon | Default, Left-Icon, Right-Icon, Double-Icon, Just-Icon |

**Spesifikasi**

| Properti | Nilai |
|---|---|
| Tinggi | 37px |
| Padding | 8px semua sisi |
| Radius | 4px |
| Gap ikon ke label | 12px |
| Varian Just-Icon | 36x36 |

**Warna**

| Level dan State | Background | Border | Teks |
|---|---|---|---|
| Primary Regular | `red-600` | `red-600` | `neutral-white` |
| Primary Pressed | `red-700` | `red-700` | `neutral-white` |
| Primary Disable | `neutral-75` | `neutral-100` | `neutral-150` |
| Secondary Regular | `red-100` | `red-200` | `neutral-800` |
| Tertiary Regular | `neutral-white` | `neutral-100` | `neutral-800` |

Ikon di dalam tombol Primary memakai stroke `neutral-50`. Ikon di komponen ini berbasis stroke, bukan fill, jadi ganti properti stroke ketika mewarnai ikon.

Hover state belum dirancang. Saran, Primary hover pakai `red-700`. Konfirmasi dulu sebelum difinalkan.

**Pola pemakaian yang dikonfirmasi dari crawling:**
- Tombol submit form (Tambah/Simpan) memakai state **Disable** sampai validasi form terpenuhi, lalu otomatis aktif (Primary Regular) — ini pola konsisten di SEMUA modal tambah (Karyawan, Jabatan, Kelas, dst)
- Modal ubah (edit data existing) tombol submitnya **selalu aktif** dari awal (tidak menunggu ada perubahan) — beda dengan modal tambah
- Tombol batal di semua modal konsisten pakai level Tertiary

### 3.3 Checkbox

Komponen sederhana dengan dua state, Check True dan Check False. Ukuran 16x16 dengan radius 4px. Dipakai terutama di layar RBAC.

**Catatan dari crawling RBAC:** checkbox dipakai berjenjang (modul → section → sub-section → permission Lihat/Edit). Indikator "x/y" muncul di sisi kanan tiap grup untuk menunjukkan berapa dari total permission yang aktif — kemungkinan besar checkbox level induk berperilaku tri-state (checked/unchecked/indeterminate) mengikuti state gabungan child-nya, tapi **tidak ada contoh visual state indeterminate** di screenshot manapun — perlu diimplementasikan berdasar logika umum RBAC matrix, bukan ditiru pixel-perfect dari Figma.

### 3.4 Ikon

Memakai Lucide Icons. Ukuran standar 20x20 untuk nav dan aksi baris, 16x16 untuk ikon di dalam input. Contoh yang sudah dipakai di file, `ellipsis-vertical` untuk aksi baris tabel, `school`, `book-marked`, `users`, `user-cog`, `graduation-cap`, `book-user`, `layout-dashboard`, `backpack`, `user-round-cog`, `chevron-down`.

**Asset icon yang sudah diekspor** (`assets/icons/`): backpack, bolt, book_marked, book_user, calendar, chevron, edit, external_link, graduation_cap, layout_dashboard, minimize, more_vertical (≡ ellipsis-vertical), refresh, school, search, tooltip, trash, user_cog, user_round_cog, users. Semua SVG stroke-based (`stroke="currentColor"`), bisa di-recolor dan di-rotate (untuk chevron) lewat CSS tanpa perlu asset terpisah per warna/arah.

### 3.5 Skala Penilaian Rapor (Komponen baru, ditemukan dari crawling — TIDAK ADA di design-erapor.md asli)

Ini komponen visual khusus yang muncul di Pratinjau Template dan Pratinjau Rapor Murid, berupa legenda 4 simbol non-numerik:

| Simbol | Label |
|---|---|
| `/` (garis miring) | Baru dikenalkan |
| Segitiga outline kecil | Mulai Berkembang |
| Segitiga outline besar | Berkembang Sesuai Harapan |
| Segitiga solid/penuh | Berkembang Sangat Baik |

Skala ini dipakai sebagai isi sel pada tabel penilaian (bukan angka/huruf). Untuk mata pelajaran tertentu (Pendidikan Agama Islam, Bacaan Jilid) skala berbeda dipakai (grade huruf seperti "A-", label seperti "Tahfizh Mumtaz") — lihat `schema.md` untuk desain tabel `skala_nilai` yang fleksibel per konteks.

---

## 4. Pattern

### 4.1 Tabel

Struktur dasar yang dipakai hampir di semua layar daftar.

| Bagian | Spesifikasi |
|---|---|
| Container | radius 8px, border `neutral-75` 1px |
| Header row | background `neutral-75`, padding 12px vertikal dan 16px horizontal, tinggi 45px |
| Data row | tanpa background, padding 16px, tinggi 53px sampai 55px |
| Pemisah baris | border `neutral-75` |
| Gap antar kolom | 16px |
| Kolom aksi | lebar 20px, ikon `ellipsis-vertical` di ujung kanan |

Teks header dan isi sel sama sama memakai Body/Regular/SM. Isi sel berupa teks polos kecuali kolom status.

**Daftar kolom tabel per halaman (hasil crawling, untuk referensi implementasi cepat):**

| Halaman | Kolom |
|---|---|
| Manajemen Murid | Nama Lengkap, NISN, Kelas, Jenis Kelamin, Guru Kelas, Status |
| Daftar Murid (Portal Guru) | NISN, Nama Lengkap, Level Kelas, Kelas, Jenis Kelamin *(tanpa Status, tanpa aksi CRUD)* |
| Daftar Karyawan | Nama Karyawan, Jabatan, Status |
| Jabatan | Nama Jabatan, Status |
| Manajemen Kelas | Level Kelas, Nama Kelas, Jumlah Murid, Jumlah Guru |
| Manajemen Template | Template, Kategori, Tipe, Status, Terakhir Diperbarui |
| Periode Penilaian | Nama Periode, Awal Periode, Akhir Periode |
| Manajemen Guru | *(bukan tabel, format card per guru + list murid di bawahnya)* |
| Rapor Murid | *(bukan tabel flat, format accordion 3 level: Periode → Sesi → per-murid)* |

### 4.2 Badge Status

Badge dipakai di kolom status. Radius 4px, padding horizontal 8px, padding vertikal 0.

| Jenis status | Background | Teks | Contoh nilai |
|---|---|---|---|
| Positif | `green-50` | `green-600` | Bersekolah, Aktif |
| Peringatan | `orange-50` | `orange-600` | Tanpa Keterangan |
| Netral atau selesai | `neutral-75` | `neutral-300` | Tamat, Berhenti |
| Destruktif | `red-50` | `red-600` | Perlu Revisi |

Badge selalu memakai tint lembut, tidak pernah fill solid. Ini disengaja supaya badge tidak tertukar dengan tombol.

**Status tambahan yang ditemukan dari crawling (Rapor Murid):** "Belum diisi" (kemungkinan varian destruktif/merah), "Menunggu persetujuan" (kemungkinan varian peringatan/oranye) — dikonfirmasi warnanya konsisten dengan pola di atas walau tidak 1:1 terlihat jelas di semua screenshot `[ASUMSI warna pasti]`.

### 4.3 Modal

| Properti | Nilai |
|---|---|
| Lebar | 440px untuk form pendek, 668px untuk form dengan daftar |
| Padding | 24px |
| Radius | 12px |
| Gap antar section | 20px |
| Background | putih |
| Overlay | `neutral-800` dengan opacity 20 persen |

Susunan isi modal, judul di atas, lalu isi form, lalu baris Actions di bawah dengan gap 12px. Tombol batal memakai level Tertiary, tombol konfirmasi memakai Primary. Untuk aksi yang menghapus atau membatalkan data, tombol konfirmasi tetap Primary merah karena merah memang warna brand di sini.

**Teks konfirmasi hapus (persis, hasil crawling — pakai kalimat ini sebagai copy standar):**
- Hapus Periode: *"Periode yang telah dihapus akan menghilang dari data periode penilaian dan tidak dapat diakses atau digunakan kembali."*
- Hapus Karyawan: *"Karyawan yang telah dihapus akan menghilang dari data karyawan dan tidak dapat diakses atau digunakan kembali. Pastikan data telah dibackup terlebih dahulu sebelum dihapus."*
- Hapus Kelas: *"Kelas yang telah dihapus akan menghilang dari data kelas dan tidak dapat diakses atau digunakan kembali. Murid yang masih terkait dengan kelas yang dihapus akan mengosongkan kelas murid terkait. Pastikan data telah dibackup terlebih dahulu sebelum dihapus."*

### 4.4 Form dan Field Row

Form disusun sebagai baris berisi dua field per baris, memakai komponen input. Gap antar baris 12px, gap antar kolom 24px. Untuk field panjang seperti alamat, gunakan satu field yang mengisi penuh lebar baris.

### 4.5 Pola Assign Many-to-Many (Komponen baru dari crawling)

Dipakai di 2 tempat: "Atur Anak Murid" (assign murid ke guru) dan assign murid ke guru-dalam-kelas. Pola UI-nya **bukan** checklist dua kolom tersedia/terpilih, melainkan:
- Card highlight berisi konteks entitas induk (nama guru/jabatan)
- Section "Daftar Murid" dengan tombol "+ Tambah Murid"
- Baris dinamis: dropdown searchable "Nama murid" + tombol hapus (icon X) per baris — bisa tambah baris baru berkali-kali
- Footer: Batal / Simpan

### 4.6 Accordion Bertingkat (Komponen baru dari crawling)

Dipakai di halaman Rapor Murid (daftar). 3 level nesting: Periode Penilaian (badge jumlah murid) → Sesi Pembagian Rapor (nama, rentang tanggal, badge jumlah) → baris per-murid dengan status. Juga dipakai dalam bentuk berbeda di form Pengisian Rapor guru (Kategori besar → Sub-kategori/Tujuan → item individual) dan RBAC (Modul → Section → Sub-section → permission).

---

## 5. Daftar Layar

Hampir semua layar berukuran 1280px lebar. Tinggi bervariasi, ada yang 832px dan ada yang lebih panjang karena kontennya memang panjang. Satu layar dibuat di 320px, lihat catatan di bagian 6.

Nama frame di Figma sudah dirapikan memakai pola `Modul -> Nama Layar (Kondisi)`, jadi nama di tabel ini sama persis dengan nama frame. Total 52 frame layar, tidak ada duplikat, semuanya dipakai.

**Modul Sekolah**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Data Sekolah (Informasi Umum) | Empty, Filled, Error Validasi, Sidebar Collapsed |
| Data Sekolah (Kontak & Media) | Empty, Filled, Error Validasi, Sidebar Collapsed |
| Data Sekolah (Modal Perbarui Tahun Ajaran) | Satu frame, varian sidebar collapsed |

**Modul Kurikulum**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Manajemen Template (Daftar) | Daftar template |
| Pratinjau Template | Panel pratinjau dokumen rapor, tinggi 1110px |
| Periode Penilaian (Daftar) | Daftar periode, dengan filter Semua Tipe dan Semua Kategori |
| Periode Penilaian (Modal Ubah) | Modal ubah periode |
| Periode Penilaian (Modal Hapus) | Modal konfirmasi hapus, judul "Hapus Periode?" |

**Modul Human Capital**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Daftar Karyawan (Daftar) | Daftar karyawan |
| Daftar Karyawan | Modal Tambah, Modal Ubah, Modal Hapus |
| Jabatan (Daftar) | Daftar jabatan |
| Jabatan | Modal Tambah, Modal Ubah |
| Manajemen Guru (Daftar) | Daftar guru |
| Manajemen Guru (Modal Atur Anak Murid) | Assign murid ke guru |

**Modul Murid**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Manajemen Murid (Daftar) | Daftar murid dengan badge status |
| Tambah Murid | Tiga tab (Data Murid, Informasi Pendaftaran, Relasi & Kontak), masing masing Empty dan Filled |
| Ubah Data Murid | Tiga tab yang sama, kondisi terisi |
| Detail Murid | Tiga tab yang sama, mode baca saja |
| Manajemen Kelas (Daftar) | Daftar kelas |
| Manajemen Kelas | Modal Tambah, Modal Ubah, Modal Hapus |
| Detail Kelas | Detail satu kelas |
| Detail Kelas (Modal Atur Anak Murid) | Assign murid ke kelas |
| Rapor Murid (Daftar) | Daftar rapor per periode |
| Rapor Murid (Pratinjau Rapor) | Pratinjau dokumen, tinggi 1110px |

**Modul Portal Guru**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Dashboard | Tiga blok, Agenda Sedang Berlangsung, Daftar Murid, dan Agenda Berikutnya |
| Dashboard (Agenda Berikutnya Kosong/Empty) | Varian saat admin belum menambahkan agenda berikutnya |
| Daftar Murid | Daftar murid milik guru bersangkutan |
| Pengisian Rapor (Desktop) | Form pengisian rapor oleh guru, tinggi 2028px |
| Pengisian Rapor (Mobile) | Versi 320px dari layar yang sama, tinggi 2326px |
| Pratinjau Rapor Murid | Pratinjau hasil isian, tinggi 1116px |

**Modul Sistem dan Auth**

| Layar | Kondisi yang sudah dirancang |
|---|---|
| Sistem -> RBAC | Matriks permission bertingkat dengan checkbox, tinggi 1148px |
| Auth -> Login | Split kiri form dan kanan panel brand |

### 5.1 Login

Layout dua kolom dengan gap 46px, total lebar konten 1176px.

| Bagian | Spesifikasi |
|---|---|
| Kolom kiri | 620px, berisi logo di atas, form di tengah, footer di bawah |
| Blok form | 410px, gap 48px antar section |
| Footer | Copyright Yayasan Zivana Insan Mandiri di kiri, Privacy Policy di kanan |
| Kolom kanan | 510px, radius 20px, panel brand merah dengan headline dan kartu pratinjau |

Isi form terdiri dari field email, field password, baris checkbox Ingat Saya dan link Lupa kata sandi, lalu tombol Primary.

**Detail hasil crawling:**
- Judul form: "Selamat datang kembali!" + subjudul "Masukkan email dan kata sandi untuk mengakses akun"
- Field Email: placeholder "Isi email anda"
- Field Kata Sandi: placeholder "Isi kata sandi anda", icon toggle show/hide
- Panel kanan headline: "Dengan Mudah Mengelola Rapor Kelas" + subteks "Lihat agenda pengisian rapor yang sedang berjalan, pantau siapa saja yang belum diisi, dan selesaikan sebelum tenggat." Kartu preview di dalamnya adalah mockup mini-dashboard Portal Guru.

### 5.2 Manajemen Template

Layar ini paling kompleks dan paling berisiko molor kalau tidak dibaca dulu. Isinya panel pratinjau dokumen rapor lengkap dengan placeholder variable seperti `{Nama Siswa}`, `{Kelas Siswa}`, dan `{NISN Siswa}`, plus indikator paginasi yang menunjukkan dokumen rapor terdiri dari 4 halaman.

Konsekuensinya untuk developer, output rapor bukan satu halaman melainkan dokumen multi halaman yang digabung jadi satu PDF per murid per periode. Pastikan arsitektur PDF generation mengasumsikan multi halaman sejak awal, jangan dibangun untuk satu halaman lalu ditambal belakangan.

**Detail struktur halaman 1 (hasil crawling — halaman 2-4 tidak ada di screenshot, ASUMSI strukturnya mengikuti pola form Pengisian Rapor guru):**
- Header: "LAPORAN PERKEMBANGAN TENGAH SEMESTER", "T.P {tahun ajaran}", logo Zivana
- Identitas: Nama Siswa / Kelas / NISN (placeholder)
- Legenda skala penilaian 4 simbol (lihat 3.5)
- Body: tabel per Area (contoh "AREA KETERAMPILAN HIDUP") → sub-kategori berlabel huruf (a. Perawatan Diri, b. Motorik Halus, c. Motorik Kasar, d. Kepedulian Terhadap Lingkungan) → daftar item "Tujuan" dengan kolom skor TS Ganjil / TS Genap
- Watermark logo transparan, footer nama laporan

### 5.3 RBAC

Matriks permission bertingkat. Strukturnya modul, lalu section, lalu permission individual, masing masing dengan checkbox. Kedalaman nesting bisa sampai empat level. Perhatikan bahwa ini adalah layar dengan interaksi state paling banyak, terutama untuk logika parent checkbox yang otomatis tercentang atau tidak ketika anaknya berubah.

**Role fix yang dikonfirmasi dari crawling:** SUPERADMIN, ADMIN, KOORDINATOR GURU, GURU. Tombol "Tambah Role" tetap tersedia di halaman (lihat prd.md poin asumsi soal ini).

### 5.4 Pengisian Rapor (Portal Guru)

**(Baru — layar ini hanya disebut namanya di dokumen asli, detail berikut hasil crawling)**

Header: "Pengisian Rapor" + dropdown "Nama Murid" + peringatan teks "Pastikan tiap penilaian sudah benar sebelum diselesaikan. Penilaian rapor yang telah selesai dan diapprove oleh Kepala Sekolah tidak dapat diubah kembali." + progress bar bertahap. Tombol "Arsip Rapor" (secondary) dan "Selesaikan Rapor" (primary).

Isi form berjenjang: **Kategori besar** (band merah — contoh: "Area Keterampilan Hidup", "Laporan Perkembangan", "Ruang Lingkup Pendidikan Agama Islam", "Bacaan Jilid") → **Sub-kategori/Tujuan** (band oranye) → baris item skill individual dengan satu dropdown skala penilaian per baris. Tiap kategori besar diakhiri textarea "Catatan Guru".

**Perbedaan desktop vs mobile:** layout struktural identik, mobile full-width single column, tombol aksi pindah ke sticky bar bawah (bukan toolbar atas).

---

## 6. Hal yang Perlu Diperhatikan

Bagian ini penting dibaca sebelum mulai koding.

**Nama frame adalah sumber kebenaran.** File Figma sudah dibersihkan, tidak ada lagi frame duplikat atau frame sisa dari proyek lain. Setiap frame yang ada di page UI memang dipakai. Kalau ada dua frame dengan nama layar sama, bedanya ada di dalam kurung dan itu memang dua kondisi berbeda yang dua duanya harus dibuat.

**State yang belum ada di Figma, pakai default saja dulu.** Hover untuk semua elemen interaktif, focus ring untuk input, dan loading state belum dirancang. Untuk pilot ini pakai default yang wajar dari framework atau library yang dipakai, tidak perlu menunggu desain. Nanti disempurnakan setelah pilot jalan. Yang penting state-nya ada dan tidak bikin user bingung, bukan pixel perfect.

**Empty state ada satu contoh, pakai itu sebagai pola.** Layar `Portal Guru -> Dashboard (Agenda Berikutnya Kosong/Empty)` menunjukkan cara menangani kondisi kosong, yaitu teks penjelas satu kalimat yang menyebut siapa yang harus mengisinya, bukan sekadar tulisan "Tidak ada data". Ikuti pola itu untuk empty state di tabel dan blok lain, sesuaikan kalimatnya dengan konteks masing masing. **Teks persis dari crawling:** "Belum ada agenda berikutnya yang ditambahkan oleh admin".

**Input state active sama persis dengan default.** Ini kelemahan desain yang sudah diketahui, bukan salah baca. Untuk sementara tambahkan focus ring default browser atau framework supaya tetap aksesibel. Jangan dihilangkan dengan `outline: none` tanpa pengganti.

**Token modal dan overlay belum konsisten.** Modal dan overlay masih terikat ke koleksi variable lain yaitu `color-neutral/white` dan `color-neutral/800`, bukan koleksi Zivana Color System yang dipakai seluruh komponen lain. Nilai warnanya tetap benar, tapi saat implementasi pakai token dari tabel bagian 1.1 saja supaya seragam.

**Responsive dirancang sebagian.** Semua layar dibuat di 1280px desktop, kecuali satu, yaitu `Portal Guru -> Pengisian Rapor (Mobile)` yang dibuat di 320px. Layar itu jadi acuan bahwa layar guru untuk mengisi rapor memang harus bisa dibuka dari HP. Layar lain belum ada versi mobile, jadi untuk pilot cukup pastikan layar pengisian rapor benar benar jalan di HP, sisanya boleh desktop dulu. Kalau nanti butuh mobile untuk layar lain, itu keputusan bersama, bukan diimprovisasi.

**Aksesibilitas.** Beberapa kombinasi warna perlu dicek kontrasnya saat implementasi, terutama teks `neutral-300` di atas `neutral-75` pada badge status netral. Kalau rasio kontrasnya kurang dari 4.5 banding 1, naikkan ke step yang lebih gelap dan kabari designer.

**[BARU] Copy/placeholder yang kemungkinan bug, bukan disengaja.** Field "URL/Link Media" di tab Kontak & Media (Data Sekolah) placeholder-nya tertulis "Isi alamat email sekolah" — kemungkinan copy-paste error dari field Email Sekolah. Konfirmasi ke designer sebelum implementasi, jangan ditiru apa adanya.

---

## 7. Lampiran — Ringkasan Temuan Crawling per Modul

Bagian ini murni ringkasan navigasi cepat; detail lengkap field/kolom sudah diintegrasikan ke bagian 4.1 (kolom tabel) dan `schema.md` (field form lengkap per entitas).

| Modul | Jumlah layar | Temuan tambahan kunci |
|---|---|---|
| Sekolah | 14 | Struktur dokumen rapor multi-level (Area→Sub-kategori→Tujuan), skala penilaian custom |
| Human Capital | 9 | Guru = karyawan dengan jabatan tertentu, bukan entitas terpisah; jabatan "Guru Shadow" perlu klarifikasi |
| Murid (Manajemen Kelas + Rapor) | 8 | Hapus kelas = set null relasi murid, bukan cascade; rapor accordion 3 level |
| Murid (Manajemen Murid) | 13 | Field lengkap 3 tab terdokumentasi penuh di `schema.md`; field Level Kelas/Kelas di Detail adalah hasil relasi, bukan input |
| Portal Guru | 6 | Form pengisian rapor berjenjang, approval oleh "Kepala Sekolah" disebut eksplisit di teks peringatan |
| Auth + Sistem + Sidebar | 6 | 4 role fix (Superadmin/Admin/Koordinator Guru/Guru); sidebar collapsed lebar 93px |
