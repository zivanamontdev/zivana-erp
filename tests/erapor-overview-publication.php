<?php
/** Read-model and UI regression: a prepared official PDF is not yet published to parents. No application DB. */
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
$_SESSION=['csrf_token'=>'overview-fixture','csrf_token_expires'=>time()+3600];
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/EraporOverview.php';require ROOT_PATH.'/app/models/EraporPackagePlan.php';
class EraporCompleteness {
    public static function inspect(PDO $db,array $s):array {
        if(!$db->inTransaction())throw new RuntimeException('Projection lost transaction guard');
        return ['documents'=>[['filled'=>1,'required'=>1]]];
    }
}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE periode_penilaian(id INTEGER,tahun_ajaran_id INTEGER,nama TEXT,awal_periode TEXT,akhir_periode TEXT);
 CREATE TABLE murid(id INTEGER,nama_lengkap TEXT,status_kondisi TEXT,kelas_id INTEGER,status TEXT);
 CREATE TABLE kelas(id INTEGER,level_kelas TEXT,nama_kelas TEXT,tahun_ajaran_id INTEGER);
 CREATE TABLE karyawan(id INTEGER,nama TEXT);CREATE TABLE users(id INTEGER,karyawan_id INTEGER);
 CREATE TABLE kelas_guru_murid(id INTEGER,murid_id INTEGER,guru_id INTEGER);
 CREATE TABLE erapor_sesi(id INTEGER,murid_id INTEGER,guru_user_id INTEGER,periode_id INTEGER,status TEXT);
 CREATE TABLE erapor_sesi_penyetuju(sesi_id INTEGER,status TEXT);
 CREATE TABLE erapor_publikasi_pdf(sesi_id INTEGER);
 INSERT INTO periode_penilaian VALUES(1,1,'Tengah Ganjil','2026-09-01','2026-10-02');
 INSERT INTO kelas VALUES(1,'Ranting','Uji',1);INSERT INTO karyawan VALUES(1,'Guru Uji');INSERT INTO users VALUES(1,1);");
for($id=1;$id<=5;$id++){
    $db->prepare("INSERT INTO murid VALUES(?,?,'Reguler',1,'bersekolah')")->execute([$id,'Murid Uji '.$id]);
    $db->prepare('INSERT INTO kelas_guru_murid VALUES(?,?,1)')->execute([$id,$id]);
    if($id===1)continue;
    $db->prepare('INSERT INTO erapor_sesi VALUES(?,?,1,1,?)')->execute([$id,$id,$id===3?'SELESAI':'MENUNGGU_TTD']);
    for($stage=1;$stage<=4;$stage++)$db->prepare('INSERT INTO erapor_sesi_penyetuju VALUES(?,?)')->execute([$id,$id>=4&&$stage===4?'MENUNGGU':'DISETUJUI']);
    if($id!==4)$db->prepare('INSERT INTO erapor_publikasi_pdf VALUES(?)')->execute([$id]);
}
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$before=$db->query('SELECT * FROM erapor_sesi ORDER BY id')->fetchAll();$periods=EraporOverview::forYear($db,1);$rows=array_column($periods[0]['rows'],null,'id');
$check($rows[1]['status']==='BELUM_DIBUKA'&&!$rows[1]['has_official_pdf'],'Unopened row remains unopened');
$check($rows[2]['status']==='SIAP_TERBIT'&&$rows[2]['has_official_pdf'],'All approved + immutable PDF + waiting session means ready, not published');
$check($rows[3]['status']==='TERBIT'&&$rows[3]['has_official_pdf'],'Only a published session is labelled published');
$check($rows[4]['status']==='MENUNGGU_TTD'&&!$rows[4]['has_official_pdf'],'Unfinished approvals remain pending');
$check($rows[5]['status']==='MENUNGGU_TTD','Artifact alone does not override incomplete approval evidence');
$check(!$db->inTransaction()&&$db->query('SELECT * FROM erapor_sesi ORDER BY id')->fetchAll()===$before,'Overview never updates workflow status or retains a transaction');
$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);$stringRows=array_column(EraporOverview::forYear($db,1)[0]['rows'],null,'id');
$check($stringRows[2]['status']==='SIAP_TERBIT'&&$stringRows[3]['status']==='TERBIT','String-typed numeric results do not collapse the ready/published distinction');
$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,false);
$tahunOptions=[['id'=>1,'tahun_awal'=>2026,'tahun_akhir'=>2027]];$tahunId=1;$periodeList=$periods;
$source=file_get_contents(VIEW_PATH.'/admin/rapor-murid/erapor-index.php');
$source=str_replace(["require VIEW_PATH . '/layouts/shell-header.php';","require VIEW_PATH . '/layouts/shell-footer.php';"],'',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new DOMXPath($dom);
$ready=$xp->query('//tr[.//a[@href="/erapor/sesi/2/pdf"]]')->item(0);$published=$xp->query('//tr[.//a[@href="/erapor/sesi/3/pdf"]]')->item(0);
$check($ready&&str_contains($ready->textContent,'Disetujui · belum diterbitkan'),'Ready row warns it has not been published');
$check($ready&&str_contains($ready->textContent,'PDF Resmi'),'Prepared immutable PDF remains explicitly official, not a regenerated draft');
$check($published&&str_contains($published->textContent,'Rapor telah terbit'),'Published row uses a distinct published label');
$check(!str_contains($html,'Disetujui · PDF terbit'),'Ambiguous legacy publication label is gone');
$css=file_get_contents(ROOT_PATH.'/public/assets/css/components.css');
$check(str_contains($css,'.report-period-table .erapor-overview-table .col-action { position: relative;'),
    'Offscreen accessible PDF column label is anchored inside the horizontal table scroller');
$check($xp->query('//th[@scope="col"]//span[contains(@class,"ui-visually-hidden") and normalize-space(.)="PDF"]')->length>0,
    'Responsive fix preserves the accessible PDF column label');
echo "PASS: $checks publication overview checks (ready versus published, official artifact caption, read-only projection, responsive accessibility).\n";
