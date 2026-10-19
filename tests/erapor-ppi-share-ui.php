<?php
/** Render-only regression for production-like missing parent contacts on a published separate PPI. */
define('ROOT_PATH',dirname(__DIR__));define('VIEW_PATH',ROOT_PATH.'/app/views');define('BASE_PATH','');
require ROOT_PATH.'/app/helpers/functions.php';require ROOT_PATH.'/app/helpers/ui.php';
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$render=static function(array $contacts,string $status='TERBIT'):array{
    $session=['id'=>42,'status'=>'SELESAI'];$canEdit=false;$canSend=true;
    $ppiState=['separate'=>true,'included'=>false,'status'=>$status,'label'=>$status==='TERBIT'?'Terbit':'Menunggu Wali Kelas',
        'can_submit'=>false,'progress'=>['filled'=>30,'required'=>30]];
    $ppiShare=['contacts'=>$contacts,'shared_at'=>null];
    ob_start();require VIEW_PATH.'/portal-guru/_ppi-workflow.php';$html=ob_get_clean();
    $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="UTF-8">'.$html);return [$html,new DOMXPath($dom)];
};
[$html,$xp]=$render([]);
$check($xp->query('//button[@disabled and @aria-describedby="erapor-ppi-share-hint"]')->length===1,'Published PPI retains a disabled sharing action with an accessible explanation');
$check($xp->evaluate('normalize-space(//*[@id="erapor-ppi-share-hint"]/text()[1])')==='Kontak belum tersedia'
    &&$xp->query('//*[@id="erapor-ppi-share-hint"]//*[@data-ui-tooltip and @tabindex="0"]//*[@role="tooltip"]')->length===1
    &&str_contains($html,'nomor HP ayah atau ibu'),'Missing contacts use a short visible label and a separate focusable info tooltip, not a visible paragraph');
$check($xp->query('//a[@data-erapor-share-ppi]')->length===0,'Missing contacts never produce a fake WhatsApp destination');
$father=['label'=>'Ayah','wa_url'=>'https://wa.me/6281234567890?text=PPI-uji'];
[$html,$xp]=$render(['AYAH'=>$father]);
$check($xp->query('//a[@data-erapor-share-ppi="AYAH" and @target="_blank" and @rel="noopener"]')->length===1,'One valid parent contact has one direct share action');
$check($xp->query('//*[@id="erapor-ppi-share-hint"]')->length===0&&$xp->query('//button[@disabled]')->length===0,'Missing-contact warning disappears when a valid contact exists');
$check($xp->query('//*[@data-action-menu]')->length===0,'One contact does not create an unnecessary dropdown');
[$html,$xp]=$render(['AYAH'=>$father,'IBU'=>['label'=>'Ibu <script>unsafe</script>','wa_url'=>'https://wa.me/6281234567891?text=PPI-uji']]);
$check($xp->query('//*[@data-action-menu]')->length===1&&$xp->query('//a[@data-erapor-share-ppi]')->length===2,'Two contacts use the actual shared action-menu trigger contract with distinct father/mother actions');
$check($xp->query('//*[contains(@class,"action-menu-dropdown") and @hidden]')->length===0,'Dropdown visibility is controlled by the shared menu class, not a permanent hidden attribute');
$check(!str_contains($html,'<script>unsafe</script>')&&str_contains($html,'&lt;script&gt;unsafe&lt;/script&gt;'),'Parent labels remain escaped');
[$html,$xp]=$render(['AYAH'=>$father],'MENUNGGU_WALI');
$check($xp->query('//a[@data-erapor-share-ppi]')->length===0&&!str_contains($html,'Bagikan PPI'),'PPI cannot be shared before independent publication');
$check($xp->query('//section[contains(@class,"ui-card")]')->length===0,'Workflow fragment does not introduce a second information card');
$css=file_get_contents(ROOT_PATH.'/public/assets/css/portal-guru.css');
$check(str_contains($css,'.erapor-ppi-workflow .erapor-save-actions .ui-button { margin-block: 0; }')
    &&str_contains($css,'.erapor-ppi-workflow > .erapor-save-actions { align-items: center; margin-top: 0; }'),'PDF links and native action buttons use zero margins and common alignment within the PPI action row');
echo "PASS: $checks separate PPI sharing UI checks (missing/one/two contacts, accessibility, escaping, publication gate).\n";
