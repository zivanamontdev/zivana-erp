# Cetak dan preview Quicksand — 21 Oktober 2026

## Perubahan

- Renderer **`erapor-print-v10`** dipakai untuk render baru, termasuk draft, template, PDF persetujuan, dan PPI standalone.
- Kolom tanda terima **Orang Tua Siswa dihapus hanya dari UMMI dan BING**. Semua penandatangan sekolah/peran rangkap tetap dipertahankan. RTS, Agama dan PPI masih memiliki kolom orang tua; kolom Hasil Capaian Orangtua di PPI tidak dihapus.
- Isi kertas menggunakan **Quicksand Regular/Bold**, tertanam di PDF dan dibundel lokal beserta SIL OFL. Tidak memerlukan internet, instalasi font, Python, atau build di hosting. Font dan warna shell aplikasi tetap mengikuti tema sebelumnya.
- Area gambar TTD menjadi 116pt; batas gambar Kepala **108pt** (sebelumnya 72pt), penandatangan lain 90pt. Lebar menyesuaikan jumlah kolom, maksimum 180pt, dengan perhitungan khusus margin PPI. Gambar kecil ikut diperbesar, tanpa mengubah file profil/snapshot aslinya.
- Ukuran akhir gambar dihitung dari rasio PNG. Ditemukan saat pengujian bahwa `width` + `max-height` saja dapat mendistorsi gambar pada Dompdf; kini lebar dan tinggi dihitung bersama, termasuk gambar vertikal/persegi dan cap.
- Preview HTML legacy juga memakai Quicksand, terbatas pada `.template-paper`. Padding vertikal tabel diselaraskan ke 4px agar seluruh isi empat lembar tetap muat pada geometri kertas yang sama. Jalur PDF legacy memakai konfigurasi font yang sama.

## Pengujian

Seluruh alur mutasi menggunakan database lokal salinan yang sudah terisolasi (`:8013`), identitas/isian/gambar sintetis, dan storage file lokal, bukan production atau R2 asli. Kasus uji pengguna sebelumnya tidak direset.

| Pemeriksaan | Hasil |
| --- | --- |
| Struktur renderer | **65** pemeriksaan lulus: kebijakan kolom orang tua per jenis, peran rangkap, tanda tangan, rasio/ukuran empat bentuk gambar, watermark, semester dan isi Ummi. |
| Dompdf nyata | **34** pemeriksaan lulus: font lokal normal/bold, registry font portabel tanpa penulisan ulang, draft bersih dan watermark di belakang isi setelah Finalisasi. |
| Workflow PPI MySQL | **76** pemeriksaan lulus pada clone sekali pakai: included/excluded/late-excluded, persetujuan, rollback, PDF immutable, penyimpanan, akses dan distribusi. |
| Recovery MySQL | **13** pemeriksaan lulus pada clone sekali pakai. |
| Browser eRapor | **47** assertion lulus: reguler tanpa PPI, ABK included, ABK separate, standalone PPI, template lima jenis × dua semester, editor 1440/768/390/375px, shell tetap memakai Plus Jakarta Sans, tidak ada overflow/exception JS. |
| Browser HTML legacy | **26** assertion lulus: empat kertas × Tengah/Akhir, viewport 1440/768/390/375px, font lokal ter-load, tidak ada isi melebihi tinggi kertas, unduhan PDF berhasil. |
| Analisis hasil PDF | **206** assertion lulus pada **23 PDF / 191 halaman**: seluruh teks memakai Quicksand Regular/Bold tertanam, tidak ada teks di luar kertas/glyph pengganti, kebijakan kolom orang tua, gambar pengesahan tetap di dalam halaman. Semua halaman memiliki teks. |
| Isi panjang | Paket **27 halaman**, nama panjang, catatan Ummi/BING multiline serta 30 isian PPI panjang. **35 penanda akhir** ditemukan seluruhnya setelah normalisasi whitespace akibat wrapping, tidak ada isian terpotong. |
| Bentuk TTD | PNG vertikal, lebar, kecil, persegi diuji melalui PDF nyata; rasio gambar terjaga. Contoh vertikal: guru 33.75×90pt, Kepala 40.5×108pt; persegi: guru 90×90pt, Kepala 108×108pt. |
| Preview versus unduhan | Bytes identik untuk tiga PDF utama baru dan PPI standalone. |
| Regresi lain | API adapter 37, RBAC 91, filename 10, publication signer 7, signer snapshot 16, sharing UI 12, badge 7, overview 13, session/approval UI, template/access/account regression dan JS autosave lulus. PHP lint dan `git diff --check` lulus. |

