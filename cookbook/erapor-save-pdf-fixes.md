# Revisi pengisian dan PDF eRapor — 18 Oktober 2026

Keputusan pengguna: Wali Kelas ikut bertanda tangan. Jika merangkap koordinator, dua kolom peran tetap dicetak, masing-masing setelah persetujuannya sendiri. Logo watermark mulai tampil sejak Guru PIC menekan **Finalisasi**, bukan menunggu persetujuan Kepala Sekolah.

## Perubahan

- Autosave menyimpan batch request yang belum mendapat pengakuan server. Setelah respons putus/timeout, batch yang **identik** dicoba kembali sebelum isian lebih baru. Backend tetap memeriksa expected value dan mempertahankan idempotensi; konflik nyata dari tab lain tidak ditimpa otomatis.
- Retry otomatis dibatasi tiga kali setelah kegagalan pertama, memakai jeda bertambah dan timeout 20 detik. Koneksi kembali atau Coba simpan lagi/Simpan Draft dapat memulai ulang. Isian tetap berada dalam memori halaman; **bukan** penyimpanan offline permanen. Jangan menutup halaman saat ada peringatan belum tersimpan.
- Simpan Draft menunggu seluruh batch dan perubahan terbaru tersimpan; baris tes Ummi yang belum lengkap tidak dianggap berhasil tersimpan. Menutup/memuat ulang halaman saat belum tersimpan memunculkan peringatan browser.
- Wali kelas diambil dari snapshot persetujuan tahap `WALI_KELAS`, berdasarkan penugasan kelas sesi, bukan dari profil PIC. Koordinator dan wali yang sama tetap mempunyai bukti persetujuan terpisah. Mapping kelas dikelola lewat **Detail Kelas → Ubah Data Kelas → Wali Kelas**. Sesi historis tanpa tahap Wali Kelas tetap memakai snapshot guru lama.
- Nama file pratinjau, PDF resmi, dan unduhan R2/orang tua memakai helper yang sama: `Rapor-Nama-Anak-TS-Ganjil-2026-2027.pdf` (pratinjau belum resmi berawalan `Draf-`). Nama dibersihkan agar aman sebagai header/filename. Mengubah nama unduhan PDF lama tidak mengubah byte PDF-nya.
- Renderer `erapor-print-v8`: area TTD 82pt, tinggi gambar maksimum 72pt (sebelumnya 40pt); lebar mengikuti jumlah kolom sehingga empat kolom tidak bertumpuk. Stempel yang menyatu dalam PNG ikut diperbesar; belum ada model/stempel terpisah.
- Watermark PNG `assets/logo-icon.png` digambar pada setiap halaman **sebelum konten**, transparansi 10%, lebar sekitar 88% kertas. Status `BELUM_DIISI`/`TELAH_DIISI` serta template kosong tidak memakai watermark apa pun (termasuk tulisan DRAF lama). `MENUNGGU_TTD`/`SELESAI` memakai logo. Hash asset ikut sumber artefak resmi.

## Deploy dan batasan

- **Tidak ada migrasi atau reset/seed baru.** Deploy kode dan file baru bersama-sama. Skema Agama `-` yang sebelumnya diminta tetap membutuhkan migrasi lama `20260930_erapor_agama_tanpa_keterangan.sql` jika belum terpasang.
- Artefak PDF resmi lama di database maupun R2 **tidak dirender ulang atau ditimpa**. Tampilan baru hanya berlaku untuk render baru; perubahan nama unduhan boleh berlaku pada artefak lama tanpa mengubah isinya.
- RAS tetap ditunda karena spesifikasi/seed belum tersedia.
- Unduhan dua artefak R2 yang ada diuji tanpa cookie/login dan cocok hash-nya. Tidak ada perbaikan perangkat khusus yang diasumsikan tanpa bukti. `APP_URL` production wajib memakai domain publik yang benar; tautan `localhost` lokal tidak dapat dibuka dari perangkat lain. `.env` pengguna tidak diubah.
- Tenggat periode lokal sudah berakhir; pengujian tulis dilakukan di **database disposable hasil kloning**, bukan memperpanjang tenggat atau mengubah nilai lokal asli. Tidak ada unggah/publikasi R2 atau pesan WhatsApp baru selama tes.

## Uji browser lanjutan

Uji langsung pada aplikasi HTTP, desktop/mobile dan alur penuh Regular serta ABK, dicatat dalam [laporan visual 18 Oktober](erapor-ui-validation-20261018.md), lengkap dengan screenshot sintetis. Ditemukan dan diperbaiki tambahan: peringatan unload palsu saat inisialisasi Ummi, kontrol tes Ummi/kalender yang belum disabled setelah Finalisasi, serta halaman token unduh tidak berlaku yang belum memakai shared theme.

## Verifikasi

```bash
node tests/erapor-autosave-ui.js
php tests/erapor-pdf-renderer.php
php tests/erapor-pdf-artifact.php
php tests/erapor-publication-signatures.php
php tests/erapor-pdf-filename.php
php tests/erapor-session-ui.php
php tests/erapor-download-ui.php
php tests/erapor-save-recovery-mysql.php --run
```

Tes MySQL terakhir khusus fixture lokal saat ini: memerlukan satu draft dan satu sesi lengkap yang menunggu approval. Membuat database acak `zivana_fix_test_*`, memeriksa isi sumber tidak berubah, lalu menghapus database disposable. Tidak ditujukan untuk production.

Pemeriksaan tambahan: JS asli dijalankan di headless Chrome dengan respons terputus setelah commit yang disimulasikan; perubahan berikutnya tersimpan setelah retry identik. PDF draft bersih dan PDF terfinalisasi diperiksa secara visual dari render lokal, termasuk TTD Wali Kelas dan ukuran stempel. Bukan pengujian pada HP fisik atau production.
