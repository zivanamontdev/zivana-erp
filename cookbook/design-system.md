# eRapor Zivana Montessori
> Revisi desain terbaru mengikuti prompt user. Jika aturan di bawah berbeda
> dengan arsip `design-erapor.md`, aturan terbaru di dokumen ini yang dipakai
> saat implementasi.

## Design Specification untuk Developer (Versi Lengkap — hasil crawling)

> Dokumen ini menggabungkan arsip `design-erapor.md`, temuan dari 52 screenshot di `assets/ss/`, dan revisi spesifikasi pengguna. Aturan warna, font, komponen reusable, dan Login di dokumen ini sudah direvisi; bukan salinan persis arsip. Spesifikasi terbaru pengguna didahulukan jika berbeda dengan screenshot lama.

---

# ACUAN DESAIN (arsip dan revisi)

Dokumen ini adalah acuan UI aplikasi eRapor. Ukuran dasar berasal dari Figma
dan revisi pengguna. Aturan responsive pada bagian 1.4 adalah keputusan
implementasi turunan, bukan ukuran tambahan yang diklaim berasal dari Figma.

**File Figma**
- Design System (token dan komponen) ada di page "Design System"
- Semua layar UI ada di page "UI"

**Prinsip utama yang harus dijaga**
1. Semua warna wajib memakai token, bukan hex mentah di dalam kode komponen
2. Semua teks wajib memakai type scale yang sudah didefinisikan
3. Light mode saja. Dark mode tidak ada di scope ini
4. Acuan desktop **1280 × 832px**. Semua halaman wajib mengikuti aturan
   penskalaan, margin fluid, dan reflow di bagian 1.4.

**Aturan implementasi yang berlaku mulai revisi ini**
5. Background halaman selalu memakai token `page-background` (`neutral-50`).
6. Untuk setiap nilai HEX dari prompt/desain, periksa `config/colors.php`
   terlebih dahulu. Jika warnanya sudah ada, gunakan token yang tersedia;
   jika belum ada, tambahkan ke bank warna sebelum implementasi komponen.
   Jangan menulis HEX langsung di view, helper, atau stylesheet komponen.
7. PHP mengambil warna lewat `colorToken('nama-token')`. CSS memakai custom
   property yang dirender dari file yang sama oleh `colorCssVariables()`.
8. Jika prompt menyebut font `Geist`, gunakan font Geist. Jika font tidak
   disebutkan, gunakan Plus Jakarta Sans.
9. Komponen yang berpotensi dipakai lintas halaman harus dibuat sebagai
   variant reusable di `app/helpers/ui.php` dan `public/assets/css/components.css`.

---

## 1. Design Token

### 1.1 Warna

Ada empat keluarga warna utama. Ramp dibangun dengan metode perceptual
lightness, jadi jangan menambah atau mengganti step sendiri pakai fungsi
lighten atau darken biasa. `blue-600` adalah token aksen khusus progress bar;
token semantic Login didokumentasikan setelah ramp warna.

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

**Sumber implementasi warna**

Nilai HEX tidak disalin ulang ke stylesheet. Semua token berada di
`config/colors.php`. `config/config.php` memuat token tersebut sebagai
`COLOR_TOKENS`, sedangkan `layouts/head.php` merendernya menjadi CSS custom
properties melalui `colorCssVariables()`. Untuk PHP gunakan
`colorToken('neutral-50')`; untuk CSS gunakan `var(--color-neutral-50)`.

Token semantic tambahan untuk Login berada di file yang sama, misalnya
`page-background`, `login-heading`, `login-preview-row`, dan
`login-preview-divider`.

### 1.1.1 Token semantic revisi Login

| Token | Pemakaian |
|---|---|
| page-background | Background semua halaman |
| login-heading | Judul "Selamat datang kembali!" |
| login-muted | Footer dan teks pendamping Login |
| login-label | Label field Login, `#656565` (revisi terbaru) |
| login-input-text | Isi field Login |
| login-brand-surface | Background panel kanan Login |
| login-brand-text | Teks di panel kanan Login |
| login-preview-text | Teks mockup dashboard |
| login-preview-row | Background baris data mockup |
| login-preview-divider | Garis pemisah mockup |
| login-preview-success | Aksi "Lihat Rapor" |
| login-callout-surface | Background card agenda aktif |
| login-callout-border | Border card agenda aktif |
| login-input-shadow | Inset shadow field Login, opacity sekitar 4% |

### 1.2 Tipografi

**Typeface default** Plus Jakarta Sans. Hanya dua weight yang dipakai,
Regular (400) dan Bold (700). Ambil dari Google Fonts.

**Typeface override** Jika spesifikasi layar secara eksplisit menulis
`font: Geist`, gunakan Geist untuk elemen tersebut. Class implementasinya
adalah `.font-geist` atau option `font => 'geist'` pada `uiText()`, `uiField()`
dan `uiCheckbox()`. Kedua font dimuat melalui Google Fonts di
`app/views/layouts/head.php`; koneksi internet diperlukan untuk mengunduh font.

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
| Preview | 6.03px | 9.05px | Regular, Bold |
| Preview callout | 6.84px | 10.26px | Regular, Bold |

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
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;700&family=Plus+Jakarta+Sans:wght@400;700&display=swap" rel="stylesheet">
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

### 1.4 Responsive dan skala lintas halaman

**Prinsip:** 1280 × 832px adalah ukuran acuan desain, bukan ukuran canvas
yang dipaksakan di browser. Angka px dalam tabel dokumen ini menyatakan
ukuran pada acuan tersebut, dengan font dasar browser 16px dan zoom 100%.
Tinggi 832px bukan tinggi tetap halaman maupun dasar untuk mengecilkan teks.

Ada tiga aturan yang harus diterapkan bersama:

1. **Skala isi:** teks, ikon, logo, padding komponen, gap, dan lebar elemen
   mengikuti token skala yang sama. Jangan hanya memperbesar section
   sementara seluruh isi tetap memakai ukuran px dari desain.
2. **Batas lebar:** area aplikasi dipusatkan dan mempunyai lebar maksimum.
   Pada monitor sangat lebar, sisa ruang menjadi ruang di luar area aplikasi;
   jangan terus meregangkan card/form sampai isinya terlihat terlalu kecil.
3. **Reflow:** di layar sempit atau saat zoom mempersempit viewport, kurangi
   ruang luar dan susun ulang kolom. Jangan mengecilkan seluruh halaman
   seperti gambar agar muat satu layar.

**Token skala bersama**

Gunakan satu unit desain dengan batas skala 100–125%. Root font tetap
`100%`; gunakan unit ini melalui token komponen, bukan mengganti ukuran
font root menjadi vw murni. Konversi ukuran acuan: `nilai px / 16 × unit`.
Rem saja tidak otomatis membesar ketika viewport membesar; bagian fluid
pada unit inilah yang menyelaraskan ukuran isi dengan ruang desktop.

```css
html { font-size: 100%; }

:root {
  --ui-unit: clamp(1rem, calc(0.5rem + 0.625vw), 1.25rem);
  --layout-max-width: calc(80 * var(--ui-unit));

  /* Margin horizontal mengikuti lebar; vertikal mengikuti tinggi viewport.
     Keduanya sekitar 50px pada acuan 1280 × 832, bukan fixed 40/50px. */
  --page-gutter-inline: clamp(1rem, 3.90625vw, 5rem);
  --page-gutter-block: clamp(1rem, 6.009615svh, 4.5rem);
  --layout-gap: clamp(1rem, 3.90625vw, calc(3.125 * var(--ui-unit)));

  --font-size-body-sm: calc(0.875 * var(--ui-unit));
  --line-height-body-sm: calc(1.3125 * var(--ui-unit));
  --font-size-display-xs: calc(2 * var(--ui-unit));
  --line-height-display-xs: calc(3 * var(--ui-unit));
  --space-2: calc(0.5 * var(--ui-unit));
  --space-6: calc(1.5 * var(--ui-unit));
  --icon-sm: var(--ui-unit);
}

/* Wrapper terluar, bukan padding yang ditambahkan di setiap nested section. */
.page-frame {
  box-sizing: border-box;
  width: min(100%, var(--layout-max-width));
  min-height: 100svh;
  margin-inline: auto;
}

.page-frame--auth {
  padding-inline: var(--page-gutter-inline);
  padding-block: var(--page-gutter-block);
}

.page-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: var(--layout-gap);
}

.page-grid > * { min-width: 0; }

@media (max-width: 56rem) {
  .page-grid { grid-template-columns: minmax(0, 1fr); }
}
```

Kontrak ini diterapkan di `public/assets/css/tokens.css`, `app-shell.css`,
`components.css`, dan stylesheet layar. Login memakai `.login-wrapper`
sebagai padanan frame auth. Seluruh type scale dan spacing shared memakai
`--ui-unit`; jangan menyalin formula skala berbeda ke tiap halaman.
Token warna tetap hanya bersumber dari `config/colors.php`.

Pengecualian untuk halaman bersidebar: `.app-shell` wajib memakai
`width: 100%` dan `margin-inline: 0`, tanpa batas `--layout-max-width`
pada wrapper tersebut. Sidebar harus menempel ke tepi kiri viewport,
baik expanded maupun collapsed. Batas lebar konten, jika diperlukan,
diterapkan di dalam area konten, bukan pada shell beserta sidebarnya.
Frame auth/login tetap mengikuti batas lebar dan gutter di atas.

| Lebar viewport CSS | Unit desain | Teks dasar 14px | Judul dasar 32px | Batas lebar aplikasi |
|---|---|---|---|---|
| 1280px | 16px | 14px | 32px | 1280px |
| 1440px | 17px | 14.875px | 34px | 1360px |
| 1600px | 18px | 15.75px | 36px | 1440px |
| 1920px atau lebih | 20px (batas) | 17.5px | 40px | 1600px |

Tabel mengasumsikan font dasar 16px. Pada viewport di bawah 1280px, ukuran
dasar isi tidak diturunkan di bawah 100%; gunakan reflow. Browser zoom dan
preferensi ukuran font tetap harus dihormati, bukan dikoreksi dengan
`zoom`, `transform: scale()`, atau `maximum-scale=1`. Karena formula
fluid juga merespons viewport, verifikasi pembesaran teks 200% secara
terpisah; jangan menganggap penggunaan rem saja membuktikan aksesibilitas.

**Penerapan per jenis halaman**

- Login/auth: gutter luar horizontal dan vertikal terpisah seperti contoh.
  Rasio dua section mengikuti desain layar; untuk Login gunakan 620:510.
  Padding kiri 105px dan form 410px adalah acuan, bukan lebar fixed.
