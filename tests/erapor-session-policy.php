<?php
require __DIR__ . '/../app/models/EraporSessionPolicy.php';
$checks = 0;
function policyCheck(bool $ok): void {
    global $checks;
    if (!$ok) throw new RuntimeException('Policy assertion failed at ' . ($checks + 1));
    $checks++;
}
function policyReject(callable $call): void {
    try { $call(); } catch (DomainException $e) { policyCheck(true); return; }
    throw new RuntimeException('Expected rejection');
}
foreach ([null, '', " \t\r\n", "\u{00A0}\u{2003}"] as $blank) {
    policyCheck(EraporSessionPolicy::isBlank($blank));
    policyCheck(EraporSessionPolicy::textForStorage($blank) === null);
}
foreach (['0', "  Kalimat guru\n\n  ", '- Baris satu\nBaris dua'] as $raw) {
    policyCheck(EraporSessionPolicy::textForStorage($raw) === $raw);
}
policyReject(fn() => EraporSessionPolicy::textForStorage("\xFF"));
$order = 0;
foreach (['GANJIL','GENAP'] as $semester) foreach (['TENGAH','AKHIR'] as $type) {
    policyCheck(EraporSessionPolicy::periodOrder($semester, $type) === ++$order);
}
policyReject(fn() => EraporSessionPolicy::periodOrder('ganjil', 'TENGAH'));
foreach (['BELUM_DIISI','TELAH_DIISI'] as $status) {
    EraporSessionPolicy::assertWritable($status, '2026-09-24', '2026-09-24');
    policyCheck(true);
    policyReject(fn() => EraporSessionPolicy::assertWritable($status, '2026-09-23', '2026-09-24'));
}
foreach (['MENUNGGU_TTD','SELESAI','disetujui','UNKNOWN'] as $status) {
    policyReject(fn() => EraporSessionPolicy::assertWritable($status, '2099-01-01', '2026-09-24'));
}
policyReject(fn() => EraporSessionPolicy::assertWritable('BELUM_DIISI', '2026-02-30', '2026-01-01'));
policyCheck(EraporSessionPolicy::confirmFilled('BELUM_DIISI', true) === 'TELAH_DIISI');
policyCheck(EraporSessionPolicy::confirmReception('TELAH_DIISI', true) === 'MENUNGGU_TTD');
foreach (EraporSessionPolicy::STATES as $status) {
    policyReject(fn() => EraporSessionPolicy::confirmFilled($status, false));
    policyReject(fn() => EraporSessionPolicy::confirmReception($status, false));
    if ($status !== 'BELUM_DIISI') policyReject(fn() => EraporSessionPolicy::confirmFilled($status, true));
    if ($status !== 'TELAH_DIISI') policyReject(fn() => EraporSessionPolicy::confirmReception($status, true));
}
EraporSessionPolicy::assertExtension('2026-09-20','2026-09-25','2026-09-24','Diskusi belum selesai');
policyCheck(true);
foreach ([['2026-09-24','alasan'],['2026-09-25',"\t\n"]] as [$date,$reason]) {
    policyReject(fn() => EraporSessionPolicy::assertExtension('2026-09-20',$date,'2026-09-24',$reason));
}
policyReject(fn() => EraporSessionPolicy::assertExtension('2026-10-01','2026-09-30','2026-09-24','alasan'));
$rows = [
    ['id'=>1,'urutan'=>1,'status'=>'MENUNGGU'],
    ['id'=>2,'urutan'=>1,'status'=>'MENUNGGU'],
    ['id'=>3,'urutan'=>2,'status'=>'MENUNGGU'],
];
foreach ([1,2] as $id) { EraporSessionPolicy::assertApprovalOrder('MENUNGGU_TTD',$rows,$id); policyCheck(true); }
policyReject(fn() => EraporSessionPolicy::assertApprovalOrder('MENUNGGU_TTD',$rows,3));
$rows[0]['status']='DISETUJUI';
policyReject(fn() => EraporSessionPolicy::assertApprovalOrder('MENUNGGU_TTD',$rows,3));
policyReject(fn() => EraporSessionPolicy::assertApprovalOrder('MENUNGGU_TTD',$rows,1));
$rows[1]['status']='DISETUJUI';
EraporSessionPolicy::assertApprovalOrder('MENUNGGU_TTD',$rows,3); policyCheck(true);
policyReject(fn() => EraporSessionPolicy::finalize('MENUNGGU_TTD',$rows,true));
$rows[2]['status']='DISETUJUI';
policyReject(fn() => EraporSessionPolicy::finalize('MENUNGGU_TTD',$rows,false));
policyCheck(EraporSessionPolicy::finalize('MENUNGGU_TTD',$rows,true) === 'SELESAI');
foreach ([[], [$rows[0],$rows[0]], [['id'=>1,'urutan'=>0,'status'=>'DISETUJUI']], [['id'=>1,'urutan'=>1,'status'=>'INVALID']]] as $invalid) {
    policyReject(fn() => EraporSessionPolicy::finalize('MENUNGGU_TTD',$invalid,true));
}
foreach (['BELUM_DIISI','TELAH_DIISI','SELESAI'] as $status) {
    policyReject(fn() => EraporSessionPolicy::finalize($status,$rows,true));
    policyReject(fn() => EraporSessionPolicy::assertApprovalOrder($status,$rows,3));
}
echo "PASS: $checks assertions (text, period identity, deadlines, transitions, parallel approvals, publication gates).\n";
