<?php
namespace Dompdf {
    // CPDF resolves extension_loaded in its namespace. Force ONLY its Imagick alpha-PNG branch in this process.
    function extension_loaded($name) {return $name==='imagick' || ($name!=='gmagick' && \extension_loaded($name));}
}
namespace {
    if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
    if(\extension_loaded('imagick')){echo json_encode(['skipped'=>true,'reason'=>'Native Imagick present; native renderer tests cover it.']);exit;}
    define('ROOT_PATH',dirname(__DIR__,2));
    require ROOT_PATH.'/vendor/autoload.php';require ROOT_PATH.'/app/helpers/functions.php';
    require ROOT_PATH.'/app/models/EraporPrintFont.php';require ROOT_PATH.'/app/models/EraporPackagePdfRenderer.php';
    if(!class_exists('Imagick',false)) {
    class ImagickException extends \RuntimeException {}
    /** Test double, NOT native Imagick. Real GD pixels implement the few operations CPDF needs. */
    class Imagick {
        const IMAGICK_EXTVER='3.7.0';const CHANNEL_ALPHA=1;const COMPOSITE_COPYRED=2;const COMPOSITE_COPYGREEN=3;const COMPOSITE_COPYBLUE=4;
        public static array $reads=[];public static bool $failWatermark=false;
        private $image;
        public function setRegistry($key,$value){return true;}
        public function setFormat($format){return true;}
        public static function getVersion(){return ['versionNumber'=>1799];}
        public function readImage($file){
            self::$reads[]=$file;
            if(str_starts_with($file,'data:'))throw new ImagickException('Strict fixture rejects inline image protocol');
            if(!is_file($file)||!is_readable($file))throw new ImagickException('Strict fixture requires readable local image');
            if(self::$failWatermark&&str_starts_with(basename($file),'ewm'))throw new ImagickException('Injected image failure');
            $this->image=imagecreatefrompng($file);if(!$this->image)throw new ImagickException('PNG decode failed');return true;
        }
        public function getImageAlphaChannel(){return true;}
        public function __clone(){
            $old=$this->image;$this->image=imagecreatetruecolor(imagesx($old),imagesy($old));
            imagealphablending($this->image,false);imagesavealpha($this->image,true);
            imagecopy($this->image,$old,0,0,0,0,imagesx($old),imagesy($old));
        }
        public function separateImageChannel($channel){
            for($y=0;$y<imagesy($this->image);$y++)for($x=0;$x<imagesx($this->image);$x++){
                $c=imagecolorsforindex($this->image,imagecolorat($this->image,$x,$y));$v=(int)round($c['alpha']*255/127);
                imagesetpixel($this->image,$x,$y,($v<<16)|($v<<8)|$v);
            }return true;
        }
        public function negateImage($gray){
            for($y=0;$y<imagesy($this->image);$y++)for($x=0;$x<imagesx($this->image);$x++)imagesetpixel($this->image,$x,$y,0xffffff^(imagecolorat($this->image,$x,$y)&0xffffff));return true;
        }
        public function newImage($w,$h,$color,$format){$this->image=imagecreatetruecolor($w,$h);imagefill($this->image,0,0,0xffffff);return true;}
        public function compositeImage($source,$mode,$x0,$y0){
            $mask=match($mode){self::COMPOSITE_COPYRED=>0xff0000,self::COMPOSITE_COPYGREEN=>0x00ff00,self::COMPOSITE_COPYBLUE=>0x0000ff};
            for($y=0;$y<imagesy($this->image);$y++)for($x=0;$x<imagesx($this->image);$x++){
                $a=imagecolorat($this->image,$x,$y)&0xffffff;$b=imagecolorat($source->image,$x,$y)&0xffffff;
                imagesetpixel($this->image,$x,$y,($a&(~$mask))|($b&$mask));
            }return true;
        }
        public function writeImage($file){imagesavealpha($this->image,false);return imagepng($this->image,$file);}
    }
    }
    $mode=$argv[1]??'fixed';$p=require __DIR__.'/erapor-watermark-package.php';$before=glob(sys_get_temp_dir().'/ewm*')?:[];
    try {
        if($mode==='legacy'){
            $pdf=new \Dompdf\Dompdf(\EraporPrintFont::options());
            $pdf->getCanvas()->image($p['watermark']['data_uri'],10,10,100,100);
            echo json_encode(['legacy_rejected'=>false]);
        }else{
            Imagick::$failWatermark=$mode==='failure';
            $result=\EraporPackagePdfRenderer::renderArtifact($p);
            $watermarks=array_values(array_filter(Imagick::$reads,static fn($file)=>str_starts_with(basename($file),'ewm')));
            echo json_encode(['ok'=>str_starts_with($result['bytes'],'%PDF-'),'pages'=>$result['pages'],
                'inline_reads'=>count(array_filter(Imagick::$reads,static fn($file)=>str_starts_with($file,'data:'))),
                'watermark_local_reads'=>count($watermarks),'watermark_files_removed'=>array_filter($watermarks,'file_exists')===[],
                'leftover_files'=>count(array_diff(glob(sys_get_temp_dir().'/ewm*')?:[],$before))]);
        }
    }catch(ImagickException $e){
        echo json_encode(['legacy_rejected'=>$mode==='legacy','injected_failure'=>$mode==='failure','class'=>get_class($e),
            'leftover_files'=>count(array_diff(glob(sys_get_temp_dir().'/ewm*')?:[],$before)),
            'cache_cleared'=>(new \ReflectionProperty(\Dompdf\Image\Cache::class,'_cache'))->getValue()===[]]);
    }finally{\Dompdf\Image\Cache::clear();}
}
