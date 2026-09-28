<?php

/** Wali kelas (satu akun pegawai per kelas) pemegang tahap persetujuan WALI_KELAS eRapor. */
final class WaliKelas
{
    public static function available(PDO $db): bool
    {
        $q = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='erapor_wali_kelas'");
        return (int) $q->fetchColumn() > 0;
    }

    /** @return array{user_id:int,nama:string}|null */
    public static function forKelas(PDO $db, int $kelasId): ?array
    {
        $q = $db->prepare('SELECT w.user_id,k.nama FROM erapor_wali_kelas w JOIN users u ON u.id=w.user_id JOIN karyawan k ON k.id=u.karyawan_id WHERE w.kelas_id=?');
        $q->execute([$kelasId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row ? ['user_id' => (int) $row['user_id'], 'nama' => (string) $row['nama']] : null;
    }

    /** Akun guru aktif yang dapat ditunjuk sebagai wali kelas: [user_id => nama]. */
    public static function candidates(PDO $db): array
    {
        return $db->query("SELECT u.id,k.nama FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
            WHERE u.is_active=1 AND k.is_active=1 AND j.is_active=1 AND j.nama LIKE 'Guru%' ORDER BY k.nama")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function set(PDO $db, int $kelasId, ?int $userId, ?int $actorId): void
    {
        if ($userId === null) {
            $db->prepare('DELETE FROM erapor_wali_kelas WHERE kelas_id=?')->execute([$kelasId]);
            return;
        }
        if (!isset(self::candidates($db)[$userId])) throw new DomainException('Wali kelas harus akun guru yang aktif.');
        $db->prepare('INSERT INTO erapor_wali_kelas (kelas_id,user_id,ditetapkan_oleh) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),ditetapkan_oleh=VALUES(ditetapkan_oleh),ditetapkan_pada=CURRENT_TIMESTAMP')
            ->execute([$kelasId, $userId, $actorId]);
    }
}
