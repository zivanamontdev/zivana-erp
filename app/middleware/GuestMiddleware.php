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

        $base = defined('BASE_PATH') ? BASE_PATH : '';
        $role = (new Role())->find((int) ($_SESSION['role_id'] ?? 0));
        $isGuru = $role && $role['nama'] === 'Guru';

        header('Location: ' . $base . ($isGuru ? '/portal-guru/dashboard' : '/sekolah'));
        exit;
    }
}
