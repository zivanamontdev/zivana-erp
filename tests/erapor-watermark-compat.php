<?php
/** No DB/network. Native renderer + isolated strict-Imagick transport regression. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('ROOT_PATH',dirname(__DIR__));
require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
require ROOT_PATH.'/app/models/EraporPrintFont.php';require ROOT_PATH.'/app/models/EraporPackagePdfRenderer.php';
$p=require __DIR__.'/fixtures/erapor-watermark-package.php';
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$before=glob(sys_get_temp_dir().'/ewm*')?:[];
$temporary=new ReflectionMethod(EraporPackagePdfRenderer::class,'watermarkFile');
[$file,$ratio]=$temporary->invoke(null,$p['watermark']['data_uri'],sys_get_temp_dir());
try{
 $check(is_file($file)&&is_readable($file)&&!str_contains($file,'data:'),'Watermark is a real readable local file');
 $check(file_get_contents($file)===file_get_contents(ROOT_PATH.'/assets/logo-icon.png'),'Snapshot PNG bytes remain exactly unchanged');
 $check(abs($ratio-96/92)<.00001,'Original image aspect ratio is preserved');
 if(DIRECTORY_SEPARATOR==='/')$check((fileperms($file)&0777)===0600,'Temporary PNG is private');
}finally{unlink($file);}
foreach(['https://example.invalid/watermark.png','file:///etc/passwd','data:image/svg+xml;base64,AAAA','data:image/png;base64,invalid','data:image/png;base64,'.base64_encode('not PNG')] as $bad){
 try{$temporary->invoke(null,$bad,sys_get_temp_dir());throw new RuntimeException('Invalid image accepted');}catch(DomainException){$check(true,'Invalid paths/protocols/image bytes rejected');}
}
try{$temporary->invoke(null,$p['watermark']['data_uri'],sys_get_temp_dir().'/missing-'.bin2hex(random_bytes(8)));throw new RuntimeException('Invalid temp directory accepted');}catch(RuntimeException $e){$check($e->getMessage()==='File sementara watermark tidak dapat dibuat.','No silent temp fallback or remote fetch');}
$r=EraporPackagePdfRenderer::renderArtifact($p);
$check(str_starts_with($r['bytes'],'%PDF-')&&$r['pages']>=2,'Real native PDF render succeeds with watermark and transparent signatures');
$check(array_diff(glob(sys_get_temp_dir().'/ewm*')?:[],$before)===[],'Watermark temporary file removed after success');
foreach(['render_layout','render_watermark_image'] as $stage){
 $found=false;
 try{EraporPackagePdfRenderer::renderArtifact($p,static function($current)use($stage,&$found,$before){if($current!==$stage)return;$new=array_diff(glob(sys_get_temp_dir().'/ewm*')?:[],$before);$found=count($new)>0;throw new RuntimeException('Injected render failure');});throw new RuntimeException('Failure injection missed');}
 catch(RuntimeException $e){
  $check($e->getMessage()==='Injected render failure'&&$found,'Temporary PNG exists during injected phase '.$stage);
  $cache=new ReflectionProperty(\Dompdf\Image\Cache::class,'_cache');
  $check($cache->getValue()===[],'Cached HTML signature/logo files cleared on failure at '.$stage);
 }
 finally{\Dompdf\Image\Cache::clear();}
 $check(array_diff(glob(sys_get_temp_dir().'/ewm*')?:[],$before)===[],'Temporary PNG removed on exception at '.$stage);
}
foreach(['legacy','fixed','failure'] as $mode){
 $process=proc_open([PHP_BINARY,__DIR__.'/fixtures/erapor-imagick-strict.php',$mode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
 $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$result=json_decode($out,true);
 $check($code===0&&is_array($result),'Strict image-reader fixture completes: '.$mode.' '.$err);
 if(!empty($result['skipped']))continue;
 if($mode==='legacy')$check($result['legacy_rejected']&&$result['class']==='ImagickException','Old direct canvas data URI reproduces ImagickException');
 elseif($mode==='fixed'){
  $check($result['ok']&&$result['pages']>=2&&$result['inline_reads']===0,'New renderer never passes data URI to strict Imagick branch');
  $check($result['watermark_local_reads']>0&&$result['watermark_files_removed']&&$result['leftover_files']===0,'Strict Imagick branch consumes local PNG and all watermark files are cleaned');
 }else $check($result['injected_failure']&&$result['leftover_files']===0&&$result['cache_cleared'],'A CPDF image-reader exception cleans watermark and HTML-image caches');
}
echo "PASS: $checks watermark compatibility checks (native output, strict Imagick transport simulation, success/failure cleanup).\n";
