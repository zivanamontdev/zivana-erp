<?php

/**
 * Front controller tunggal aplikasi Zivana ERP.
 * Native PHP, tanpa framework — lihat cookbook/architecture.md.
 */

// --- Konstanta path dasar ---
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CORE_PATH', APP_PATH . '/core');
define('MODEL_PATH', APP_PATH . '/models');
define('CONTROLLER_PATH', APP_PATH . '/controllers');
define('MIDDLEWARE_PATH', APP_PATH . '/middleware');
define('VIEW_PATH', APP_PATH . '/views');
define('HELPER_PATH', APP_PATH . '/helpers');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('BASE_PATH', ''); // isi kalau aplikasi di-deploy di subfolder, mis. '/erp'

// --- Autoload dependency pihak ketiga (Composer) ---
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

// --- Autoload manual untuk class aplikasi sendiri ---
spl_autoload_register(function (string $class): void {
    $searchPaths = [CORE_PATH, MODEL_PATH, CONTROLLER_PATH, MIDDLEWARE_PATH];

    foreach ($searchPaths as $path) {
        $file = $path . '/' . $class . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// --- Helper functions (bukan class, jadi tidak lewat autoload) ---
if (file_exists(HELPER_PATH . '/functions.php')) {
    require HELPER_PATH . '/functions.php';
}

if (file_exists(HELPER_PATH . '/ui.php')) {
    require HELPER_PATH . '/ui.php';
}

// --- Konfigurasi aplikasi (.env, konstanta DB/APP) ---
if (file_exists(CONFIG_PATH . '/config.php')) {
    require CONFIG_PATH . '/config.php';
}

// --- Routing ---
if (file_exists(CORE_PATH . '/Router.php') && file_exists(ROOT_PATH . '/routes/web.php')) {
    $router = new Router();
    require ROOT_PATH . '/routes/web.php';

    // Lihat cookbook/security.md bagian 4 — exception tak tertangani
    // tidak boleh bocor ke publik sebagai stack trace mentah saat
    // APP_DEBUG=false. Detail tetap dicatat via error_log() untuk
    // debugging, ditampilkan ke layar hanya kalau APP_DEBUG true.
    try {
        $router->dispatch();
    } catch (Throwable $exception) {
        error_log($exception->getMessage() . "\n" . $exception->getTraceAsString());
        http_response_code(500);
        require VIEW_PATH . '/errors/500.php';
    }
} else {
    echo 'Zivana ERP — bootstrap siap, routing belum dikonfigurasi.';
}
