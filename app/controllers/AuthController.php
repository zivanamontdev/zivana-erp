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

        if ($rememberMe) {
            $this->setRememberToken($userModel, (int) $user['id']);
        }

        $role = (new Role())->find((int) $user['role_id']);
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
        ]);
    }
}
