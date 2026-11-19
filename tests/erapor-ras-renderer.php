<?php
// Struktur HTML RAS (tanpa database/Dompdf): kolom semester, tanda centang, tanda -, narasi, dan blok pengesahan.
if (!function_exists('e')) { function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); } }
if (!function_exists('colorToken')) { function colorToken(string $n): string { return '#000'; } }
require __DIR__.'/../app/models/EraporRtsSeed.php';
require __DIR__.'/../app/models/EraporRasSeed.php';
require __DIR__.'/../app/models/EraporPackagePdfRenderer.php';
$checks=0;
$check=static function(bool $ok,string $message) use (&$checks): void { if (!$ok) throw new RuntimeException($message); $checks++; };
$seed=EraporRasSeed::load(__DIR__.'/../database/seeds/rubrik_ras_seed.json');
$areas=$subs=$items=[]; $nid=1;
foreach ($seed['area'] as $a) {
    $aid=$nid++; $areas[]=['id'=>$aid,'nama'=>$a['nama']];
    foreach ($a['sub_area'] as $s) {
        $sid=$nid++; $subs[]=['id'=>$sid,'area_id'=>$aid,'huruf'=>$s['huruf'],'nama'=>$s['nama'],'implisit'=>(int)$s['implisit']];
        foreach ($s['indikator'] as $i) $items[]=['id'=>$nid++,'sub_area_id'=>$sid,'tujuan'=>$i['tujuan'],'aparatus'=>null,'grup_id'=>null];
    }
}
$scale=[['id'=>null,'nilai'=>0,'kode'=>'BLM','label'=>'Belum Dikenalkan','simbol'=>'-']];
foreach ($seed['rubrik']['skala'] as $s) $scale[]=['id'=>$s['nilai'],'nilai'=>$s['nilai'],'kode'=>$s['kode'],'label'=>$s['label'],'simbol'=>$s['simbol']];
$g=[];$e=[]; // Ganjil: semua BSH (3); Genap: indikator pertama "-" (0), kedua BD (1), sisanya kosong.
foreach ($items as $i) $g['nilai:'.$i['id']]=3;
$e['nilai:'.$items[0]['id']]=0; $e['nilai:'.$items[1]['id']]=1;
$sig=['signers'=>['KEPALA_SEKOLAH'=>['nama'=>'Kepala Uji','nuptk'=>null,'ttd_data_uri'=>null],'GURU_KELAS'=>['nama'=>'Wali Uji','nuptk'=>'123456','ttd_data_uri'=>null]],'tempat'=>'Makassar','tanggal'=>'19 Desember 2026'];
$doc=['jenis_dokumen'=>'RAS','nama'=>'Rapor Akhir Semester - Montessori','judul_cetak'=>$seed['rubrik']['judul_cetak'],
    'form'=>['definitions'=>['areas'=>$areas,'subareas'=>$subs,'items'=>$items,'groups'=>[],'scale'=>$scale]],
    'period_values'=>['AS_GANJIL'=>$g+['narasi'=>"Narasi ganjil <b>uji</b>\nbaris dua"],'AS_GENAP'=>$e+['narasi'=>'Narasi genap uji']],
    'signers'=>$seed['rubrik']['penandatangan'],'signature_periods'=>['GANJIL'=>$sig,'GENAP'=>$sig],'signature_current'=>$sig];
$package=['student'=>['nama_lengkap'=>'Murid Uji','nisn'=>'123','nama_kelas'=>'Ranting Akasia'],
    'period'=>['jenis'=>'AKHIR','semester'=>'GENAP','label'=>'Akhir Semester Genap','tahun_label'=>'2026/2027','semester_label'=>'GENAP'],
    'school'=>['snapshot'=>['nama_komersial'=>'Sekolah Uji'],'logo_data_uri'=>''],'documents'=>[$doc]];
$m=new ReflectionMethod(EraporPackagePdfRenderer::class,'ras'); $m->setAccessible(true);
$html=$m->invoke(null,$package,$doc);
// Sel kolom tingkat: Ganjil 62x4 + Genap (1 baris BD x4) + 60 baris kosong x4; indikator "-" memakai satu sel colspan=4.
$none=substr_count($html,'ras-none'); $marks=substr_count($html,'class="grade ras-mark"');
$check($none===1,'satu indikator Genap bernilai "-" ditampilkan sebagai satu sel');
$check($marks===(62*4)+(1*4)+(60*4),'jumlah sel kolom tingkat');
$check(substr_count($html,'<img src="data:image/svg+xml')===62+1,'centang: 62 Ganjil + 1 Genap (BD)');
$check(str_contains($html,'Akhir Semester Ganjil</th><th colspan="4">Akhir Semester Genap'),'dua kolom semester');
$check(str_contains($html,'Akhir Semester Ganjil:</strong>') && str_contains($html,'Akhir Semester Genap:</strong>'),'dua narasi');
$check(str_contains($html,'Narasi ganjil &lt;b&gt;uji&lt;/b&gt;<br>') && !str_contains($html,'<b>uji</b>'),'narasi di-escape dan baris baru dipertahankan');
$check(str_contains($html,'Pengesahan Akhir Semester Ganjil dan Genap'),'judul pengesahan dua semester');
$check(str_contains($html,'Guru Kelas Ranting Akasia') && str_contains($html,'Wali Uji') && str_contains($html,'NUPTK: 123456'),'Wali Kelas bertanda tangan dengan NUPTK');
$check(str_contains($html,'Orang Tua Siswa') && str_contains($html,'Kepala Sekolah') && str_contains($html,'Kepala Uji'),'orang tua dan kepala sekolah');
$check(str_contains($html,'ras-narratives'),'judul dan narasi satu blok');
// Sesi Ganjil: narasi Genap kosong tidak dicetak; narasi Ganjil tetap.
$pg=$package; $pg['period']['semester']='GANJIL'; $dg=$doc; $dg['period_values']=['AS_GANJIL'=>$doc['period_values']['AS_GANJIL']]; $dg['signature_periods']=['GANJIL'=>$sig];
$h2=$m->invoke(null,$pg,$dg);
$check(str_contains($h2,'Akhir Semester Ganjil:</strong>') && !str_contains($h2,'Akhir Semester Genap:</strong>'),'sesi Ganjil hanya narasi Ganjil');
$check(str_contains($h2,'Pengesahan Akhir Semester Ganjil</p>'),'judul pengesahan Ganjil');
// Draf sesi Genap tanpa narasi Ganjil: blok Genap tetap tampil (kosong), Ganjil dilewati.
$dn=$doc; $dn['period_values']=['AS_GENAP'=>$e]; $h3=$m->invoke(null,$package,$dn);
$check(!str_contains($h3,'Akhir Semester Ganjil:</strong>') && str_contains($h3,'Akhir Semester Genap:</strong>'),'blok narasi hanya untuk semester sesi atau yang berisi');
// Susunan paket: akhir semester memakai RAS, bukan RTS.
$rm=new ReflectionMethod(EraporPackagePdfRenderer::class,'renderArtifact');
$wrong=$package; $wrong['documents'][0]['jenis_dokumen']='RTS';
try { EraporPackagePdfRenderer::renderArtifact($wrong); $check(false,'paket Akhir dengan RTS harus ditolak'); } catch (DomainException|RuntimeException $x) { $check(true,'ok'); }
echo "PASS: $checks RAS renderer structure checks.\n";
