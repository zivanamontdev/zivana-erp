<?php

class RbacController extends Controller
{
    public function index(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sistem', 'RBAC', 'lihat');

        $roles = (new Role())->all('id ASC');
        $permissions = (new Permission())->all('display_order ASC');
        $rolePermissionRows = (new RolePermission())->all();

        // Peta cepat: role_id => [permission_id => true]
        $granted = [];
        foreach ($rolePermissionRows as $row) {
            $granted[(int) $row['role_id']][(int) $row['permission_id']] = true;
        }

        $this->view('admin.rbac.index', [
            'pageTitle' => 'Role-Based Access Control',
            'breadcrumb' => null,
            'activeNavItem' => 'rbac',
            'roles' => $roles,
            'permissionTree' => $this->buildPermissionTree($permissions),
            'granted' => $granted,
            'canEdit' => (new RoleMiddleware())->check('Sistem', 'RBAC', 'edit'),
        ]);
    }

    public function update(): void
    {
        $this->middleware(AuthMiddleware::class);
        $this->middleware(RoleMiddleware::class, 'Sistem', 'RBAC', 'edit');

        $roleId = (int) $this->input('role_id');
        $permissionIds = $this->input('permission_ids', []);

        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        $role = (new Role())->find($roleId);
        $available = array_filter((new Permission())->all(), static fn($p) => PermissionCatalog::visible($p)
            && (($role['nama'] ?? '') !== 'Guru' || in_array($p['modul'], ['Portal Guru'], true)
                || ($p['modul'] === 'eRapor' && in_array($p['section'],['Persetujuan','Profil Penandatangan'],true))));
        $known = array_map('intval', array_column($available, 'id'));
        if (!$role || array_diff($permissionIds, $known)) {
            $_SESSION['rbac_error'] = 'Role atau pilihan izin tidak valid. Tidak ada perubahan disimpan.';
            $this->redirect('/rbac');
            return;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $deleteStmt = $db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $deleteStmt->execute(['role_id' => $roleId]);

            $insertStmt = $db->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );
            foreach ($permissionIds as $permissionId) {
                $insertStmt->execute(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $this->redirect('/rbac');
    }

    /**
     * Susun daftar permission flat jadi tree modul -> section -> sub_section
     * -> [lihat, edit] supaya gampang di-render nested di view.
     */
    private function buildPermissionTree(array $permissions): array
    {
        $tree = [];

        foreach ($permissions as $permission) {
            if (!PermissionCatalog::visible($permission)) continue;
            $modul = $permission['modul'];
            $section = $permission['section'] ?? '_';
            $subSection = $permission['sub_section'] ?? '_';

            $tree[$modul][$section][$subSection][] = $permission;
        }

        return $tree;
    }
}
