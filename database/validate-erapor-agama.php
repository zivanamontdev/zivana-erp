<?php
/** Read-only definition validation; no environment or database access. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/models/EraporAgamaSeed.php';
$seed=EraporAgamaSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_agama_seed.json');
$semesters=['GANJIL'=>0,'GENAP'=>0]; $names=0; $groups=0;
foreach ($seed['ruang_lingkup'] as $scope) foreach ($scope['sub'] as $sub) foreach ($sub['item'] as $item) {
    $semesters[$item['semester']]++;
    if (isset($item['nama'])) { $groups++; $names+=count($item['nama']); }
}
echo json_encode([
    'kode'=>$seed['rubrik']['kode'],'ruang_lingkup'=>count($seed['ruang_lingkup']),
    'butir'=>array_sum($semesters),'semester'=>$semesters,
    'kelompok_asmaul_husna'=>$groups,'nama_asmaul_husna'=>$names,
    'pilihan_dropdown'=>count($seed['rubrik']['skala']['pilihan_dropdown']),
    'catatan_wajib'=>count($seed['ruang_lingkup']),'applied'=>false,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