Seluruh halaman hasil render dirasterisasi ke contact sheet dan ditinjau, dengan pemeriksaan resolusi lebih besar pada blok pengesahan Ummi, English, RTS dan PPI. Contoh cap Kepala sengaja sintetis. Bukti aman terpilih serta ledger ada di [`docs/testing/erapor-print-quicksand-20261021/`](../docs/testing/erapor-print-quicksand-20261021/). Contact sheet tambahan dan PDF mentah sintetis berada dalam folder privat `.local-ppi-test/`.

Fingerprint isi **82 tabel database utama tetap identik** dengan baseline. Hash PDF resmi yang sudah ada dalam salinan diverifikasi tetap sama selama persiapan contoh. Profil TTD Kepala pada salinan dikembalikan setelah membuat snapshot persetujuan contoh; tidak ada penggantian profil asli/production. Browser audit, konteks autentikasi, dan sesi milik audit ditutup; server manual `:8013` tetap aktif.

## Contoh yang dapat dibuka pengguna

Buka **http://127.0.0.1:8013/__local-test**, login **akun 04 / Guru PIC (Ainul Annisa) pada `credentials`**. Password bersama mengacu pada **baris berawalan `pass:`** (nomor baris berubah setelah anotasi role); tidak disalin ke laporan. Panduan privat diperbarui di `.local-ppi-test/AKSES-LOKAL.md`.

| Contoh sintetis terbaru | Preview PDF |
| --- | --- |
| `[UJI PDF Quicksand] 01 Reguler tanpa PPI` | http://127.0.0.1:8013/erapor/sesi/17/pdf |
| `[UJI PDF Quicksand] 02 ABK dengan PPI` | http://127.0.0.1:8013/erapor/sesi/18/pdf |
| `[UJI PDF Quicksand] 03 ABK PPI terpisah` — rapor utama | http://127.0.0.1:8013/erapor/sesi/19/pdf |
| PPI standalone untuk contoh 03 | http://127.0.0.1:8013/erapor/ppi/19/pdf |

Gunakan print/download pada PDF viewer, atau tambahkan `?unduh=1` untuk unduhan aplikasi. Editor berada di `/portal-guru/sesi/17`, `/18`, `/19`, pada periode `[UJI] Tengah Ganjil - PPI Lokal`. Jika server mati, jalankan `.local-ppi-test/MULAI-UJI.cmd`; jangan jalankan ulang script persiapan/reset. Folder privat dan file `credentials` tetap diabaikan Git serta tidak boleh diunggah ke hosting.

## Deployment dan batas

**Tidak ada migration baru.** Deploy kode beserta seluruh `public/assets/fonts/quicksand/` dan helper `EraporPrintFont.php`; jangan hanya menyalin CSS/renderer.

PDF resmi historis tetap immutable: hasil produksi lama **tidak berubah otomatis** setelah deployment, termasuk saat dibuka melalui preview yang mengambil artefak tersimpan. Desain baru muncul pada render draft/template dan PDF resmi yang baru disiapkan. Perubahan PDF lama memerlukan keputusan penerbitan ulang berversi, bukan overwrite diam-diam.

Tidak mengakses atau mengubah production dalam pekerjaan ini. Tidak menguji printer fisik, Safari/iOS fisik, atau integrasi R2 production. Pengujian lokal bukan klaim seluruh variasi isi pengguna mungkin sudah tercakup. Tidak commit/push/deploy otomatis.
