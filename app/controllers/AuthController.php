<?php

class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->middleware(GuestMiddleware::class);

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        $this->view('auth.login', ['error' => $error]);
    }

    public function login(): void
    {
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $rememberMe = $this->input('remember_me') !== null;

        $userModel = new User();
        $user = $userModel->whereFirst('email', $email);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_error'] = 'Email atau kata sandi salah.';
            $this->redirect('/login');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['user_name'] = $user['email'];
        $_SESSION['karyawan_id'] = $user['karyawan_id'] !== null ? (int) $user['karyawan_id'] : null;

        $role = (new Role())->find((int) $user['role_id']);
        $_SESSION['role_name'] = $role['nama'] ?? '';

        // [FIX] Widget profil di page-header (avatar inisial + nama + role)
        // butuh nama tampilan asli, bukan email — dikonfirmasi dari
        // assets/ss/*.svg mana pun yang menampilkan header ("Nur Sahayana"
        // / "Admin"), bukan disebut eksplisit di design-system.md (gap
        // dokumentasi sebelumnya). Fallback ke bagian sebelum "@" email
        // untuk akun tanpa data karyawan (mis. Superadmin).
        $karyawanNama = $_SESSION['karyawan_id'] !== null
            ? ((new Karyawan())->find($_SESSION['karyawan_id'])['nama'] ?? null)
            : null;
        $_SESSION['display_name'] = $karyawanNama ?? ucfirst(strstr($user['email'], '@', true) ?: $user['email']);

        if ($rememberMe) {
            $this->setRememberToken($userModel, (int) $user['id']);
        }

        $isGuru = $role && $role['nama'] === 'Guru';

        $this->redirect($isGuru ? '/portal-guru/dashboard' : '/sekolah');
    }

    public function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            (new User())->update($_SESSION['user_id'], ['remember_token' => null]);
        }

        setcookie('remember_token', '', time() - 3600, '/');
        $_SESSION = [];
        session_destroy();

        $this->redirect('/login');
    }

    private function setRememberToken(User $userModel, int $userId): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $userModel->update($userId, ['remember_token' => $hashedToken]);

        setcookie('remember_token', $rawToken, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }

    /**
     * Alur reset password. [ASUMSI - lihat cookbook/prd.md poin asumsi #8]
     * Belum ada desain Figma untuk halaman ini, jadi tampilan mengikuti
     * gaya visual Login apa adanya. Pengiriman email BELUM disambungkan
     * ke SMTP asli (kredensial di .env masih kosong) — sesuai batasan
     * agentic task ini, tidak boleh mengirim email sungguhan ke layanan
     * produksi tanpa kredensial nyata. Untuk sementara link reset
     * ditulis ke error_log dan (hanya saat APP_DEBUG true) ditampilkan
     * langsung di layar supaya tetap bisa diuji end-to-end secara lokal.
     */
    public function showForgotPassword(): void
    {
        $this->middleware(GuestMiddleware::class);
        $this->view('auth.forgot-password', ['message' => null, 'debugLink' => null]);
    }

    public function sendResetLink(): void
    {
        $email = trim((string) $this->input('email', ''));
        $user = (new User())->whereFirst('email', $email);

        $debugLink = null;
        $genericMessage = 'Kalau email terdaftar, tautan reset kata sandi sudah dikirim. Silakan cek email Anda.';

        if ($user && $user['is_active']) {
            $rawToken = bin2hex(random_bytes(32));
            $hashedToken = hash('sha256', $rawToken);

            (new PasswordReset())->create([
                'user_id' => $user['id'],
                'token' => $hashedToken,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600), // 1 jam
            ]);

            $resetUrl = APP_URL . (defined('BASE_PATH') ? BASE_PATH : '') . '/reset-kata-sandi/' . $rawToken;

            // TODO: sambungkan ke PHPMailer + SMTP asli sesuai cookbook/architecture.md
            // poin 5, begitu kredensial SMTP produksi tersedia di .env.
            error_log("[password-reset] Link reset untuk {$email}: {$resetUrl}");

            if (APP_DEBUG) {
                $debugLink = $resetUrl;
            }
        }

        $this->view('auth.forgot-password', ['message' => $genericMessage, 'debugLink' => $debugLink]);
    }

    public function showResetForm(string $rawToken): void
    {
        $this->middleware(GuestMiddleware::class);

        $reset = $this->findValidReset($rawToken);

        if (!$reset) {
            $this->view('auth.reset-password', [
                'token' => $rawToken,
                'error' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
                'expired' => true,
            ]);
            return;
        }

        $this->view('auth.reset-password', ['token' => $rawToken, 'error' => null, 'expired' => false]);
    }

    public function resetPassword(string $rawToken): void
    {
        $reset = $this->findValidReset($rawToken);

        if (!$reset) {
            $this->view('auth.reset-password', [
                'token' => $rawToken,
                'error' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
                'expired' => true,
            ]);
            return;
        }

        $password = (string) $this->input('password', '');
        $passwordConfirmation = (string) $this->input('password_confirmation', '');

        if (strlen($password) < 8 || $password !== $passwordConfirmation) {
            $this->view('auth.reset-password', [
                'token' => $rawToken,
                'error' => 'Kata sandi minimal 8 karakter dan konfirmasi harus sama persis.',
                'expired' => false,
            ]);
            return;
        }

        $userModel = new User();
        $userModel->update($reset['user_id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'remember_token' => null,
        ]);

        (new PasswordReset())->delete($reset['id']);

        $_SESSION['login_error'] = null;
        $this->redirect('/login');
    }

    private function findValidReset(string $rawToken): ?array
    {
        $hashedToken = hash('sha256', $rawToken);
        $reset = (new PasswordReset())->whereFirst('token', $hashedToken);

        if (!$reset || strtotime($reset['expires_at']) < time()) {
            return null;
        }

        return $reset;
    }
}
