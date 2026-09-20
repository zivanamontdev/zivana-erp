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

// --- Human Capital: Daftar Karyawan ---
$router->get('/karyawan', [KaryawanController::class, 'index']);
$router->post('/karyawan', [KaryawanController::class, 'store']);
$router->post('/karyawan/{id}', [KaryawanController::class, 'update']);
$router->post('/karyawan/{id}/kata-sandi', [KaryawanController::class, 'updatePassword']);
$router->post('/karyawan/{id}/hapus', [KaryawanController::class, 'destroy']);
