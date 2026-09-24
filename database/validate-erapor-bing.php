<?php
/** Definition validation only; no environment, DB connection or writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/models/EraporBingSeed.php';
$seed=EraporBingSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_bing_seed.json');
echo json_encode([
    'kode'=>$seed['rubrik']['kode'],
    'indikator'=>count($seed['indikator']),
    'komentar'=>count($seed['komentar']),
    'skala'=>array_column($seed['skala'],'peringkat','kode'),
    'applied'=>false,
    'warning'=>'Official seed preserved. Reconcile revised PDF text before application activation; no silent spelling/definition changes.',
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
