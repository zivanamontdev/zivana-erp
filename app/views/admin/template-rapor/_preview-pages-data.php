<?php
// Design fixtures only; pages 3–4 temporarily repeat pages 1–2.
$selfCare = [
    'Menutup mulut saat batuk dan bersin', 'Membersihkan hidung',
    'Dapat mengelap keringat', 'Menggunakan keran air dengan benar',
    'Mencuci tangan dengan benar', 'Memakai dan melepas sepatu dan kaus kaki',
    'Makan sendiri', 'Membuka dan memasang Velcro', 'Membuka dan menutup resleting',
    'Membuka dan memasang kancing besar', 'Membuka dan memasang kancing kecil',
    'Membuka dan memasang kancing tekan', 'Membuka dan memasang sabuk',
    'Membuka dan memasang kancing kait', 'Membuka dan memasang tali sepatu',
    'Membuka dan memakai jaket', 'Membuka dan memakai pakaian', 'Melipat baju',
    'Mengikat pita', 'Dapat menggunakan toilet',
];
$firstPage = ['legend' => true, 'columns' => [[
    ['title' => 'AREA KETERAMPILAN HIDUP', 'apparatus' => true, 'groups' => [
        ['name' => 'a. Perawatan Diri', 'items' => $selfCare],
    ]],
]]];
$secondPage = ['legend' => false, 'columns' => [
    [
        ['title' => 'AREA KETERAMPILAN HIDUP', 'groups' => [
            ['name' => 'e. Tata Krama', 'items' => [
                'Mengucap dan menjawab salam', 'Mengucap permisi pada situasi yang tepat',
                'Mengucap terima kasih dan menjawabnya', 'Meminta maaf pada situasi yang tepat',
                'Dapat meminta bantuan jika membutuhkan', 'Meminta izin',
            ]],
            ['name' => 'f. Kebiasaan Bekerja', 'items' => [
                'Mengembalikan material pada tempatnya', 'Bekerja secara mandiri', 'Bekerja dengan rapi dan teratur',
            ]],
        ]],
        ['title' => 'AREA SENSORIAL', 'groups' => [
            ['name' => 'a. Indra Penglihatan', 'items' => ['Knobbed Cylinder', 'Pink Tower', 'Brown Stair', 'Knobless Cylinder', 'Kotak warna 1', 'Kotak warna 2', 'Kotak warna 3']],
            ['name' => 'b. Indra Peraba', 'items' => ['Touch board']],
        ]],
    ],
    [
        ['title' => 'AREA SENSORIAL', 'groups' => [
            ['name' => '', 'items' => ['Touch tablets', 'Touch fabric', 'Stereognostic Bag', 'Baric tablets', 'Thermic tablets', 'Geometry solid', 'Geometry cabinet', 'Constructive triangles', 'Binomial cube', 'Trinomial cube']],
            ['name' => 'c. Indra Penciuman', 'items' => ['Smelling bottle']],
            ['name' => 'd. Indra Perasa', 'items' => ['Tasting solution']],
            ['name' => 'e. Indra Pendengaran', 'items' => ['Sound boxes']],
        ]],
    ],
]];
return [$firstPage, $secondPage, $firstPage, $secondPage];
