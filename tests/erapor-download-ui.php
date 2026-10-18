<?php
// Public token error/preview page uses shared theme; this test never connects to a database.
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');define('APP_NAME','Zivana Uji');
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';require ROOT_PATH.'/app/core/Controller.php';require ROOT_PATH.'/app/controllers/EraporDownloadController.php';
$_SERVER['REQUEST_METHOD']='GET';
$controller=new EraporDownloadController();$page=new ReflectionMethod($controller,'page');
ob_start();$page->invoke($controller,'Tautan <uji> tidak berlaku','Hubungi guru & sekolah.');$html=ob_get_clean();
$checks=[
    str_contains($html,'id="zivana-color-tokens"'),
    str_contains($html,'fonts.googleapis.com'),
    str_contains($html,'components.css'),
    str_contains($html,'ui-card--outlined')&&str_contains($html,'erapor-download-card'),
    str_contains($html,'Tautan &lt;uji&gt; tidak berlaku')&&!str_contains($html,'Tautan <uji>'),
    str_contains($html,'Hubungi guru &amp; sekolah.'),
];
foreach($checks as $ok)if(!$ok)throw new RuntimeException('Public eRapor download theme assertion failed');
echo 'PASS: '.count($checks)." public download UI checks (shared font, color tokens, card, components, escaped text).\n";
