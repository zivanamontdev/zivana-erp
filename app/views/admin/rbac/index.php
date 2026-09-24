<?php
/**
 * Halaman Sistem -> RBAC. Lihat cookbook/design-system.md bagian 5.3
 * dan cookbook/schema.md bagian 1.
 *
 * Variabel dari RbacController::index():
 * - $roles (array)
 * - $permissionTree (array) modul => section => subSection => [permission rows]
 * - $granted (array) role_id => [permission_id => true]
 */
$headerActions = '';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/rbac.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/rbac.css') ?>">

<?php if (!empty($_SESSION['rbac_error'])): ?>
<p role="alert"><?= uiText($_SESSION['rbac_error'], 'body-sm', ['tone'=>'status-inactive']) ?></p>
<?php unset($_SESSION['rbac_error']); endif; ?>
<p class="text-caption-md">Izin Edit memerlukan Lihat. Guru dapat mengakses Portal Guru, profil tanda tangan miliknya sendiri, dan persetujuan eRapor bila ditugaskan secara eksplisit; halaman penugasan penyetuju hanya tersedia bagi role non-guru. Hak role dan penugasan approver sama-sama diperlukan. Login ulang setelah perubahan izin.</p>
<div class="rbac-list">
    <?php foreach ($roles as $role): ?>
    <?php $roleId = (int) $role['id']; ?>
    <div class="rbac-role">
        <button type="button" class="rbac-role-header" data-role-toggle>
            <?= icon('icon_edit', 'rbac-edit-icon') ?>
            <span class="text-body-sm font-bold"><?= strtoupper(e($role['nama'])) ?></span>
            <span class="rbac-chevron"><?= icon('icon_chevron') ?></span>
        </button>

        <form method="POST" action="<?= BASE_PATH ?>/rbac" class="rbac-role-body" data-rbac-form>
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="role_id" value="<?= $roleId ?>">

            <fieldset class="rbac-permissions" <?= $canEdit ? '' : 'disabled' ?>>
            <?php foreach ($permissionTree as $modul => $sections): ?>
            <?php if ($role['nama'] === 'Guru' && $modul !== 'Portal Guru' && !($modul === 'eRapor' && (isset($sections['Persetujuan']) || isset($sections['Profil Penandatangan'])))) continue; ?>
            <div class="rbac-modul" data-tree-group>
                <label class="rbac-modul-header">
                    <input type="checkbox" class="checkbox" data-tree-parent>
                    <span class="text-body-sm"><?= e($modul) ?></span>
                    <span class="rbac-count" data-tree-count></span>
                </label>
                <div class="rbac-modul-body">
                    <?php foreach ($sections as $sectionName => $subSections): ?>
                        <?php if ($role['nama'] === 'Guru' && $modul === 'eRapor' && !in_array($sectionName,['Persetujuan','Profil Penandatangan'],true)) continue; ?>
                        <?php $isLeafSection = count($subSections) === 1 && isset($subSections['_']); ?>
                        <?php if ($isLeafSection): ?>
                        <div class="rbac-leaf">
                            <span class="rbac-leaf-label text-body-sm"><?= e($sectionName) ?></span>
                            <div class="rbac-leaf-actions">
                                <?php foreach ($subSections['_'] as $perm): ?>
                                <label class="rbac-action-checkbox">
                                    <input type="checkbox" class="checkbox" data-tree-leaf name="permission_ids[]"
                                        value="<?= (int) $perm['id'] ?>"
                                        <?= isset($granted[$roleId][$perm['id']]) ? 'checked' : '' ?>>
                                    <span class="text-caption-md"><?= e(PermissionCatalog::label($perm)) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="rbac-section" data-tree-group>
                            <label class="rbac-section-header">
                                <input type="checkbox" class="checkbox" data-tree-parent>
                                <span class="text-body-sm"><?= e($sectionName) ?></span>
                                <span class="rbac-count" data-tree-count></span>
                            </label>
                            <div class="rbac-section-body">
                                <?php foreach ($subSections as $subName => $perms): ?>
                                <?php if ($subName === '_') { continue; } ?>
                                <div class="rbac-leaf">
                                    <span class="rbac-leaf-label text-body-sm"><?= e($subName) ?></span>
                                    <div class="rbac-leaf-actions">
                                        <?php foreach ($perms as $perm): ?>
                                        <label class="rbac-action-checkbox">
                                            <input type="checkbox" class="checkbox" data-tree-leaf name="permission_ids[]"
                                                value="<?= (int) $perm['id'] ?>"
                                                <?= isset($granted[$roleId][$perm['id']]) ? 'checked' : '' ?>>
                                            <span class="text-caption-md"><?= e(PermissionCatalog::label($perm)) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            </fieldset>
            <?php if ($canEdit): ?>
            <div class="rbac-role-actions">
                <button type="submit" class="ui-button ui-button--primary">Simpan Perubahan</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<script src="<?= BASE_PATH ?>/assets/js/rbac.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/rbac.js') ?>"></script>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
