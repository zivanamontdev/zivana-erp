<?php

/** Temporary, explicit Superadmin maintenance UI. Normal/public downloads never gain mutation routes. */
final class EraporPdfRevisionController extends Controller
{
    public function index(): void
    {
        $this->guard();
        try {$kind=$this->kind();}catch(DomainException){$this->missing();}
        $this->render($this->number('session_id',false),$kind);
    }
    public function run(): void
    {
        $this->guard();$sid=$this->number('session_id',false);$kind='RAPOR';$check=null;$notice=null;$error=null;
        try {
            if (!hash_equals(self::token(),$this->value('token',256))) {http_response_code(403);throw new DomainException('REVISION_TOKEN_INVALID');}
            $kind=$this->kind();
            if ((int)($_SESSION['pdf_revision_next']??0)>time()) {http_response_code(429);throw new DomainException('REVISION_COOLDOWN');}
            $db=Database::getInstance();$actor=(int)$_SESSION['user_id'];$action=$this->value('action');
            if ($action==='migrate') {
                if (defined('ERAPOR_API_ENABLED')&&ERAPOR_API_ENABLED) throw new DomainException('REVISION_DISABLE_API_FOR_MIGRATION');
                if ($this->value('confirm')!=='1') throw new DomainException('REVISION_CONFIRM_REQUIRED');
                ignore_user_abort(true);if(function_exists('set_time_limit'))@set_time_limit(0);
                EraporMigrationRunner::apply($db,ROOT_PATH.'/database/migrations/'.EraporPdfRevision::MIGRATION.'.sql');
                $notice='Empat tabel revisi telah terpasang. Aktifkan kembali ERAPOR_API_ENABLED=true sebelum menyiapkan revisi.';
            } else {
                if (!defined('ERAPOR_API_ENABLED')||!ERAPOR_API_ENABLED) throw new DomainException('REVISION_API_DISABLED');
                if ($sid<1) throw new DomainException('REVISION_SESSION_REQUIRED');
                $_SESSION['pdf_revision_next']=time()+5;
                if ($action==='inspect') $check=EraporPdfRevision::readiness($db,$actor,$sid,$kind);
                elseif ($action==='prepare') {
                    if ($this->value('confirm')!=='1') throw new DomainException('REVISION_CONFIRM_REQUIRED');
                    $id=EraporPdfRevision::prepare($db,$actor,$sid,$kind,$this->value('package_hash'),$this->value('design_hash'),$this->value('reason',2000));
                    $notice=EraporPdfRevision::selection($db,$sid,$kind)===(string)$id
                        ?'Revisi #'.$id.' sudah aktif untuk preview internal. Tidak dibuat ulang atau diterbitkan ke orang tua.'
                        :'Revisi #'.$id.' disiapkan. Belum aktif dan tidak diterbitkan ke orang tua.';
                    $check=EraporPdfRevision::readiness($db,$actor,$sid,$kind);
                } elseif ($action==='activate') {
                    $id=$this->number('revision_id');$hash=$this->value('pdf_hash');
                    $seen=$_SESSION['pdf_revision_seen'][$sid.':'.$kind]??[];
                    $original=$this->value('original_hash');
                    if ($this->value('confirm')!=='1' || ($seen['format']??0)!==2 || ($seen['actor']??0)!==$actor
                        || ($seen['original']['time']??0)<time()-1800 || ($seen['revisions'][$id]['time']??0)<time()-1800
                        || ($seen['original']['hash']??'')!==$original || ($seen['revisions'][$id]['hash']??'')!==$hash) throw new DomainException('REVISION_REVIEW_REQUIRED');
                    EraporPdfRevision::activate($db,$actor,$sid,$kind,$id,$this->value('expected'),$hash,$this->value('reason',2000));
                    $notice='Revisi #'.$id.' aktif untuk preview internal. Arsip dan tautan orang tua tetap file asli.';
                } elseif ($action==='restore') {
                    if ($this->value('confirm')!=='1') throw new DomainException('REVISION_CONFIRM_REQUIRED');
                    EraporPdfRevision::restore($db,$actor,$sid,$kind,$this->value('expected'),$this->value('reason',2000));
                    $notice='Preview internal kembali memakai file asli. Semua revisi dan jejak audit tetap tersimpan.';
                } else throw new DomainException('REVISION_ACTION_INVALID');
                $_SESSION['pdf_revision_next']=time()+5;
            }
        } catch (DomainException $e) {
            $error=EraporPdfRevision::safeCode($e);
        } catch (Throwable $e) {
            $error='REVISION_OPERATION_FAILED';
            // Filtered evidence is available in the protected page, never raw SQL, values, paths or exception args.
            $_SESSION['pdf_revision_error']=EraporPdfDiagnostics::safeError($e);
        }
        $this->render($sid,$kind,$check,$notice,$error);
    }

