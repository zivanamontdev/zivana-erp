<?php
// Check real HTTP dispatch/controller/middleware rejection for every private route.
// Runs in CLI subprocesses with a fresh in-memory DB; this is not a browser test.
$router=new class {
    public array $routes=[];
    public function get($path,$handler):void {$this->routes[]=['GET',$path,$handler];}
    public function post($path,$handler):void {$this->routes[]=['POST',$path,$handler];}
};
require dirname(__DIR__).'/routes/web.php';
$count=0;
foreach($router->routes as [$verb,$path,$handler]) {
    if($handler[0]==='AuthController') continue;
    $path=preg_replace('/\{[^}]+\}/','1',$path);
    $process=proc_open([PHP_BINARY,__DIR__.'/account-rbac-regression.php','route',$verb,$path],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
    $result=json_decode($output,true);
    if($code!==0 || ($result['status']??0)!==403 || ($result['employees']??-1)!==0) throw new RuntimeException("Route guard failed: $verb $path: $output $error");
    $count++;
}
echo "PASS: $count private routes reject an authenticated role with no permissions via actual router and middleware.\n";
