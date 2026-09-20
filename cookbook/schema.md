# Database Schema — Zivana ERP

## Konvensi (mengikuti pola `zivanamontdev-php-repo`)

- Primary key: `id INT AUTO_INCREMENT PRIMARY KEY` di semua tabel
- Nama tabel: plural, snake_case. Nama kolom: snake_case
- Kolom timestamp wajib di semua tabel: `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- Flag aktif pakai `is_active TINYINT(1) DEFAULT 1`, bukan soft-delete `deleted_at`
- Status pakai `ENUM(...)` langsung di kolom
- Foreign key eksplisit; default `ON DELETE CASCADE` untuk relasi child murni, tapi **`ON DELETE SET NULL`** untuk relasi yang secara eksplisit dikonfirmasi tidak boleh cascade (lihat catatan per tabel)
- Engine `InnoDB`, charset `utf8mb4`, collation `utf8mb4_unicode_ci`
- Kolom `display_order INT DEFAULT 0` untuk tabel yang butuh urutan tampilan custom

Setiap field yang tidak eksplisit terlihat di screenshot ditandai `[ASUMSI]` pada tipe data/panjangnya.

---

## 1. Auth & RBAC

### `roles`
Seed 4 baris tetap, dikonfirmasi dari layar RBAC.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama | VARCHAR(50) | Superadmin, Admin, Koordinator Guru, Guru |
| created_at, updated_at | TIMESTAMP | |

### `permissions`
Daftar seluruh node permission yang bisa dicentang (modul/section/sub-section/aksi), didefinisikan sekali sebagai referensi. `[ASUMSI struktur — bisa juga di-hardcode di kode tanpa tabel referensi, tapi tabel lebih fleksibel untuk "Tambah Role" baru]`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| modul | VARCHAR(50) | Sekolah, Human Capital, Murid, Portal Guru, Sistem |
| section | VARCHAR(100) NULL | mis. "Data Sekolah", "Kurikulum" |
| sub_section | VARCHAR(100) NULL | mis. "Manajemen Template" |
| aksi | ENUM('lihat','edit') | |
| display_order | INT | |

### `role_permissions`
Pivot: permission mana yang aktif untuk role mana.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| role_id | INT FK → roles.id (CASCADE) | |
| permission_id | INT FK → permissions.id (CASCADE) | |
| created_at | TIMESTAMP | |

### `users`
Akun login. Dipisah dari `karyawan` karena Portal Guru login pakai akun yang sama dengan yang dibuat di modul Karyawan `[ASUMSI: users 1-1 dengan karyawan; kalau ternyata Superadmin tidak perlu jadi karyawan, users bisa berdiri sendiri tanpa karyawan_id]`.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| karyawan_id | INT FK → karyawan.id NULL | null untuk Superadmin/akun sistem |
| role_id | INT FK → roles.id | |
| email | VARCHAR(150) UNIQUE | |
| password_hash | VARCHAR(255) | bcrypt via `password_hash()` |
| remember_token | VARCHAR(255) NULL | hash SHA-256, bukan plaintext (ikut pola repo referensi) |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at, updated_at | TIMESTAMP | |

### `password_resets`
Untuk alur "Lupa kata sandi?" (belum ada screenshot halamannya, tapi link-nya ada di Login — lihat prd.md asumsi #8).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| user_id | INT FK → users.id (CASCADE) | |
| token | VARCHAR(255) | |
| expires_at | TIMESTAMP | `[ASUMSI]` expiry 1 jam, ikut pola repo referensi |
| created_at | TIMESTAMP | |

---

## 2. Sekolah

### `sekolah`
Singleton (hanya 1 baris, tidak multi-tenant).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama_legal | VARCHAR(200) | contoh: "Yayasan Zivana Insan Mandiri" |
| nama_komersial | VARCHAR(200) | contoh: "TK Zivana Montessori Makassar" |
| bentuk_pendidikan | VARCHAR(50) | dropdown, contoh: "TK" `[ASUMSI daftar opsi lengkap — hanya "TK" yang terlihat di contoh data]` |
| npsn | VARCHAR(20) | |
| alamat | TEXT | |
| no_telepon | VARCHAR(30) | |
| email | VARCHAR(150) | |
| created_at, updated_at | TIMESTAMP | |

### `sekolah_media`
Repeatable — akun media sosial sekolah.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| sekolah_id | INT FK → sekolah.id (CASCADE) | |
| jenis_media | VARCHAR(50) | dropdown, contoh: "Instagram" |
| nama_akun | VARCHAR(150) | |
| url | VARCHAR(255) | |
| display_order | INT | |
| created_at | TIMESTAMP | |

### `tahun_ajaran`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| tahun_awal | YEAR | contoh: 2026 |
| tahun_akhir | YEAR | contoh: 2027 |
| is_active | TINYINT(1) | hanya 1 baris boleh aktif — enforce di application layer |
| created_at, updated_at | TIMESTAMP | |

---

## 3. Kurikulum & Template Rapor

### `template_rapor`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama | VARCHAR(150) | contoh: "Rapor Montessori Tengah Semester" |
| kategori | ENUM('rapor_murid','rapor_sekolah') | dari filter "Semua Kategori" |
| tipe | ENUM('system','custom') DEFAULT 'system' | semua contoh data bertipe "System" |
| is_active | TINYINT(1) DEFAULT 1 | kolom Status di tabel |
| created_at, updated_at | TIMESTAMP | |

### `template_rapor_area`
Level 1 struktur dokumen (contoh: "AREA KETERAMPILAN HIDUP").

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| template_id | INT FK → template_rapor.id (CASCADE) | |
| nama_area | VARCHAR(150) | |
| display_order | INT | |

### `template_rapor_subkategori`
Level 2, berlabel huruf (a/b/c/d).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| area_id | INT FK → template_rapor_area.id (CASCADE) | |
| label | VARCHAR(5) | "a", "b", "c", "d" |
| nama | VARCHAR(150) | contoh: "Perawatan Diri", "Motorik Halus" |
| display_order | INT | |

### `template_rapor_item`
Level 3, item "Tujuan" individual — leaf node yang benar-benar dinilai.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| subkategori_id | INT FK → template_rapor_subkategori.id (CASCADE) | |
| nama_tujuan | VARCHAR(255) | contoh: "Menutup mulut saat batuk dan bersin" |
| skala_nilai_id | INT FK → skala_nilai.id | tiap item BISA punya skala berbeda (lihat 3.5 di design-system.md — PAI/Bacaan Jilid pakai skala lain) |
| display_order | INT | |

### `skala_nilai`
Definisi skala penilaian yang bisa berbeda per konteks/mapel.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama | VARCHAR(100) | contoh: "Montessori 4 Simbol", "Grade Huruf PAI" `[ASUMSI penamaan]` |
| created_at | TIMESTAMP | |

### `skala_nilai_opsi`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| skala_id | INT FK → skala_nilai.id (CASCADE) | |
| simbol | VARCHAR(20) | contoh: "/", "△", "▲" atau "A-", "Tahfizh Mumtaz" |
| label | VARCHAR(100) | contoh: "Baru dikenalkan", "Berkembang Sangat Baik" |
| display_order | INT | urutan dari terendah ke tertinggi |

**Seed awal skala Montessori 4 simbol** (dari crawling Pratinjau Template & Pratinjau Rapor Murid):
1. `/` — Baru dikenalkan
2. Segitiga outline kecil — Mulai Berkembang
3. Segitiga outline besar — Berkembang Sesuai Harapan
4. Segitiga solid — Berkembang Sangat Baik

`[ASUMSI]` Skala untuk Pendidikan Agama Islam/Bacaan Jilid belum terdokumentasi lengkap (hanya contoh dummy "A-", "Tahfizh Mumtaz" terlihat di form guru) — perlu konfirmasi daftar opsi lengkapnya ke user.

### `periode_penilaian`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| tahun_ajaran_id | INT FK → tahun_ajaran.id | |
| nama | VARCHAR(150) | pola: "Penilaian Rapor [Tengah/Akhir] Semester [tahun ajaran]" |
| tipe | VARCHAR(50) | dari filter "Semua Tipe" `[ASUMSI nilai pasti]` |
| kategori | VARCHAR(50) | dari filter "Semua Kategori" `[ASUMSI nilai pasti]` |
| awal_periode | DATE | |
| akhir_periode | DATE | |
| created_at, updated_at | TIMESTAMP | |

### `sesi_pembagian_rapor`
Level di bawah periode (dari accordion Rapor Murid).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| periode_id | INT FK → periode_penilaian.id (CASCADE) | |
| nama | VARCHAR(150) | contoh: "Pembagian Rapor Tengah Semester 25/26" |
| tanggal_mulai | DATE | |
| tanggal_selesai | DATE | |
| created_at | TIMESTAMP | |

---

## 4. Human Capital

### `jabatan`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama | VARCHAR(100) | Kepala Sekolah, Admin, Guru Kelas, Guru Shadow (`[ASUMSI definisi]`) |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at, updated_at | TIMESTAMP | |

### `karyawan`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| jabatan_id | INT FK → jabatan.id | |
| nama | VARCHAR(150) | |
| is_active | TINYINT(1) DEFAULT 1 | Status di tabel |
| created_at, updated_at | TIMESTAMP | |

Catatan: `email` dan `password` yang terlihat di modal "Tambah Karyawan" disimpan di tabel `users` (relasi `users.karyawan_id`), bukan duplikasi di sini — supaya satu sumber kebenaran kredensial login. `[ASUMSI arsitektur ini; alternatif: gabung langsung ke tabel karyawan kalau mau lebih sederhana]`.

---

## 5. Murid & Kelas

### `kelas`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| tahun_ajaran_id | INT FK → tahun_ajaran.id | kelas kemungkinan berbeda per tahun ajaran `[ASUMSI]` |
| level_kelas | VARCHAR(50) | dropdown, contoh: "Ranting", "Kucup", "Pucuk" (istilah Montessori) |
| nama_kelas | VARCHAR(100) | contoh nama pohon: Akasia, Mahoni, Jati, Eboni, Pinus, Cemara, Cendana, Gaharu, Flamboyan, Zaitun, Kenari |
| created_at, updated_at | TIMESTAMP | |

### `kelas_guru_murid`
Pivot 3-arah: satu kelas bisa punya beberapa guru, masing-masing guru punya subset murid sendiri di kelas itu (dikonfirmasi dari Detail Kelas: card guru berulang, masing-masing dengan list murid sendiri).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| kelas_id | INT FK → kelas.id (CASCADE) | |
| guru_id | INT FK → karyawan.id (CASCADE) | |
| murid_id | INT FK → murid.id (CASCADE) | |
| created_at | TIMESTAMP | |

Ini juga menjadi sumber data untuk modul "Manajemen Guru → Atur Anak Murid" (assign murid ke guru) — kedua modal assign (dari Manajemen Guru dan dari Detail Kelas) menulis ke tabel yang sama.

### `murid`
Field lengkap hasil crawling 13 screenshot form (Tambah/Ubah/Detail × 3 tab).

**Tab Data Murid:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| nama_lengkap | VARCHAR(150) | wajib |
| nama_panggilan | VARCHAR(50) | wajib |
| nisn | VARCHAR(20) NULL | **opsional** — satu-satunya field tidak wajib di tab ini |
| agama | VARCHAR(30) | dropdown, wajib |
| nik | VARCHAR(20) | wajib |
| no_registrasi_akte | VARCHAR(50) | wajib |
| jenis_kelamin | ENUM('L','P') | wajib |
| tempat_lahir | VARCHAR(100) | wajib |
| tanggal_lahir | DATE | wajib |
| alamat | TEXT | wajib |

Catatan: kolom **"Umur"** yang terlihat di form adalah read-only/computed dari `tanggal_lahir` — **tidak disimpan sebagai kolom**, dihitung on-the-fly di application layer.

**Tab Informasi Pendaftaran:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| tanggal_masuk_sekolah | DATE | wajib |
| status_kondisi | VARCHAR(50) | dropdown, contoh: "Reguler", wajib |
| jenis_kebutuhan | VARCHAR(150) NULL | opsional |
| kelengkapan_berkas | VARCHAR(150) NULL | opsional — `[ASUMSI tipe: bisa jadi ini harusnya checklist/upload, bukan text bebas, perlu klarifikasi]` |
| kelas_id | INT FK → kelas.id, **ON DELETE SET NULL** | bukan field input di form ini — hasil relasi dari modul Manajemen Kelas (field "Level Kelas"/"Kelas" yang muncul di mode Detail berasal dari sini via join) |

**Tab Relasi & Kontak:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| alamat_domisili | VARCHAR(255) | wajib |
| anak_ke | INT NULL | opsional |
| jumlah_saudara | INT | wajib |
| nama_ayah | VARCHAR(150) | wajib |
| pendidikan_ayah | VARCHAR(100) | wajib |
| pekerjaan_ayah | VARCHAR(100) | wajib |
| telp_ayah | VARCHAR(30) | wajib |
| nama_ibu | VARCHAR(150) | wajib |
| pendidikan_ibu | VARCHAR(100) | wajib |
| pekerjaan_ibu | VARCHAR(100) | wajib |
| telp_ibu | VARCHAR(30) | wajib |

**Status murid (kolom terpisah, dipakai di kolom Status tabel daftar):**

| Kolom | Tipe | Keterangan |
|---|---|---|
| status | ENUM('bersekolah','tanpa_keterangan','tamat','berhenti') | badge warna sesuai design-system.md 4.2 |
| created_at, updated_at | TIMESTAMP | |

`[ASUMSI]` Tidak ada kolom foto (`foto_url`) — tidak ditemukan field upload foto murid di screenshot manapun. Tambahkan kalau nanti dikonfirmasi masuk scope.

---

## 6. Rapor & Penilaian

### `rapor`
Satu baris = satu rapor satu murid untuk satu sesi pembagian.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| murid_id | INT FK → murid.id (CASCADE) | |
| sesi_pembagian_id | INT FK → sesi_pembagian_rapor.id (CASCADE) | |
| template_id | INT FK → template_rapor.id | |
| guru_id | INT FK → karyawan.id NULL | guru pengisi |
| status | ENUM('belum_diisi','menunggu_persetujuan','disetujui') DEFAULT 'belum_diisi' | |
| disetujui_oleh | INT FK → karyawan.id NULL | `[ASUMSI]` teks peringatan di form guru menyebut "diapprove oleh Kepala Sekolah" — perlu tabel/role approver yang jelas (lihat prd.md asumsi #2) |
| disetujui_at | TIMESTAMP NULL | |
| created_at, updated_at | TIMESTAMP | |

### `rapor_nilai`
Nilai per item penilaian per semester.

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| rapor_id | INT FK → rapor.id (CASCADE) | |
| item_id | INT FK → template_rapor_item.id | |
| semester | ENUM('ganjil','genap') | kolom TS Ganjil / TS Genap |
| skala_nilai_opsi_id | INT FK → skala_nilai_opsi.id | nilai yang dipilih guru |
| created_at, updated_at | TIMESTAMP | |

### `rapor_catatan_guru`
Textarea naratif per kategori besar (Area Keterampilan Hidup, Laporan Perkembangan, dst).

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | INT PK | |
| rapor_id | INT FK → rapor.id (CASCADE) | |
| area_id | INT FK → template_rapor_area.id | |
| catatan | TEXT | |
| created_at, updated_at | TIMESTAMP | |

---

## 7. Diagram Relasi (ringkas)

```
roles ─┬─< role_permissions >─┬─ permissions
       │                       