- App shell: wrapper aplikasi dibatasi dan dipusatkan; sidebar berada di
  dalam wrapper. Jangan menambahkan gutter auth di antara viewport dan
  sidebar. Padding area konten memakai token shared dan mengecil sesuai
  ruang. Lebar sidebar juga mengikuti skala; pada ruang sempit gunakan
  navigasi buka/tutup di bagian atas dengan akses ke semua menu.
  Implementasi beralih pada 56rem; preferensi collapsed desktop disimpan
  terpisah dari kondisi menu mobile. Escape menutup menu mobile.
- Form/card: `width: 100%`, `min-width: 0`, tinggi mengikuti isi,
  label/teks boleh membungkus. Form dua kolom menjadi satu kolom bila
  field sudah terlalu sempit. Jangan mengunci posisi isi dengan koordinat.
- Tabel: lebar mengikuti area konten. Bila kolom tidak bisa dipersempit
  tanpa kehilangan informasi, gunakan scroll horizontal **di wrapper
  tabel**, bukan overflow seluruh halaman.
  Menu aksi tabel diposisikan terhadap viewport supaya tidak terpotong
  wrapper scroll. Menu ditutup saat viewport berubah atau tabel digeser.
- Modal: batasi lebar ke viewport setelah gutter; isi panjang bisa
  di-scroll dan tombol tetap dapat dijangkau.
- Ilustrasi: boleh memakai aspect-ratio dan clipping dekorasi. Kartu agenda
  yang berisi teks harus mengikuti alur dokumen agar tidak menimpa isi saat
  teks membungkus. Ukuran teks mini pada ilustrasi bukan acuan tabel nyata.
- Border tipis dapat tetap 1px; ukuran sentuh/teks tidak boleh diperkecil
  demi mempertahankan rasio gambar. Jangan mengunci tinggi halaman ke
  832px atau memberi `overflow: hidden` pada body.

Stylesheet PDF `rapor-document-pdf.css` memakai ukuran cetak sendiri dan
tidak menerima skala viewport. Pratinjau layar mengikuti skala aplikasi
serta menyediakan scroll lokal untuk tabel rapor yang lebar.

**Pemeriksaan sebelum halaman dianggap responsive**

Periksa viewport 320, 375, 768, 1024, 1280, 1440, 1920, dan 2560px,
termasuk layar pendek dan konten panjang. Di browser, periksa zoom
80%, 100%, 125%, 150%, dan 200% serta pembesaran teks 200%.
Pastikan font sudah dimuat: tidak ada overflow horizontal halaman,
teks terpotong, kontrol yang tertutup, atau section yang terus membesar
sementara teks/ikon tertinggal kecil. Zoom tidak harus mempertahankan
jumlah kolom; perubahan susunan saat ruang menyempit adalah perilaku benar.

## 2. App Shell

Semua layar aplikasi selain Login memakai shell yang sama. Bangun ini sekali sebagai layout component, jangan diulang per halaman.

