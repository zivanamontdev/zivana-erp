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

echo "PASS: $assertions PDF renderer structure checks.\n";
