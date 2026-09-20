# PRD — Zivana ERP (Modul eRapor)

## Latar Belakang

Zivana ERP adalah sistem internal untuk operasional sekolah Zivana Montessori. Skala penuh aplikasi ini adalah ERP sekolah, tapi fitur yang diprioritaskan untuk dibangun lebih dulu adalah **modul eRapor** — mulai dari data sekolah, data karyawan/guru, data murid, sampai proses penilaian dan penerbitan rapor.

Dokumen ini disusun dari hasil crawling `design-erapor.md` (spesifikasi desain) dan seluruh 52 screenshot UI di `assets/ss/` (hasil export Figma). Semua fitur yang disebutkan di sini punya bukti visual di salah satu screenshot — bagian yang tidak eksplisit terlihat ditandai `[ASUMSI]`.

Aplikasi dibangun sebagai **native PHP tanpa framework**, target deploy ke **shared hosting**, mengikuti pola arsitektur yang sudah dipakai di project Zivana sebelumnya (lihat `architecture.md`).

---

## 1. Aktor / Role

Ditemukan dari layar Sistem → RBAC, ada **4 role tetap** (bukan role bebas/custom):

| Role | Gambaran akses (dari matriks RBAC) |
|---|---|
| **Superadmin** | Akses penuh ke seluruh modul (kemungkinan termasuk konfigurasi RBAC itu sendiri) |
| **Admin** | Operasional harian: data sekolah, karyawan, murid, kelas, kurikulum |
| **Koordinator Guru** | [ASUMSI] Kemungkinan pengawas/approval alur rapor guru (lihat status "Menunggu persetujuan" di Rapor Murid) — perlu dikonfirmasi persis batas aksesnya |
| **Guru** | Hanya modul Portal Guru (Dashboard, Daftar Murid, Pengisian Rapor) — dari contoh RBAC, role Guru terkonfirmasi hanya punya akses "Lihat" ke Dashboard dan Daftar Murid Portal Guru (2/2 permission di grup itu), 0 akses ke modul lain |

Permission disusun sebagai matriks bertingkat: **Modul → Section → Sub-section → aksi (Lihat/Edit)**, dengan indikator hitung "x/y" per grup dan kemungkinan logika parent-checkbox otomatis tercentang berdasar state child `[ASUMSI logika pasti tri-state]`.

---

## 2. Struktur Modul & Menu

```
Sekolah
├── Data Sekolah              (tab: Informasi Umum, Kontak & Media)
└── Kurikulum
    ├── Manajemen Template    (daftar + pratinjau dokumen rapor)
    └── Periode Penilaian     (daftar, tambah/ubah/hapus)
Human Capital
└── Karyawan
    ├── Daftar Karyawan       (tambah/ubah/hapus, termasuk akun login)
    ├── Jabatan               (tambah/ubah)
    └── Manajemen Guru        (assign murid ke guru)
Murid
├── Manajemen Murid           (tambah/ubah/detail — 3 tab, daftar dengan status)
├── Manajemen Kelas           (tambah/ubah/hapus, detail kelas, assign murid ke kelas per guru)
└── Rapor Murid               (daftar accordion 3 level, pratinjau dokumen)
Portal Guru                    (area kerja guru, akses dibatasi RBAC)
├── Dashboard                 (agenda + daftar murid ampuan)
├── Daftar Murid              (read-only, murid ampuan guru bersangkutan)
├── Pengisian Rapor           (form input nilai, desktop & mobile)
└── Pratinjau Rapor Murid     (preview sebelum submit)
Sistem
└── RBAC                      (matriks permission per role)
```

---

## 3. Fitur per Modul

### 3.1 Sekolah — Data Sekolah
- Kelola profil sekolah 1 entitas (bukan multi-tenant): nama legal, nama komersial, bentuk pendidikan, NPSN, alamat, kontak (telepon, email), daftar akun media sosial (repeatable: jenis, id/nama, URL)
- Mode lihat vs mode ubah terpisah (tombol "Ubah Data Sekolah" ⇄ "Simpan")
- Validasi wajib per field dengan pesan error "Wajib Diisi"
- Fitur "Perbarui Tahun Ajaran" via modal terpisah — set tahun ajaran aktif berjalan (tahun awal/akhir)