```
Canvas acuan 1280px (ukuran runtime mengikuti bagian 1.4)
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
| Lebar acuan | **236px** expanded, **92px** collapsed (revisi pengguna); runtime mengikuti `--ui-unit` |
| Background | `red-50` |
| Border kanan | `neutral-100`, 1px |
| Padding | 16px atas, 16px kiri kanan, 32px bawah |
| Gap antar blok | 16px |

**Sidebar Header** berisi logo dan tombol collapse, padding kiri 12px dan
gap 12px. Tinggi header mengikuti tombol. Ikon minimize 20×20px, warna
`neutral-300`; pembungkus transparan, padding 8px, border 1px
`neutral-100`, radius 4px. Total ukuran pembungkus pada acuan adalah
38×38px (20 + 16 + 2), tanpa background hover. Ukuran mengikuti skala UI.

Pada desktop collapsed, logo lengkap dan ikon minimize digantikan oleh
`assets/logo-icon.png` (runtime copy di `public/assets/images/logo-icon.png`),
ukuran acuan **24×24px**, rata tengah horizontal. Logo berada dalam button
toggle yang sama: klik atau Enter/Space memperluas sidebar kembali.
Dalam kondisi ini **header hanya menampilkan logo ikon tersebut**; logo
lengkap dan SVG minimize disembunyikan secara eksplisit melalui atribut
`hidden` yang disinkronkan dengan state sidebar.
Pembungkus logo tanpa border/background/padding;
padding horizontal sidebar **24px** per sisi. Header mobile tetap memakai
logo lengkap dan tombol menu.

Indikator scrollbar disembunyikan pada sidebar dan area navigasinya,
tetapi scrolling tetap aktif melalui wheel, touch, dan navigasi keyboard.

State desktop collapsed dipulihkan dari localStorage lewat script sinkron
di awal head shell, sebelum CSS dan body dirender. Class root
`sidebar-collapsed` menggunakan aturan yang sama dengan `.is-collapsed`,
termasuk ukuran, visibilitas label/logo, dan padding. Interaksi menyinkronkan
keduanya. Transisi lebar baru aktif setelah inisialisasi selesai
(`sidebar-ready`), agar perpindahan halaman tidak berkedip expanded dahulu.
Visibilitas logo lengkap, logo kecil, dan ikon minimize hanya diatur CSS;
Pada desktop collapsed, `.sidebar-brand-logo` dan ikon minimize disembunyikan,
sementara `.sidebar-expand-logo` menjadi satu-satunya logo yang tampil.
Ketiga aturan berada dalam media query desktop yang sama; pada mobile
logo lengkap tetap tampil dan logo kecil tersembunyi.
JavaScript tidak mengganti atribut `hidden` setelah halaman dimuat. Inisialisasi
sidebar menggunakan state root yang sudah dipulihkan di head. Logo kecil
disertakan sebagai data URI dari asset PNG yang sama, dengan ukuran eksplisit
dan decoding sinkron, sehingga tidak menunggu request gambar terpisah.

**Nav Menu** disusun dalam 5 grup yang dipisah garis horizontal. Gap antar item 8px.
Setiap grup menampilkan judul Sekolah, Human Capital, Murid, Portal Guru,
atau Sistem, sesuai menu yang diizinkan bagi pengguna. Judul menggunakan
`uiText(..., 'body-sm', ['weight' => 'regular', 'tone' => 'muted'])`:
Plus Jakarta Sans, 14/21px, weight 400, letter-spacing 0, warna
`neutral-300` (melalui token muted yang sudah tersedia).
Jarak judul ke menu pertama 12px (margin 4px + gap grup 8px).
Judul disembunyikan saat sidebar desktop collapsed, sementara ikon dan
separator tetap tampil. Mode navigasi mobile menampilkan judul saat menu dibuka.

**Nav Item**

| Properti | Nilai |
|---|---|
| Tinggi | 45px |
| Padding horizontal | 12px |
| Gap ikon ke label | 16px |
| Radius | 8px |
| Background aktif | `red-600` |
| Teks dan ikon aktif | `neutral-white`, seperti button primary |
| Background non aktif | transparan |
| Teks non aktif | `neutral-600` |

Menu tunggal aktif (tanpa submenu) tetap **align start** saat expanded
atau navigasi mobile dibuka, dengan ikon/teks rata tengah vertikal.
Pemusatan horizontal sebelumnya tetap berlaku untuk menu induk bersubmenu.
Saat collapsed, ikon semua menu tetap di tengah. Gap ikon
ke teks 12px, radius **8px** (`radius-md`) baik expanded maupun collapsed,
weight 400, ikon 20px, mengikuti skala bersama. Radius button umum tetap 4px;
aturan 8px ini khusus komponen navigasi sidebar.
Link menu tetap elemen navigasi, bukan button submit.
Saat collapsed, semua ikon menu dan tombol expand dipusatkan di ruang
sidebar 92px. Pada layar sempit navigasi tetap full width dengan pola
buka/tutup; ukuran 236/92 berlaku untuk sidebar desktop.

Hover state belum dirancang di Figma. Saran implementasi, pakai `neutral-75` sebagai background hover untuk item non aktif. Konfirmasi dulu ke designer sebelum difinalkan.

**Submenu (revisi terbaru):** menu induk (mis. Kurikulum/Karyawan) ditandai
card merah primary jika salah satu child merupakan halaman aktif, termasuk
saat sidebar collapsed. Membuka grup lain saja tidak menandainya sebagai
halaman terpilih. Indent kiri container submenu kini **15px** (berkurang
5px lagi dari 20px), sehingga garis dan isi submenu bergeser bersama. Padding kiri
internal submenu tetap **4px**. Semua ukuran mengikuti skala UI.
Garis vertikal mempunyai cabang melengkung pada setiap item menggunakan
bentuk dari `assets/curved-submenu.svg`, ukuran acuan 10×8px.
Jarak tepi kanan kotak lengkungan ke teks submenu **10px**. Dengan posisi
lengkungan -4px dari item dan lebarnya 10px, padding kiri item menjadi
16px (10 + 10 - 4); angka mengikuti `--ui-unit`.
Runtime asset `public/assets/icons/curved-submenu.svg` memakai currentColor;
warna garis/cabang berasal dari token `sidebar-connector` di colors.php,
mengikuti warna sumber #B4B4B4. Garis berakhir pada lengkungan item terakhir,
bukan memanjang sampai melewati item terakhir.
Semua state submenu tetap **align start**, tanpa card/background.
Child nonaktif berwarna netral; saat hover teks memakai `red-400`
(merah muda). Saat ditekan atau selected, teks memakai `red-600`;
selected tetap merah meskipun sedang di-hover.
Gunakan token dari `config/colors.php` untuk semua warna sidebar,
termasuk judul grup, ikon, border, dan state aktif; jangan menulis HEX di view/CSS.

Chevron submenu berada dalam kotak **20×20px** tanpa ruang baseline teks.
Rotasi dilakukan pada SVG dengan `transform-box: view-box` dan
`transform-origin: 50% 50%`: poros di tengah kotak ikon, bukan ujung
chevron atau pembungkus teks. Saat grup terbuka, SVG berputar 180 derajat.

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

Ada tiga baris header yang bertumpuk. Padding horizontal 24px. Toolbar dan
breadcrumb memakai padding vertikal 12px; baris judul memakai 15.5px di atas
dan bawah (jarak bawaan ke konten berikutnya, tanpa padding atas konten tambahan).
Semua ukuran mengikuti `--ui-unit`.

| Baris | Tinggi | Isi |
|---|---|---|
| Toolbar | 61px | Search field lebar 167px di kiri, info user dan action icon di kanan |
| Breadcrumb | 45px | Path lokasi halaman |
| Judul | Mengikuti isi + 31px | Judul di kiri, filter dan aksi di kanan, align center vertikal |

Judul menu menggunakan Plus Jakarta Sans 20px/30px, bold 700, letter-spacing 0,
warna token `page-title` dari `config/colors.php`. Dropdown pengganti judul
seperti pemilih murid mewarisi style yang sama. Filter dan aksi halaman wajib
dirender melalui `$headerActions`, gap 4px, tanpa margin vertikal tombol.
Pada layar sempit boleh membungkus agar tidak overflow; aksi tetap rata kanan.
Jangan menambahkan toolbar filter halaman terpisah di bawah judul.

Breadcrumb memiliki divider `neutral-100` fullwidth di bawah barisnya.
Nama menu induk: 14px/21px, regular 400, warna `neutral-100`.
Gap induk ke chevron kanan 16px dan chevron ke submenu 16px.
Nama submenu: 14px/12px, regular 400, warna `page-title`.
Pratinjau Manajemen Rapor memakai urutan `Kurikulum > Manajemen Rapor > Pratinjau`,
dengan satu ikon tooltip setelah Pratinjau. Helper `breadcrumb()` menerima
segmen tambahan; hanya segmen terakhir memakai style current.
Ikon tooltip 16×16px, gap 8px dari submenu, align center vertikal.

Data Sekolah: dropdown custom outline berlabel `Tahun Ajaran berjalan: {tahun}`
memakai tahun aktif dari database; sebelah kanannya tombol outline
`Ubah Data Sekolah` dengan gap 4px. Dropdown menampilkan tahun aktif dan
aksi pembaruan tahun bagi pengguna dengan izin edit. Data sekolah bersifat
singleton, bukan arsip per tahun; dropdown tidak memfilter data historis.

Dropdown filter custom menggunakan `data-dropdown-match-trigger` pada
wrapper `data-action-menu`: lebar border-box overlay selalu sama dengan
lebar aktual tombol pemicu, diukur ketika dibuka (termasuk setelah resize).
Isi panjang membungkus; dropdown aksi biasa tidak ikut berubah lebarnya.

Standarisasi implementasi seluruh halaman:
- Tombol aksi menggunakan `uiButton()` atau markup `.ui-button` dengan
  `.ui-button--primary` / `.ui-button--outline`. Link navigasi tetap `<a>`;
  submit, atribut `form`, `formaction`, izin akses, dan trigger modal dipertahankan.
- Filter daftar menggunakan `uiFilter()`, termasuk value aktif, label aksesibel,
  nama parameter GET, dan perilaku submit sebelumnya. Native select tetap
  digunakan untuk filter daftar; outline dan ukuran berasal dari komponen UI.
- Search menggunakan `uiField()` bertipe search, ikon `icon_search` di kiri,
  gap 12px, warna `neutral-150`, dan label yang disembunyikan secara visual.
  Search daftar (`form.list-filter`, parameter `q`) tidak memakai tombol Cari.
  Submit GET otomatis setelah jeda mengetik 400ms melalui `list-search.js`,
  termasuk saat teks dikosongkan. Seluruh filter aktif tetap dikirim bersama.
  Enter tetap dapat digunakan; komposisi IME ditunggu sampai selesai.
  Tanpa JavaScript pengguna dapat menekan Enter untuk mencari.
- Kontrol khusus seperti tab, accordion, pilihan nilai rapor, dan tombol
  item dropdown tetap mengikuti komponen khususnya, bukan tombol aksi umum.
- Hindari class legacy `.btn`, `.btn-primary`, dan `.btn-tertiary` pada halaman baru.

Kelompok tombol memakai `.ui-actions` (atau wrapper khusus seperti
`.modal-actions`): flex horizontal, align center vertikal, justify end,
gap 12px, margin vertikal tombol 0. Header tetap memakai gap 4px.
Tombol tidak dipaksa fullwidth/kolom; baris membungkus hanya saat ruang habis.
Filter pada layar sempit berbagi ruang secara fleksibel, bukan masing-masing
100% lebar. Bar aksi sticky rapor mengikuti pola horizontal yang sama.

Background header `neutral-50`. Divider `neutral-100` hanya berada tepat di
bawah toolbar search + profil, bukan di bawah judul. Toolbar memakai padding
horizontal 24px dan vertikal 12px (mengikuti `--ui-unit`), termasuk pada mobile.
Baris breadcrumb hanya muncul di halaman yang punya parent, misalnya submenu Kurikulum dan Karyawan.

Search pada seluruh halaman shell memakai `uiField()` dengan `type => 'search'`,
`icon => 'icon_search'`, `iconPosition => 'left'`, dan `hideLabel => true`.
Label tetap tersedia bagi pembaca layar. Posisi dan lebar acuan search 167px
dipertahankan; tinggi toolbar mengikuti isi dan padding bila konten membungkus.

**Catatan hasil crawling:** halaman top-level seperti Data Sekolah, Manajemen Guru, Manajemen Murid, Manajemen Kelas, Dashboard Portal Guru **tidak** menampilkan baris breadcrumb (langsung toolbar → judul). Breadcrumb baru muncul di halaman yang benar-benar 2 level ke bawah (mis. "Kurikulum > Manajemen Template", "Karyawan > Daftar Karyawan", "Manajemen Murid > Detail Murid", "Rapor Murid > Pratinjau Rapor Murid").

### 2.4 Area Konten

Periode Rapor: form urut Nama, Semester (Ganjil/Genap), Tipe (Tengah/Akhir),
Awal, Akhir. Tipe dinonaktifkan sampai Semester dipilih. Semester dan tipe
independen; satu tahun ajaran maksimal empat kombinasi unik. Tahun ajaran
periode tidak berubah ketika diedit. Periode yang sudah mempunyai rapor tidak
bisa diubah semester/tipe-nya; periode lama dengan semester NULL harus
diklasifikasikan eksplisit dan tidak boleh bertentangan dengan nilai tersimpan.
Migrasi `20260923_report_semester.sql` menambahkan kolom tanpa menebak semester.

Membuat periode otomatis membuat sesi dan rapor kosong untuk murid Bersekolah
pada kelas di tahun ajaran terkait, dengan guru dari penugasan murid. Tidak ada
lagi tombol/endpoint Tambah Sesi pada Rapor Murid. Halaman daftar hanya memiliki
filter Tahun Ajaran (default tahun aktif), menampilkan breakdown periode/sesi
yang sudah ada. Data sesi historis tidak digabung atau dihapus otomatis.
Status Belum diisi tidak memiliki tautan pratinjau; endpoint preview dan PDF admin
juga menolak status tersebut. Menunggu Persetujuan membuka pratinjau dengan
button Setujui primary untuk Kepala Sekolah/Admin berizin edit (serta akun
Superadmin sistem tanpa jabatan). Guru tidak mendapat akses modul Rapor Murid;
pengisian tetap melalui Portal Guru dengan pemeriksaan kepemilikan.

Pratinjau rapor asli memakai komponen template-preview/paper: pembungkus abu-abu
dari bank warna, scroll horizontal tanpa indikator, tabel dua kolom, logo ikon
sebagai watermark. Jumlah lembar mengikuti data asli, tidak dipaksa empat atau
menyalin fixture. Nama/NISN/kelas, tahun dan tipe berasal dari rapor; nilai tidak
dibuat-buat. Simpan PDF outline, refresh outline icon-only, Setujui primary.
Template tanpa item tidak dapat diajukan untuk persetujuan; semua nilai template
harus lengkap. Semester nilai diambil dari periode, bukan dari tipe Tengah/Akhir.

`uiFilter()` memakai dropdown custom global (ui-select.js), dengan trigger
button outline, font Plus Jakarta Sans, ikon kanan 20px. Overlay selebar
trigger, mengikuti tema opsi dropdown aplikasi. Native select tetap menjadi
sumber nilai GET dan fallback tanpa JavaScript; onchange/auto-submit tetap
berfungsi, termasuk kombinasi pencarian, Jabatan dan Status pada Karyawan.

Jabatan dibatasi ke Kepala Sekolah, Admin, Guru Kelas, Guru Shadow melalui
dropdown dan validasi backend (`Jabatan::NAMES`). Nama duplikat ditolak.
Jabatan yang masih digunakan karyawan, termasuk karyawan nonaktif, tidak boleh
dihapus; FK tetap menjadi pengaman terakhir terhadap perubahan bersamaan.
Manajemen Guru menampilkan semua karyawan aktif berjabatan Guru Kelas/Guru Shadow
berdasarkan nama jabatan, bukan role RBAC. Setiap guru mendapat kartu/list sendiri
termasuk jika belum ada murid. Filter jabatan hanya menawarkan dua jabatan guru;
aturan yang sama berlaku pada pilihan guru di Detail Kelas dan validasi penugasan.
Data jabatan lama di luar empat nama tidak dihapus atau dialihkan otomatis jika
masih dipakai karyawan; diperlukan pilihan pemindahan dari pemilik data.

Relasi Murid/Kelas/Guru: form Tambah/Ubah Murid menampilkan Kelas terlebih
dahulu, kemudian Level Kelas pada tab Informasi Pendaftaran. Pengguna memilih
nama kelas; level otomatis terisi dan dropdown level hanya menawarkan level
milik kelas tersebut. Mengganti/mengosongkan kelas memperbarui/mengosongkan level.
kelas_id adalah sumber kebenaran, level tidak disimpan ulang pada murid.
Murid boleh sementara tanpa guru. Seorang guru dapat mengampu murid lintas kelas,
tetapi setiap murid maksimal satu guru. Dropdown penugasan menampilkan nama murid
beserta kelas, menyembunyikan murid milik guru lain, dan backend memvalidasi ulang.
Penugasan guru lain harus dilepas terlebih dahulu; konflik tidak mengubah data.
Perubahan kelas murid mempertahankan guru dan menyinkronkan kelas penugasan.
Penghapusan kelas membuat murid tanpa kelas dan melepas penugasan di kelas itu.

Database baru memakai UNIQUE(murid_id) di kelas_guru_murid. Database existing
memerlukan `database/migrations/20260923_single_teacher.sql`; jalankan audit
duplikat terlebih dahulu dan selesaikan pilihan guru secara manual jika ada.
Migrasi tidak memilih/menghapus data duplikat otomatis. Aplikasi memakai transaksi
dan row lock murid untuk menghindari penugasan ganda melalui kedua alur UI.

Detail Kelas: header memakai button outline Ubah Data Kelas yang membuka modal
form kelas global dan kembali ke detail setelah disimpan. Level/Nama Kelas
memakai uiDataCard dua kolom (gap horizontal 24px). Daftar Guru & Murid memakai
guru-murid-card dengan padding baris 16px horizontal/12px vertikal, jumlah murid
berupa uiText Geist body-sm tanpa badge, nama guru regular, baris murid memiliki
chevron kanan 20px dan tautan ke detail murid. Tidak ada tombol/modal Tambah Guru:
daftar otomatis berasal dari guru yang memiliki penugasan murid di kelas tersebut.
Modal Atur Anak Murid menggunakan partial global `components/student-assignment-modal.php`
bersama Manajemen Guru: dropdown compact, X padding 8px, uiModal assignment.
Di Manajemen Guru, chevron murid juga menuju detail murid terkait.
Semua isi berasal dari data kelas,
bukan nama atau jumlah contoh di screenshot.

Daftar Kelas memakai tabel global dengan margin atas 16px, nama/level berupa teks,
aksi Lihat Detail untuk pengguna berizin lihat, Ubah/Hapus untuk izin edit.
Tambah Kelas memakai button primary dengan ikon plus di kanan. Modal tambah/ubah
memakai uiModal form (padding 24px, kontrol 400px, gap 20px), uiSelect Level Kelas
dan uiField Nama Kelas (Geist). Pilihan level hanya Akar, Batang, Ranting, Daun,
bersumber dari Kelas::LEVELS dan divalidasi server-side. Nama Kelas bebas diisi.
Kedua field wajib; submit disabled sampai valid, primary untuk tambah dan outline
untuk ubah. Modal hapus memakai uiModal delete, Batal outline dan Hapus Kelas
outline-danger; data murid/guru tidak ikut dihapus.

Breadcrumb: setiap level induk adalah tautan kembali ke route terkait;
level terakhir menandai halaman aktif (`aria-current="page"`). Helper
`breadcrumb()` mendukung segmen `[label, path]` untuk tujuan spesifik, termasuk
Portal Guru agar tidak menuju route admin. Grup Kurikulum yang tidak memiliki
halaman sendiri menuju Manajemen Rapor. Warna dan tipografi tetap mengikuti
komponen breadcrumb, dengan hover/focus dari token warna.
Daftar Murid: Kondisi ditampilkan Regular/ABK, Nama mendapat porsi utama;
urutan akhir kolom adalah Kondisi, Guru Kelas, Status, aksi. Menu Lihat Detail
tersedia bagi pengguna dengan izin lihat, sedangkan Ubah memerlukan izin edit.
Pembungkus tabel memiliki margin atas 16px.

Field disabled menggunakan `uiField(..., ['disabled' => true])`: border 1px
`neutral-150`, background `neutral-75`, teks `neutral-600`. Radius, padding,
tipografi dan inner shadow mengikuti varian form asal, tanpa opacity pudar.
Umur pada Tambah/Ubah Murid adalah field disabled yang menghitung usia dalam
tahun penuh dari Tanggal Lahir, diperbarui saat input berubah termasuk melalui
datepicker. Nilai hanya angka (tanpa "tahun"), kosong untuk tanggal kosong/tidak
valid/masa depan. Nilai turunan ini tidak dikirim atau disimpan ke database.

Tambah/Ubah/Detail Murid berbagi satu view: mode input memakai `uiField`
variant form (Geist), `uiSelect`, datepicker custom dan button tabular
active/inactive (gap 12px). Field number/tel mempertahankan tipe inputnya;
label wajib diberi asterisk, error server dan nilai input lama tetap ditampilkan.
Validasi membuka tab field wajib pertama yang belum valid.
Mode detail memakai `uiDataCard` yang sama dengan Detail Sekolah di semua tab,
bukan input disabled: border 1px neutral-75, radius 8px, padding 12px, gap
label/nilai 8px, label Geist 12px/18px dan nilai Geist 14px/21px. Grid dua kolom
memakai gap horizontal 24px dan vertikal 20px, turun ke satu kolom di layar kecil.
Alamat fullwidth; field kosong ditampilkan sebagai tanda strip.

Manajemen Guru: header dan baris murid memakai padding 16px horizontal/12px
vertikal. Jumlah murid menggunakan uiText body-sm, font Geist, weight regular,
tone preview (bank warna login-preview-text), tanpa badge. Nama murid di kiri,
level dan nama kelas di kanan menggunakan gaya body-sm yang sama, diikuti
chevron kanan 20px dengan gap 12px.

Modal Atur Anak Murid memakai uiModal variant assignment: lebar 680px responsif,
padding 24px dan jarak konten 20px. Panel guru memakai orange-50, dropdown
memakai uiSelect dengan hideLabel (label tetap tersedia bagi pembaca layar).
Tambah Murid dan hapus baris X memakai button outline, Batal outline, Simpan
primary. Baris baru memakai placeholder kosong, bukan memilih murid pertama.
Ikon tiga titik pada Manajemen Guru langsung membuka modal Atur Anak Murid
untuk guru terkait, tanpa dropdown perantara karena hanya memiliki satu aksi.
Dropdown murid menggunakan `uiSelect(..., ['variant' => 'compact'])`: padding
vertikal 8px dan horizontal 12px, dengan tipografi form tetap 14px/21px.
Varian berlaku pada native fallback maupun trigger dropdown custom, termasuk
baris yang ditambahkan. Varian default tidak berubah. Tombol X memakai icon-only
outline dengan padding 8px di semua sisi, ikon 20px, tanpa ukuran paksa 47px.

Jabatan mengikuti komponen Karyawan: Tambah Jabatan dengan `icon_plus` kanan,
status teks `status-active`/`status-inactive`, modal `uiModal()` (400px field,
padding 24px, gap 20px), `uiField()` Nama Jabatan dan `uiSelect()` Role Sistem.
Keduanya required; submit menggunakan `data-complete-form`, primary untuk
tambah dan outline untuk ubah. Menu status menawarkan Nonaktifkan untuk
jabatan aktif dan Aktifkan untuk jabatan nonaktif, masing-masing dikonfirmasi
melalui modal 440px. Perubahan status tidak mengubah akun/penugasan karyawan.
Jabatan nonaktif tidak tersedia pada pilihan penugasan baru. Hapus Jabatan
menghapus data secara permanen setelah konfirmasi, bukan mengubah status.
Jika masih digunakan karyawan, penghapusan ditolak dengan pesan; status jabatan
tetap sama. Pindahkan penugasan terlebih dahulu atau gunakan Nonaktifkan.

Status teks tanpa badge menggunakan `uiText()` variant `body-sm`, weight
`regular` (14px/21px, 400). Tone `status-active` memakai `green-600` dan
`status-inactive` memakai `red-600`, keduanya dari bank warna. Kolom Status
Daftar Karyawan menampilkan `Aktif` / `Nonaktif` dengan tone tersebut,
tanpa background, border, atau padding badge. Tone `success` yang sudah ada
tetap dipertahankan untuk penggunaan sebelumnya dengan warna berbeda.

**Komponen modal bersama**

Standar ini diimplementasikan oleh `uiModal(id, title, content, options)`
di helper UI dan `.ui-modal` di `components.css`, dipakai bersama oleh
Karyawan dan Periode Rapor. `content` adalah HTML form terkontrol dari view;
judul dan deskripsi di-escape. Opsi `variant => 'delete'` memakai ukuran
440px, `description` menambahkan teks deskripsi dan relasi aksesibilitas.
Variant default `form` memiliki lebar kontrol 400px + padding 24px per sisi.

Karyawan: tombol Tambah Karyawan memakai `icon_plus` di kanan. Modal
tambah memiliki Nama Karyawan, Jabatan, Email Karyawan, Kata Sandi Karyawan,
Ulangi Kata Sandi Karyawan; semua berasterisk dan required. Modal Ubah Karyawan
hanya memiliki nama, jabatan, email; tidak mengubah password. Modal terpisah
Ubah Kata Sandi hanya memiliki password dan konfirmasinya.
Password tidak pernah diisi dari database, minimal 8 karakter, satu huruf
besar A–Z, satu angka, satu simbol, dan konfirmasi harus sama. Field berwarna
merah dengan pesan persyaratan sampai valid. Kebijakan identik diterapkan di
client dan server untuk tambah/ubah sandi. Toggle mata memakai komponen password bersama. `data-complete-form`
dan `complete-form.js` mengatur disabled native beserta variant `disabled`
sampai field lengkap dan valid, lalu memulihkan primary (tambah) atau outline
(edit/password). Validasi juga dilakukan server-side; perubahan sandi menyimpan
hash baru dan menghapus remember token lama.

Menu Hapus dan Nonaktifkan terpisah, masing-masing memiliki konfirmasi.
Hapus menjalankan penghapusan akun login terkait dan karyawan dalam satu
transaksi; relasi mengikuti FK (rapor tetap disimpan, referensi guru/penyetuju
dapat menjadi null, penugasan guru terkait terhapus). Nonaktifkan mempertahankan
data, mengubah status akun/karyawan menjadi Nonaktif dan menghapus remember token.
Middleware memeriksa akun aktif pada setiap request sehingga akun terhapus atau
nonaktif tidak dapat melanjutkan sesi lama. Penonaktifan hanya ditawarkan untuk
karyawan aktif; karyawan Nonaktif mendapat aksi Aktifkan. Judul, deskripsi,
dan tombol konfirmasi mengikuti aksi tersebut. Aktivasi memperbarui status
karyawan dan akun login dalam satu transaksi; konfirmasi aktivasi memakai primary,
penonaktifan memakai outline-danger. Tidak ada perubahan data saat hanya membuka
atau membatalkan modal.

Ukuran modal Periode Rapor: padding 24px, lebar kontrol
400px (modal 448px), dibatasi viewport pada layar sempit. Empat field
vertikal: Nama Periode Penilaian, Tipe Periode (Tengah/Akhir Semester),
Awal Periode, Akhir Periode. Gap field 20px. Judul 16px/24px bold 700,
warna `modal-text`, margin bawah 20px; gap field terakhir ke aksi 20px.
Tambah: Batal outline + Tambah Periode primary. Edit: Batal outline +
Simpan Perubahan outline. Kategori tersembunyi dipertahankan saat edit;
data baru memakai Rapor Murid. Semua ukuran mengikuti skala UI.

Modal hapus: lebar 440px, padding 24px, gap vertikal 20px. Deskripsi
12px/18px regular, `modal-text`. Judul/deskripsi rata kiri; tombol rata kanan,
gap 12px. Batal outline; Hapus Periode `outline-danger` (outline transparan,
border `neutral-100`, teks/ikon `red-600`). Variant ini tersedia di `uiButton()`.

`uiField()` type date mendukung `icon => 'icon_calendar', iconCalendar => true`:
ikon kanan membuka datepicker custom dari `ui-datepicker.js`. Mendukung
navigasi bulan, keyboard, Escape, klik luar, dan penempatan menyesuaikan
viewport. Nilai POST tetap ISO YYYY-MM-DD dari input date, bukan teks tanggal
terformat. Input tetap dapat digunakan tanpa JavaScript.
Judul bulan/tahun datepicker dapat diklik untuk memilih tahun (12 tahun per
rentang; panah berpindah rentang), kemudian menampilkan Januari–Desember
pada tahun pilihan. Pilih bulan untuk kembali ke tanggal. Nilai form baru
berubah setelah tanggal dipilih; pemilihan tahun/bulan tidak menutup popup.

Manajemen Rapor (route tetap `/kurikulum/manajemen-template`) menampilkan
dua section: Rapor Akhir Semester dan Rapor Tengah Semester. Margin atas
section pertama 16px, jarak judul section ke tabel 12px, tabel ke judul
section berikutnya 20px. Kolom: Urutan Tahapan, Template, Terakhir Diperbarui,
serta ikon aksi. Judul section memakai weight 400 (regular).
Header tabel 14px/21px weight 700 (bold), warna `report-table-heading`; isi template teks biasa
`report-template-text`. Ikon more vertical memakai `shadow-black`.
Tanggal berformat `27 Juli 2026, 13:32`. Lima baris sementara sesuai desain
berada di `_preview-data.php`, tanpa menulis database atau mengarang ID.
Search otomatis memfilter nama di kedua section; fixture diasumsikan
System/kategori Rapor Murid. Nama sidebar: Manajemen Rapor dan Periode Rapor;
route dan permission key lama dipertahankan agar akses tidak berubah.
Sementara semua ikon more pada section Akhir/Tengah Semester langsung membuka
template database `Rapor Montessori Akhir Semester` / `Rapor Montessori Tengah Semester`.
Route pratinjau memakai nama semester yang diizinkan dan mencari ID dari database,
bukan mengasumsikan ID fixture. Judul halaman `Pratinjau {nama template}`;
breadcrumb tetap `Kurikulum > Manajemen Rapor > Pratinjau`.

**Pola kartu data baca (`uiDataCard(label, value, options)`)**

Mode edit Data Sekolah menggunakan `uiField()` variant `form`, font Geist:
label 12px/18px `login-label`, isi 14px/21px `neutral-900`, label gap 4px,
padding kontrol 8px horizontal/12px vertikal, radius 4px, border `neutral-75`.
Textarea memakai style yang sama dengan tinggi minimum lebih besar.
Dropdown menggunakan `uiSelect(name, label, choices, options)` dengan nilai,
error, disabled, required, dan id opsional. Native select menjadi fallback
tanpa JavaScript dan sumber nilai POST; `ui-select.js` menambahkan overlay
custom selebar pemicu, dukungan panah/Home/End/Enter/Escape/Tab, dan status
selected aksesibel. Dropdown baru pada baris dinamis otomatis diinisialisasi.
ID dan referensi label di template baris diganti saat clone.
Ornamen baris media edit: lebar 4px `neutral-75`, gap kiri ke kontrol 16px,
mulai setelah label + gap (22px), tinggi kontrol 47px pada skala dasar;
tidak memanjang sepanjang label atau keseluruhan baris.

Gunakan kartu data, bukan input disabled/readonly, untuk halaman detail.
Kartu transparan, border 1px `neutral-75`, radius 8px, padding 12px,
gap label–nilai 8px. Label Geist regular 12px/18px, warna `login-label`
(token yang sudah tersedia); nilai Geist regular 14px/21px, `neutral-900`.
Letter-spacing 0. Nilai di-escape, teks panjang membungkus, nilai kosong `-`.
Opsi `externalLink => true` menambahkan `icon_external_link` 20×20px pada
kartu URL: inset kanan 12px, center vertikal terhadap kartu. Ruang teks
disisihkan agar tidak menimpa ikon. Hanya URL HTTP/HTTPS valid yang aktif,
membuka tab baru dengan `target="_blank"` dan `rel="noopener noreferrer"`.

Data Sekolah mode lihat memakai partial `_details.php`; mode edit tetap form.
Tab memakai `uiButton()` variant `tabular-active`/`tabular-inactive`, gap 12px,
dengan margin atas wrapper tab Data Sekolah 16px.
Informasi Umum: grid 2 kolom, gap horizontal 24px dan vertikal 20px,
urutan nama legal, nama komersial, bentuk pendidikan, NPSN, alamat.
Alamat membentang penuh. Kode TK ditampilkan sebagai Taman Kanak-Kanak (TK).
Kontak: telepon dan email pada grid 2 kolom yang sama. Setiap data media
membentuk baris Jenis Media, Nama Akun, URL / Link Media: dua kartu pertama
berbagi bagian kiri; kartu URL selebar kolom kanan pada baris kontak.
Ornamen kiri selebar 4px `neutral-75`, gap ke kartu 16px, setinggi baris.
Pada layar sempit grid membungkus menjadi satu kolom. Data berasal dari
database; contoh desain tidak menimpa data tersimpan, dan semua media tetap
ditampilkan (bukan dibatasi satu baris).

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

`uiSelect()` mengikuti font default Plus Jakarta Sans. Gunakan opsi `font => 'geist'`
hanya bila desain secara eksplisit meminta Geist; trigger dropdown custom dan
native select fallback harus memakai font yang sama.

### 3.2 Buttons dan dropdown filter

Spesifikasi terbaru memakai `uiButton(label, variant, options)` di
`app/helpers/ui.php` dan class `.ui-button` pada `components.css`.
Default font Plus Jakarta Sans. Nilai berikut adalah ukuran acuan;
runtime mengikuti `--ui-unit` (bagian 1.4).

| Properti | Nilai |
|---|---|
| Margin vertikal (luar) | 8px, dapat diubah lewat `marginVertical` |
| Padding vertikal (dalam) | 8px, dapat diubah lewat `paddingVertical` |
| Padding horizontal | 12px default, dapat diubah lewat `paddingHorizontal` |
| Teks | 14px / line-height 21px / weight 400, tengah |
| Radius | 4px |
| Ikon | 20 × 20px, warna mengikuti teks melalui currentColor |
| Gap ikon dan teks | 12px |
| Full width | Lebar 100%; kelompok ikon/teks tetap di tengah |
| Tinggi | Auto; minimum 21px + 2 × 8px padding + 2 × 1px border = 39px |

**Interpretasi margin:** “margin vertical 8px” diterapkan sebagai margin luar.
Padding vertikal 8px adalah asumsi untuk memberi ruang isi dan menyamakan
tinggi button/filter. Keduanya dapat diubah terpisah. Dalam toolbar yang
sudah mempunyai gap, gunakan `marginVertical => 0` bila tidak ingin tambahan
ruang vertikal. Primary tetap memakai border transparan 1px agar tingginya
sama dengan outline; tidak ada garis border yang terlihat.

| Variant | Background | Teks dan ikon | Border 1px |
|---|---|---|---|
| `primary` | red-600 | neutral-white | transparan |
| `outline` | transparan | button-outline-text | neutral-100 |
| `disabled` | neutral-75 | neutral-150 | neutral-100 |
| `tabular-active` | red-50 | red-600 | red-400 |
| `tabular-inactive` | transparan | button-tab-inactive-text | button-tab-inactive-border |

Warna tambahan hanya didefinisikan di `config/colors.php`:
`button-outline-text` = #272727, `button-tab-inactive-text` = #8B8B8B,
`button-tab-inactive-border` = #DDDDDD. Warna lain memakai token yang
sudah tersedia. Focus keyboard mempunyai outline; hover belum diberi
warna baru karena belum dispesifikasikan.

| Option | Default / perilaku |
|---|---|
| `type` | `button`; dapat `submit` atau `reset` |
| `icon` | Tanpa ikon; isi nama asset tanpa ekstensi, mis. `icon_search` |
| `iconPosition` | `left` atau `right`; default kiri |
| `iconOnly` | false; jika true label menjadi aria-label, ikon wajib ada |
| `fullWidth` | false; berlaku juga pada button ikon saja |
| `disabled` | false; true menonaktifkan button dan menampilkan variant disabled |
| `paddingHorizontal` | 12; angka px acuan, otomatis mengikuti skala |
| `paddingVertical` | 8; angka px acuan |
| `marginVertical` | 8; angka px acuan |
| `class` / `attributes` | Class tambahan / atribut seperti id, name, value, data-* |

Variant `disabled` selalu menghasilkan atribut native `disabled`, bukan
sekadar warna. Variant tabular mengisi `aria-pressed` sesuai active/inactive;
tidak otomatis membuat sistem tabs atau berpindah panel. Pengendali halaman
mengatur aksi dan state berikutnya. Semua label dan nilai atribut di-escape.

```php
<?= uiButton('Simpan', 'primary', ['type' => 'submit']) ?>
<?= uiButton('Cari', 'outline', ['icon' => 'icon_search']) ?>
<?= uiButton('Berikutnya', 'outline', [
    'icon' => 'icon_external_link', 'iconPosition' => 'right',
]) ?>
<?= uiButton('Hapus', 'outline', ['icon' => 'icon_trash', 'iconOnly' => true]) ?>
<?= uiButton('Tidak tersedia', 'disabled') ?>
<?= uiButton('Informasi', 'tabular-active') ?>
<?= uiButton('Kontak', 'tabular-inactive') ?>
<?= uiButton('Lanjutkan', 'primary', [
    'fullWidth' => true, 'paddingHorizontal' => 24, 'marginVertical' => 0,
]) ?>
```

**Dropdown filter**

Gunakan `uiFilter(name, label, choices, options)` yang merender native
`<select>`. `choices` berupa pasangan value => label. Label dipakai sebagai
nama aksesibel. Options: `value`, `id`, `disabled`, `fullWidth`, ketiga opsi
spacing di atas, `class`, dan `attributes` (misalnya `required` atau `form`).
Ukuran font, border, margin, padding vertikal, dan tinggi mengikuti button;
warna mengikuti outline. Chevron 20px berada di kanan dengan ruang khusus.
Dropdown dapat dipakai dengan keyboard tanpa JavaScript tambahan.

```php
<form method="get" class="list-filter">
    <?= uiFilter('status', 'Filter status', [
        '' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif',
    ], ['value' => $status ?? '', 'marginVertical' => 0]) ?>
    <?= uiButton('Terapkan', 'outline', [
        'type' => 'submit', 'icon' => 'icon_search', 'marginVertical' => 0,
    ]) ?>
