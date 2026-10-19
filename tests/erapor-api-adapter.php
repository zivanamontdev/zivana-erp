<?php
$cases=[
 ['show','',200],['guest','',401],['inactive','',401],['denied','',403],['disabled','',503],
 ['wrong-id','',422],['foreign','',409],['failure','',503],['csrf','{}',419],
 ['no-edit','{"changes":[]}',403],['no-send','{}',403],['content-type','{}',422],
 ['save','{"changes":[]}',200],['save','[]',422],['save','{',422],
 ['save','{"changes":{}}',422],
 ['save','{"changes":[],"actor_id":9}',422],['save','{"changes":{},"clock":"2030-01-01"}',422],
 ['save','{"changes":[{"key":"catatan","value":"abc","expected":null}]}',200],
 ['save','{"changes":[{"key":"catatan","value":"abc","expected":null,"extra":true}]}',422],
 ['foreign-doc','{"changes":[]}',409],['filled','{}',200],['reception','{}',200],['filled','{"complete":true}',422],
 ['save',str_repeat('a',1048577),422],
 ['reception','{"include_ppi":false}',200],['reception','{"include_ppi":true}',200],['reception','{"include_ppi":"false"}',422],
 ['ppi-choice','{"include":false}',200],['ppi-choice','{"include":true}',200],['ppi-choice','{"include":"false"}',422],
 ['ppi-choice','{}',422],['ppi-choice','{"include":false,"actor_id":99}',422],['ppi-choice-no-edit','{"include":false}',403],
 ['ppi-submit','{}',200],['ppi-submit','{"actor_id":99}',422],['ppi-submit-no-send','{}',403],
];
foreach ($cases as [$mode,$body,$expected]) {
    $p=proc_open([PHP_BINARY,__DIR__.'/fixtures/erapor-api-adapter.php',$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],$body); fclose($pipes[0]); $raw=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p); $r=json_decode($raw,true);
    if ($exit!==0 || ($r['status'] ?? null)!==$expected || !is_string($r['body']['csrf_token'] ?? null) || str_contains($raw,'secret')) throw new RuntimeException("API case $mode failed: $raw $err");
    if ($expected===200 && ($r['body']['data']['actor'] ?? null)!==1) throw new RuntimeException('Actor must come from session.');
    if ($expected===200 && $mode==='ppi-choice' && $r['body']['data']['ppi']['included']!==json_decode($body,true)['include']) throw new RuntimeException('PPI choice must be forwarded without coercion.');
    if ($expected===200 && $mode==='reception' && array_key_exists('include_ppi',json_decode($body,true)) && $r['body']['data']['include_ppi']!==json_decode($body,true)['include_ppi']) throw new RuntimeException('Finalisasi must receive the exact optional PPI choice.');
}
echo 'PASS: '.count($cases)." API adapter cases (isolated controller/RBAC/CSRF with service spies, not network E2E).\n";
