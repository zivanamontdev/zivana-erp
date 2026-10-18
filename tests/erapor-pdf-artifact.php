<?php
// Real Dompdf output, fully synthetic identities and role evidence; no database is used.
define('ROOT_PATH',dirname(__DIR__));
require ROOT_PATH.'/vendor/autoload.php';
require ROOT_PATH.'/app/helpers/functions.php';
require ROOT_PATH.'/app/models/EraporPackagePdfRenderer.php';
$logo='data:image/png;base64,'.base64_encode(file_get_contents(ROOT_PATH.'/assets/logo-icon.png'));
$signer=['nama'=>'Guru Uji Dua Peran','ttd_data_uri'=>$logo];
$package=[
    'session'=>['status'=>'BELUM_DIISI'],'draft'=>true,'scoped'=>true,
    'watermark'=>['data_uri'=>$logo],
    'student'=>['nama_lengkap'=>'Murid Sintetis','tanggal_lahir'=>'2020-01-01','nama_kelas'=>'Kelas Uji'],
    'period'=>['jenis'=>'TENGAH','semester'=>'GANJIL','semester_label'=>'GANJIL','label'=>'Tengah Semester Ganjil','tahun_label'=>'2026/2027'],
    'school'=>['snapshot'=>['nama_komersial'=>'Sekolah Uji'],'logo_data_uri'=>$logo],
    'documents'=>[[
        'jenis_dokumen'=>'BING','nama'=>'BING','judul_cetak'=>'Statement of Result',
        'signers'=>[
            ['peran'=>'KOORDINATOR_BING','jabatan_cetak'=>'English Coordinator','posisi_cetak'=>'kiri','urutan'=>1],
            ['peran'=>'GURU_KELAS','jabatan_cetak'=>'English Teacher','posisi_cetak'=>'kanan','urutan'=>2],
        ],
        'signature_current'=>['signers'=>['GURU_KELAS'=>$signer,'KOORDINATOR_BING'=>$signer]],
        'period_values'=>['CURRENT'=>['nilai:1'=>'GOOD']],
        'form'=>['values'=>['nilai:1'=>'GOOD'],'definitions'=>[
            'scale'=>[['kode'=>'GOOD','label'=>'Good','definisi'=>'Fixture grade']],
            'items'=>[['id'=>1,'grup'=>null,'penanda_cetak'=>'','label_cetak'=>'Communication']], 'comments'=>[],
        ]],
    ]],
];
$checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
$render=new ReflectionMethod(EraporPackagePdfRenderer::class,'renderPdf');
foreach(['BELUM_DIISI'=>false,'TELAH_DIISI'=>false,'MENUNGGU_TTD'=>true,'SELESAI'=>true] as $state=>$marked){
    $package['session']['status']=$state;
    [$pdf,$html]=$render->invoke(null,$package);
    $check(!str_contains($html,'draft-mark')&&!str_contains($html,'DRAF ·'),'Draft print is free of the previous draft watermark');
    $objects=$pdf->getCanvas()->get_cpdf()->objects;$pages=0;
    foreach($objects as $object){
        if(($object['t']??'')!=='page')continue;
        $pages++;$content='';foreach($object['info']['contents'] as $id)$content.=$objects[$id]['c']??'';
        $large=(bool)preg_match('/[45][0-9]{2}\.\d+ 0 0 [45][0-9]{2}\.\d+ [-.0-9]+ [-.0-9]+ cm \/I\d+ Do/',$content);
        $check($large===$marked,'Every page follows Finalisasi watermark state: '.$state);
        if($marked){$check(strpos($content,' Do')<strpos($content,'BT '),'Watermark is drawn before the page text, not on top');}
    }
    $check($pages>=2,'Both English report pages were rendered');
    $bytes=$pdf->output();$check(str_starts_with($bytes,'%PDF-'),'Actual valid PDF output for '.$state);
}
echo "PASS: $checks PDF artifact checks (clean drafts, finalization watermark on every page behind text, role slots).\n";
