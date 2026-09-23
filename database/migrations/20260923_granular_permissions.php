<?php
// CLI only. Add granular controls while preserving effective legacy permissions.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('ROOT_PATH',dirname(__DIR__,2)); define('CONFIG_PATH',ROOT_PATH.'/config');
require CONFIG_PATH.'/config.php'; require ROOT_PATH.'/app/core/Database.php';
require ROOT_PATH.'/app/models/PermissionCatalog.php';
$db=Database::getInstance();
$db->exec('ALTER TABLE permissions MODIFY aksi VARCHAR(40) NOT NULL');
$db->beginTransaction();
try {
    $added=0;
    foreach(PermissionCatalog::EXTRA as [$module,$section,$sub,$action,$legacy]) {
        $lookup=$db->prepare('SELECT id FROM permissions WHERE modul=? AND section=? AND sub_section <=> ? AND aksi=?');
        $lookup->execute([$module,$section,$sub,$action]);
        if($lookup->fetchColumn()) continue; // Never re-grant an explicitly revoked permission.
        $db->prepare('INSERT INTO permissions(modul,section,sub_section,aksi,display_order) VALUES(?,?,?,?,100)')->execute([$module,$section,$sub,$action]);
        $id=$db->lastInsertId();
        $db->prepare('INSERT INTO role_permissions(role_id,permission_id) SELECT DISTINCT rp.role_id,? FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.modul=? AND p.section=? AND p.sub_section <=> ? AND p.aksi=?')->execute([$id,$module,$section,$sub,$legacy]);
        $added++;
    }
    $db->commit(); echo "Added $added feature permissions; existing grants retained.\n";
} catch(Throwable $e) { $db->rollBack(); throw $e; }