</form>
```

Filter tidak melakukan auto-submit. Pemanggil memakai tombol submit atau
handler sendiri. Komponen baru siap digunakan; button lama `.btn` tetap
kompatibel dan tidak dimigrasikan massal dalam perubahan komponen ini.
Button Login juga tidak otomatis ditambahkan oleh helper.

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

### 3.6 Komponen UI reusable

Komponen server-rendered disediakan melalui `app/helpers/ui.php`, dengan
style variant di `public/assets/css/components.css`. Tujuannya supaya view
tidak mengulang markup dan class yang sama di setiap halaman.

| Komponen | API | Variant awal |
|---|---|---|
| Button | `uiButton(label, variant, options)` | primary, outline, disabled, tabular-active, tabular-inactive; icon kiri/kanan/only; full width |
| Dropdown filter | `uiFilter(name, label, choices, options)` | native select, outline, disabled, full width; dimensi mengikuti button |
| Text | `uiText(teks, variant, options)` | type scale, regular/bold, base/Geist, tone, alignment |
| Field | `uiField(nama, label, options)` | text, email, password, date, search, textarea; state default/active/filled/viewonly/negative; variant default/login |
| Checkbox | `uiCheckbox(nama, label, checked, options)` | variant default/login; regular/bold, base/Geist, checked/unchecked/indeterminate |
| Card | `uiCard(content, variant, options)` | surface, brand, preview, outlined, callout |
| Inline metadata | `uiInlineMeta(primary, secondary, options)` | teks dengan separator dot |

Contoh pemakaian:

```php
<?= uiText('Selamat datang kembali!', 'display-xs', [
    'tag' => 'h1',
    'weight' => 'bold',
    'tone' => 'heading',
    'align' => 'center',
]) ?>