### 3.2 Sekolah — Kurikulum → Manajemen Template
- Daftar template rapor (tampaknya predefined/system, kolom Tipe = "System", tidak ada tombol tambah) — contoh: "Rapor Montessori Tengah Semester", "Rapor Montessori Akhir Semester", "Rapor Al-Qur'an", "Rapor Bahasa Inggris"
- Filter: Semua Tipe, Semua Kategori (kategori: Rapor Murid / Rapor Sekolah)
- **Pratinjau Template**: render dokumen rapor multi-halaman (4 halaman) dengan placeholder variable (`{Nama Siswa}`, `{Kelas Siswa}`, `{NISN Siswa}`), skala penilaian custom Montessori (4 simbol non-numerik), struktur hierarkis Area → Sub-kategori (a/b/c/d) → Tujuan (item penilaian individual). Tombol "Simpan PDF" — fitur generate PDF dari template + data murid.
- `[ASUMSI]` Hanya halaman 1 dari 4 yang punya screenshot; struktur halaman 2–4 (kemungkinan area penilaian lain, Bacaan Jilid, PAI, catatan guru) diasumsikan dari struktur form Pengisian Rapor guru — perlu dikonfirmasi ke user/designer.

### 3.3 Sekolah — Kurikulum → Periode Penilaian
- CRUD periode penilaian: nama, tipe, kategori, tanggal awal/akhir
- Pola penamaan konsisten: "Penilaian Rapor [Tengah/Akhir] Semester [tahun ajaran]"
- Hapus dengan modal konfirmasi ("Hapus Periode?")

### 3.4 Human Capital — Karyawan
- **Daftar Karyawan**: CRUD karyawan dengan akun login langsung dibuat saat tambah (nama, jabatan, email, password + konfirmasi password). Ubah data TIDAK mengubah password di form yang sama (ada tombol terpisah "Ganti Kata Sandi").
- **Jabatan**: CRUD jabatan sederhana (hanya field nama). Data ditemukan: Kepala Sekolah, Admin, Guru Kelas, **Guru Shadow** `[ASUMSI — "Guru Shadow" tidak disebut di design-erapor.md, kemungkinan guru pendamping/asisten, perlu dikonfirmasi definisinya]`.
- **Manajemen Guru**: bukan CRUD guru (guru dibuat lewat Daftar Karyawan dengan jabatan guru) — modul ini murni untuk **assign murid ke guru** lewat modal "Atur Anak Murid" (list dinamis dropdown nama murid, bukan checkbox).

### 3.5 Murid — Manajemen Murid
- Form 3 tab konsisten di mode Tambah/Ubah/Detail: **Data Murid**, **Informasi Pendaftaran**, **Relasi & Kontak** (lihat rincian field lengkap di `schema.md`)
- Mode Detail menampilkan 2 field tambahan (Level Kelas, Kelas) yang berasal dari relasi ke Manajemen Kelas, bukan input langsung
- Daftar murid dengan status: Bersekolah, Tanpa Keterangan, Tamat, Berhenti
- Fitur "Import" murid (bulk) — tombol ada di toolbar, detail mekanisme belum terlihat `[ASUMSI]`
- `[ASUMSI]` Tidak ada field upload foto murid di screenshot manapun — kemungkinan sengaja di luar scope pilot, perlu dikonfirmasi.

### 3.6 Murid — Manajemen Kelas
- CRUD kelas: level kelas (dropdown, contoh nilai: Ranting, Kucup, Pucuk — istilah Montessori) + nama kelas (contoh: nama pohon — Akasia, Mahoni, Jati, dst)
- Detail kelas menampilkan multi-guru per kelas, masing-masing dengan subset murid sendiri (satu kelas bisa dipegang beberapa guru berbeda kelompok murid)
- Assign murid ke guru-dalam-kelas lewat modal "Atur Anak Murid" (pola sama dengan Manajemen Guru)
- Hapus kelas **tidak menghapus murid** — murid terkait kehilangan relasi kelas (set null, bukan cascade delete), sesuai teks konfirmasi modal hapus

### 3.7 Murid — Rapor Murid
- Struktur accordion 3 level: **Periode Penilaian** → **Sesi Pembagian Rapor** → **per-murid** dengan status (Belum diisi / Menunggu persetujuan / selesai)
- Pratinjau dokumen rapor per murid, sama strukturnya dengan Pratinjau Template tapi sudah terisi data nyata

