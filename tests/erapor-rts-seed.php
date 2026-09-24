<?php
require __DIR__ . '/../app/models/EraporRtsSeed.php';
$seed=EraporRtsSeed::load(__DIR__.'/../eRapor_Zivana_Spesifikasi/rubrik_rts_seed.json');
$checks=1;
function rejectRts(array $seed): void {
    global $checks;
    try { EraporRtsSeed::validate($seed); }
    catch (DomainException $e) { $checks++; return; }
    throw new RuntimeException('Invalid seed accepted');
}
$bad=$seed; array_pop($bad['area'][0]['sub_area'][0]['indikator']); rejectRts($bad);
$bad=$seed; $bad['area'][0]['sub_area'][0]['indikator'][1]['kode']=$bad['area'][0]['sub_area'][0]['indikator'][0]['kode']; rejectRts($bad);
$bad=$seed; $bad['rubrik']['bagian'][0]['wajib']=false; rejectRts($bad);
$bad=$seed; $bad['rubrik']['skala'][0]['nilai']=0; rejectRts($bad);
$bad=$seed; $bad['rubrik']['kolom_periode'][1]['jenis']='AKHIR'; rejectRts($bad);
$bad=$seed; $bad['area'][2]['sub_area'][0]['nama']='Judul yang tidak boleh muncul'; rejectRts($bad);
$bad=$seed; $bad['area'][3]['sub_area'][2]['indikator'][0]['grup']='Grup lain'; rejectRts($bad);
$bad=$seed; $bad['area'][0]['sub_area'][0]['indikator'][0]['tujuan']=' '; rejectRts($bad);
$bad=$seed; $bad['rubrik']['khusus_abk']=true; rejectRts($bad);
$bad=$seed; $bad['rubrik']['penandatangan'][0]['peran']='ADMIN'; rejectRts($bad);
echo "PASS: $checks RTS seed validation scenarios; official source unchanged.\n";
