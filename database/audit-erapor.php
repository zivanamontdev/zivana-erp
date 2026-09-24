<?php

// Local CLI only; no migrations, writes, account data, or public HTTP endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
require CONFIG_PATH . '/config.php';
require ROOT_PATH . '/app/core/Database.php';
require ROOT_PATH . '/app/models/EraporLegacyAudit.php';
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!in_array(DB_HOST, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, "Audit CLI ini dibatasi ke database lokal. Gunakan salinan database hosting.\n");
    exit(1);
}
$db = null;
try {
    $db = Database::getInstance();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $result = EraporLegacyAudit::inspect($db);
    $db->rollBack();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    $code = $e instanceof PDOException ? (string) ($e->errorInfo[1] ?? $e->getCode()) : 'internal';
    fwrite(STDERR, "Audit gagal (kode database: $code). Periksa koneksi lokal dan kelengkapan skema lama; tidak ada migrasi dijalankan.\n");
    exit(1);
}
