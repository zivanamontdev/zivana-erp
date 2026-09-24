<?php
require __DIR__.'/../app/models/EraporApprovalPlan.php';
$checks=0;
function approvalCheck(bool $ok): void { global $checks; if (!$ok) throw new RuntimeException('Approval plan assertion failed'); $checks++; }
function approvalReject(callable $fn): void { try { $fn(); } catch (DomainException $e) { approvalCheck(true); return; } throw new RuntimeException('Invalid approval plan accepted'); }
$documents=[];
foreach (['RTS','AGAMA','UMMI','BING','PPI'] as $i=>$type) $documents[]=['id'=>$i+1,'rubrik_id'=>$i+11,'jenis_dokumen'=>$type];
$flows=[
    ['id'=>1,'kode'=>'KOORDINATOR_QURAN','label'=>'Koordinator Quran','urutan'=>1,'cakupan'=>'TERBATAS','aktif'=>1],
    ['id'=>2,'kode'=>'KOORDINATOR_BING','label'=>'Koordinator BING','urutan'=>1,'cakupan'=>'TERBATAS','aktif'=>1],
    ['id'=>3,'kode'=>'KEPALA_SEKOLAH','label'=>'Kepala Sekolah','urutan'=>2,'cakupan'=>'SEMUA','aktif'=>1],
];
$scopes=[['penyetuju_id'=>1,'rubrik_id'=>13],['penyetuju_id'=>2,'rubrik_id'=>14]];
$plan=EraporApprovalPlan::build($documents,$flows,$scopes);
$byCode=array_column($plan,null,'kode');
approvalCheck(array_column($plan,'urutan')===[1,1,2]);
approvalCheck($byCode['KOORDINATOR_QURAN']['dokumen_ids']===[3]);
approvalCheck($byCode['KOORDINATOR_BING']['dokumen_ids']===[4]);
approvalCheck($byCode['KEPALA_SEKOLAH']['dokumen_ids']===[1,2,3,4,5]);
$regular=EraporApprovalPlan::build(array_slice($documents,0,4),$flows,$scopes);
approvalCheck(count($regular[2]['dokumen_ids'])===4);
approvalReject(fn()=>EraporApprovalPlan::build([],$flows,$scopes));
approvalReject(fn()=>EraporApprovalPlan::build($documents,[],$scopes));
approvalReject(fn()=>EraporApprovalPlan::build($documents,$flows,[]));
foreach (['aktif'=>0,'urutan'=>2,'cakupan'=>'SEMUA'] as $key=>$value) {
    $bad=$flows; $bad[0][$key]=$value;
    approvalReject(fn()=>EraporApprovalPlan::build($documents,$bad,$scopes));
}
$bad=$scopes; $bad[]=['penyetuju_id'=>1,'rubrik_id'=>14];
approvalReject(fn()=>EraporApprovalPlan::build($documents,$flows,$bad));
$bad=$scopes; $bad[]=['penyetuju_id'=>3,'rubrik_id'=>11];
approvalReject(fn()=>EraporApprovalPlan::build($documents,$flows,$bad));
approvalReject(fn()=>EraporApprovalPlan::build($documents,[...$flows,$flows[0]],$scopes));
approvalReject(fn()=>EraporApprovalPlan::build([...$documents,$documents[0]],$flows,$scopes));
$flows[0]['label']='Changed later';
approvalCheck($byCode['KOORDINATOR_QURAN']['label']==='Koordinator Quran');
echo "PASS: $checks approval plan checks.\n";
