<?php

/** Detached PPI uses existing RBAC permissions, with independent assignment and immutable artifact checks. */
final class EraporPpiController extends Controller
{
    public function index(): void
    {
        $this->ready();$this->middleware(AuthMiddleware::class);$this->middleware(RoleMiddleware::class,'eRapor','Persetujuan','lihat');
        $done=$this->input('status','')==='selesai';
        $this->view('admin.erapor-ppi.index',['pageTitle'=>'Persetujuan PPI Terpisah','activeNavItem'=>'erapor-approval',
            'breadcrumb'=>breadcrumb(['Persetujuan eRapor','/erapor/persetujuan'],'PPI Terpisah'),
            'tasks'=>EraporPpiWorkflow::inbox(Database::getInstance(),(int)$_SESSION['user_id'],$done),'done'=>$done]);
    }

    public function review(string $id): void
    {
        $this->ready();$this->middleware(AuthMiddleware::class);$this->readPermission();$sid=$this->id($id);
        try{$review=EraporPpiWorkflow::review(Database::getInstance(),$sid,(int)$_SESSION['user_id']);}catch(DomainException){$this->missing();}
        $edit=(new RoleMiddleware())->check('eRapor','Persetujuan','edit');
        $review['can_approve']=$review['can_approve']&&$edit;$review['can_publish']=$review['can_publish']&&$edit;
        $home=(int)$review['session']['guru_user_id']===(int)$_SESSION['user_id']?['Rapor Utama','/portal-guru/sesi/'.$sid]:['PPI Terpisah','/erapor/ppi'];
        $this->view('admin.erapor-ppi.review',['pageTitle'=>'Tinjau PPI Terpisah','activeNavItem'=>'erapor-approval',
            'breadcrumb'=>breadcrumb($home,'Tinjau'),'review'=>$review]);
    }

    public function approve(string $id,string $stage): void
    {
        $this->ready();$this->middleware(AuthMiddleware::class);$this->middleware(RoleMiddleware::class,'eRapor','Persetujuan','edit');
        $sid=$this->id($id);$code=match($stage){'wali'=>'WALI_KELAS','kepala'=>'KEPALA_SEKOLAH',default=>null};
        if (!$code) $this->missing();
        try{EraporPpiWorkflow::approve(Database::getInstance(),$sid,(int)$_SESSION['user_id'],$code);flashToast('Persetujuan PPI tersimpan.');}
        catch(DomainException $e){flashToast($e->getMessage(),'error');}
        catch(Throwable $e){error_log('PPI approval failed: '.get_class($e));flashToast('Persetujuan PPI gagal. Tidak ada perubahan yang disimpan.','error');}
        $this->redirect('/erapor/ppi/'.$sid);
    }

    public function publish(string $id): void
    {
        $this->ready();$this->middleware(AuthMiddleware::class);$this->middleware(RoleMiddleware::class,'eRapor','Persetujuan','edit');$sid=$this->id($id);
        try{EraporPpiWorkflow::publish(Database::getInstance(),$sid,(int)$_SESSION['user_id']);flashToast('PPI diterbitkan sebagai file tersendiri.');}
        catch(DomainException $e){flashToast($e->getMessage(),'error');}
        catch(Throwable $e){error_log('PPI publish failed: '.get_class($e));flashToast('PPI gagal diterbitkan. Silakan coba lagi.','error');}
        $this->redirect('/erapor/ppi/'.$sid);
    }

    public function pdf(string $id): void
    {
        $this->ready();$this->middleware(AuthMiddleware::class);$this->readPermission();$sid=$this->id($id);$db=Database::getInstance();
        if (!EraporPpiWorkflow::canView($db,$sid,(int)$_SESSION['user_id'])) $this->missing();
        try{
            $artifact=EraporPdfRevision::activeArtifact($db,$sid,'PPI')??EraporPpiWorkflow::artifact($db,$sid);
            header('X-Erapor-Pdf-Source: '.(($artifact['source']??'')==='revision'?'revision':'original'));
            if (isset($artifact['id'])) header('X-Erapor-Pdf-Revision: '.(int)$artifact['id']);
            $bytes=$artifact['bytes']??EraporPackagePdfRenderer::render(EraporPpiWorkflow::package($db,$sid));
            $name=($artifact['source']??'')==='revision'?$artifact['filename']:EraporPdfFilename::ppiForSession($db,$sid,$artifact===null);
        }catch(DomainException){http_response_code(422);echo 'PDF PPI belum dapat dibuat.';return;}
        header('Content-Type: application/pdf');header('Content-Disposition: '.($this->input('unduh','')==='1'?'attachment':'inline').'; filename="'.$name.'"');
        header('Content-Length: '.strlen($bytes));header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex');echo $bytes;exit;
    }

    public function share(string $id): void
    {
        $this->middleware(AuthMiddleware::class);$this->middleware(RoleMiddleware::class,'Portal Guru','Daftar Murid','edit');$this->ready();$sid=$this->id($id);
        $target=$this->input('penerima','');
        try{if(!is_string($target))throw new DomainException('Penerima tidak valid.');EraporPpiWorkflow::markShared(Database::getInstance(),$sid,(int)$_SESSION['user_id'],$target);$this->json(['ok'=>true]);}
        catch(DomainException){$this->json(['ok'=>false],409);}
    }

    private function readPermission(): void
    {
        $rbac=new RoleMiddleware();
        if (!$rbac->check('eRapor','Persetujuan','lihat')&&!$rbac->check('Portal Guru','Daftar Murid','lihat')) {http_response_code(403);require VIEW_PATH.'/errors/403.php';exit;}
    }
    private function ready(): void
    {
        header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
        if (!defined('ERAPOR_API_ENABLED')||!ERAPOR_API_ENABLED||!EraporPpiWorkflow::available(Database::getInstance())) $this->missing();
    }
    private function id(string $id): int {if(!preg_match('/^[1-9][0-9]*$/D',$id)||filter_var($id,FILTER_VALIDATE_INT)===false)$this->missing();return (int)$id;}
    private function missing(): never {http_response_code(404);require VIEW_PATH.'/errors/404.php';exit;}
}
