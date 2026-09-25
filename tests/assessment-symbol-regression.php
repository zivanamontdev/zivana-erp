<?php
define('ROOT_PATH',dirname(__DIR__));define('BASE_PATH','');
require ROOT_PATH.'/app/helpers/functions.php';
require ROOT_PATH.'/app/helpers/ui.php';
$files=['slash'=>'penilaian-1-sisi.svg','triangle-sm'=>'penilaian-2-sisi.svg','triangle-lg'=>'penilaian-3-sisi.svg','triangle-full'=>'penilaian-full.svg'];
foreach($files as $code=>$file){
    $src=skalaSimbolSrc($code);
    if(base64_decode(substr($src,strpos($src,',')+1))!==file_get_contents(ROOT_PATH.'/assets/'.$file)) throw new RuntimeException('Incorrect asset: '.$code);
    if(!str_contains(renderSkalaSimbol($code),'<img ')) throw new RuntimeException('Shared image renderer missing');
}
if(skalaSimbolSrc('unknown')!=='' || renderSkalaSimbol('unknown')!=='') throw new RuntimeException('Unknown rating should not invent a symbol');
// erapor_skala_nilai.simbol menyimpan glyph, bukan kode legacy; editor & PDF eRapor harus tetap dapat SVG yang sama.
foreach(['/'=>'slash','∠'=>'triangle-sm','△'=>'triangle-lg','▲'=>'triangle-full'] as $glyph=>$code){
    if(skalaSimbolSrc($glyph)==='' || skalaSimbolSrc($glyph)!==skalaSimbolSrc($code)) throw new RuntimeException('eRapor glyph not mapped: '.$glyph);
}
$html=uiSelect('nilai[1]','Nilai',[''=>'Pilih',1=>'Baru dikenalkan',2=>'Mulai Berkembang'],['value'=>2,'optionImages'=>[1=>skalaSimbolSrc('slash'),2=>skalaSimbolSrc('triangle-sm')]]);
$dom=new DOMDocument();@$dom->loadHTML($html);$xpath=new DOMXPath($dom);
if($xpath->query('//option[@data-option-image]')->length!==2 || $xpath->query('//option[@selected and @value="2"]')->length!==1) throw new RuntimeException('Image select must preserve submitted selection');
if($xpath->query('//option[@value=""]/@data-option-image')->length!==0) throw new RuntimeException('Placeholder must have no rating symbol');
echo "PASS: exact four SVG assets, unknown-code fallback, select image metadata, text labels and persisted selection.\n";
