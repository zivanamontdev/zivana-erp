<?php
// Synthetic, database-free report for both native-GD and strict-Imagick regression tests.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$logo='data:image/png;base64,'.base64_encode(file_get_contents(ROOT_PATH.'/assets/logo-icon.png'));
$signer=['nama'=>'Penandatangan Sintetis','ttd_data_uri'=>$logo];
return [
    'session'=>['status'=>'MENUNGGU_TTD'],'scoped'=>true,'draft'=>true,'watermark'=>['data_uri'=>$logo],
    'student'=>['nama_lengkap'=>'Murid Sintetis Watermark','tanggal_lahir'=>'2020-01-01','nama_kelas'=>'Kelas Uji'],
    'period'=>['jenis'=>'TENGAH','semester'=>'GANJIL','semester_label'=>'GANJIL','label'=>'Tengah Semester Ganjil','tahun_label'=>'2026/2027'],
    'school'=>['snapshot'=>['nama_komersial'=>'Sekolah Uji'],'logo_data_uri'=>$logo],
    'documents'=>[[
        'jenis_dokumen'=>'BING','nama'=>'BING','judul_cetak'=>'Statement of Result',
        'signers'=>[
            ['peran'=>'KOORDINATOR_BING','jabatan_cetak'=>'English Coordinator','posisi_cetak'=>'kiri','urutan'=>1],
            ['peran'=>'KEPALA_SEKOLAH','jabatan_cetak'=>'Principal','posisi_cetak'=>'tengah','urutan'=>2],
            ['peran'=>'GURU_KELAS','jabatan_cetak'=>'English Teacher','posisi_cetak'=>'kanan','urutan'=>3],
        ],
        'signature_current'=>['signers'=>['GURU_KELAS'=>$signer,'KOORDINATOR_BING'=>$signer,'KEPALA_SEKOLAH'=>$signer]],
        'period_values'=>['CURRENT'=>['nilai:1'=>'GOOD']],
        'form'=>['values'=>['nilai:1'=>'GOOD'],'definitions'=>[
            'scale'=>[['kode'=>'GOOD','label'=>'Good','definisi'=>'Synthetic grade definition']],
            'items'=>[['id'=>1,'grup'=>null,'penanda_cetak'=>'','label_cetak'=>'Communication']], 'comments'=>[],
        ]],
    ]],
];