<?= uiField('email', 'Email', [
    'type' => 'email',
    'variant' => 'login',
    'font' => 'geist',
    'placeholder' => 'Isi email anda',
]) ?>
```

Komponen hanya mengatur struktur dan variant yang berulang. Layout khusus
seperti split screen Login, mockup tabel, serta ukuran one-off 5.17px/5.86px
tetap berada di stylesheet halaman agar tidak memaksa pola khusus menjadi
komponen global.

Text memiliki 17 variant ukuran: 6 display, 3 headline, 3 body, 3 caption,
`preview` (6.03/9.05px), dan `preview-callout` (6.84/10.26px).
Tone yang tersedia: `default`, `heading`, `muted`, `strong`, `brand`,
`success`, `secondary` (neutral-400), `inverse`, dan `preview`.
Default font selalu Plus Jakarta Sans. Gunakan `font => 'geist'` hanya
untuk elemen yang secara eksplisit memintanya.

```php
<?= uiCheckbox('remember_me', 'Ingat Saya', false, [
    'variant' => 'login', 'tone' => 'secondary',
]) ?>
<?= uiCard(uiText('Agenda Berikutnya', 'preview', ['weight' => 'bold']), 'outlined') ?>
```

Password toggle dari `uiField()` memakai `aria-controls` untuk memilih input.

Field mendukung ikon kiri atau kanan melalui opsi `icon` (nama asset tanpa
ekstensi) dan `iconPosition => 'left'|'right'` (default kanan, kompatibel dengan
password). Berlaku pada variant default maupun login. Ukuran ikon 16px,
warna memakai token `neutral-150` dari bank warna, dan jarak ikon ke teks
input/placeholder 12px. Inset ikon dari tepi field 12px; padding sisi berikon
40px = 12 + 16 + 12. Semua ukuran mengikuti `--ui-unit`.
Ikon dekoratif tidak menangkap klik; klik tetap memfokuskan input. Untuk aksi
lihat/sembunyikan password gunakan `iconToggle => true`.

```php
echo uiField('search', 'Cari', [
    'type' => 'search', 'placeholder' => 'Cari', 'hideLabel' => true,
    'icon' => 'icon_search', 'iconPosition' => 'left',
]);
echo uiField('reference', 'Referensi', [
    'icon' => 'icon_search', 'iconPosition' => 'right',
]);
```
Muat `public/assets/js/login.js` pada halaman yang menggunakan toggle.
Pratinjau Login dirakit dari komponen tersebut di
`app/views/auth/partials/report-preview.php`, bukan gambar screenshot.

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

Semua tabel data memakai komponen CSS `.data-table` di `components.css`.
Header: Body/SM 14px/21px, bold 700, letter-spacing 0, warna token
`report-table-heading`. Isi sel tetap Body/Regular/SM. Jangan menduplikasi
tipografi header di stylesheet halaman; varian hanya mengatur layout kolom
atau kebutuhan isi khusus. Isi sel berupa teks polos kecuali kolom status.
Tabel dokumen rapor/PDF tetap memakai komponen dokumen terpisah.

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

Badge dipakai di kolom status melalui `uiBadge($label, $variant)`. Font default
Plus Jakarta Sans 14px/21px regular, radius 4px, padding horizontal 8px dan vertikal 0,
border 1px. Ukuran mengikuti token responsif; tinggi mengikuti isi, bukan dikunci.

| Variant | Background | Teks | Border | Status Murid |
|---|---|---|---|---|
| `positif` | `green-50` | `green-600` | `green-100` | Bersekolah |
| `peringatan` | `orange-50` | `orange-600` | `orange-100` | Tanpa Keterangan |
| `netral` | `neutral-75` | `neutral-300` | `neutral-100` | Tamat |
| `destruktif` | `red-50` | `red-600` | `red-400` | Berhenti |

Semua warna varian ini sudah tersedia di bank warna; jangan membuat duplikat.
CSS `.badge` global juga memperbarui pengguna badge lama. Status Karyawan/Jabatan
tetap berupa teks tanpa badge sesuai spesifikasi halaman tersebut.

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

#### Canvas dan layout luar

- Background seluruh halaman memakai token `page-background` (`#FCFCFD`).
- Revisi responsive: **50px** adalah acuan margin/gap pada 1280 × 832px.
  Gunakan `--page-gutter-inline`, `--page-gutter-block`, dan `--layout-gap`
  dari bagian 1.4. Margin horizontal dan vertikal tidak dikunci atau
  dipaksa selalu sama; keduanya mengikuti dimensi viewport masing-masing.
