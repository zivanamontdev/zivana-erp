# Hasil uji browser eRapor — 18 Oktober 2026

**Hasil:** alur revisi pengisian → Finalisasi → approval → PDF → publikasi → berbagi/unduh lulus setelah tiga temuan tambahan diperbaiki. Ini uji browser pada aplikasi sebenarnya, bukan hanya unit test atau mock DOM. Cakupan laporan adalah modul yang terdampak revisi ini, **bukan audit seluruh CRUD ERP**.

## Lingkungan dan pengamanan

- Chrome Headless 155, interaksi DOM serta klik mouse melalui CDP. Tampilan screenshot di bawah diperiksa langsung.
- Ukuran: **1440×900, 1280×832, 768×1024, 390×844, 375×812**.
- Kode aplikasi yang sama dijalankan pada server uji terpisah `127.0.0.1:8013`; layanan pengguna di port 8000 tidak diubah.
- Database uji merupakan salinan lokal. Nama murid/pegawai dan TTD pengujian dibuat sintetis; perubahan tenggat, mapping, kontak, dan nilai hanya di salinan.
- Delapan konteks browser terpisah: PIC Regular, PIC ABK, Koordinator Agama, koordinator BING sekaligus wali, Kepala Sekolah, guru lain, Superadmin, dan tamu.
- Publikasi browser memakai **storage lokal pengganti R2** agar tidak menambah objek cloud. Tombol berbagi diuji tanpa membuka/mengirim WhatsApp sungguhan. Unduhan dua objek **R2 asli** diuji baca-saja tanpa login/cookie: HTTP 200, MIME PDF, attachment, hash sama dengan artefak sumber.
- Fingerprint seluruh **82 tabel sumber**, termasuk nilai, profil, assignment dan PDF resmi lama, dibandingkan sebelum/sesudah. Database uji serta server/browser uji dibersihkan sesudah pengujian.

## Skenario dan hasil

| Area | Uji melalui browser | Hasil |
|---|---|---|
| Dashboard | Murid sesuai PIC, status Lanjutkan Draft, desktop/mobile | Lulus |
| Editor | Empat tab Regular; ABK lima tab; section collapse; filter Semua/Belum Diisi | Lulus |
| Autosave | Jawaban lama diubah berulang, dilanjutkan jawaban berikutnya, reload lalu baca nilai lewat API nyata | Lulus |
| Respons hilang | Respons save HTTP 200 diputus **sesudah commit server**, lalu jawaban yang sama diubah lagi | Lulus; batch identik diulang sebelum perubahan terbaru |
| Respons lambat | Respons ditahan ketika guru mengubah jawaban yang sedang disimpan | Lulus; jawaban terakhir tersimpan |
| Offline | Simpan Draft ketika offline, kontrol recovery/toast, kembali online, ulang simpan | Lulus; tidak mengklaim data sudah tersimpan saat gagal |
| Token keamanan | Retry save sempat menerima 419 setelah respons hilang | Pulih dengan token baru; tidak kehilangan isian |
| Agama | Dropdown custom mobile, `-` disimpan sebagai isian sah, satu narasi | Lulus |
| Ummi | Inisialisasi eksplisit, PRA TK, batal/hapus nilai, aktifkan ulang kosong, tambah tes dengan nomor otomatis | Lulus setelah perbaikan reload |
| BING | Mulai pengisian opsional, dropdown dan empat catatan; tidak menghalangi kelengkapan saat kosong | Lulus |
| ABK/PPI | 30 textarea; satu isian kosong menghalangi selesai; filter; isian terakhir tidak tertutup pager | Lulus |
| Status | Selesaikan → TELAH_DIISI; edit jawaban lama tetap boleh; Finalisasi → MENUNGGU_TTD | Lulus |
| Penguncian | Semua nilai menjadi baca-saja; percobaan paksa tulis setelah Finalisasi mendapat 409 | Lulus setelah kontrol tes Ummi diperbaiki |
| Urutan approval | Wali menunggu kedua koordinator; Kepala Sekolah menunggu wali; bukan hanya pembatasan tombol | Lulus |
| Akun rangkap | BING dan wali oleh orang yang sama; dua aksi dan dua riwayat, TTD tiap peran baru muncul setelah aksinya | Lulus |
| Mapping | Modal Detail Kelas menampilkan wali yang ditetapkan, bukan PIC pengisi | Lulus |
| PDF | Draft bersih, watermark mulai setelah Finalisasi saat masih menunggu approval, TTD bertahap, nama file | Lulus |
| Paket final | Regular 17 halaman dan ABK/PPI 15 halaman; tata letak seluruh halaman dan detail blok TTD diperiksa | Lulus |
| Publikasi | Setelah head approve PDF tersimpan; tombol Terbitkan mengubah sesi SELESAI melalui storage uji | Lulus |
| Berbagi | Tanpa kontak disabled; satu kontak langsung; dua kontak dropdown Ayah/Ibu | Lulus |
| Unduh tamu | Link aplikasi tanpa login, PDF byte-identik dengan artefak resmi, filename nama anak | Lulus |
| Akses negatif | Guru lain mendapat 404 PDF; token tidak dikenal mendapat 404 tanpa membocorkan identitas | Lulus |
| Responsive/tema | Tidak ada overflow halaman pada lima ukuran; warna/font/komponen shared terpakai | Lulus |

