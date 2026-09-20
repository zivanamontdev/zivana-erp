<?php

/**
 * Enforce permission granular per role (modul/section/sub-section/aksi)
 * SERVER-SIDE. Wajib dipanggil di tiap controller method yang butuh
 * proteksi RBAC — menyembunyikan nav item di UI saja tidak cukup.
 * Lihat cookbook/security.md bagian 3 dan cookbook/schema.md bagian 1.
 */
class RoleMiddleware
{
    public function handle(string $modul, ?string $subSection = null, string $aksi = 'lihat'): void
    {
        $roleId = (int) ($_SESSION['role_id'] ?? 0);

        if ($roleId === 0 || !$this->hasAccess($roleId, $modul, $subSection, $aksi)) {
            $this->deny();
        }
    }

    private function hasAccess(int $roleId, string $modul, ?string $subSection, string $aksi): bool
    {
        $db = Database::getInstance();

        $sql = 'SELECT COUNT(*) as total
                FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
                  AND p.modul = :modul
                  AND p.aksi = :aksi';

        $params = [
            'role_id' => $roleId,
            'modul' => $modul,
            'aksi' => $aksi,
        ];

        if ($subSection !== null) {
            $sql .= ' AND (p.sub_section = :sub_section OR p.section = :sub_section)';
            $params['sub_section'] = $subSection;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetch()['total'] > 0;
    }

    private function deny(): void
    {
        http_response_code(403);
        $view = VIEW_PATH . '/errors/403.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo 'Akses ditolak — Anda tidak punya izin untuk mengakses halaman ini.';
        }
        exit;
    }
}