- Lebar keseluruhan Login mengikuti `--layout-max-width`. Skala teks,
  ikon, logo, dan spacing mengikuti `--ui-unit`, bukan hanya lebar panel.
- Grid menggunakan proporsi **620:510**: pada acuan 1280px, section kiri
  620px dan kanan 510px setelah margin/gap. Ini menyediakan ruang untuk
  padding konten kiri 105px per sisi dan form 410px pada ukuran acuan.
- Di bawah **56rem (896px pada font dasar 16px)**, section ditumpuk.
  Konten, footer, dan metadata membungkus sesuai ruang yang tersedia.
  Tinggi halaman mengikuti konten; scrolling vertikal diperbolehkan.
- Tidak memakai CSS `zoom`, skala transform seluruh halaman, atau
  pengaturan yang memaksa browser 150%. Browser zoom mengubah viewport
  CSS dan layout mengikuti breakpoint tersebut. Font memakai rem agar
  tetap membesar sesuai preferensi pengguna.
- Section kiri tidak memiliki background, outline, atau border.
- Section kanan adalah card brand dengan radius **20px**, padding horizontal
  **40px** pada ukuran acuan (padding internal panel, bukan margin layar).
  Padding ini mengikuti unit desain dan mengecil hingga 1rem bila perlu;
  `overflow: hidden` agar dekorasi terpotong oleh
  batas card.

#### Section 1 - form Login

Section kiri memakai susunan vertikal: logo di atas, blok form di tengah,
dan footer di batas paling bawah.

**Logo**

- Asset: `assets/logo-colored.png` atau runtime copy di
  `public/assets/images/logo-colored.png`.
- Posisi di ujung kiri section.
- Ukuran render: **131x48px**.

**Wrapper konten form**

- `.login-main` membungkus greetings dan form dengan padding horizontal
  **105px (6.5625rem)** di desktop. Logo/footer tidak ikut padding ini.
- Batas padding desktop mengikuti `calc(6.5625 * var(--ui-unit))`.
  Kurangi padding sesuai ruang container sebelum menyempitkan form secara
  berlebihan. Pada layout satu kolom gunakan padding lebih kecil.
- Wrapper form di dalamnya tanpa background, outline, border, atau padding
  tambahan. Lebarnya **100% dari ruang setelah padding**, bukan fixed 410px.
  410px hanya hasil pada ukuran acuan 1280px.
- Tinggi mengikuti isi konten.
- Semua isi di dalamnya full-width terhadap wrapper yang fluid.
- Wrapper ditempatkan di tengah section kiri secara vertikal sesuai ruang
  yang tersedia. Ukuran dan posisi mengikuti konten saat zoom/reflow.

**Greetings**

- Teks: `Selamat datang kembali!`
- Align horizontal: center.
- Style: `login-heading`, 32px, line-height 48px, weight 700, regular
  letter-spacing 0%.
- Jarak ke subteks: **14px**.
- Subteks: `Masukkan email dan kata sandi untuk mengakses akun`.
- Style: `login-muted`, 14px, line-height 21px, weight 400, letter-spacing 0%.
- Jarak setelah subteks: **48px**.

**Form input**

- Field tersusun vertikal dengan jarak antar field **24px**.
- Label `Email` dan `Kata Sandi` memakai font **Geist**, token `login-label` (`#656565`),
  12px, line-height 18px, weight 400, letter-spacing 0%.
- Jarak label ke field: **4px**.
- Field full-width dari wrapper responsive.
- Field memakai border 1px token `neutral-75`, radius **4px**, inset shadow
  `login-input-shadow` (warna `shadow-black`, opacity sekitar 4%), padding
  horizontal **8px**, padding vertikal **12px**, dan gap internal **12px**.
- Tinggi field mengikuti isi (`height: auto`/hug), bukan tinggi fixed.
- Placeholder `Isi email anda` dan `Isi kata sandi anda` memakai font Geist,
  weight 400, ukuran 14px, line-height 21px, letter-spacing 0%.
- Isi field memakai token `login-input-text`, weight 400, ukuran 14px,
  line-height 21px, letter-spacing 0%.
