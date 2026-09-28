<?php

/** Portal Guru > Profil: data diri, NUPTK, dan tanda tangan akun yang sedang login. */
final class ProfileController extends Controller
{
    public function index(): void
    {
        $this->guard();
        $db = Database::getInstance();
        $profile = empty($_SESSION['karyawan_id']) ? null : TeacherProfile::read($db, (int) $_SESSION['user_id']);
        $signer = new RoleMiddleware();
        $this->takeSignerNotice();
        header('Cache-Control: private, no-store, max-age=0');
        $this->view('portal-guru.profil', [
            'pageTitle' => 'Profil', 'breadcrumb' => null, 'activeNavItem' => 'portal-profil',
            'profile' => $profile,
            // Akun tanpa data pegawai (mis. Superadmin): tampilkan info akun baca-saja.
            'account' => $profile === null ? $this->account($db) : null,
            'canViewSignature' => $profile !== null && $signer->check('eRapor', 'Profil Penandatangan', 'lihat'),
            'canEditSignature' => $profile !== null && $signer->check('eRapor', 'Profil Penandatangan', 'edit'),
        ]);
    }

    public function update(): void
    {
        $this->guard(true);
        $db = Database::getInstance();
        $userId = (int) $_SESSION['user_id'];
        try {
            // Periksa NUPTK lebih dulu agar nama tidak tersimpan sebagian bila NUPTK ditolak.
            $nuptk = isset($_POST['nuptk']) && is_string($_POST['nuptk']) ? trim($_POST['nuptk']) : null;
            if ($nuptk !== null && $nuptk !== '' && !preg_match('/^[0-9]{16}$/D', $nuptk)) throw new DomainException('NUPTK harus 16 digit atau kosong.');
            $name = TeacherProfile::updateName($db, $userId, (string) $this->input('nama', ''));
            $_SESSION['display_name'] = $name;
            if (isset($_POST['nuptk']) && (new RoleMiddleware())->check('eRapor', 'Profil Penandatangan', 'edit')) {
                EraporSignerProfile::save($db, $userId, is_string($_POST['nuptk']) ? $_POST['nuptk'] : null, null, false);
            }
            $this->notice('success', 'Profil tersimpan.');
        } catch (DomainException $e) {
            $this->notice('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Profile update failed: ' . $e->getMessage());
            $this->notice('error', 'Profil tidak tersimpan. Coba lagi.');
        }
        $this->redirect('/portal-guru/profil');
    }

    private function account(PDO $db): array
    {
        $q = $db->prepare('SELECT u.email,r.nama AS role,u.is_active FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
        $q->execute([(int) $_SESSION['user_id']]);
        return ($q->fetch(PDO::FETCH_ASSOC) ?: ['email' => '-', 'role' => '-', 'is_active' => 1]) + ['nama' => (string) ($_SESSION['display_name'] ?? 'Akun')];
    }

    private function guard(bool $write = false): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Portal Guru', 'Dashboard', 'lihat');
        if ($write && empty($_SESSION['karyawan_id'])) { http_response_code(403); require VIEW_PATH . '/errors/403.php'; exit; }
    }

    private function notice(string $type, string $message): void
    {
        flashToast($message, $type === 'success' ? 'success' : 'error');
    }

    /** Hasil form tanda tangan (EraporSignerProfileController, return_to=profil) ditampilkan sebagai toast. */
    private function takeSignerNotice(): void
    {
        $notice = $_SESSION['erapor_signer_notice'] ?? null;
        unset($_SESSION['erapor_signer_notice']);
        if (is_array($notice) && isset($notice['type'], $notice['message'])) $this->notice((string) $notice['type'], (string) $notice['message']);
    }
}