Antrean persetujuan pada mobile menggunakan **scroll horizontal di dalam container tabel**, bukan melebar ke seluruh halaman. Ini perilaku tabel yang sudah ada; kolom kanan tetap dapat dijangkau dengan menggeser tabel.

## Temuan tambahan yang diperbaiki dan diuji ulang

1. **Inisialisasi Ummi memicu peringatan perubahan belum tersimpan saat reload.** Flag `saving` belum dilepas meskipun respons berhasil. Kini reload hanya dilakukan setelah acknowledgement, dan tidak menimpa perubahan lain yang masuk selama request. Diuji ulang pada paket ABK tanpa dialog unload palsu.
2. **Kolom tes Ummi masih tampak dapat diedit sesudah Finalisasi.** Server sudah menolak, tetapi tanggal/jilid/nilai serta tombol kalender belum disabled. Kini semua kontrol dikunci menggunakan state komponen UI yang sama. Ada regresi markup dan pengujian browser setelah reload.
3. **Halaman tautan unduh tidak berlaku belum memuat token warna/font aplikasi.** Kini memakai shared `layouts/head.php`, `uiCard`, dan `uiText`, tanpa warna baru. Diuji ulang pada mobile dan tablet.

Tidak ada skema/migrasi baru. TTD/stempel yang menyatu di PNG diperbesar bersama; tidak dibuat fitur pengelolaan stempel terpisah. PDF resmi lama tidak dirender ulang.

## Bukti visual (data sintetis)

- [Editor desktop](../docs/testing/erapor-20261018/editor-desktop.png) · [editor mobile](../docs/testing/erapor-20261018/editor-mobile.png)
- [Offline dan pemulihan](../docs/testing/erapor-20261018/autosave-offline-mobile.png)
- [Modal Finalisasi](../docs/testing/erapor-20261018/finalisasi-mobile.png) · [modal approval](../docs/testing/erapor-20261018/approval-modal-mobile.png)
- [PPI dan pager mobile](../docs/testing/erapor-20261018/ppi-mobile.png)
- [Mapping wali kelas](../docs/testing/erapor-20261018/class-mapping-desktop.png)
- [Pilihan kontak orang tua](../docs/testing/erapor-20261018/share-dropdown-mobile.png)
- [Dua kolom TTD satu orang](../docs/testing/erapor-20261018/pdf-two-role-signatures.png)
- [PPI draft bersih](../docs/testing/erapor-20261018/pdf-ppi-draft-clean.png) · [PPI final berwatermark](../docs/testing/erapor-20261018/pdf-ppi-watermark.png)
- [Halaman tautan unduh tidak berlaku](../docs/testing/erapor-20261018/download-error-mobile.png)

## Batas yang tidak diklaim

- Bukan pengujian pada HP fisik, Safari/iOS, Firefox, atau production.
- Tidak melakukan publikasi/upload R2 asli atau mengirim WhatsApp sungguhan.
- Tidak membuat/menguji RAS karena spesifikasi dan seed belum tersedia.
- Pengisian offline tetap ditahan dalam memori halaman, bukan penyimpanan offline permanen. Jangan menutup halaman jika belum tersimpan.
- Watermark adalah penanda visual; bukan tanda tangan elektronik tersertifikasi.
