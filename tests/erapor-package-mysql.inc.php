<?php
// Read-only checks over the disposable restored calendar and seeded catalog.
require_once ROOT_PATH.'/app/models/EraporSessionPolicy.php';
require_once ROOT_PATH.'/app/models/EraporCalendar.php';
require_once ROOT_PATH.'/app/models/EraporPackagePlan.php';
$composition=require ROOT_PATH.'/config/erapor-package.php';
$catalog=$db->query("SELECT * FROM erapor_rubrik WHERE kode<>'TEST_RTS'")->fetchAll(PDO::FETCH_ASSOC);
$planBefore=catalogFingerprints($db);
$periods=$db->query('SELECT * FROM periode_penilaian ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
catalogCheck(count($periods)>0,'Calendar available for adapter checks');
foreach ($periods as $row) {
    $year=EraporCalendar::year($db,(int)$row['tahun_ajaran_id']);
    $normalized=EraporCalendar::normalize($row);
    catalogCheck(in_array($normalized,$year['periode'],true),'Adapter returns canonical existing period');
    foreach (['Regular','ABK'] as $condition) {
        $packagePlan=EraporPackagePlan::build(1,$condition,$row,$catalog,$composition);
        if ($normalized['jenis']==='TENGAH') {
            catalogCheck($packagePlan['dapat_dibentuk'] && count($packagePlan['dokumen'])===($condition==='ABK'?5:4),'Midsemester package count');
        } else {
            catalogCheck(!$packagePlan['dapat_dibentuk'] && $packagePlan['rubrik_belum_tersedia']===['RAS'] && $packagePlan['dokumen']===[],'No incomplete finalsemester package');
        }
    }
}
catalogCheck(catalogFingerprints($db)===$planBefore,'Calendar/package planning never writes');
