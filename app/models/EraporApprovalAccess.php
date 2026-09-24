<?php

/** Assignment gate paired with the role permission; neither grants authority alone. */
final class EraporApprovalAccess
{
    public static function assigned(int $userId): bool
    {
        if ($userId<1 || !defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) return false;
        try {
            $db=Database::getInstance();
            $q=$db->prepare("SELECT 1 FROM erapor_penyetuju_user a
                JOIN users u ON u.id=a.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE a.user_id=? AND a.aktif=1 AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 LIMIT 1");
            $q->execute([$userId]); return (bool)$q->fetchColumn();
        } catch (PDOException $e) {
            // If the opt-in schema is incomplete, fail closed.
            return false;
        }
    }
}
