<?php
require __DIR__.'/../app/models/EraporAgamaSeed.php';
$official=EraporAgamaSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_agama_seed.json');
$cases=[
    fn(&$s)=>$s['rubrik']['cakupan']='SEMESTER',
    fn(&$s)=>$s['rubrik']['cetak_gabung_periode']=false,
    fn(&$s)=>$s['rubrik']['khusus_abk']=true,
    fn(&$s)=>$s['rubrik']['bagian'][1]['wajib']=false,
    fn(&$s)=>$s['rubrik']['kolom_periode'][0]['semester']='GANJIL',
    fn(&$s)=>$s['rubrik']['penandatangan'][2]['peran']='KOORDINATOR_QURAN',
    fn(&$s)=>$s['rubrik']['skala']['bentuk_isian']='checkbox',
    fn(&$s)=>$s['rubrik']['skala']['tahapan'][0]['subtingkat']=[],
    fn(&$s)=>$s['rubrik']['skala']['tahapan'][2]['subtingkat'][0]['kode']='X',
    fn(&$s)=>$s['rubrik']['skala']['pilihan_dropdown'][2]['subtingkat']=null,
    fn(&$s)=>$s['rubrik']['skala']['pilihan_dropdown'][0]['subtingkat']='D',
    fn(&$s)=>$s['rubrik']['skala']['pilihan_dropdown'][3]['kolom_cetak']='TAHFIZH/D',
    fn(&$s)=>array_pop($s['ruang_lingkup']),
    fn(&$s)=>$s['ruang_lingkup'][0]['sub'][0]['item'][0]['semester']='GENAP',
    fn(&$s)=>$s['ruang_lingkup'][0]['sub'][0]['item'][0]['teks']=' ',
    fn(&$s)=>$s['ruang_lingkup'][0]['sub'][0]['item'][1]['kode']=$s['ruang_lingkup'][0]['sub'][0]['item'][0]['kode'],
    fn(&$s)=>$s['ruang_lingkup'][0]['sub'][0]['nama']='Hidden title',
    fn(&$s)=>$s['ruang_lingkup'][3]['sub'][1]['huruf']='A',
    fn(&$s)=>array_pop($s['ruang_lingkup'][4]['sub'][0]['item'][0]['nama']),
    fn(&$s)=>$s['ruang_lingkup'][4]['sub'][0]['item'][0]['teks']='Wrong names',
    fn(&$s)=>$s['ruang_lingkup'][0]['sub'][0]['item'][0]['nama']=['Invalid'],
];
foreach ($cases as $mutate) {
    $seed=$official; $mutate($seed);
    try { EraporAgamaSeed::validate($seed); }
    catch (DomainException $e) { continue; }
    throw new RuntimeException('Invalid Agama seed accepted');
}
echo 'PASS: '.(count($cases)+1)." Agama validation scenarios.\n";
