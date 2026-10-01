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
$signers=[
    ['peran'=>'GURU_KELAS','jabatan_cetak'=>'Guru Kelas','posisi_cetak'=>'kiri','urutan'=>1],
    ['peran'=>'KEPALA_SEKOLAH','jabatan_cetak'=>'Kepala Sekolah','posisi_cetak'=>'tengah','urutan'=>2],
];
$emptySignature=['signers'=>[],'tempat'=>'','tanggal'=>null];

$signatureBlock=new ReflectionMethod(EraporPackagePdfRenderer::class,'signatureBlock');
$signatureHtml=$signatureBlock->invoke(null,$package,[
    'signers'=>[
        ['peran'=>'KOORDINATOR_BING','jabatan_cetak'=>'English Coordinator','posisi_cetak'=>'kiri','urutan'=>1],
        ['peran'=>'GURU_KELAS','jabatan_cetak'=>'English Teacher','posisi_cetak'=>'kanan','urutan'=>2],
        ['peran'=>'KEPALA_SEKOLAH','jabatan_cetak'=>'Principal','posisi_cetak'=>'tengah','urutan'=>3],
    ],
], 'Pengesahan Rapor', ['signers'=>[
    'GURU_KELAS'=>['nama'=>'Wali Kelas','ttd_data_uri'=>'data:image/png;base64,d2FsaQ=='],
    'KOORDINATOR_BING'=>['nama'=>'Koordinator Inggris','ttd_data_uri'=>null],
    'KEPALA_SEKOLAH'=>['nama'=>'Kepala Sekolah','ttd_data_uri'=>null],
], 'tempat'=>'Makassar','tanggal'=>'1 Oktober 2026']);
$check(!str_contains($signatureHtml,'English Teacher') && !str_contains($signatureHtml,'Wali Kelas'),
    'Class-teacher signature label and evidence are omitted from report previews');
$check(str_contains($signatureHtml,'English Coordinator') && str_contains($signatureHtml,'Principal')
    && str_contains($signatureHtml,'Orang Tua Siswa'),'Coordinator, principal, and parent receipt slots remain visible');

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
$check(str_contains($bingHtml,'Acknowledged by') && str_contains($bingHtml,'Student&#039;s Parent')
    && !str_contains($bingHtml,'Orang Tua Siswa'),'BING signature caption uses English parent wording');

$agama=new ReflectionMethod(EraporPackagePdfRenderer::class,'agama');
$agamaDoc=[
    'jenis_dokumen'=>'AGAMA','nama'=>'Agama','judul_cetak'=>'Rapor Agama','signers'=>$signers,
    'signature_current'=>$emptySignature,'period_values'=>['TENGAH_GANJIL'=>['nilai:1'=>'TALQIN'],'TENGAH_GENAP'=>['nilai:2'=>'TADIB']],
    'form'=>['definitions'=>['scopes'=>[['id'=>1,'nomor_romawi'=>'I','nama'=>'AQIDAH']],
        'subscopes'=>[['id'=>1,'huruf'=>null,'nama'=>null,'implisit'=>1]],'stages'=>[]]],
    'all_items'=>[
        ['id'=>1,'semester'=>'GANJIL','lingkup_id'=>1,'sub_id'=>1,'nomor'=>1,'teks'=>'Capaian ganjil'],
        ['id'=>2,'semester'=>'GENAP','lingkup_id'=>1,'sub_id'=>1,'nomor'=>1,'teks'=>'Capaian genap'],
    ],
    'item_names'=>[],'narrative'=>'Narasi murid',
];
$agamaHtml=$agama->invoke(null,$package,$agamaDoc);
$ganjil=strpos($agamaHtml,'CAPAIAN SEMESTER GANJIL');
$genap=strpos($agamaHtml,'CAPAIAN SEMESTER GENAP');
$check($ganjil!==false && $genap!==false && $ganjil<$genap,'Agama prints semester sections in Ganjil then Genap order');
$check(substr_count($agamaHtml,'CAPAIAN SEMESTER GANJIL')===1 && substr_count($agamaHtml,'CAPAIAN SEMESTER GENAP')===1,
    'Agama prints one section heading per semester');
$check(!str_contains($agamaHtml,'AKHIR SEMESTER') && str_contains($agamaHtml,'agama-wide'),'Agama hides Akhir Semester columns and widens the table before RAS exists');
$ganjilOnly=$agamaDoc; $ganjilOnly['period_values']=['TENGAH_GANJIL'=>['nilai:1'=>'TALQIN']];
$ganjilOnlyHtml=$agama->invoke(null,$package,$ganjilOnly);
$check(str_contains($ganjilOnlyHtml,'CAPAIAN SEMESTER GANJIL') && !str_contains($ganjilOnlyHtml,'CAPAIAN SEMESTER GENAP'),'Agama hides Capaian Semester Genap until genap is filled');
$withRas=$agamaDoc; $withRas['period_values']['AKHIR_GANJIL']=['nilai:1'=>'TADIB'];
$withRasHtml=$agama->invoke(null,$package,$withRas);
$check(str_contains($withRasHtml,'AKHIR SEMESTER') && !str_contains($withRasHtml,'agama-wide'),'Agama prints Akhir Semester columns once RAS is filled');
$check(str_contains($agamaHtml,'<p class="signature-title">Pengesahan Rapor Agama Islam</p>') && !str_contains($agamaHtml,'signature-identity'),'Signature block uses a centered title without the report header when it stays on the page');
$withDash=$agamaDoc; $withDash['period_values']['TENGAH_GANJIL']['nilai:1']='-';
$withDashHtml=$agama->invoke(null,$package,$withDash);
$check(str_contains($withDashHtml,'<th rowspan="2">-</th>')
    && str_contains($withDashHtml,'<td class="grade agama-grade"><img src="data:image/svg+xml;base64,'), 'Agama no-note dash is visible in the printed assessment');

$rts=new ReflectionMethod(EraporPackagePdfRenderer::class,'rts');
$rtsDoc=['jenis_dokumen'=>'RTS','nama'=>'RTS','judul_cetak'=>'RTS','signers'=>$signers,'period_values'=>[],
    'form'=>['definitions'=>['scale'=>[],'areas'=>[],'subareas'=>[],'groups'=>[],'items'=>[]]],
    'signature_periods'=>['GANJIL'=>$emptySignature]];
$rtsHtml=$rts->invoke(null,$package,$rtsDoc);
$check(substr_count($rtsHtml,'signature-block')===1 && str_contains($rtsHtml,'Pengesahan Tengah Semester Ganjil<'),'RTS prints only the Ganjil signature block before TS Genap exists');
$check(str_contains($rtsHtml,'Mengetahui,<br>Orang Tua Siswa'),'Non-BING signature caption remains Indonesian');
$rtsDoc['signature_periods']['GENAP']=$emptySignature;
$rtsHtml=$rts->invoke(null,$package,$rtsDoc);
$check(substr_count($rtsHtml,'signature-block')===1 && str_contains($rtsHtml,'Pengesahan Tengah Semester Ganjil dan Genap'),'RTS merges Ganjil and Genap into one signature block');

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
