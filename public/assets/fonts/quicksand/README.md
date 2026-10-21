# Quicksand untuk dokumen rapor

Sumber: [Google Fonts / Quicksand](https://github.com/google/fonts/tree/main/ofl/quicksand), versi 3.006, diambil 21 Oktober 2026. Lisensi SIL Open Font License disertakan pada `OFL.txt`.

- `Quicksand-Regular.ttf` dan `Quicksand-Bold.ttf` adalah instance statis weight 400/700 dari `Quicksand[wght].ttf`, dibuat dengan fontTools `instantiateVariableFont`. Nama style/PostScript dinormalisasi untuk membedakan font tertanam.
- `.ufm` dibuat memakai `FontLib\Font::saveAdobeFontMetrics()` dari dependency Composer yang sudah tersedia.
- `installed-fonts.json` memakai nama relatif, sehingga tidak menyimpan path komputer pengembang. `EraporPrintFont::options()` memuat registry ini langsung; tidak perlu instalasi font, internet, Python, atau Composer di hosting.
- Cache metrik runtime Dompdf berada di direktori sementara sistem, bukan di source/vendor. Font sumber tidak diubah saat render.
- Font hanya untuk isi kertas rapor/PDF. Font shell aplikasi tetap mengikuti design system.

Saat memperbarui font, hasilkan ulang kedua `.ufm` dan bersihkan cache metrik sementara yang terkait bila diperlukan. Jangan mengganti font/gambar pada artefak PDF resmi historis.
