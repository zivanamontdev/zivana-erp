<?php

/** Internal step 1 -> 2 only. Caller supplies authenticated actor and route RBAC/CSRF.
 * Does not confirm reception, approve or publish. Deadline deliberately does not block
 * confirmation of already-complete work, per session policy.
 */
final class EraporConfirmFilled
{
    public static function confirm(PDO $db,int $sessionId,int $actorId): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$actorId)<1) throw new DomainException('Identitas sesi tidak valid.');
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or assessment write is running.');
        try {
            $db->beginTransaction();
            $session=self::one($db,'SELECT * FROM erapor_sesi WHERE id=? FOR UPDATE',[$sessionId]);
            if (!$session || (int)$session['guru_user_id']!==$actorId) throw new DomainException('Sesi bukan milik guru ini.');
            $assigned=self::one($db,"SELECT m.id FROM murid m JOIN kelas_guru_murid a ON a.murid_id=m.id AND a.kelas_id=m.kelas_id
                JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id JOIN users u ON u.karyawan_id=k.id
                WHERE m.id=? AND m.kelas_id=? AND u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow') FOR UPDATE",[$session['murid_id'],$session['kelas_id'],$actorId]);
            if (!$assigned) throw new DomainException('Penugasan guru tidak aktif atau berubah.');
            if (!in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true)) throw new DomainException('Sesi sudah terkunci untuk konfirmasi isi.');
            $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=? FOR UPDATE',[$session['periode_id']]);
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $p=EraporCalendar::normalize($period);
            if ($p['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id'] || $p['semester']!==$session['semester'] || $p['jenis']!==$session['jenis']) throw new DomainException('Identitas periode berubah.');
            $completion=EraporCompleteness::inspect($db,$session);
            if (!$completion['complete']) {
                $db->commit();
                return ['result'=>'incomplete','status'=>$session['status'],'completion'=>$completion];
            }
            if ($session['status']==='TELAH_DIISI') {
                $event=self::one($db,"SELECT id FROM erapor_sesi_log WHERE sesi_id=? AND aksi='KONFIRMASI_ISI' LIMIT 1",[$sessionId]);
                if (!$event) throw new DomainException('Status tidak memiliki jejak konfirmasi.');
                $db->commit();
                return ['result'=>'already_confirmed','status'=>'TELAH_DIISI','completion'=>$completion];
            }
            $next=EraporSessionPolicy::confirmFilled($session['status'],true);
            $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$next,$sessionId]);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'KONFIRMASI_ISI',?,?)")->execute([$sessionId,$actorId,$session['status'],$next]);
            $db->commit();
            return ['result'=>'confirmed','status'=>$next,'completion'=>$completion];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack(); throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
