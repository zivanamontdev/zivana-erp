# eRapor Zivana Montessori — Paket Spesifikasi untuk Developer

Paket ini berisi seluruh spesifikasi dan data seed untuk membangun modul rapor eRapor Zivana Montessori. Ditujukan untuk developer dan untuk AI coding assistant yang dipakai developer.

Versi paket: 23 September 2026.

---

## 1. Baca dengan urutan ini

| # | Berkas | Isinya |
|---|---|---|
| 1 | `README.md` | berkas ini |
| 2 | **`SPEK_ALUR_PENGISIAN.md`** | **wajib dibaca pertama.** Alur sesi, empat status, persetujuan, penguncian, penerbitan, tanda tangan, dan **seluruh tabel bersama** |
| 3 | `SPEK_RUBRIK_RTS.md` | Rapor Tengah Semester Montessori, 175 indikator |
| 4 | `SPEK_RUBRIK_AGAMA.md` | Rapor Pendidikan Agama Islam, 73 butir dan 99 Asmaul Husna |
| 5 | `SPEK_RUBRIK_UMMI.md` | Rapor Al-Quran Metode Ummi, 7 jilid dan 27 materi |
| 6 | `SPEK_RUBRIK_BING.md` | Rapor Bahasa Inggris (Statement of Result) |
| 7 | `SPEK_RUBRIK_PPI.md` | Program Pembelajaran Individu, khusus murid ABK |

**Aturan kalau ada yang bertentangan:** `SPEK_ALUR_PENGISIAN.md` menang untuk urusan alur dan tabel bersama. Spesifikasi rubrik menang untuk isi dokumennya masing-masing. Kalau masih ragu, tanyakan dulu, jangan menebak.

---

## 2. Isi paket

```
README.md
SPEK_ALUR_PENGISIAN.md        alur + tabel bersama + API sesi + kriteria penerimaan alur
SPEK_RUBRIK_RTS.md            + rubrik_rts_seed.json
SPEK_RUBRIK_AGAMA.md          + rubrik_agama_seed.json
SPEK_RUBRIK_UMMI.md           + rubrik_ummi_seed.json
SPEK_RUBRIK_BING.md           + rubrik_bing_seed.json
SPEK_RUBRIK_PPI.md            + rubrik_ppi_seed.json
uji_tinjauan_ppi.py           uji logika pencarian tinjauan Hasil Capaian PPI
```

**Seed adalah sumber kebenaran untuk teks.** Tabel di dalam spesifikasi untuk dibaca manusia. Untuk seeding, pakai berkas JSON, jangan mengetik ulang dari tabel.

---

## 3. Gambaran besarnya dalam satu halaman

**Satu sesi = satu murid, satu periode.** Ada empat periode per tahun ajaran, dan tiap sesi berisi empat dokumen, atau lima untuk murid ABK.

| Periode | Tahap 1 | Tahap 2 | Tahap 3 | Tahap 4 | Tahap 5 |
|---|---|---|---|---|---|
| Tengah Semester Ganjil | RTS | Agama | Ummi | Bahasa Inggris | PPI |
| Akhir Semester Ganjil | RAS | Agama | Ummi | Bahasa Inggris | PPI |
| Tengah Semester Genap | RTS | Agama | Ummi | Bahasa Inggris | PPI |
| Akhir Semester Genap | RAS | Agama | Ummi | Bahasa Inggris | PPI |

Tahap 5 hanya untuk murid ABK. RAS belum dispesifikasi, lihat bagian 6.

**Empat status, di sesi, bukan di dokumen.**

```
BELUM_DIISI → TELAH_DIISI → MENUNGGU_TTD → SELESAI
  (guru)        (guru)       (koordinator,    (final)
                              lalu kepsek)
```

Satu guru kelas mengisi seluruh dokumen dalam satu sesi. Satu tombol konfirmasi untuk seluruh sesi. Satu rantai persetujuan. Seluruh dokumen terbit bersamaan sebagai PDF yang dikirim lewat surel dan WhatsApp.

---

## 4. Urutan membangun yang disarankan

1. **Tabel bersama** dari `SPEK_ALUR_PENGISIAN.md` bagian 7: `user`, `berkas`, `tahun_ajaran`, `periode`, `rubrik` beserta turunannya, `rapor`, `rapor_sesi` beserta turunannya, dan `rapor_isian_log`.
2. **Tabel khusus tiap dokumen** dari bagian skema di masing-masing spesifikasi rubrik.
3. **Seeding** dari kelima berkas `rubrik_*_seed.json`, lalu jalankan pemeriksaan jumlah di bagian "Cara Memakai Berkas Ini" tiap spesifikasi.
4. **Penulisan isian** beserta aturan kunci di `SPEK_ALUR_PENGISIAN.md` bagian 7.6. Pasang di lapisan penyimpanan, bukan di controller.
5. **Layar wizard sesi**, lalu endpoint sesi di bagian 7.9.
6. **Persetujuan dan penerbitan.**
7. **Generator PDF**, paling akhir, tapi rancang skemanya sejak awal dengan tata letak di tiap spesifikasi.

