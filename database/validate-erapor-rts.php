<?php
/** Read-only CLI validation. No database connection or application writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/models/EraporRtsSeed.php';
try {
    $seed=EraporRtsSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_rts_seed.json');
    $areas=[]; $groups=[]; $named=0; $implicit=0;
    foreach ($seed['area'] as $area) {
        $count=0;
        foreach ($area['sub_area'] as $sub) {
            $sub['implisit'] ? $implicit++ : $named++;
            $count+=count($sub['indikator']);
            foreach ($sub['indikator'] as $item) if ($item['grup']!==null) $groups[$sub['kode'].'/'.$item['grup']]=true;
        }
        $areas[$area['kode']]=$count;
    }
    echo json_encode(['rubrik'=>$seed['rubrik']['kode'],'areas'=>$areas,'indicators'=>array_sum($areas),
        'named_subareas'=>$named,'implicit_subareas'=>$implicit,'groups'=>count($groups),
        'scale_values'=>count($seed['rubrik']['skala']),'database_modified'=>false],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'RTS validation failed: '.$error->getMessage().PHP_EOL); exit(1);
}
