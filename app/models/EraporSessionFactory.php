<?php

/** Internal creation service only; not exposed to HTTP yet.
 * Actor must come from authenticated server context, never request parameters.
 * Only active assigned teachers can create; route-level RBAC remains required.
 * No assessment/status/approval updates are provided by this foundation.
 */
final class EraporSessionFactory
{
    public static function create(PDO $db,int $studentId,int $periodId,int $actorId): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($studentId,$periodId,$actorId)<1) throw new DomainException('Identitas sesi tidak valid.');
        // Serialize against migrations/seeds and other factories before beginning transaction.
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or session creation is running.');
        try {
            $db->beginTransaction();
            $student=self::one($db,'SELECT id,kelas_id,status_kondisi FROM murid WHERE id=? FOR UPDATE',[$studentId]);
            if (!$student) throw new DomainException('Murid tidak tersedia.');
            $student['status_kondisi']=EraporPackagePlan::normalizeCondition((string)$student['status_kondisi']) ?? '';
            $assignment=self::one($db,"SELECT a.kelas_id,u.id AS user_id FROM kelas_guru_murid a
                JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id
                JOIN users u ON u.karyawan_id=k.id
                WHERE a.murid_id=? AND u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow') FOR UPDATE",[$studentId,$actorId]);
            if (!$assignment || (int)$assignment['kelas_id']!==(int)$student['kelas_id']) throw new DomainException('Guru aktif tidak ditugaskan pada murid/kelas ini.');
            $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=? FOR UPDATE',[$periodId]);
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $year=self::one($db,'SELECT id FROM tahun_ajaran WHERE id=? FOR UPDATE',[$period['tahun_ajaran_id']]);
            if (!$year) throw new DomainException('Tahun ajaran tidak tersedia.');
            EraporCalendar::year($db,(int)$period['tahun_ajaran_id']);
            $normalized=EraporCalendar::normalize($period);
            $existing=self::one($db,'SELECT * FROM erapor_sesi WHERE murid_id=? AND periode_id=? FOR UPDATE',[$studentId,$periodId]);
            if ($existing) {
                if ((int)$existing['guru_user_id']!==$actorId || (int)$existing['kelas_id']!==(int)$assignment['kelas_id']
                    || (int)$existing['tahun_ajaran_id']!==$normalized['tahun_ajaran_id']
                    || $existing['semester']!==$normalized['semester'] || $existing['jenis']!==$normalized['jenis']) {
                    throw new DomainException('Identitas/penugasan sesi berubah; perlu rekonsiliasi eksplisit.');
                }
                self::assertPackage($db,$existing);
                $db->commit();
                return ['id'=>(int)$existing['id'],'result'=>'already_created'];
            }
            $catalog=$db->query('SELECT r.* FROM erapor_rubrik r JOIN erapor_seed_history h ON h.rubrik_id=r.id ORDER BY r.id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
            $composition=require __DIR__.'/../../config/erapor-package.php';
            $plan=EraporPackagePlan::build($studentId,$student['status_kondisi'],$period,$catalog,$composition);
            if (!$plan['dapat_dibentuk']) throw new DomainException('Rubrik belum tersedia: '.implode(', ',$plan['rubrik_belum_tersedia']));
            $documents=[];
            foreach ($plan['dokumen'] as $row) {
                $identity=$row['identitas'];
                $args=[$studentId,$normalized['tahun_ajaran_id'],$row['rubrik_id'],$identity['semester']];
                $document=self::one($db,'SELECT id FROM erapor_dokumen WHERE murid_id=? AND tahun_ajaran_id=? AND rubrik_id=? AND semester=? FOR UPDATE',$args);
                if (!$document) {
                    $q=$db->prepare('INSERT INTO erapor_dokumen(murid_id,tahun_ajaran_id,rubrik_id,semester) VALUES(?,?,?,?)'); $q->execute($args);
                    $document=['id'=>(int)$db->lastInsertId()];
                }
                $documents[]=['id'=>(int)$document['id'],'rubrik_id'=>$row['rubrik_id'],'semester'=>$identity['semester'],
                    'urutan'=>$row['urutan'],'wajib'=>1];
                // First reference freezes this rubric version; no seed/content rewrite.
                $db->prepare("UPDATE erapor_rubrik SET status='terkunci' WHERE id=? AND status='draft'")->execute([$row['rubrik_id']]);
            }
            $hash=self::hash($documents);
            $q=$db->prepare('INSERT INTO erapor_sesi(murid_id,periode_id,tahun_ajaran_id,semester,jenis,kondisi,kelas_id,guru_user_id,paket_sha256) VALUES(?,?,?,?,?,?,?,?,?)');
            $q->execute([$studentId,$periodId,$normalized['tahun_ajaran_id'],$normalized['semester'],$normalized['jenis'],
                $student['status_kondisi'],$assignment['kelas_id'],$actorId,$hash]);
            $id=(int)$db->lastInsertId();
            $q=$db->prepare('INSERT INTO erapor_sesi_dokumen(sesi_id,dokumen_id,murid_id,tahun_ajaran_id,semester,dokumen_semester,urutan,wajib) VALUES(?,?,?,?,?,?,?,1)');
            foreach ($documents as $row) $q->execute([$id,$row['id'],$studentId,$normalized['tahun_ajaran_id'],$normalized['semester'],$row['semester'],$row['urutan']]);
            $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'SESI_DIBUAT',NULL,'BELUM_DIISI')")->execute([$id,$actorId]);
            $db->commit(); return ['id'=>$id,'result'=>'created'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        } finally {
            $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]);
        }
    }

    /** Internal guard. Caller must own the transaction and lock the session row. */
    public static function assertPackage(PDO $db,array $session): void
    {
        if (!$db->inTransaction()) throw new RuntimeException('Package guard requires a transaction.');
        $q=$db->prepare('SELECT d.id,d.rubrik_id,d.semester,s.urutan,s.wajib FROM erapor_sesi_dokumen s JOIN erapor_dokumen d ON d.id=s.dokumen_id WHERE s.sesi_id=? ORDER BY s.urutan');
        $q->execute([$session['id']]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows || !hash_equals($session['paket_sha256'],self::hash($rows))) throw new DomainException('Paket sesi mengalami drift; tidak diperbaiki otomatis.');
    }

    private static function hash(array $rows): string
    {
        return hash('sha256',json_encode(array_map(fn($r)=>[(int)$r['id'],(int)$r['rubrik_id'],$r['semester'],(int)$r['urutan'],(int)$r['wajib']],$rows),JSON_THROW_ON_ERROR));
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