### 3.8 Portal Guru
- **Dashboard**: 3 blok — Agenda Sedang Berlangsung (dengan hitung mundur), Daftar Murid ampuan (link kontekstual "Lihat Rapor"/"Isi Rapor" tergantung status), Agenda Berikutnya (empty state kalau admin belum set)
- **Daftar Murid**: read-only, kolom lebih sedikit dari versi Admin (tanpa status, tanpa aksi CRUD)
- **Pengisian Rapor**: form panjang berjenjang (Kategori besar → Sub-kategori/Tujuan → item skill individual dengan dropdown skala penilaian), + textarea "Catatan Guru" naratif per kategori besar. Ada progress bar bertahap (X dari 4 halaman/section). Setelah "Selesaikan Rapor", data terkunci ("tidak dapat diubah kembali" setelah diapprove Kepala Sekolah — mengindikasikan ada approval flow oleh role lain, kemungkinan Koordinator Guru atau Kepala Sekolah `[ASUMSI siapa approver-nya]`). Tersedia versi desktop dan mobile (mobile wajib jalan baik, sisanya boleh desktop-only untuk pilot).
- **Pratinjau Rapor Murid**: preview hasil isian sebelum/sesudah submit, bisa ganti murid tanpa keluar halaman, tombol "Simpan PDF"

### 3.9 Sistem — RBAC
- Kelola permission matrix per role (4 role tetap), tombol "Tambah Role" tetap ada `[ASUMSI — apakah role benar-benar bisa ditambah bebas, atau tombol ini untuk keperluan lain]`

### 3.10 Auth — Login
- Login email + password, checkbox "Ingat Saya" (remember-me), link "Lupa kata sandi?" (forgot-password flow — perlu modul reset password meski belum ada screenshotnya `[ASUMSI]`)
- Panel kanan brand: preview mini-dashboard Portal Guru sebagai materi promosi visual

---

## 4. Alur Utama (End-to-End)

**Alur setup awal (Admin):**
1. Login → Isi Data Sekolah → Set Tahun Ajaran aktif
2. Buat Jabatan → Buat Karyawan (termasuk guru, dengan akun login)
3. Buat Kelas → Buat Periode Penilaian
4. Buat/import Murid → Assign murid ke Kelas → Assign murid ke Guru (bisa lewat Manajemen Guru atau Manajemen Kelas)
5. Buat Sesi Pembagian Rapor untuk periode berjalan

**Alur pengisian rapor (Guru):**
1. Login sebagai Guru → Dashboard menampilkan agenda sedang berlangsung + daftar murid ampuan
2. Klik "Isi Rapor" pada murid tertentu → masuk form Pengisian Rapor (berjenjang per kategori, textarea catatan per kategori)
3. Simpan progress bertahap → Pratinjau Rapor Murid untuk cek hasil
4. Klik "Selesaikan Rapor" → status berubah jadi "Menunggu persetujuan" `[ASUMSI: siapa yang approve]`
5. Setelah disetujui, rapor terkunci (read-only) dan siap di-generate PDF final

**Alur monitoring (Admin/Superadmin):**
1. Buka Rapor Murid → accordion Periode → Sesi → lihat status tiap murid (Belum diisi/Menunggu persetujuan/Selesai)
2. Pratinjau atau download PDF rapor per murid

---

## 5. Di Luar Scope (Eksplisit dari design-erapor.md)

- Dark mode
- Responsive penuh untuk semua halaman (hanya Pengisian Rapor guru yang wajib mobile-ready)
- Hover/focus/loading state custom (pakai default framework/browser dulu)

---

## 6. Daftar Asumsi yang Perlu Dikonfirmasi

1. Batas akses persis role **Koordinator Guru** (approval rapor?)
2. Siapa yang meng-approve rapor guru sehingga statusnya berubah dari "Menunggu persetujuan" ke selesai — Koordinator Guru, Admin, atau role lain
3. Definisi jabatan **"Guru Shadow"**
4. Struktur konten halaman 2–4 dokumen rapor (hanya halaman 1 yang ada di screenshot)
5. Apakah foto murid memang di luar scope pilot ini
6. Mekanisme tombol **"Import"** murid (format file, mapping kolom, dsb)
7. Apakah tombol "Tambah Role" di RBAC benar-benar membuat role baru secara bebas, atau 4 role memang fixed selamanya
8. Alur forgot-password (belum ada screenshot halaman ini)
