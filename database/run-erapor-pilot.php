<?php

/** Private cPanel Cron entry point; this file is never an HTTP migration route. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

$root = dirname(__DIR__);
require_once $root . '/app/models/EraporPilotInstaller.php';

$mode = $argv[1] ?? '--help';
if ($mode === '--plan') {
    try {
        echo json_encode(EraporPilotInstaller::plan($root),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, '[eRapor plan] Failed: ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (!in_array($mode, ['--check', '--apply-production-schema-only'], true)) {
    fwrite(STDERR, "Usage:\n  php database/run-erapor-pilot.php --plan\n  php database/run-erapor-pilot.php --check\n  php database/run-erapor-pilot.php --apply-production-schema-only\n");
    exit($mode === '--help' ? 0 : 64);
}

$stage = 'bootstrap';
try {
    define('ROOT_PATH', $root);
    define('CONFIG_PATH', ROOT_PATH . '/config');
    require ROOT_PATH . '/config/config.php';
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    require_once ROOT_PATH . '/app/core/Database.php';
    $db = Database::getInstance();
    $status = EraporPilotInstaller::preflight($db, ROOT_PATH);

    echo '[eRapor] Preflight passed; database server: ' . $status['server'] . PHP_EOL;
    echo '[eRapor] ' . $status['migration_count'] . '/' . $status['migration_total']
        . ' migrations complete; rubric seeds=' . $status['rubric_count']
        . '; approval stages=' . $status['approval_stage_count'] . PHP_EOL;
    if ($mode === '--check') {
        echo '[eRapor] Check-only mode: no writes performed.' . PHP_EOL;
        exit(0);
    }

    $result = EraporPilotInstaller::apply($db, ROOT_PATH,
        static function (string $message) use (&$stage): void {
            $stage = $message;
            fwrite(STDOUT, '[eRapor] ' . $message . PHP_EOL);
        });
    if ($result['already_complete']) {
        echo '[eRapor] Schema and pilot seeds were already complete; no changes needed.' . PHP_EOL;
    } else {
        echo '[eRapor] ERAPOR_API_ENABLED remains false.' . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, '[eRapor] STOP at ' . $stage . ': ' . $error->getMessage() . PHP_EOL);
    fwrite(STDERR, '[eRapor] Do not delete migration ledger rows or retry after partial DDL; preserve this output for inspection.' . PHP_EOL);
    exit(1);
}
