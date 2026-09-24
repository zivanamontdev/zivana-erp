<?php
define('ROOT_PATH',dirname(__DIR__)); define('VIEW_PATH',ROOT_PATH.'/app/views'); define('BASE_PATH','');
$_SESSION=['csrf_token'=>'signer-profile-csrf','csrf_token_expires'=>time()+3600];
require ROOT_PATH.'/app/helpers/functions.php'; require ROOT_PATH.'/app/helpers/ui.php';
function signerUiCheck(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
function renderSignerProfile(array $data): string {
    extract($data); $source=file_get_contents(VIEW_PATH.'/admin/erapor-signer-profile/index.php');
    $source=str_replace(["require VIEW_PATH.'/layouts/shell-header.php';","require VIEW_PATH.'/layouts/shell-footer.php';"],'',$source);
    ob_start(); eval('?>'.$source); return ob_get_clean();
}
$profile=['nuptk'=>'0012345678901234','has_signature'=>false,'consented_at'=>null];
$html=renderSignerProfile(['pageTitle'=>'Profil Penandatangan','profile'=>$profile,'canEdit'=>true,'notice'=>null]);
$dom=new DOMDocument(); @$dom->loadHTML($html); $xpath=new DOMXPath($dom);
signerUiCheck($xpath->query('//form[@enctype="multipart/form-data" and contains(@action,"/erapor/profil-penandatangan")]')->length===1,'Profile upload uses multipart self-service form');
signerUiCheck($xpath->query('//input[@type="file" and @name="signature" and @accept="image/png"]')->length===1,'PNG uploader uses native file form control');
signerUiCheck($xpath->query('//input[@name="csrf_token" and @value="signer-profile-csrf"]')->length===1,'Upload form includes CSRF token');
signerUiCheck($xpath->query('//input[@type="checkbox" and @name="consent"]')->length===1 && str_contains($html,'milik saya'), 'Owner consent is explicit');
signerUiCheck($xpath->query('//input[@type="file" and @value]')->length===0,'No prefilled file path or signature bytes are rendered');
signerUiCheck(!str_contains($html,'data:image/png') && !str_contains($html,'Cabut Persetujuan'),'No image or revocation control when signature is absent');
$profile['has_signature']=true; $profile['consented_at']='2026-09-25 10:30:00';
$saved=renderSignerProfile(['pageTitle'=>'Profil Penandatangan','profile'=>$profile,'canEdit'=>true,'notice'=>null]);
$savedDom=new DOMDocument(); @$savedDom->loadHTML($saved); $savedPath=new DOMXPath($savedDom);
signerUiCheck($savedPath->query('//img[contains(@src,"/erapor/profil-penandatangan/tanda-tangan")]')->length===1,'Preview streams only the current owner private image route');
signerUiCheck(str_contains($saved,'data-modal-open="modal-cabut-persetujuan-tanda-tangan"')
    && stripos($saved,'snapshot penerimaan atau persetujuan yang sudah tercatat tidak diubah')!==false,'Revocation uses shared confirmation modal and explains immutable snapshots');
signerUiCheck(!preg_match('/#[0-9a-f]{3,8}\b/i',$saved),'Profile UI contains no hardcoded hex colors');
$readOnly=renderSignerProfile(['pageTitle'=>'Profil Penandatangan','profile'=>$profile,'canEdit'=>false,'notice'=>null]);
signerUiCheck(!str_contains($readOnly,'Simpan Profil') && str_contains($readOnly,'Pratinjau tanda tangan tersimpan'),'Read-only role gets private status/preview but no mutation form');
echo "PASS: signer profile UI uses shared UI components, explicit owner consent, private preview route and revocation confirmation.\n";
