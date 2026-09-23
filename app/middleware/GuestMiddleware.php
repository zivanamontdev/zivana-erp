<?php

/**
 * Kebalikan dari AuthMiddleware — blokir user yang sudah login dari
 * halaman Login, arahkan ke dashboard sesuai role-nya.
 */
class GuestMiddleware
{
    public function handle(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $user = (new User())->find((int) $_SESSION['user_id']);
        if (!$user || !AccountAccess::active($user)) {
            $_SESSION = [];
            return;
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';

        header('Location: ' . $base . AccountAccess::landing());
        exit;
    }
}
