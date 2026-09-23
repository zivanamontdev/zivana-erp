<?php
// Local UI fixture server: php -S 127.0.0.1:8011 -t public tests/report-ui-router.php
// Serves no application data and permits only existing public assets and this fixture.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/')) return false;
if($path!=='/__report-ui') { http_response_code(404); exit; }
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
define('COLOR_TOKENS',require ROOT_PATH.'/config/colors.php');
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
require ROOT_PATH.'/app/models/ReportPreview.php';
$rapor=['nama_lengkap'=>'Eira Salsabila','nisn'=>'123456','level_kelas'=>'Ranting','nama_kelas'=>'Akasia','periode_tipe'=>'Tengah Semester','tahun_awal'=>'2026','tahun_akhir'=>'2027'];
$items=[];for($i=0;$i<120;$i++)$items[]=['nama_tujuan'=>['Menutup mulut saat batuk dan bersin','Mencuci tangan dengan benar','Memakai dan melepas sepatu sendiri'][$i%3], 'nilai_ganjil'=>'triangle-lg','nilai_genap'=>null];
$previewPages=ReportPreview::pages([['nama_area'=>'AREA KETERAMPILAN HIDUP','subkategori'=>[['label'=>'a','nama'=>'Perawatan Diri','item'=>$items]]]]);
?><!DOCTYPE html><html><head><meta charset="utf-8"><style><?= colorCssVariables() ?></style>
<link rel="stylesheet" href="/assets/css/tokens.css"><link rel="stylesheet" href="/assets/css/components.css"><link rel="stylesheet" href="/assets/css/template-preview.css">
<style>body{margin:24px;background:var(--color-page-background);font-family:var(--font-family-base)}header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}h1{font-size:20px}</style></head><body>
<header><h1>Eira Salsabila</h1><div class="ui-actions"><?= uiButton('Muat ulang','outline',['icon'=>'icon_refresh','iconOnly'=>true,'marginVertical'=>0]) ?><?= uiButton('Simpan PDF','outline',['marginVertical'=>0]) ?><?= uiButton('Setujui','primary',['marginVertical'=>0]) ?></div></header>
<?php require VIEW_PATH.'/admin/template-rapor/_preview-pages.php'; ?>
</body></html>