users ─┴─(karyawan_id)─ karyawan ─── jabatan
                          │
                          ├─< kelas_guru_murid >─┬─ kelas ── tahun_ajaran
                          │                       └─ murid
                          └─< rapor (guru_id)

murid ─┬─(kelas_id, SET NULL)─ kelas
       └─< rapor >─┬─ sesi_pembagian_rapor ── periode_penilaian ── tahun_ajaran
                    ├─ template_rapor ─┬─< template_rapor_area >─┬─< template_rapor_subkategori >─< template_rapor_item ── skala_nilai ─< skala_nilai_opsi
                    │                   
                    ├─< rapor_nilai >── template_rapor_item, skala_nilai_opsi
                    └─< rapor_catatan_guru >── template_rapor_area
```

---

## 8. Daftar Asumsi Kunci

1. `users` terpisah dari `karyawan` (1-1) — arsitektur ini bisa disederhanakan kalau ternyata tidak perlu
2. Struktur `template_rapor_area → subkategori → item` diasumsikan dari 1 halaman dokumen yang terlihat; kategori seperti "Bacaan Jilid"/"PAI" yang muncul di form guru mungkin butuh model tambahan di luar struktur Area/Sub-kategori standar (perlu dicek apakah itu `template_rapor_area` biasa atau butuh tabel terpisah)
3. `disetujui_oleh` pada tabel `rapor` — role approver belum pasti (Koordinator Guru vs Kepala Sekolah sebagai jabatan)
4. Field `kelengkapan_berkas` di Informasi Pendaftaran mungkin seharusnya berupa checklist atau file upload, bukan text
5. Tidak ada kolom foto murid — ditunda sampai dikonfirmasi
6. `tipe` dan `kategori` di `periode_penilaian` — nilai enum pastinya belum terlihat lengkap (hanya opsi filter "Semua Tipe"/"Semua Kategori" yang terlihat, bukan daftar valuenya)
