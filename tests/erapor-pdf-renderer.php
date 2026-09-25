<?php
if (!function_exists('e')) {
    function e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
}
require __DIR__.'/../app/models/EraporPackagePdfRenderer.php';

$assertions=0;
$check=static function(bool $condition,string $message) use (&$assertions): void {
    if (!$condition) throw new RuntimeException($message);
    $assertions++;
};
$student=['nama_lengkap'=>'<Nama Murid>','nama_panggilan'=>'Ananda','nisn'=>'123','tempat_lahir'=>'Makassar',
    'tanggal_lahir'=>'2020-01-01','nama_kelas'=>'Ranting Akasia','usia'=>'6 tahun'];
$package=['student'=>$student,'period'=>['jenis'=>'TENGAH','label'=>'Tengah Semester Ganjil','tahun_label'=>'2025/2026','semester_label'=>'GANJIL'],
    'school'=>['snapshot'=>['nama_komersial'=>'TK Zivana'],'logo_data_uri'=>'']];
$signers=[['peran'=>'GURU_KELAS','jabatan_cetak'=>'Guru Kelas','posisi_cetak'=>'kiri','urutan'=>1]];
$emptySignature=['signers'=>[],'tempat'=>'','tanggal'=>null];

$bing=new ReflectionMethod(EraporPackagePdfRenderer::class,'bing');
$bingHtml=$bing->invoke(null,$package,[
    'jenis_dokumen'=>'BING','nama'=>'Bahasa Inggris','judul_cetak'=>'Statement of Result','signers'=>$signers,
    'signature_current'=>$emptySignature,'period_values'=>['CURRENT'=>[]],
    'form'=>['definitions'=>[
        'scale'=>[],'comments'=>[],
        'items'=>[
            ['id'=>1,'grup'=>null,'penanda_cetak'=>'','label_cetak'=>'Attendance'],
            ['id'=>2,'grup'=>'Speaking Test Result','penanda_cetak'=>'a.','label_cetak'=>'Grammar'],
            ['id'=>3,'grup'=>'Speaking Test Result','penanda_cetak'=>'b.','label_cetak'=>'Pronunciation'],
            ['id'=>4,'grup'=>'Speaking Test Result','penanda_cetak'=>'c.','label_cetak'=>'Communication'],
        ],
    ]],
]);
$check(substr_count($bingHtml,'Speaking Test Result')===1,'BING group heading appears once for its contiguous indicators');
$check(str_contains($bingHtml,'&lt;Nama Murid&gt;') && !str_contains($bingHtml,'<Nama Murid>'),'PDF identity text is escaped');

$agama=new ReflectionMethod(EraporPackagePdfRenderer::class,'agama');
$agamaHtml=$agama->invoke(null,$package,[
    'jenis_dokumen'=>'AGAMA','nama'=>'Agama','judul_cetak'=>'Rapor Agama','signers'=>$signers,
    'signature_current'=>$emptySignature,'period_values'=>[],
    'form'=>['definitions'=>['scopes'=>[['id'=>1,'nomor_romawi'=>'I','nama'=>'AQIDAH']],
        'subscopes'=>[['id'=>1,'huruf'=>null,'nama'=>null,'implisit'=>1]],'stages'=>[]]],
    'all_items'=>[
        ['id'=>1,'semester'=>'GANJIL','lingkup_id'=>1,'sub_id'=>1,'nomor'=>1,'teks'=>'Capaian ganjil'],
        ['id'=>2,'semester'=>'GENAP','lingkup_id'=>1,'sub_id'=>1,'nomor'=>1,'teks'=>'Capaian genap'],
    ],
    'item_names'=>[],'narrative'=>'Narasi murid',
]);
$ganjil=strpos($agamaHtml,'CAPAIAN SEMESTER GANJIL');
$genap=strpos($agamaHtml,'CAPAIAN SEMESTER GENAP');
$check($ganjil!==false && $genap!==false && $ganjil<$genap,'Agama prints semester sections in Ganjil then Genap order');
$check(substr_count($agamaHtml,'CAPAIAN SEMESTER GANJIL')===1 && substr_count($agamaHtml,'CAPAIAN SEMESTER GENAP')===1,
    'Agama prints one section heading per semester');

$ummi=new ReflectionMethod(EraporPackagePdfRenderer::class,'ummi');
$ummiBase=[
    'jenis_dokumen'=>'UMMI','nama'=>'Ummi','judul_cetak'=>'Rapor Ummi','signers'=>$signers,
    'signature_current'=>$emptySignature,'period_values'=>[],'tests'=>[],'items'=>[],'show_pra_tk'=>false,
    'form'=>['definitions'=>['scale'=>[],'volumes'=>[],'items'=>[]]],
];
$emptyUmmiHtml=$ummi->invoke(null,$package,$ummiBase);
$emptyTestTable=substr($emptyUmmiHtml,strpos($emptyUmmiHtml,'<h2>TES KENAIKAN JILID</h2>'));
$emptyTestTable=substr($emptyTestTable,0,strpos($emptyTestTable,'</table>')+8);
preg_match('~<tbody>(.*?)</tbody>~s',$emptyTestTable,$emptyTestBody);
$check(substr_count($emptyTestBody[1] ?? '', '<tr')===2 && substr_count($emptyTestBody[1] ?? '', 'test-placeholder-row')===2
    && substr_count($emptyTestBody[1] ?? '', '>—</td>')===8,'Empty Ummi tests print two placeholder rows without becoming stored test values');

$oneUmmi=$ummiBase;
$oneUmmi['tests']=['TENGAH'=>[['urutan'=>1,'tanggal_tes'=>'2026-09-01','jilid'=>'I','nilai'=>'B']]];
$oneUmmiHtml=$ummi->invoke(null,$package,$oneUmmi);
$oneTestTable=substr($oneUmmiHtml,strpos($oneUmmiHtml,'<h2>TES KENAIKAN JILID</h2>'));
$oneTestTable=substr($oneTestTable,0,strpos($oneTestTable,'</table>')+8);
$check(substr_count($oneTestTable,'<tr class="test-placeholder-row">')===1
    && str_contains($oneTestTable,'2026-09-01'),'One Ummi test prints one data row and pads to the two-row minimum');

$manyUmmi=$ummiBase;
$manyUmmi['tests']=['TENGAH'=>[
    ['urutan'=>1,'tanggal_tes'=>'2026-09-01','jilid'=>'I','nilai'=>'B'],
    ['urutan'=>2,'tanggal_tes'=>'2026-09-05','jilid'=>'I','nilai'=>'A'],
    ['urutan'=>3,'tanggal_tes'=>'2026-09-09','jilid'=>'II','nilai'=>'A+'],
]];
$manyUmmiHtml=$ummi->invoke(null,$package,$manyUmmi);
$manyTestTable=substr($manyUmmiHtml,strpos($manyUmmiHtml,'<h2>TES KENAIKAN JILID</h2>'));
$manyTestTable=substr($manyTestTable,0,strpos($manyTestTable,'</table>')+8);
$check(substr_count($manyTestTable,'<tr class="test-placeholder-row">')===0
    && substr_count($manyTestTable,'2026-09-')===3
    && str_contains($manyTestTable,'2026-09-09'),'Ummi prints every recorded test without truncating to the two-row baseline');

echo "PASS: $assertions PDF renderer structure checks.\n";
