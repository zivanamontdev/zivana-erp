<?php

/** Self-service employee signer profile; signatures remain private DB bytes. */
final class EraporSignerProfile
{
    private const CONSENT_VERSION='signature-consent-v1';
    private const MAX_BYTES=2097152;

    public static function read(PDO $db,int $userId): array
    {
        self::requireMysql($db,$userId);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $owner=self::owner($db,$userId);
            if (!$owner) throw new DomainException('Profil hanya tersedia untuk akun pegawai aktif milik sendiri.');
            $q=$db->prepare('SELECT nuptk,ttd_png IS NOT NULL AS has_signature,ttd_disetujui_pada FROM erapor_profil_penandatangan WHERE user_id=?');
            $q->execute([$userId]); $row=$q->fetch(PDO::FETCH_ASSOC);
            $db->commit();
            return ['nuptk'=>$row['nuptk'] ?? null,'has_signature'=>(bool)($row['has_signature'] ?? false),
                'consented_at'=>$row['ttd_disetujui_pada'] ?? null];
        } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    public static function signature(PDO $db,int $userId): ?string
    {
        self::requireMysql($db,$userId);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            if (!self::owner($db,$userId)) throw new DomainException('Profil penandatangan tidak tersedia.');
            $q=$db->prepare('SELECT ttd_png FROM erapor_profil_penandatangan WHERE user_id=?');
            $q->execute([$userId]); $bytes=$q->fetchColumn();
            $db->commit();
            return is_string($bytes) && $bytes!==''?$bytes:null;
        } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    public static function save(PDO $db,int $userId,?string $nuptk,?string $upload,bool $consent): array
    {
        self::requireMysql($db,$userId);
        $nuptk=self::normalizeNuptk($nuptk);
        $png=$upload===null?null:self::normalizePng($upload);
        if ($png!==null && !$consent) throw new DomainException('Persetujuan pemilik wajib dicentang saat mengunggah tanda tangan.');
        $lock=self::lockName($db); self::acquire($db,$lock);
        try {
            $db->beginTransaction();
            if (!self::owner($db,$userId,true)) throw new DomainException('Profil hanya tersedia untuk akun pegawai aktif milik sendiri.');
            $q=$db->prepare('SELECT nuptk,ttd_png IS NOT NULL AS has_signature FROM erapor_profil_penandatangan WHERE user_id=? FOR UPDATE');
            $q->execute([$userId]); $current=$q->fetch(PDO::FETCH_ASSOC);
            $oldNuptk=$current['nuptk'] ?? null;
            $hadSignature=(bool)($current['has_signature'] ?? false);
            if ($png===null && $current && $oldNuptk===$nuptk) { $db->commit(); return ['changed'=>false,'has_signature'=>$hadSignature]; }
            $consentAt=$png!==null?(new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s'):null;
            if ($png!==null) {
                $write=$db->prepare('INSERT INTO erapor_profil_penandatangan(user_id,nuptk,ttd_png,ttd_disetujui_pada) VALUES(?,?,?,?)
                    ON DUPLICATE KEY UPDATE nuptk=VALUES(nuptk),ttd_png=VALUES(ttd_png),ttd_disetujui_pada=VALUES(ttd_disetujui_pada)');
                $write->execute([$userId,$nuptk,$png,$consentAt]);
                self::audit($db,$userId,'SETUJUI_TANDA_TANGAN',$nuptk,hash('sha256',$png),self::CONSENT_VERSION);
            } elseif ($current) {
                $db->prepare('UPDATE erapor_profil_penandatangan SET nuptk=? WHERE user_id=?')->execute([$nuptk,$userId]);
                self::audit($db,$userId,'PERBARUI_NUPTK',$nuptk,null,null);
            } elseif ($nuptk!==null) {
                $db->prepare('INSERT INTO erapor_profil_penandatangan(user_id,nuptk) VALUES(?,?)')->execute([$userId,$nuptk]);
                self::audit($db,$userId,'PERBARUI_NUPTK',$nuptk,null,null);
            }
            $db->commit();
            return ['changed'=>true,'has_signature'=>$png!==null || $hadSignature];
        } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
        finally { self::release($db,$lock); }
    }

    public static function revoke(PDO $db,int $userId): array
    {
        self::requireMysql($db,$userId);
        $lock=self::lockName($db); self::acquire($db,$lock);
        try {
            $db->beginTransaction();
            if (!self::owner($db,$userId,true)) throw new DomainException('Profil hanya tersedia untuk akun pegawai aktif milik sendiri.');
            $q=$db->prepare('SELECT nuptk,ttd_png FROM erapor_profil_penandatangan WHERE user_id=? FOR UPDATE');
            $q->execute([$userId]); $current=$q->fetch(PDO::FETCH_ASSOC);
            if (!$current || !is_string($current['ttd_png']) || $current['ttd_png']==='') {
                $db->commit(); return ['changed'=>false];
            }
            $hash=hash('sha256',$current['ttd_png']);
            $db->prepare('UPDATE erapor_profil_penandatangan SET ttd_png=NULL,ttd_disetujui_pada=NULL WHERE user_id=?')->execute([$userId]);
            self::audit($db,$userId,'CABUT_TANDA_TANGAN',$current['nuptk'],$hash,self::CONSENT_VERSION);
            $db->commit(); return ['changed'=>true];
        } catch (Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
        finally { self::release($db,$lock); }
    }

    private static function audit(PDO $db,int $userId,string $action,?string $nuptk,?string $hash,?string $version): void
    {
        $db->prepare('INSERT INTO erapor_profil_penandatangan_audit(user_id,aksi,nuptk,ttd_sha256,versi_persetujuan) VALUES(?,?,?,?,?)')
            ->execute([$userId,$action,$nuptk,$hash,$version]);
    }

    private static function normalizeNuptk(?string $nuptk): ?string
    {
        if ($nuptk===null || trim($nuptk)==='') return null;
        $nuptk=trim($nuptk);
        if (!preg_match('/^[0-9]{16}$/D',$nuptk)) throw new DomainException('NUPTK harus 16 digit atau kosong.');
        return $nuptk;
    }

    private static function normalizePng(string $bytes): string
    {
        if ($bytes==='' || strlen($bytes)>self::MAX_BYTES || !function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new DomainException('Tanda tangan harus berupa PNG valid maksimal 2 MB; pemroses gambar tidak tersedia.');
        }
        $info=@getimagesizefromstring($bytes);
        if (!$info || ($info[2] ?? null)!==IMAGETYPE_PNG || ($info['mime'] ?? '')!=='image/png'
            || $info[0]<1 || $info[1]<1 || $info[0]>4096 || $info[1]>2048 || $info[0]*$info[1]>4000000) {
            throw new DomainException('PNG tanda tangan harus maksimal 4096×2048 piksel dan 4 megapiksel.');
        }
        $image=@imagecreatefromstring($bytes);
        if (!$image instanceof GdImage) throw new DomainException('PNG tanda tangan tidak dapat dibaca.');
        $stream=fopen('php://temp','w+b');
        if ($stream===false) { imagedestroy($image); throw new RuntimeException('Buffer normalisasi gambar tidak tersedia.'); }
        try {
            imagealphablending($image,false); imagesavealpha($image,true);
            if (!imagepng($image,$stream,6)) throw new DomainException('PNG tanda tangan gagal dinormalisasi.');
            rewind($stream); $normalized=stream_get_contents($stream);
            if (!is_string($normalized) || $normalized==='' || strlen($normalized)>self::MAX_BYTES) throw new DomainException('PNG hasil normalisasi melebihi batas ukuran.');
            return $normalized;
        } finally { fclose($stream); imagedestroy($image); }
    }

    private static function owner(PDO $db,int $userId,bool $lock=false): array|false
    {
        $q=$db->prepare('SELECT u.id FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
            WHERE u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1'.($lock?' FOR UPDATE':''));
        $q->execute([$userId]); return $q->fetch(PDO::FETCH_ASSOC);
    }

    private static function requireMysql(PDO $db,int $userId): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction() || $userId<1) throw new RuntimeException('Dedicated MySQL connection required.');
    }
    private static function lockName(PDO $db): string { return substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64); }
    private static function acquire(PDO $db,string $lock): void
    {
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Operasi eRapor lain sedang berjalan. Coba lagi.');
    }
    private static function release(PDO $db,string $lock): void { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
}
