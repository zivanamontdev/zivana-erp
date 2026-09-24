# Provisioning penugasan persetujuan eRapor

Halaman ini adalah konfigurasi operasional untuk alur persetujuan tiga tahap yang sudah ditentukan. Ia tidak mengubah urutan/scope, tidak memilih orang otomatis, dan tidak memberikan permission role.

## Urutan penerapan

1. Backup database dan pastikan katalog rubrik resmi telah di-seed.
2. Terapkan migrasi `20260924_erapor_approval_flow.sql` bila belum ada, lalu migrasi `20260925_erapor_approval_assignment_audit.sql` lewat prosedur eRapor yang sama.
3. Import `database/seeds/20260925_erapor_approval_setup.sql` melalui phpMyAdmin. Seed ini idempotent dan tidak menimpa nilai alur yang sudah ada. Ia menambahkan permission RBAC, tiga baris alur, dan mapping semua rubrik UMMI/BING yang telah tersedia; ia sengaja tidak membuat assignment user.
4. Jika versi rubrik UMMI/BING ditambahkan kemudian, jalankan ulang seed untuk mengisi mapping baru. Jika flow yang sudah ada berbeda dari kode, urutan, scope, atau status wajib, hentikan dan audit secara manual; aplikasi akan menolak assignment/reception daripada memperbaiki konfigurasi diam-diam.
5. Lewat RBAC, berikan `eRapor > Penugasan Penyetuju` kepada role pengelola dan `eRapor > Persetujuan` kepada role calon penyetuju. Guru tidak pernah dapat membuka setup meskipun role checkbox setup diberi secara manual.
6. Login ulang, buka `Sistem > Penugasan Penyetuju`, pilih minimal satu akun aktif per tahap, dan berikan alasan perubahan. Koordinator boleh ditetapkan sebagai tugas kepada pegawai aktif yang memiliki permission persetujuan; tahap kepala sekolah hanya menerima jabatan `Kepala Sekolah`.
7. Verifikasi audit perubahan dan login masing-masing penyetuju. Penugasan yang dicabut tidak lagi dapat membuka pekerjaan pending; snapshot persetujuan yang sudah terjadi tetap utuh.

Flow tetap: Koordinator Al-Qur’an untuk dokumen UMMI, Koordinator Bahasa Inggris untuk BING (keduanya tahap pertama/paralel), lalu Kepala Sekolah untuk seluruh paket, termasuk PPI pada murid ABK.

Migrasi dan seed ini belum mengaktifkan `ERAPOR_API_ENABLED`. Flag produksi tetap OFF sampai UI browser, profil tanda tangan, PDF, dan alur publikasi selesai diverifikasi.
