<?php

/** Self-service NUPTK/signature consent; target user is always the logged-in owner. */
final class EraporSignerProfileController extends Controller
{
    public function index(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Profil Penandatangan','lihat');
        try { $profile=EraporSignerProfile::read(Database::getInstance(),(int)$_SESSION['user_id']); }
        catch (DomainException $e) { $this->deny(); return; }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        $this->view('admin.erapor-signer-profile.index',[
            'pageTitle'=>'Profil Penandatangan','breadcrumb'=>null,'activeNavItem'=>'erapor-signer-profile',
            'profile'=>$profile,'canEdit'=>(new RoleMiddleware())->check('eRapor','Profil Penandatangan','edit'),
            'notice'=>$this->takeNotice(),
        ]);
    }

    public function update(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Profil Penandatangan','edit');
        try {
            $upload=$this->uploadedPng();
            if (isset($_POST['nuptk']) && !is_string($_POST['nuptk'])) {
                throw new DomainException('NUPTK harus berupa teks 16 digit atau kosong.');
            }
            $result=EraporSignerProfile::save(Database::getInstance(),(int)$_SESSION['user_id'],
                $_POST['nuptk'] ?? null,$upload,($_POST['consent'] ?? '')==='1');
            $_SESSION['erapor_signer_notice']=['type'=>'success','message'=>$result['changed']
                ? 'Profil penandatangan tersimpan.' : 'Tidak ada perubahan profil.'];
        } catch (DomainException $e) {
            $_SESSION['erapor_signer_notice']=['type'=>'error','message'=>$e->getMessage()];
        } catch (Throwable $e) {
            error_log('eRapor signer profile update failed: '.$e->getMessage());
            $_SESSION['erapor_signer_notice']=['type'=>'error','message'=>'Profil tidak tersimpan. Muat ulang halaman dan coba kembali.'];
        }
        $this->redirect('/erapor/profil-penandatangan');
    }

    public function revoke(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Profil Penandatangan','edit');
        try {
            $result=EraporSignerProfile::revoke(Database::getInstance(),(int)$_SESSION['user_id']);
            $_SESSION['erapor_signer_notice']=['type'=>'success','message'=>$result['changed']
                ? 'Persetujuan tanda tangan dicabut. Snapshot rapor yang telah dibuat tetap tersimpan.' : 'Belum ada tanda tangan tersimpan untuk dicabut.'];
        } catch (Throwable $e) {
            error_log('eRapor signer consent revocation failed: '.$e->getMessage());
            $_SESSION['erapor_signer_notice']=['type'=>'error','message'=>'Persetujuan tanda tangan belum dapat dicabut. Coba kembali.'];
        }
        $this->redirect('/erapor/profil-penandatangan');
    }

    public function signature(): void
    {
        if (!$this->enabled()) return;
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class,'eRapor','Profil Penandatangan','lihat');
        try { $bytes=EraporSignerProfile::signature(Database::getInstance(),(int)$_SESSION['user_id']); }
        catch (DomainException $e) { $this->notFound(); return; }
        if ($bytes===null) { $this->notFound(); return; }
        header('Content-Type: image/png');
        header('Content-Length: '.strlen($bytes));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow');
        echo $bytes;
    }

    private function uploadedPng(): ?string
    {
        $file=$_FILES['signature'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
        $error=(int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error!==UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new DomainException($error===UPLOAD_ERR_INI_SIZE || $error===UPLOAD_ERR_FORM_SIZE
                ? 'Ukuran PNG melampaui batas upload 2 MB.' : 'Upload PNG gagal. Pilih file tanda tangan kembali.');
        }
        $reportedSize=(int)($file['size'] ?? 0);
        $actualSize=filesize((string)$file['tmp_name']);
        if ($actualSize===false || $actualSize<1 || $actualSize>2097152 || $reportedSize!==$actualSize) {
            throw new DomainException('Ukuran PNG tanda tangan harus maksimal 2 MB.');
        }
        $bytes=file_get_contents((string)$file['tmp_name']);
        if (!is_string($bytes) || strlen($bytes)!==$actualSize) throw new DomainException('File tanda tangan tidak dapat dibaca.');
        return $bytes;
    }

    private function enabled(): bool
    {
        if (defined('ERAPOR_API_ENABLED') && ERAPOR_API_ENABLED) return true;
        $this->notFound(); return false;
    }
    private function deny(): void { http_response_code(403); require VIEW_PATH.'/errors/403.php'; }
    private function notFound(): void { http_response_code(404); require VIEW_PATH.'/errors/404.php'; }
    private function takeNotice(): ?array
    {
        $notice=$_SESSION['erapor_signer_notice'] ?? null; unset($_SESSION['erapor_signer_notice']);
        return is_array($notice) && isset($notice['type'],$notice['message'])?$notice:null;
    }
}
