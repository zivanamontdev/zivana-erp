<?php
/** Read-only: validates official definitions without loading .env or accessing DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/models/EraporUmmiPpiSeed.php';
$ummi=EraporUmmiPpiSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_ummi_seed.json');
$ppi=EraporUmmiPpiSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_ppi_seed.json');
echo json_encode([
    'ummi'=>['jilid'=>count($ummi['jilid']),
        'materi'=>array_sum(array_map(fn($j)=>count($j['materi']),$ummi['jilid'])),
        'skala'=>count($ummi['rubrik']['skala']['huruf']['nilai']),
        'bagian_wajib'=>array_column(array_filter($ummi['rubrik']['bagian'],fn($b)=>$b['wajib']),'kode')],
    'ppi'=>['aspek'=>count($ppi['aspek']),'kolom_cetak'=>count($ppi['kolom']),
        'textarea_wajib'=>count($ppi['aspek'])*count(array_filter($ppi['kolom'],fn($c)=>$c['wajib'] && $c['diisi_di_sesi'])),
        'kolom_di_luar_sesi'=>array_column(array_filter($ppi['kolom'],fn($c)=>!$c['diisi_di_sesi']),'kode')],
    'applied'=>false,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
