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
