<?php
require __DIR__.'/../app/models/EraporPdfFilename.php';
$checks=0;
$check=static function(bool $ok)use(&$checks){if(!$ok)throw new RuntimeException('PDF filename assertion failed');$checks++;};
$check(EraporPdfFilename::build('Nama Anak','TENGAH','GANJIL','2026/2027')==='Rapor-Nama-Anak-TS-Ganjil-2026-2027.pdf');
$check(EraporPdfFilename::build('Nama Anak','AKHIR','GENAP','2026/2027',true)==='Draf-Rapor-Nama-Anak-AS-Genap-2026-2027.pdf');
$check(EraporPdfFilename::build("../Anak\r\n\"<script>",'TENGAH','GANJIL','2026/2027')==='Rapor-Anak-script-TS-Ganjil-2026-2027.pdf');
$check(EraporPdfFilename::build('','TENGAH','GANJIL','')==='Rapor-Murid-TS-Ganjil.pdf');
$check(strlen(EraporPdfFilename::build(str_repeat('Nama ',100),'TENGAH','GENAP','2026/2027'))<150);
$db=new PDO('sqlite::memory:');
$db->exec('CREATE TABLE murid(id INTEGER,nama_lengkap TEXT);CREATE TABLE tahun_ajaran(id INTEGER,tahun_awal INTEGER,tahun_akhir INTEGER);CREATE TABLE erapor_sesi(id INTEGER,murid_id INTEGER,tahun_ajaran_id INTEGER,jenis TEXT,semester TEXT)');
$db->exec("INSERT INTO murid VALUES(1,'Nama Anak');INSERT INTO tahun_ajaran VALUES(1,2026,2027);INSERT INTO erapor_sesi VALUES(5,1,1,'TENGAH','GANJIL')");
$check(EraporPdfFilename::forSession($db,5)==='Rapor-Nama-Anak-TS-Ganjil-2026-2027.pdf');
$check(EraporPdfFilename::forSession($db,5,true)==='Draf-Rapor-Nama-Anak-TS-Ganjil-2026-2027.pdf');
$check(EraporPdfFilename::ppiForSession($db,5)==='PPI-Nama-Anak-TS-Ganjil-2026-2027.pdf');
$check(EraporPdfFilename::ppiForSession($db,5,true)==='Draf-PPI-Nama-Anak-TS-Ganjil-2026-2027.pdf');
try{EraporPdfFilename::forSession($db,999);throw new RuntimeException('Missing session accepted');}catch(DomainException){$check(true);}
echo "PASS: $checks PDF filename checks.\n";
