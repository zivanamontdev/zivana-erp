<?php
require __DIR__.'/../app/models/EraporUmmiPpiSeed.php';
$ummi=EraporUmmiPpiSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_ummi_seed.json');
$ppi=EraporUmmiPpiSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_ppi_seed.json');
$cases=[
    [$ummi,fn(&$s)=>$s['rubrik']['bagian'][0]['wajib']=true],
    [$ummi,fn(&$s)=>$s['rubrik']['bagian'][2]['wajib']=false],
    [$ummi,fn(&$s)=>$s['rubrik']['kolom_periode'][1]['jenis']='TENGAH'],
    [$ummi,fn(&$s)=>$s['rubrik']['skala']['huruf']['nilai'][0]['peringkat']=1],
    [$ummi,fn(&$s)=>$s['jilid'][1]['hanya_pra_tk']=true],
    [$ummi,fn(&$s)=>array_pop($s['jilid'][0]['materi'])],
    [$ummi,fn(&$s)=>$s['jilid'][0]['materi'][1]['kode']=$s['jilid'][0]['materi'][0]['kode']],
    [$ummi,fn(&$s)=>$s['jilid'][0]['materi'][0]['teks']='   '],
    [$ppi,fn(&$s)=>$s['rubrik']['khusus_abk']=false],
    [$ppi,fn(&$s)=>$s['rubrik']['cetak_gabung_periode']=true],
    [$ppi,fn(&$s)=>$s['aspek'][4]['kode']='SENI'],
    [$ppi,fn(&$s)=>$s['kolom'][6]['wajib']=true],
    [$ppi,fn(&$s)=>$s['kolom'][7]['diisi_di_sesi']=true],
    [$ppi,fn(&$s)=>$s['kolom'][0]['diisi_di_sesi']=false],
    [$ppi,fn(&$s)=>$s['kolom'][0]['lebar_cetak_cm']=0],
    [$ppi,fn(&$s)=>$s['kolom'][0]['render']='HTML'],
    [$ppi,fn(&$s)=>$s['rubrik']['penandatangan'][1]['peran']='KOORDINATOR_QURAN'],
    [$ppi,fn(&$s)=>array_pop($s['identitas_baris'])],
];
foreach ($cases as [$seed,$mutate]) {
    $mutate($seed);
    try { EraporUmmiPpiSeed::validate($seed); }
    catch (DomainException $e) { continue; }
    throw new RuntimeException('Invalid seed accepted');
}
echo 'PASS: '.(count($cases)+2)." Ummi/PPI validation scenarios.\n";
