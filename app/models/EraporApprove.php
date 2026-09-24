<?php

/** Internal approval action. Future route must supply authenticated actor + RBAC/CSRF.
 * This never publishes PDFs or changes the session to SELESAI.
 */
final class EraporApprove
{
    public static function approve(PDO $db,int $sessionId,int $approvalId,int $actorId): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$approvalId,$actorId)<1) throw new DomainException('Identitas persetujuan tidak valid.');
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or assessment write is running.');
        try {
            $db->beginTransaction();
            $session=self::one($db,'SELECT * FROM erapor_sesi WHERE id=? FOR UPDATE',[$sessionId]);
            if (!$session || $session['status']!=='MENUNGGU_TTD') throw new DomainException('Sesi belum menunggu persetujuan.');
            $q=$db->prepare('SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id=? ORDER BY urutan,id FOR UPDATE');
            $q->execute([$sessionId]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
            $target=null;
            foreach ($rows as $row) if ((int)$row['id']===$approvalId) $target=$row;
            if (!$target) throw new DomainException('Persetujuan bukan bagian sesi ini.');
            // Current explicit assignment can be revoked; flow configuration itself is snapshotted.
            $actor=self::one($db,"SELECT k.nama,j.nama AS jabatan FROM erapor_penyetuju_user a
                JOIN users u ON u.id=a.user_id JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE a.penyetuju_id=? AND a.user_id=? AND a.aktif=1 AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 FOR UPDATE",[$target['penyetuju_id'],$actorId]);
            if (!$actor || ($target['kode']==='KEPALA_SEKOLAH' && $actor['jabatan']!=='Kepala Sekolah')) throw new DomainException('Akun tidak berwenang sebagai penyetuju ini.');
            $reception=self::one($db,'SELECT sesi_id FROM erapor_sesi_penerimaan WHERE sesi_id=?',[$sessionId]);
            $event=self::one($db,"SELECT id FROM erapor_sesi_log WHERE sesi_id=? AND aksi='KONFIRMASI_PENERIMAAN' LIMIT 1",[$sessionId]);
            if (!$reception || !$event) throw new DomainException('Jejak penerimaan tidak lengkap.');
            self::assertSnapshotScope($db,$session,$rows);
            $existing=self::one($db,'SELECT * FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id=?',[$approvalId]);
            if ($target['status']==='DISETUJUI') {
                if (!$existing || (int)$existing['user_id']!==$actorId) throw new DomainException('Persetujuan telah diberikan oleh akun lain atau jejak tidak lengkap.');
                $event=self::one($db,"SELECT id FROM erapor_sesi_log WHERE id=? AND sesi_id=? AND aktor_id=? AND aksi='SETUJUI'",[$existing['log_id'],$sessionId,$actorId]);
                if (!$event) throw new DomainException('Jejak persetujuan tidak lengkap.');
                $db->commit();
                return ['result'=>'already_approved','status'=>'MENUNGGU_TTD','all_approved'=>self::allApproved($rows)];
            }
            if ($existing) throw new DomainException('Snapshot persetujuan memiliki status yang salah.');
            $order=array_map(fn($r)=>['id'=>(int)$r['id'],'urutan'=>(int)$r['urutan'],'status'=>$r['status']],$rows);
            EraporSessionPolicy::assertApprovalOrder($session['status'],$order,$approvalId);
            // Earlier approved rows must have actual immutable evidence, not just a flag.
            foreach ($rows as $row) if ($row['status']==='DISETUJUI') {
                $evidence=self::one($db,"SELECT s.sesi_penyetuju_id FROM erapor_persetujuan_snapshot s JOIN erapor_sesi_log l ON l.id=s.log_id
                    AND l.sesi_id=s.sesi_id AND l.aktor_id=s.user_id AND l.aksi='SETUJUI' WHERE s.sesi_penyetuju_id=? AND s.sesi_id=?",[$row['id'],$sessionId]);
                if (!$evidence) throw new DomainException('Jejak persetujuan sebelumnya tidak lengkap.');
            }
            $profile=self::one($db,'SELECT * FROM erapor_profil_penandatangan WHERE user_id=? FOR UPDATE',[$actorId]);
            $signer=EraporSignerSnapshot::build($actor['nama'],$profile ?: null);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'SETUJUI','MENUNGGU_TTD','MENUNGGU_TTD')")->execute([$sessionId,$actorId]);
            $logId=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO erapor_persetujuan_snapshot(sesi_penyetuju_id,sesi_id,user_id,nama,nuptk,ttd_png,ttd_sha256,ttd_disetujui_pada,log_id) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$approvalId,$sessionId,$actorId,$signer['nama'],$signer['nuptk'],$signer['ttd_png'],$signer['ttd_sha256'],$signer['ttd_disetujui_pada'],$logId]);
            $db->prepare("UPDATE erapor_sesi_penyetuju SET status='DISETUJUI' WHERE id=?")->execute([$approvalId]);
            foreach ($rows as &$row) if ((int)$row['id']===$approvalId) $row['status']='DISETUJUI';
            unset($row);
            $db->commit();
            return ['result'=>'approved','status'=>'MENUNGGU_TTD','all_approved'=>self::allApproved($rows)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack(); throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }
    /** Ensure the immutable approval/document snapshot still exactly matches the package before display or action. */
    public static function assertSnapshotScope(PDO $db,array $session,array $rows): void
    {
        EraporSessionFactory::assertPackage($db,$session);
        $q=$db->prepare('SELECT d.id,d.rubrik_id,r.jenis_dokumen FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? ORDER BY sd.urutan');
        $q->execute([$session['id']]); $docs=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT a.sesi_penyetuju_id,a.dokumen_id,d.rubrik_id FROM erapor_sesi_penyetuju_dokumen a JOIN erapor_dokumen d ON d.id=a.dokumen_id WHERE a.sesi_id=?');
        $q->execute([$session['id']]); $links=$q->fetchAll(PDO::FETCH_ASSOC);
        $flows=[]; $scopes=[]; $actual=[];
        foreach ($rows as $r) {
            $flows[]=['id'=>$r['penyetuju_id'],'kode'=>$r['kode'],'label'=>$r['label'],'urutan'=>$r['urutan'],'cakupan'=>$r['cakupan'],'aktif'=>1];
            $actual[(int)$r['penyetuju_id']]=[];
            foreach ($links as $link) if ((int)$link['sesi_penyetuju_id']===(int)$r['id']) {
                $actual[(int)$r['penyetuju_id']][]=(int)$link['dokumen_id'];
                if ($r['cakupan']==='TERBATAS') $scopes[]=['penyetuju_id'=>$r['penyetuju_id'],'rubrik_id'=>$link['rubrik_id']];
            }
        }
        $expected=EraporApprovalPlan::build($docs,$flows,$scopes);
        if (count($expected)!==count($rows)) throw new DomainException('Cakupan persetujuan sesi tidak valid.');
        foreach ($expected as $r) {
            $a=$actual[$r['penyetuju_id']]; $b=$r['dokumen_ids']; sort($a); sort($b);
            if ($a!==$b) throw new DomainException('Cakupan persetujuan sesi tidak lengkap.');
        }
    }
    private static function allApproved(array $rows): bool
    {
        return $rows!==[] && count(array_filter($rows,fn($r)=>$r['status']!=='DISETUJUI'))===0;
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
