<?php
require __DIR__.'/../app/models/EraporBingSeed.php';
$official=EraporBingSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_bing_seed.json');
$cases=[
    fn(&$s)=>$s['rubrik']['cakupan']='TAHUNAN',
    fn(&$s)=>$s['rubrik']['cetak_gabung_periode']=true,
    fn(&$s)=>$s['rubrik']['khusus_abk']=true,
    fn(&$s)=>$s['rubrik']['bahasa']='id',
    fn(&$s)=>$s['rubrik']['bagian'][1]['wajib']=false,
    fn(&$s)=>$s['rubrik']['penandatangan'][0]['peran']='GURU_KELAS',
    fn(&$s)=>$s['skala'][0]['peringkat']=3,
    fn(&$s)=>$s['skala'][1]['kode']='EXCELLENT',
    fn(&$s)=>$s['skala'][1]['definisi']=' ',
    fn(&$s)=>$s['indikator'][0]['jenis']='ANGKA',
    fn(&$s)=>$s['indikator'][]=$s['indikator'][0],
    fn(&$s)=>$s['indikator'][3]['kode']='speaking__pronunciation',
    fn(&$s)=>$s['indikator'][3]['grup']=null,
    fn(&$s)=>$s['grup'][0]['sel_nilai']='DROPDOWN',
    fn(&$s)=>array_pop($s['komentar']),
    fn(&$s)=>$s['komentar'][2]['wajib']=false,
    fn(&$s)=>$s['identitas_baris'][1]['sumber']='hitung:usia',
    fn(&$s)=>array_pop($s['teks_tetap']),
];
foreach ($cases as $mutate) {
    $seed=$official; $mutate($seed);
    try { EraporBingSeed::validate($seed); }
    catch (DomainException $e) { continue; }
    throw new RuntimeException('Invalid seed accepted');
}
echo 'PASS: '.(count($cases)+1)." BING validation scenarios.\n";
