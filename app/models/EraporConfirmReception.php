<?php

/** Internal step 2 -> 3; authenticated actor + RBAC/CSRF required at future route.
 * No approval or publication. Never reads a signature path supplied by the client.
 */
final class EraporConfirmReception
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
            $teacher=self::one($db,"SELECT k.nama FROM murid m JOIN kelas_guru_murid a ON a.murid_id=m.id AND a.kelas_id=m.kelas_id
                JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id JOIN users u ON u.karyawan_id=k.id
                WHERE m.id=? AND m.kelas_id=? AND u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow') FOR UPDATE",[$session['murid_id'],$session['kelas_id'],$actorId]);
            if (!$teacher) throw new DomainException('Penugasan guru tidak aktif atau berubah.');
            if (!in_array($session['status'],['TELAH_DIISI','MENUNGGU_TTD'],true)) throw new DomainException('Status tidak mengizinkan konfirmasi penerimaan.');
            $snapshot=self::one($db,'SELECT * FROM erapor_sesi_penerimaan WHERE sesi_id=?',[$sessionId]);
            if ($session['status']==='MENUNGGU_TTD') {
                $event=self::one($db,"SELECT id FROM erapor_sesi_log WHERE sesi_id=? AND aktor_id=? AND aksi='KONFIRMASI_PENERIMAAN' LIMIT 1",[$sessionId,$actorId]);
                $count=self::one($db,'SELECT COUNT(*) AS jumlah FROM erapor_sesi_penyetuju WHERE sesi_id=?',[$sessionId]);
                if (!$snapshot || (int)$snapshot['guru_user_id']!==$actorId || !$event || (int)$count['jumlah']===0) throw new DomainException('Jejak penerimaan tidak lengkap.');
                $db->commit();
                return ['result'=>'already_confirmed','status'=>'MENUNGGU_TTD'];
            }
            if ($snapshot) throw new DomainException('Snapshot penerimaan sudah tersedia pada status yang salah.');
            $event=self::one($db,"SELECT id FROM erapor_sesi_log WHERE sesi_id=? AND aksi='KONFIRMASI_ISI' LIMIT 1",[$sessionId]);
            if (!$event) throw new DomainException('Status tidak memiliki jejak konfirmasi isi.');
            $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=? FOR UPDATE',[$session['periode_id']]);
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $p=EraporCalendar::normalize($period);
            if ($p['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id'] || $p['semester']!==$session['semester'] || $p['jenis']!==$session['jenis']) throw new DomainException('Identitas periode berubah.');
            $completion=EraporCompleteness::inspect($db,$session);
            if (!$completion['complete']) {
                $db->commit();
                return ['result'=>'incomplete','status'=>'TELAH_DIISI','completion'=>$completion];
            }
            $q=$db->prepare('SELECT d.id,d.rubrik_id,r.jenis_dokumen FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? ORDER BY sd.urutan');
            $q->execute([$sessionId]); $documents=$q->fetchAll(PDO::FETCH_ASSOC);
            $flows=$db->query('SELECT * FROM erapor_alur_penyetuju ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            $scopes=$db->query('SELECT * FROM erapor_alur_dokumen ORDER BY penyetuju_id,rubrik_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            $plan=EraporApprovalPlan::build($documents,$flows,$scopes);
            $profile=self::one($db,'SELECT * FROM erapor_profil_penandatangan WHERE user_id=? FOR UPDATE',[$actorId]);
            $signer=EraporSignerSnapshot::build($teacher['nama'],$profile ?: null);
            $db->prepare('INSERT INTO erapor_sesi_penerimaan(sesi_id,guru_user_id,guru_nama,guru_nuptk,guru_ttd_png,guru_ttd_sha256,guru_ttd_disetujui_pada) VALUES(?,?,?,?,?,?,?)')
                ->execute([$sessionId,$actorId,$signer['nama'],$signer['nuptk'],$signer['ttd_png'],$signer['ttd_sha256'],$signer['ttd_disetujui_pada']]);
            $insert=$db->prepare('INSERT INTO erapor_sesi_penyetuju(sesi_id,penyetuju_id,kode,label,urutan,cakupan,status) VALUES(?,?,?,?,?,?,?)');
            $link=$db->prepare('INSERT INTO erapor_sesi_penyetuju_dokumen(sesi_penyetuju_id,sesi_id,dokumen_id) VALUES(?,?,?)');
            foreach ($plan as $row) {
                $insert->execute([$sessionId,$row['penyetuju_id'],$row['kode'],$row['label'],$row['urutan'],$row['cakupan'],$row['status']]);
                $id=(int)$db->lastInsertId();
                foreach ($row['dokumen_ids'] as $docId) $link->execute([$id,$sessionId,$docId]);
            }
            $next=EraporSessionPolicy::confirmReception($session['status'],true);
            $db->prepare('UPDATE erapor_sesi SET status=? WHERE id=?')->execute([$next,$sessionId]);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'KONFIRMASI_PENERIMAAN',?,?)")->execute([$sessionId,$actorId,$session['status'],$next]);
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