Uji dengan kriteria penerimaan di `SPEK_ALUR_PENGISIAN.md` bagian 12, ditambah kriteria di tiap spesifikasi rubrik.

---

## 5. Aturan yang paling sering salah

Kalau cuma sempat membaca satu bagian, baca ini.

1. **Periode cuma punya satu bentuk**, yaitu tabel `periode` dengan empat baris per tahun. Setiap baris isian di tabel mana pun memakai `periode_id` konkret. Jangan memakai `ENUM('TENGAH','AKHIR')` atau kode kolom seperti `TS_GANJIL` sebagai pengganti periode.
2. **Setiap baris isian milik tepat satu sesi.** Penulisan hanya diterima kalau sesinya di step 1 atau 2, tanggal akhir periode belum lewat, dan baris itu milik periode sesi yang sedang dibuka. Satu-satunya perkecualian adalah `ppi_capaian`.
3. **Step 3 ke atas hanya bisa dibaca, dan tidak ada jalan mundur.** Tidak ada tarik kembali, tidak ada pengembalian oleh penyetuju, dan tidak ada pembatalan `SELESAI`, termasuk untuk admin. Ketiadaannya disengaja.
4. **Status dan kelengkapan dua hal berbeda.** Mengosongkan isian di step 2 tidak menurunkan status. Tombol konfirmasinya saja yang mati.
5. **Kosong** berarti `NULL`, string kosong, atau hanya spasi. Satu fungsi untuk seluruh dokumen. Isian kosong dihapus barisnya.
6. **Seluruh isian berbentuk dropdown**, kecuali kotak teks bebas. Tidak ada radio button, tidak ada checkbox.
7. **Nilai huruf dan simbol tidak pernah dirata-rata.**
8. **Nama, NUPTK, dan gambar tanda tangan disalin ke sesi** saat orangnya bertindak. Jangan di-join hidup saat mencetak.
9. **Kode rubrik dibekukan sejak seeding pertama.** Sebelum itu boleh dibangkitkan ulang, sesudahnya tidak pernah lagi.
10. **Jangan menambahkan fitur yang tidak diminta.** Tidak ada pengingat, layar pemantau, pendelegasian persetujuan, tombol salin, atau pengisian otomatis. Seluruhnya sudah dipertimbangkan dan sengaja tidak dibangun.

---

## 6. Status tiap dokumen

| Dokumen | Status | Catatan |
|---|---|---|
| Alur dan tabel bersama | **siap** | |
| RTS | **siap** | seluruh salah ketik sudah diperbaiki sekolah dan diikuti seed |
| Agama | **siap** | dua ejaan dipilih dari bentuk mayoritas di dokumen, dicocokkan lagi saat contoh terisi diterima. Lihat bagian 9.1 spesifikasinya |
| Ummi | **siap** | |
| Bahasa Inggris | **V1 diterima untuk pilot** | gunakan teks/definisi seed saat ini apa adanya. Perbaikan copy ditunda ke versi rubrik berikutnya dan tidak mengubah dokumen yang sudah terbit |
| PPI | **siap** | alur Hasil Capaian setelah rapor terbit **bukan untuk pilot**, lihat bagian 6 spesifikasinya |
| RAS | **belum dispesifikasi** | menggantikan RTS di akhir semester. Jangan menanam asumsi bahwa RTS selalu ada di tiap sesi |
| Data Murid | di luar paket ini | |

Keputusan implementasi 25 September 2026: untuk Rapor Ummi, cetakan tetap menampilkan sedikitnya dua baris tes dan mengisi sel kosong dengan `—`. Aturan ini mengikuti `SPEK_RUBRIK_UMMI.md` dan mengesampingkan catatan cetak lama dalam metadata `rubrik_ummi_seed.json`; seed V1 tidak diubah.

---

## 7. Yang tidak ada di paket ini

- **Spesifikasi RAS.** Menyusul.
- **Berkas dokumen asli sekolah** (PDF, Word, Excel). Developer diberi akses terpisah. Kalau ada yang berbeda antara berkas asli dan paket ini, kabari supaya spesifikasinya diperbarui.
- **Desain antarmuka.** Rujukannya berkas Figma dan panduan desain yang diberikan terpisah.
