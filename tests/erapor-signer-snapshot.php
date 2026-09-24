<?php
require __DIR__.'/../app/models/EraporSessionPolicy.php';
require __DIR__.'/../app/models/EraporSignerSnapshot.php';
$checks=0;
function signerCheck(bool $value): void { global $checks; ++$checks; if (!$value) throw new RuntimeException('Snapshot assertion failed.'); }
function signerReject(callable $fn): void { try { $fn(); } catch (DomainException $e) { signerCheck(true); return; } throw new RuntimeException('Expected invalid snapshot.'); }
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$profile=['nuptk'=>'0012345678901234','ttd_png'=>$png,'ttd_disetujui_pada'=>'2026-09-24 10:00:00'];
$result=EraporSignerSnapshot::build('Guru Fixture',$profile);
signerCheck($result['nuptk']==='0012345678901234');
signerCheck($result['ttd_sha256']===hash('sha256',$png) && $result['ttd_png']===$png);
signerCheck(EraporSignerSnapshot::build('Guru',null)['ttd_png']===null);
signerCheck(EraporSignerSnapshot::build('Guru',['nuptk'=>"\u{00a0}"])['nuptk']===null);
signerReject(fn()=>EraporSignerSnapshot::build(' ',null));
signerReject(fn()=>EraporSignerSnapshot::build("\xff",null));
signerReject(fn()=>EraporSignerSnapshot::build(str_repeat('a',256),null));
foreach (['123','001234567890123X'] as $nuptk) signerReject(fn()=>EraporSignerSnapshot::build('Guru',['nuptk'=>$nuptk]));
foreach ([null,'2026-02-30 10:00:00',''] as $consent) signerReject(fn()=>EraporSignerSnapshot::build('Guru',array_replace($profile,['ttd_disetujui_pada'=>$consent])));
foreach (['<svg/>','',str_repeat('a',2097153)] as $bytes) signerReject(fn()=>EraporSignerSnapshot::build('Guru',array_replace($profile,['ttd_png'=>$bytes])));
signerReject(fn()=>EraporSignerSnapshot::build('Guru',array_replace($profile,['ttd_png'=>null])));
echo "PASS: $checks signer snapshot checks.\n";
