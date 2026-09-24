<?php
require __DIR__ . '/../app/models/EraporLegacyAudit.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE rapor(id INTEGER,murid_id INTEGER,sesi_pembagian_id INTEGER,template_id INTEGER,guru_id INTEGER,status TEXT);
CREATE TABLE rapor_nilai(rapor_id INTEGER,item_id INTEGER,semester TEXT,skala_nilai_opsi_id INTEGER);
CREATE TABLE rapor_catatan_guru(rapor_id INTEGER);
CREATE TABLE periode_penilaian(id INTEGER,tahun_ajaran_id INTEGER,semester TEXT,tipe TEXT,awal_periode TEXT,akhir_periode TEXT);
CREATE TABLE sesi_pembagian_rapor(id INTEGER,periode_id INTEGER);
CREATE TABLE users(id INTEGER,karyawan_id INTEGER);
CREATE TABLE template_rapor(id INTEGER);
CREATE TABLE template_rapor_area(id INTEGER,template_id INTEGER);
CREATE TABLE template_rapor_subkategori(id INTEGER,area_id INTEGER);
CREATE TABLE template_rapor_item(id INTEGER,subkategori_id INTEGER,skala_nilai_id INTEGER);
CREATE TABLE skala_nilai_opsi(id INTEGER,skala_id INTEGER);
INSERT INTO periode_penilaian VALUES(1,1,'ganjil','Tengah Semester','2026-09-01','2026-10-01');
INSERT INTO sesi_pembagian_rapor VALUES(1,1);
INSERT INTO users VALUES(10,20);
INSERT INTO template_rapor VALUES(1);
INSERT INTO template_rapor_area VALUES(1,1);
INSERT INTO template_rapor_subkategori VALUES(1,1);
INSERT INTO template_rapor_item VALUES(1,1,1);
INSERT INTO skala_nilai_opsi VALUES(1,1);
INSERT INTO rapor VALUES(1,1,1,1,20,'belum_diisi');
INSERT INTO rapor_nilai VALUES(1,1,'ganjil',1);");
function auditCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$before = $db->query('SELECT total_changes()')->fetchColumn();
$result = EraporLegacyAudit::inspect($db);
auditCheck($before === $db->query('SELECT total_changes()')->fetchColumn(), 'Audit must not write');
foreach ($result['counts'] as $name => $count) {
    auditCheck($count === (in_array($name, ['reports', 'grades'], true) ? 1 : 0), $name);
}
$db->exec("INSERT INTO users VALUES(11,20);
INSERT INTO rapor VALUES(2,1,1,1,NULL,'disetujui');
INSERT INTO rapor_nilai VALUES(2,999,'genap',999);
INSERT INTO periode_penilaian VALUES(2,1,NULL,'invalid','2026-10-01','2026-09-01');
INSERT INTO template_rapor VALUES(2);");
$result = EraporLegacyAudit::inspect($db);
foreach (['ambiguous_teacher_accounts','duplicate_pupil_periods','reports_without_teacher','grades_wrong_semester','grades_wrong_template','grades_wrong_scale','periods_missing_identity','invalid_period_dates','empty_templates'] as $name) {
    auditCheck($result['counts'][$name] === 1, $name);
}
auditCheck($result['automatic_conversion_allowed'] === false, 'Never authorize lossy conversion');
echo "PASS: clean fixture, nine anomaly categories, read-only audit, no automatic conversion.\n";
