<?php
require __DIR__.'/../app/models/EraporRtsSeed.php';
require __DIR__.'/../app/models/EraporRasSeed.php';
$file=__DIR__.'/../database/seeds/rubrik_ras_seed.json';
$seed=EraporRasSeed::load($file); $checks=1;
function rasCheck(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('RAS seed assertion failed'); $checks++; }
function rasReject(callable $mutate): void {
    global $seed; $copy=$seed; $mutate($copy);
    try { EraporRasSeed::validate($copy); } catch (DomainException) { rasCheck(true); return; }
    throw new RuntimeException('Invalid RAS seed accepted');
}
$count=0; $texts=[];
foreach ($seed['area'] as $area) foreach ($area['sub_area'] as $sub) foreach ($sub['indikator'] as $item) { $count++; $texts[]=$item['tujuan']; }
rasCheck($count===62 && count($seed['area'])===6 && $seed['rubrik']['statistik']['jumlah_sel_penilaian_per_murid']===124);
// Ejaan sudah diperbaiki; kata salah tidak boleh kembali.
foreach (['kelass','Menklasifikasikan','lambing','focus','tangan(merobek'] as $typo) foreach ($texts as $t) rasCheck(!str_contains($t,$typo));
rasCheck(in_array('Mengklasifikasikan benda berdasarkan warna, bentuk, dan ukuran',$texts,true));
rasCheck(in_array('Dapat menyimak dan fokus',$texts,true));
rasCheck(count(array_filter($texts,fn($t)=>str_contains($t,'lambang bilangan 1-10')))===2);
rasCheck(array_column($seed['rubrik']['bagian'],'jenis')===['rubrik','teks_bebas']);
rasReject(function(&$s){ $s['rubrik']['kode']='RAS_LAIN'; });
rasReject(function(&$s){ $s['rubrik']['jenis_periode']='TENGAH'; });
rasReject(function(&$s){ array_pop($s['area'][0]['sub_area'][0]['indikator']); });
rasReject(function(&$s){ $s['area'][0]['sub_area'][0]['indikator'][1]['kode']=$s['area'][0]['sub_area'][0]['indikator'][0]['kode']; });
rasReject(function(&$s){ $s['area'][0]['sub_area'][0]['indikator'][0]['kode']='agama__bukan_ras'; });
rasReject(function(&$s){ $s['rubrik']['skala'][1]['kode']='XX'; });
rasReject(function(&$s){ $s['rubrik']['kolom_periode'][1]['kode']='AS_LAIN'; });
rasReject(function(&$s){ $s['rubrik']['penandatangan'][1]['peran']='KOORDINATOR_BING'; });
rasReject(function(&$s){ $s['rubrik']['bagian'][1]['wajib']=false; });
rasReject(function(&$s){ $s['area'][1]['sub_area'][0]['indikator'][0]['grup']='Grup'; });
// Kode indikator RAS tidak boleh bentrok dengan RTS (kolom kode UNIQUE lintas rubrik).
$rts=EraporRtsSeed::load(__DIR__.'/../database/seeds/rubrik_rts_seed.json'); $rtsCodes=[];
foreach ($rts['area'] as $a) foreach ($a['sub_area'] as $s) foreach ($s['indikator'] as $i) $rtsCodes[$i['kode']]=true;
foreach ($seed['area'] as $a) foreach ($a['sub_area'] as $s) foreach ($s['indikator'] as $i) rasCheck(!isset($rtsCodes[$i['kode']]));
echo "PASS: $checks RAS seed validation scenarios; 62 indikator, ejaan diperbaiki, tanpa bentrok kode RTS.\n";
