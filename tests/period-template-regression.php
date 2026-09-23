<?php
// Isolated fixture: reuse base period tests, then exercise editing before enrollment.
require __DIR__ . '/report-workflow-regression.php';
$db->exec('INSERT INTO tahun_ajaran VALUES(3)');
$empty = $base + ['semester'=>'ganjil','tipe'=>'Tengah Semester'];
$empty['tahun_ajaran_id']=3;
$periodId=ReportWorkflow::savePeriod($empty);
$empty['tipe']='Akhir Semester';
ReportWorkflow::savePeriod($empty,$periodId);
checkReport((int)$db->query("SELECT template_id FROM sesi_pembagian_rapor WHERE periode_id=$periodId")->fetchColumn()===2,
    'Changing an empty period to Akhir must update its session template');
$db->exec("INSERT INTO kelas VALUES(3,3); INSERT INTO murid VALUES(5,3,'bersekolah')");
ReportWorkflow::savePeriod($empty,$periodId);
checkReport((int)$db->query('SELECT template_id FROM rapor WHERE murid_id=5')->fetchColumn()===2,
    'Later enrollment must use the changed period template');
$moved=$empty;$moved['tahun_ajaran_id']=2;
try {
    ReportWorkflow::savePeriod($moved,$periodId);
    throw new RuntimeException('Period with reports must not move school years');
} catch (DomainException $expected) {}
checkReport((int)$db->query("SELECT tahun_ajaran_id FROM periode_penilaian WHERE id=$periodId")->fetchColumn()===3,
    'Rejected year change leaves period identity unchanged');
echo "PASS: empty period type changes synchronize template, later enrollment uses correct template, populated period year locked.\n";
