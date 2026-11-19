<?php
/** Reviewed initial package composition, not an activation flag.
 * Future session creation must snapshot this into session-document rows.
 * RAS terikat ke RAS_MONTESSORI_V1; kode null akan memblokir paket akhir semester.
 */
return [
    ['jenis'=>'RTS','kode'=>'RTS_MONTESSORI_V1','periode'=>'TENGAH','cakupan'=>'TAHUNAN','khusus_abk'=>false],
    ['jenis'=>'RAS','kode'=>'RAS_MONTESSORI_V1','periode'=>'AKHIR','cakupan'=>'TAHUNAN','khusus_abk'=>false],
    ['jenis'=>'AGAMA','kode'=>'AGAMA_V1','periode'=>null,'cakupan'=>'TAHUNAN','khusus_abk'=>false],
    ['jenis'=>'UMMI','kode'=>'UMMI_V1','periode'=>null,'cakupan'=>'SEMESTER','khusus_abk'=>false],
    ['jenis'=>'BING','kode'=>'BING_V1','periode'=>null,'cakupan'=>'SEMESTER','khusus_abk'=>false],
    ['jenis'=>'PPI','kode'=>'PPI_V1','periode'=>null,'cakupan'=>'SEMESTER','khusus_abk'=>true],
];