    public function pdf(string $id,string $kind): void
    {
        $this->guard();$sid=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($sid===false || !in_array($kind,['RAPOR','PPI'],true) || !defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) $this->missing();
        $db=Database::getInstance();$choice=$this->value('revision');
        try {
            if ($choice==='asli') {
                $a=$kind==='RAPOR'?EraporPdfAccess::officialArtifact($db,$sid):EraporPpiWorkflow::artifact($db,$sid);
                if (!$a) $this->missing();$hash=hash('sha256',$a['bytes']);$filename='asli-'.$a['filename'];
            } else {
                $rid=filter_var($choice,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($rid===false)$this->missing();
                $a=EraporPdfRevision::reviewArtifact($db,(int)$_SESSION['user_id'],$sid,$kind,$rid);$hash=$a['sha256'];$filename='revisi-'.$rid.'-'.$a['filename'];
            }
            $key=$sid.':'.$kind;$seen=$_SESSION['pdf_revision_seen'][$key]??[];
            if (($seen['format']??0)!==2 || ($seen['actor']??0)!==(int)$_SESSION['user_id'] || ($seen['time']??0)<time()-1800) $seen=['format'=>2,'actor'=>(int)$_SESSION['user_id'],'revisions'=>[]];
            $receipt=['hash'=>$hash,'time'=>time()];
            if ($choice==='asli') $seen['original']=$receipt;else $seen['revisions'][$rid]=$receipt;
            $seen['time']=time();$_SESSION['pdf_revision_seen'][$key]=$seen;
            $_SESSION['pdf_revision_seen']=array_slice($_SESSION['pdf_revision_seen'],-16,null,true);
            session_write_close();
            header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',$filename).'"');header('Content-Length: '.strlen($a['bytes']));echo $a['bytes'];exit;
        } catch (DomainException) {http_response_code(422);header('Content-Type: text/plain; charset=UTF-8');echo 'PDF tidak lolos pemeriksaan integritas atau belum tersedia.';}
    }

    private function guard(): void
    {
        header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');
        if(strlen(self::token())<32)$this->missing();$this->middleware(AuthMiddleware::class);
        $q=Database::getInstance()->prepare('SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');$q->execute([(int)($_SESSION['user_id']??0)]);if($q->fetchColumn()!=='Superadmin')$this->missing();
    }
    private static function token(): string {return defined('ERAPOR_PDF_REVISION_TOKEN')?(string)ERAPOR_PDF_REVISION_TOKEN:'';}
    private function value(string $key,int $max=128): string {$v=$this->input($key,'');return is_string($v)?substr(trim($v),0,$max):'';}
    private function number(string $key,bool $required=true): int {$v=filter_var($this->value($key),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($v===false&&$required)throw new DomainException('REVISION_SESSION_REQUIRED');return $v===false?0:$v;}
    private function kind(): string {$v=$this->input('kind','RAPOR');if(!is_string($v)||!in_array($v,['RAPOR','PPI'],true))throw new DomainException('REVISION_KIND_INVALID');return $v;}
    private function missing(): never {http_response_code(404);require VIEW_PATH.'/errors/404.php';exit;}

    private function render(int $sid,string $kind,?array $check=null,?string $notice=null,?string $error=null): void
    {
        $db=Database::getInstance();$history=[];$selection='asli';$available=false;$originalHash='';$recent=[];
        try {
            $available=EraporPdfRevision::available($db);
            if ($available&&$sid>0) {
                $history=EraporPdfRevision::history($db,(int)$_SESSION['user_id'],$sid,$kind);$selection=EraporPdfRevision::selection($db,$sid,$kind);
                $table=$kind==='RAPOR'?'erapor_publikasi_pdf':'erapor_ppi_pdf';$q=$db->prepare("SELECT pdf_sha256 FROM $table WHERE sesi_id=?");$q->execute([$sid]);$originalHash=(string)$q->fetchColumn();
            }
            $recent=$db->query('SELECT s.id,s.kondisi,m.nama_lengkap,p.nama AS periode FROM erapor_sesi s JOIN murid m ON m.id=s.murid_id JOIN periode_penilaian p ON p.id=s.periode_id WHERE EXISTS(SELECT 1 FROM erapor_publikasi_pdf a WHERE a.sesi_id=s.id) OR EXISTS(SELECT 1 FROM erapor_ppi_pdf a WHERE a.sesi_id=s.id) ORDER BY s.id DESC LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
        } catch(Throwable $e) {$error=$error??'REVISION_SCHEMA_UNVERIFIED';}
        $technical=$_SESSION['pdf_revision_error']??null;unset($_SESSION['pdf_revision_error']);
        $this->view('admin.erapor-revisions.index',['pageTitle'=>'Revisi Desain PDF eRapor','breadcrumb'=>breadcrumb('Sistem','Revisi PDF'),'activeNavItem'=>'',
            'sid'=>$sid,'kind'=>$kind,'check'=>$check,'notice'=>$notice,'error'=>$error,'technical'=>$technical,'available'=>$available,'history'=>$history,'selection'=>$selection,'originalHash'=>$originalHash,'recent'=>$recent,
            'apiEnabled'=>defined('ERAPOR_API_ENABLED')&&ERAPOR_API_ENABLED]);
    }
}
