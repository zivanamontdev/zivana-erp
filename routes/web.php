<?php

/**
 * Pendaftaran route. $router disiapkan oleh public/index.php sebelum
 * file ini di-require. Lihat cookbook/architecture.md bagian 2.
 *
 * @var Router $router
 */

// --- Auth ---
$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/akses-terbatas', [AuthController::class, 'noAccess']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/lupa-kata-sandi', [AuthController::class, 'showForgotPassword']);
$router->post('/lupa-kata-sandi', [AuthController::class, 'sendResetLink']);
$router->get('/reset-kata-sandi/{token}', [AuthController::class, 'showResetForm']);
$router->post('/reset-kata-sandi/{token}', [AuthController::class, 'resetPassword']);

// --- Sistem / RBAC ---
$router->get('/rbac', [RbacController::class, 'index']);
$router->post('/rbac', [RbacController::class, 'update']);

// --- Sekolah ---
$router->get('/sekolah', [SekolahController::class, 'index']);
$router->post('/sekolah', [SekolahController::class, 'update']);
$router->post('/sekolah/tahun-ajaran', [SekolahController::class, 'updateTahunAjaran']);

// --- Human Capital: Jabatan ---
$router->get('/jabatan', [JabatanController::class, 'index']);
$router->post('/jabatan', [JabatanController::class, 'store']);
$router->post('/jabatan/{id}', [JabatanController::class, 'update']);
$router->post('/jabatan/{id}/hapus', [JabatanController::class, 'destroy']);
$router->post('/jabatan/{id}/nonaktifkan', [JabatanController::class, 'deactivate']);
$router->post('/jabatan/{id}/aktifkan', [JabatanController::class, 'activate']);

// --- Human Capital: Daftar Karyawan ---
$router->get('/karyawan', [KaryawanController::class, 'index']);
$router->post('/karyawan', [KaryawanController::class, 'store']);
$router->post('/karyawan/{id}', [KaryawanController::class, 'update']);
$router->post('/karyawan/{id}/kata-sandi', [KaryawanController::class, 'updatePassword']);
$router->post('/karyawan/{id}/hapus', [KaryawanController::class, 'destroy']);
$router->post('/karyawan/{id}/nonaktifkan', [KaryawanController::class, 'deactivate']);
$router->post('/karyawan/{id}/aktifkan', [KaryawanController::class, 'activate']);

// --- Murid: Manajemen Kelas ---
$router->get('/kelas', [KelasController::class, 'index']);
$router->post('/kelas', [KelasController::class, 'store']);
$router->get('/kelas/{id}', [KelasController::class, 'show']);
$router->post('/kelas/{id}', [KelasController::class, 'update']);
$router->post('/kelas/{id}/hapus', [KelasController::class, 'destroy']);
$router->post('/kelas/{id}/guru-murid', [KelasController::class, 'saveGuruMurid']);
$router->post('/kelas/{id}/guru/{guruId}/hapus', [KelasController::class, 'removeGuru']);

// --- Murid: Manajemen Murid ---
// Urutan penting: /murid/tambah harus terdaftar SEBELUM /murid/{id}
// supaya tidak ke-tangkap sebagai {id}="tambah" oleh Router.
$router->get('/murid', [MuridController::class, 'index']);
$router->get('/murid/tambah', [MuridController::class, 'create']);
$router->post('/murid', [MuridController::class, 'store']);
$router->get('/murid/{id}/ubah', [MuridController::class, 'edit']);
$router->post('/murid/{id}', [MuridController::class, 'update']);
$router->get('/murid/{id}', [MuridController::class, 'show']);

// --- Human Capital: Manajemen Guru ---
$router->get('/manajemen-guru', [ManajemenGuruController::class, 'index']);
$router->post('/manajemen-guru/{guruId}/murid', [ManajemenGuruController::class, 'saveMurid']);

// --- Sekolah: Kurikulum > Periode Penilaian ---
$router->get('/kurikulum/periode-penilaian', [PeriodePenilaianController::class, 'index']);
$router->post('/kurikulum/periode-penilaian', [PeriodePenilaianController::class, 'store']);
$router->post('/kurikulum/periode-penilaian/{id}', [PeriodePenilaianController::class, 'update']);
$router->post('/kurikulum/periode-penilaian/{id}/hapus', [PeriodePenilaianController::class, 'destroy']);

// --- Sekolah: Kurikulum > Manajemen Template ---
$router->get('/kurikulum/manajemen-template', [TemplateRaporController::class, 'index']);
$router->get('/kurikulum/manajemen-template/pratinjau/{semester}', [TemplateRaporController::class, 'previewSemester']);
$router->get('/kurikulum/manajemen-template/{id}', [TemplateRaporController::class, 'show']);
$router->get('/kurikulum/manajemen-template/{id}/pdf', [TemplateRaporController::class, 'downloadPdf']);

// --- Murid: Rapor Murid (Admin) ---
$router->get('/rapor-murid', [RaporMuridController::class, 'index']);
$router->post('/rapor-murid/{id}/setujui', [RaporMuridController::class, 'approve']);
$router->get('/rapor-murid/{id}/pdf', [RaporMuridController::class, 'downloadPdf']);
$router->get('/rapor-murid/{id}', [RaporMuridController::class, 'show']);

// --- Portal Guru ---
$router->get('/portal-guru/dashboard', [PortalGuruController::class, 'dashboard']);
$router->get('/portal-guru/murid', [PortalGuruController::class, 'daftarMurid']);
$router->get('/portal-guru/murid/{id}', [PortalGuruController::class, 'showMurid']);
$router->get('/portal-guru/rapor/{id}/pdf', [PengisianRaporController::class, 'downloadPdf']);
$router->get('/portal-guru/rapor/{id}', [PengisianRaporController::class, 'show']);
$router->post('/portal-guru/rapor/{id}/simpan', [PengisianRaporController::class, 'simpan']);
$router->post('/portal-guru/rapor/{id}/selesaikan', [PengisianRaporController::class, 'selesaikan']);
$router->get('/portal-guru/rapor/{id}/pratinjau', [PengisianRaporController::class, 'pratinjau']);
