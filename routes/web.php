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
