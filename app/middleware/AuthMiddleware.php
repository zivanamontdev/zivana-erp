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
            return;
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

        return true;
    }

    private function redirectToLogin(): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/login');
        exit;
    }
}
