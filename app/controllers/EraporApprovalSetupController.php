<?php

/** Admin-facing, explicitly authorized approver assignment screen. */
final class EraporApprovalSetupController extends Controller
{
    public function index(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Penugasan Penyetuju','lihat');
        try {
            $state=EraporApprovalAssignment::read(Database::getInstance());
            $configurationIssue=null;
        } catch (Throwable $e) {
            error_log('eRapor approval assignment read failed: '.$e->getMessage());
            $state=['ready'=>false,'flows'=>[]];
            $configurationIssue='Skema atau konfigurasi alur belum siap. Terapkan migrasi dan seed eRapor, lalu periksa kembali data konfigurasinya.';
        }
        $this->view('admin.erapor-approval.assignments',[
            'pageTitle'=>'Penugasan Penyetuju','breadcrumb'=>breadcrumb(['Penugasan Penyetuju','/erapor/persetujuan/penugasan'],'Pengaturan'),
            'activeNavItem'=>'erapor-assignments','state'=>$state,'configurationIssue'=>$configurationIssue,
            'notice'=>$this->takeNotice(),
        ]);
    }

    public function update(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Penugasan Penyetuju','edit');
        try {
            $result=EraporApprovalAssignment::save(Database::getInstance(),(int)$_SESSION['user_id'],
                is_array($_POST['assignments'] ?? null)?$_POST['assignments']:[],(string)($_POST['reason'] ?? ''));
            $_SESSION['erapor_assignment_notice']=['type'=>'success','message'=>$result['changed']
                ? 'Penugasan tersimpan. Perubahan untuk '.$result['changed'].' akun dicatat dalam audit.'
                : 'Tidak ada perubahan penugasan.'];
        } catch (DomainException $e) {
            $_SESSION['erapor_assignment_notice']=['type'=>'error','message'=>$e->getMessage()];
        } catch (Throwable $e) {
            error_log('eRapor approval assignment update failed: '.$e->getMessage());
            $_SESSION['erapor_assignment_notice']=['type'=>'error','message'=>'Perubahan tidak tersimpan. Muat ulang halaman dan pastikan skema/konfigurasi eRapor siap.'];
        }
        $this->redirect('/erapor/persetujuan/penugasan');
    }

    private function enabled(): bool
    {
        if (defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED) return true;
        $this->notFound();
        return false;
    }

    private function notFound(): void
    {
        http_response_code(404);
        require VIEW_PATH.'/errors/404.php';
    }

    private function takeNotice(): ?array
    {
        $notice=$_SESSION['erapor_assignment_notice'] ?? null;
        unset($_SESSION['erapor_assignment_notice']);
        return is_array($notice) && isset($notice['type'],$notice['message'])?$notice:null;
    }
}
