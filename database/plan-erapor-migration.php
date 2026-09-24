<?php
/** Read-only CLI plan: no .env, DB connection, or migration application. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/models/EraporMigrationRunner.php';
$plans=[];
foreach (['20260924_erapor_catalog.sql','20260924_erapor_rts.sql','20260924_erapor_ummi_ppi.sql','20260924_erapor_bing.sql','20260924_erapor_agama.sql'] as $file) {
    $plan=EraporMigrationRunner::plan(__DIR__.'/migrations/'.$file);
    $plans[]=['migration'=>$plan['id'],'sha256'=>$plan['sha256'],'creates'=>array_keys($plan['steps'])];
}
echo json_encode([
    'migrations' => $plans, 'alters_legacy_tables' => false,
    'applied' => false, 'note' => 'Plan only; no database connection. Test on a restored backup before deployment.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
