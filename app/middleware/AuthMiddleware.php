<?php

/**
 * Pastikan request datang dari user yang sudah login. Fallback ke
 * remember-token (hash SHA-256, bukan plaintext) kalau session kosong.
 * Lihat cookbook/security.md bagian 1.
 */
class AuthMiddleware
{
    public function handle(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $user = (new User())->find((int) $_SESSION['user_id']);
            if ($user && $user['is_active']) return;
            // A deleted/deactivated account must not retain an existing session.
            $_SESSION = [];
            $this->redirectToLogin();
        }

        if ($this->attemptRememberLogin()) {
            return;
        }

        $this->redirectToLogin();
    }

    private function attemptRememberLogin(): bool
    {
        if (empty($_COOKIE['remember_token'])) {
            return false;
        }

        $hashedToken = hash('sha256', $_COOKIE['remember_token']);
        $user = (new User())->whereFirst('remember_token', $hashedToken);

        if (!$user || !$user['is_active']) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['user_name'] = $user['email'];
        $_SESSION['karyawan_id'] = $user['karyawan_id'] !== null ? (int) $user['karyawan_id'] : null;

        $role = (new Role())->find((int) $user['role_id']);
        $_SESSION['role_name'] = $role['nama'] ?? '';

        $karyawanNama = $_SESSION['karyawan_id'] !== null
            ? ((new Karyawan())->find($_SESSION['karyawan_id'])['nama'] ?? null)
            : null;
        $_SESSION['display_name'] = $karyawanNama ?? ucfirst(strstr($user['email'], '@', true) ?: $user['email']);

        return true;
    }

    private function redirectToLogin(): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/login');
        exit;
    }
}
