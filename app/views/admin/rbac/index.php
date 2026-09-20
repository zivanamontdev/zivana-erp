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
$headerActions = '<button type="button" class="btn btn-tertiary" disabled '
    . 'title="[ASUMSI] Penambahan role custom belum didukung — 4 role saat ini bersifat tetap, lihat cookbook/prd.md poin asumsi #7">'
    . 'Tambah Role</button>';

require VIEW_PATH . '/layouts/shell-header.php';
?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/rbac.css">

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

            <?php foreach ($permissionTree as $modul => $sections): ?>
            <div class="rbac-modul" data-rbac-group>
                <label class="rbac-modul-header">
                    <input type="checkbox" data-rbac-parent>
                    <span class="text-body-sm"><?= e($modul) ?></span>
                    <span class="rbac-count" data-rbac-count></span>
                </label>
                <div class="rbac-modul-body">
                    <?php foreach ($sections as $sectionName => $subSections): ?>
                        <?php $isLeafSection = count($subSections) === 1 && isset($subSections['_']); ?>
                        <?php if ($isLeafSection): ?>
                        <div class="rbac-leaf">
                            <span class="rbac-leaf-label text-body-sm"><?= e($sectionName) ?></span>
                            <div class="rbac-leaf-actions">
                                <?php foreach ($subSections['_'] as $perm): ?>
                                <label class="rbac-action-checkbox">
                                    <input type="checkbox" data-rbac-checkbox name="permission_ids[]"
                                        value="<?= (int) $perm['id'] ?>"
                                        <?= isset($granted[$roleId][$perm['id']]) ? 'checked' : '' ?>>
                                    <span class="text-caption-md"><?= ucfirst(e($perm['aksi'])) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="rbac-section" data-rbac-group>
                            <label class="rbac-section-header">
                                <input type="checkbox" data-rbac-parent>
                                <span class="text-body-sm"><?= e($sectionName) ?></span>
                                <span class="rbac-count" data-rbac-count></span>
                            </label>
                            <div class="rbac-section-body">
                                <?php foreach ($subSections as $subName => $perms): ?>
                                <?php if ($subName === '_') { continue; } ?>
                                <div class="rbac-leaf">
                                    <span class="rbac-leaf-label text-body-sm"><?= e($subName) ?></span>
                                    <div class="rbac-leaf-actions">
                                        <?php foreach ($perms as $perm): ?>
                                        <label class="rbac-action-checkbox">
                                            <input type="checkbox" data-rbac-checkbox name="permission_ids[]"
                                                value="<?= (int) $perm['id'] ?>"
                                                <?= isset($granted[$roleId][$perm['id']]) ? 'checked' : '' ?>>
                                            <span class="text-caption-md"><?= ucfirst(e($perm['aksi'])) ?></span>
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

            <div class="rbac-role-actions">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<script src="<?= BASE_PATH ?>/assets/js/rbac.js"></script>
<?php require VIEW_PATH . '/layouts/shell-footer.php'; ?>
