<?php

/** Internal service; actor from authentication, RBAC/CSRF enforced by future route.
 * Extends the existing period for everyone, never updates session status.
 */
final class EraporExtendPeriod
{
    public static function preview(PDO $db,int $periodId,int $actorId): array
    {
        self::head($db,$actorId,false);
        $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=?',[$periodId]);
        if (!$period) throw new DomainException('Periode tidak tersedia.');
        $p=EraporCalendar::normalize($period);
        return ['periode_id'=>$periodId,'tanggal_akhir'=>$p['akhir_periode'],'impact'=>self::impact($db,$periodId)];
    }
    public static function extend(PDO $db,int $periodId,int $actorId,string $expectedDate,string $newDate,string $reason,?DateTimeImmutable $clock=null): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($periodId,$actorId)<1) throw new DomainException('Identitas perpanjangan tidak valid.');
        $reason=EraporSessionPolicy::textForStorage($reason);
        if ($reason===null || strlen($reason)>65535) throw new DomainException('Alasan wajib diisi, maksimal 65535 byte.');
        $today=($clock ?? new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->setTimezone(new DateTimeZone('Asia/Makassar'))->format('Y-m-d');
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or assessment write is running.');
        try {
            $db->beginTransaction();
            self::head($db,$actorId,true);
            $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=? FOR UPDATE',[$periodId]);
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $p=EraporCalendar::normalize($period);
            // Exact retry can remain successful on a later day; it grants no new time.
            if ($p['akhir_periode']===$newDate) {
                $last=self::one($db,'SELECT * FROM erapor_periode_perpanjangan WHERE periode_id=? ORDER BY id DESC LIMIT 1',[$periodId]);
                if ($last && $last['tanggal_akhir_lama']===$expectedDate && $last['tanggal_akhir_baru']===$newDate
                    && (int)$last['diperpanjang_oleh']===$actorId && $last['alasan']===$reason) {
                    $impact=self::impact($db,$periodId); $db->commit();
                    return ['result'=>'already_extended','tanggal_akhir'=>$newDate,'impact'=>$impact];
                }
            }
            if ($p['akhir_periode']!==$expectedDate) throw new DomainException('Tenggat telah berubah; muat ulang periode.');
            EraporSessionPolicy::assertExtension($expectedDate,$newDate,$today,$reason);
            $impact=self::impact($db,$periodId);
            $db->prepare('UPDATE periode_penilaian SET akhir_periode=? WHERE id=?')->execute([$newDate,$periodId]);
            $db->prepare('INSERT INTO erapor_periode_perpanjangan(periode_id,tanggal_akhir_lama,tanggal_akhir_baru,alasan,diperpanjang_oleh) VALUES(?,?,?,?,?)')
                ->execute([$periodId,$expectedDate,$newDate,$reason,$actorId]);
            $db->commit();
            return ['result'=>'extended','tanggal_akhir'=>$newDate,'impact'=>$impact];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack(); throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }
    private static function head(PDO $db,int $actorId,bool $lock): void
    {
        $actor=self::one($db,"SELECT u.id FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
            WHERE u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 AND j.nama='Kepala Sekolah'".($lock?' FOR UPDATE':''),[$actorId]);
        if (!$actor) throw new DomainException('Hanya kepala sekolah aktif yang dapat memperpanjang periode.');
    }
    private static function impact(PDO $db,int $periodId): array
    {
        $counts=array_fill_keys(EraporSessionPolicy::STATES,0);
        $q=$db->prepare('SELECT status,COUNT(*) AS jumlah FROM erapor_sesi WHERE periode_id=? GROUP BY status'); $q->execute([$periodId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $counts[$r['status']]=(int)$r['jumlah'];
        return ['per_status'=>$counts,'dapat_diisi'=>$counts['BELUM_DIISI']+$counts['TELAH_DIISI'],
            'tetap_terkunci'=>$counts['MENUNGGU_TTD']+$counts['SELESAI']];
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