- Field password memakai pasangan Lucide [eye](https://lucide.dev/icons/eye)
  dan [eye-off](https://lucide.dev/icons/eye-off), disimpan sebagai
  `public/assets/icons/icon_eye.svg` dan `icon_eye_off.svg`.
  Lisensi ada di `public/assets/icons/LICENSE-LUCIDE.txt`.
  Ukuran render **1rem × 1rem** (16x16px pada font dasar 16px),
  di ujung kanan dan align vertikal dengan placeholder; jarak sisi kanan
  terhadap field **12px**.
- Saat password tersembunyi, tampilkan eye untuk aksi membuka; saat terbuka,
  tampilkan eye-off untuk menyembunyikan. Toggle hanya mengubah type input,
  ikon, aria-label, dan aria-pressed; nilai yang diketik tetap tersimpan.
  Tombol dapat dioperasikan melalui mouse maupun keyboard.

**Meta form**

- Baris meta berjarak **8px** di bawah field password.
- Checkbox dan label `Ingat Saya` tersusun horizontal dengan gap **8px**.
- Teks `Ingat Saya`: token `neutral-400` (`#717074`), 12px, line-height 18px,
  weight 400, letter-spacing 0%.
- Checkbox: **16x16px**, border **1.6px**, token `neutral-100`, radius
  **3.2px**.
- Link `Lupa kata sandi?` berada di sisi kanan baris yang sama, token
  `red-600`, 12px, line-height 18px, weight 400, letter-spacing 0%.

**Footer section kiri**

- Menempel di batas paling bawah section kiri.
- `Copyright © Yayasan Zivana Insan Mandiri` dan `Privacy Policy` tersusun
  horizontal dengan `space-between`.
- Tidak memakai background, outline, border, atau padding tambahan.
- Style kedua teks: token `login-muted`, 14px, line-height 21px, weight 400,
  regular, letter-spacing 0%.

Tombol Login berlabel **Masuk**, memakai `uiButton()` variant `primary`,
`type => 'submit'`, tanpa ikon, dan full width terhadap form.
Jarak dari bawah baris Ingat Saya/Lupa kata sandi ke tepi atas tombol
adalah **30px** pada ukuran acuan (mengikuti skala `--ui-unit`).
Margin vertikal default button dinonaktifkan dengan `marginVertical => 0`;
gap form 24px ditambah margin atas tombol 6px, tanpa tambahan margin bawah.

**Asumsi implementasi yang belum diberi angka dalam prompt:** jarak headline
panel kanan ke deskripsi 14px, padding vertikal panel 40px, dan kelompok
konten panel kanan di tengah secara vertikal. Warna placeholder memakai
`neutral-100`, mengikuti default komponen. Privacy Policy masih berupa
placeholder karena tujuan halamannya belum tersedia.

SVG dekorasi runtime dinormalisasi ke `currentColor` tanpa opacity bawaan,
agar warna berasal dari token `red-500` dan opacity CSS 40% tidak menjadi
16% akibat dikalikan opacity 40% yang sudah ada di asset sumber.

#### Section 2 - panel brand

Panel kanan dibangun dalam tiga lapisan visual.

**Lapisan pertama: card brand**

- Background token `login-brand-surface` (`red-600`).
- Radius **20px**, padding horizontal **40px**.
- Asset dekorasi: `assets/login-Z.svg` atau runtime copy di
  `public/assets/images/login-Z.svg`.
- Dekorasi berada di layer paling belakang, ukuran acuan **537x741px**,
  lebarnya mengikuti section (105.3%) dengan rasio 537:741, opacity **40%**.
  **Tanpa rotasi CSS tambahan** karena bentuk asset sudah miring.
- Bagian dekorasi yang keluar dari card otomatis dipotong; tidak boleh
  terlihat melewati radius card.

**Lapisan kedua: copy dan mockup dashboard**

- Headline: `Dengan Mudah Mengelola Rapor Kelas`.
- Align start, token `login-brand-text`, 32px, line-height 48px, weight 700,
  letter-spacing 0%.
- Subteks: `Lihat agenda pengisian rapor yang sedang berjalan, pantau siapa saja yang belum diisi, dan selesaikan sebelum tenggat.`
- Align start, token `login-brand-text`, 14px, line-height 21px, weight 400,
  letter-spacing 0%.
- Setelah subteks, beri jarak **32px** sebelum card mockup.

Card mockup dashboard:

- Background token `page-background` (`#FCFCFD`), radius **5.17px**,
  padding **10.34px**, gap **5.17px**.
- Jarak table dari atas card sekitar **51.18px** pada ukuran acuan.
  Callout kini berada dalam alur dokumen: padding atas 10.34px, tinggi
  callout sesuai isi, lalu gap 5.67px sebelum table. Saat teks membungkus,
  table otomatis turun sehingga tidak tertimpa callout.
- Table mockup memiliki lebar penuh dari batas padding card dan radius
  **6px**.
- Table terdiri dari empat baris dengan separator **0.43px** token
  `login-preview-divider` (`#F1F1F1`).
- Baris pertama tidak memiliki background. Baris kedua sampai keempat
  memakai token `login-preview-row` (`#FBFBFB`).

Header table:

- Padding horizontal **6.89px**, padding vertikal **5.17px**.
- `Daftar Murid` dan `Tahun Ajaran 2025/2026` memakai token
  `login-preview-text`, 6.03px, line-height 9.05px, weight 400,
  letter-spacing 0%.
- Kedua teks tersusun vertikal dengan jarak **1.72px**.
- Area kanan berisi angka `3` dan `icon_chevron.svg` menghadap bawah,
  ukuran **9x9px**, dengan gap **5.17px**.

Baris data kedua sampai keempat:

- Padding horizontal **6.89px**, padding vertikal **5.17px**.
- Nama murid: `Eira Salsabila`, `Citra Novalina`, dan `Daffa Arkan Pratama`.
- Nama memakai token `login-preview-text`, 6.03px, line-height 9.05px,
  weight 400, letter-spacing 0%.
- Layout memakai `space-between`; tidak ada gap tambahan antara nama dan
  action selain ruang otomatis dari lebar baris.
- Baris kedua memakai `Lihat Rapor` dengan token `login-preview-success`
  (`green-700`).
- Baris ketiga dan keempat memakai `Isi Rapor` dengan token `red-600`.
- Gap antara label action dan icon chevron kanan: **5.17px**.

Agenda berikutnya:

- Jarak dari table: **5.17px**.
- Card tanpa background, border **0.43px**, radius **5.17px**, padding
  horizontal **6.89px**, padding vertikal **5.17px**.
- Judul `Agenda Berikutnya`: token `login-preview-text`, 6.03px,
  line-height 9.05px, weight 700, letter-spacing 0%, align start.
- Jarak judul ke baris berikutnya: **1.72px**.
- Baris berikutnya berisi `Pembagian Rapor Akhir Semester`, dot tengah
  token `login-preview-text`, dan `12/12/2026 - 24/12/2026`.
- Jarak teks ke dot dan dot ke tanggal masing-masing **5.17px**.
- Teks baris memakai token `login-preview-text`, 6.03px, line-height 9.05px,
  weight 400, letter-spacing 0%.

**Lapisan ketiga: card agenda aktif yang mengambang**

- Card ini berada di atas semua konten section kanan dan tidak memakai shadow.
- Card mengabaikan padding horizontal section kanan; lebar ditentukan oleh
  padding horizontal miliknya sendiri **23.5px** pada kedua sisi.
- Posisi vertikal: **10.34px** dari batas atas card mockup. Efek mengambang
  memakai margin horizontal negatif dan z-index, bukan posisi absolut.
  Overhang acuan 16.5px membuat jarak dari tepi section menjadi 23.5px;
  overhang ikut mengecil pada viewport sempit.
- Background token `login-callout-surface` (`#FFF2F0`), border **0.49px**
  token `login-callout-border` (`#FED2CD`), radius **5.86px**.
- Padding vertikal **5.86px**, horizontal **7.81px**.
- Judul `Agenda sedang berlangsung`: token `login-preview-text`, 6.84px,
  line-height 10.26px, weight 700, letter-spacing 0%, align start.
- Jarak ke baris berikutnya: **1.95px**.
- Baris berikutnya berisi `Pembagian Rapor Tengah Semester`, dot tengah token
  `login-muted`, dan `Sisa 2 Hari`.
- Jarak teks ke dot dan dot ke teks berikutnya masing-masing **5.86px**.
- Teks baris memakai token `login-preview-text`, 6.84px,
  line-height 10.26px, weight 400, letter-spacing 0%.

### 5.2 Manajemen Template

Pratinjau Tengah/Akhir Semester memakai `_preview-pages.php` dengan stylesheet
terisolasi `template-preview.css`. Pembungkus fullwidth memakai token bank warna
`button-tab-inactive-border` (#DDDDDD), radius 4px, padding/gap 16px. Label
“Halaman n dari 4” berada di dalam pembungkus, di atas masing-masing lembar.
Empat lembar disusun horizontal, tidak menyusut, dengan scrollbar tersembunyi;
region dapat difokuskan untuk navigasi keyboard dan mendukung geser sentuh.
Tinggi pembungkus mengikuti lembar beserta label/padding, bukan tinggi viewport.
Kertas berukuran 622.64 × 874.67px pada root 16px, menggunakan rem agar zoom browser
tetap berlaku; viewport sempit menggulir area ini tanpa melebarkan halaman aplikasi.

Fixture visual mengikuti referensi Document Preview: logo lengkap pada header,
watermark logo ikon, identitas placeholder merah, legenda empat simbol pada
halaman pertama, tabel Tujuan/Aparatus/TS Ganjil/TS Genap. Halaman kedua memakai
dua kolom keterampilan hidup dan sensorial. Halaman 3–4 sementara mengulang 1–2,
bukan kurikulum resmi. Tahun contoh 2024/2025 mengikuti gambar; judul/footer
menyesuaikan Tengah atau Akhir Semester. Fixture tidak menulis database.
Ekspor PDF tetap menggunakan `_document.php` berbasis database, belum fixture ini.

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

Workflow e-Rapor berbasis sesi (`/portal-guru/sesi/{id}`) adalah alur baru yang
terpisah dari form legacy `/portal-guru/rapor/{id}`. Sesi menampilkan nama murid
sebagai identitas, dokumen paket per periode sebagai section yang bisa dibuka/
ditutup, dan autosave serial. RTS memakai empat aset `penilaian-*.svg`; pilihan
grade dan kelengkapan selalu berasal dari definisi/snapshot server. Jangan
membuat tombol penyelesaian aktif dari hitungan browser: API mengirim ulang
capability setelah menyimpan. API juga memakai token CSRF sekali pakai, jadi
request autosave tidak boleh paralel. Feature flag tetap mati di production
sampai seluruh editor, termasuk inisialisasi dan tes Ummi, serta uji browser
siap.

---

## 6. Hal yang Perlu Diperhatikan

Bagian ini penting dibaca sebelum mulai koding.

**Nama frame adalah sumber kebenaran.** File Figma sudah dibersihkan, tidak ada lagi frame duplikat atau frame sisa dari proyek lain. Setiap frame yang ada di page UI memang dipakai. Kalau ada dua frame dengan nama layar sama, bedanya ada di dalam kurung dan itu memang dua kondisi berbeda yang dua duanya harus dibuat.

**State yang belum ada di Figma, pakai default saja dulu.** Hover untuk semua elemen interaktif, focus ring untuk input, dan loading state belum dirancang. Untuk pilot ini pakai default yang wajar dari framework atau library yang dipakai, tidak perlu menunggu desain. Nanti disempurnakan setelah pilot jalan. Yang penting state-nya ada dan tidak bikin user bingung, bukan pixel perfect.

**Empty state ada satu contoh, pakai itu sebagai pola.** Layar `Portal Guru -> Dashboard (Agenda Berikutnya Kosong/Empty)` menunjukkan cara menangani kondisi kosong, yaitu teks penjelas satu kalimat yang menyebut siapa yang harus mengisinya, bukan sekadar tulisan "Tidak ada data". Ikuti pola itu untuk empty state di tabel dan blok lain, sesuaikan kalimatnya dengan konteks masing masing. **Teks persis dari crawling:** "Belum ada agenda berikutnya yang ditambahkan oleh admin".

**Input state active sama persis dengan default.** Ini kelemahan desain yang sudah diketahui, bukan salah baca. Untuk sementara tambahkan focus ring default browser atau framework supaya tetap aksesibel. Jangan dihilangkan dengan `outline: none` tanpa pengganti.

**Token modal dan overlay belum konsisten.** Modal dan overlay masih terikat ke koleksi variable lain yaitu `color-neutral/white` dan `color-neutral/800`, bukan koleksi Zivana Color System yang dipakai seluruh komponen lain. Nilai warnanya tetap benar, tapi saat implementasi pakai token dari tabel bagian 1.1 saja supaya seragam.

**Responsive wajib lintas halaman.** Desain sumber mayoritas memakai 1280px
desktop; `Portal Guru -> Pengisian Rapor (Mobile)` memakai 320px. Keterbatasan
frame sumber tidak berarti halaman lain boleh dikunci ke desktop.
Terapkan kontrak bagian 1.4 pada setiap halaman, gunakan frame mobile yang
tersedia sebagai acuan, dan pertahankan seluruh fungsi saat reflow.

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
| Auth + Sistem + Sidebar | 6 | 4 role fix (Superadmin/Admin/Koordinator Guru/Guru); sidebar collapsed direvisi menjadi 92px |
Menu status Karyawan mengikuti status data: Aktif menampilkan Nonaktifkan Karyawan,
Nonaktif menampilkan Aktifkan Karyawan, masing-masing membuka modal konfirmasi sesuai aksi.

Komponen overflow global `.action-menu-dropdown` di `components.css` digunakan
untuk menu dari tombol tiga titik. Semua item button/link memakai padding
horizontal dan vertikal `var(--space-2)` (8px pada baseline desain, mengikuti
skala responsif). Aturan tombol ikon pada kolom aksi hanya menargetkan trigger,
bukan item dropdown, agar padding dan warna komponen menu tidak tertimpa.
### Daftar Rapor Murid per periode

Gunakan partial `components/report-period-table.php`, yang menggabungkan komponen accordion dan `data-table` menjadi **satu pembungkus per periode**, tanpa card sesi di dalamnya. Header menampilkan nama periode dan rentang tanggal di kiri, jumlah rapor tanpa badge dan chevron di kanan. Padding header/baris 12px vertikal dan 16px horizontal; jarak antarperiode 12px. Periode pertama terbuka secara default.

Baris memuat nama murid di kiri, status teks dan chevron 20px di kanan dengan gap 12px. `Belum diisi` memakai token merah dan tidak memiliki tautan; `Menunggu persetujuan` memakai token orange-600. Rapor disetujui cukup menampilkan chevron (status tetap tersedia bagi pembaca layar). Seluruh baris rapor yang tersedia menjadi tautan ke pratinjau. Semua warna berasal dari bank warna; jangan menambahkan warna HEX pada halaman.
### Portal Guru — dashboard, detail, dan pengisian rapor

- Dashboard menggunakan `uiCard` varian callout/outlined untuk agenda dan pola `accordion-item report-period-table` + `data-table` untuk murid. Jumlah murid/status tidak dibungkus badge. Seluruh baris yang dapat dibuka menjadi tautan, termasuk chevron.
- `Isi Rapor` memakai tone brand dan menuju form murid tersebut. `Lihat Rapor` memakai tone success untuk menunggu persetujuan/disetujui dan menuju pratinjau. Izin aksi diperiksa sebelum tautan ditampilkan.
- Nama tahun ajaran berasal dari periode terpilih, bukan tahun kalender browser. Dropdown Periode Rapor muncul jika lebih dari satu periode milik guru tersedia; ia memungkinkan membuka periode lampau tanpa membuat data baru.
- Agenda berlangsung dipilih dari sesi milik guru dengan tanggal hari ini di dalam rentangnya; jika bertumpuk, tampilkan yang mulai paling baru. Agenda berikutnya memakai tanggal mulai terdekat. Tabel default: agenda berlangsung, atau periode lampau terbaru, atau agenda berikutnya. Pemilihan tabel tidak mengganti blok agenda.
- More vertical Daftar Murid Guru langsung menuju `/portal-guru/murid/{id}`. Gunakan tampilan detail data bersama dengan breadcrumb `Daftar Murid Guru > Detail Murid`, tanpa tombol ubah admin.
- Pengisian tetap memakai layout fokus tanpa sidebar. Nilai memakai `uiSelect`, catatan memakai `uiField` textarea, aksi memakai `uiButton`. `uiSelect` menerima `attributes` untuk atribut native seperti data hook; komponen tidak menyimpan state nilai terpisah dari select native.
- Tombol Kirim/Selesaikan nonaktif jika template kosong atau nilai belum lengkap. Progres mengikuti perubahan dropdown; simpan draft tetap diperbolehkan. Aksi desktop berada di kartu header, aksi mobile di bawah. Peringatan keluar melindungi perubahan yang belum disimpan.
- Pratinjau guru memakai komponen kertas horizontal yang sama dengan admin, refresh dan PDF sesuai izin, tanpa Setujui. Jumlah halaman mengikuti konten nyata; jangan menduplikasi nilai atau halaman agar terlihat empat halaman.
# Simbol penilaian bersama

**Pembaruan mobile Pengisian Rapor:** pada viewport ≤40rem, header fullwidth tanpa gutter layar dan tanpa radius; padding internal tetap 20px/24px. Card penilaian tetap memiliki gutter horizontal 20px. Grup tombol header yang sama diposisikan `fixed` di bawah viewport, fullwidth, background token `page-background`, border atas `neutral-100`, dan padding 20px/24px ditambah safe-area perangkat. Tidak ada duplikasi tombol/form. Ruang bawah form mengikuti tinggi toolbar melalui ResizeObserver (fallback 7rem) agar penilaian terakhir tidak tertutup. Di desktop tombol tetap sejajar judul. Aturan ini menggantikan ketentuan sebelumnya bahwa tombol mobile tetap di bagian atas.

Khusus halaman Pengisian Rapor (layout `.focus-shell`), background menggunakan `var(--color-red-50)` dari bank `config/colors.php`. Background global halaman lain tetap menggunakan token `page-background`.

Header Pengisian Rapor: card radius 1rem (16px), padding vertikal 1.25rem (20px) dan horizontal 1.5rem (24px). Judul dan tombol Arsip/Selesaikan berada satu baris flex, aksi di kanan; pada layar sempit boleh membungkus tanpa keluar card. Nama murid sebenarnya ditampilkan sebagai teks di bawah judul, bukan dropdown. Tombol tetap di header pada mobile (menggantikan toolbar bawah sebelumnya). Baris penilaian: card radius 1rem, padding vertikal .75rem (12px), horizontal 1.5rem; antarbaris dan dari header kuning ke baris pertama berjarak .75rem. Warna tetap memakai token yang tersedia.

Pengisian Rapor menggunakan disclosure bertingkat: header merah membuka/menutup seluruh area beserta catatan guru; header kuning membuka/menutup subkategori penilaian secara independen. Keduanya tertutup saat halaman dimuat. Gunakan native `<details class="ui-disclosure">` dengan `<summary class="ui-disclosure-trigger">` dan `.ui-disclosure-chevron`, sehingga klik maupun Enter/Space bekerja tanpa JavaScript tambahan. Menutup area tidak menonaktifkan/menghapus input, tidak mereset kondisi subkategori, dan tidak mengubah perhitungan progres atau nilai yang dikirim.

Dropdown penilaian pada Pengisian Rapor memakai lebar desktop 22rem (352px pada font dasar 16px), termasuk ikon dan chevron. Jangan batasi input di dalamnya ke 220px. Pada layar hingga 56rem, dropdown berada di bawah tujuan penilaian dan mengisi lebar kontainer; label tetap boleh membungkus pada layar sempit tanpa overflow. Lebar overlay mengikuti trigger melalui komponen `uiSelect`.

Gunakan `skalaSimbolSrc()` / `renderSkalaSimbol()` untuk semua penilaian, bukan karakter Unicode atau bentuk SVG yang digambar ulang:

| Kode tersimpan | Aset sumber | Keterangan |
| --- | --- | --- |
| `slash` | `assets/penilaian-1-sisi.svg` | Baru dikenalkan |
| `triangle-sm` | `assets/penilaian-2-sisi.svg` | Mulai Berkembang |
| `triangle-lg` | `assets/penilaian-3-sisi.svg` | Berkembang Sesuai Harapan |
| `triangle-full` | `assets/penilaian-full.svg` | Berkembang Sangat Baik |

`uiSelect` menerima `optionImages` berupa peta nilai opsi → sumber gambar. Dropdown custom menampilkan gambar dan label, termasuk pilihan aktif. Native fallback tetap menampilkan label dan mengirim ID opsi yang sama. Ukuran: dropdown 24px (1.5rem), legenda pratinjau 32px (2rem), sel pratinjau 12px (.75rem), dokumen/PDF 16px. Proporsi aset dipertahankan. Sumber di-embed sebagai data URI untuk mendukung PDF tanpa akses jaringan; aset asli tidak diduplikasi atau diubah warnanya.
