<?php

/** Pending approvals are visible only through current explicit user assignments. */
final class EraporApprovalInbox
{
    public static function read(PDO $db,int $actorId): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if ($actorId<1) throw new DomainException('Identitas penyetuju tidak valid.');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $actor=self::one($db,"SELECT u.id FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
                WHERE u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1",[$actorId]);
            if (!$actor) throw new DomainException('Akun penyetuju tidak aktif.');
            $candidates=self::all($db,"SELECT a.id AS approval_id,a.sesi_id,a.kode,a.label,a.urutan,m.nama_lengkap,p.nama AS periode_nama,
                    CONCAT(t.tahun_awal,'/',t.tahun_akhir) AS tahun_label
                FROM erapor_penyetuju_user au JOIN erapor_sesi_penyetuju a ON a.penyetuju_id=au.penyetuju_id
                JOIN erapor_sesi s ON s.id=a.sesi_id JOIN murid m ON m.id=s.murid_id
                JOIN periode_penilaian p ON p.id=s.periode_id JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id
                WHERE au.user_id=? AND au.aktif=1 AND a.status='MENUNGGU' AND s.status='MENUNGGU_TTD'
                ORDER BY p.awal_periode DESC,m.nama_lengkap,a.urutan,a.id",[$actorId]);
            $tasks=[];
            foreach ($candidates as $candidate) {
                try {
                    $review=EraporApprovalReview::within($db,(int)$candidate['sesi_id'],(int)$candidate['approval_id'],$actorId,false);
                    $tasks[]=['approval_id'=>(int)$candidate['approval_id'],'session_id'=>(int)$candidate['sesi_id'],
                        'label'=>$review['approval']['label'],'code'=>$review['approval']['kode'],
                        'student'=>$review['student']['nama_lengkap'],'period'=>$review['period']['nama'].' · '.$review['period']['tahun_label'],
                        'documents'=>array_column($review['documents'],'nama'),'can_approve'=>$review['can_approve'],
                        'waiting_for'=>$review['waiting_for'],'integrity_error'=>false];
                } catch (DomainException $e) {
                    // Keep assigned work visible but non-actionable when its snapshot is inconsistent.
                    $tasks[]=['approval_id'=>(int)$candidate['approval_id'],'session_id'=>(int)$candidate['sesi_id'],
                        'label'=>$candidate['label'],'code'=>$candidate['kode'],'student'=>$candidate['nama_lengkap'],
                        'period'=>$candidate['periode_nama'].' · '.$candidate['tahun_label'],'documents'=>[],
                        'can_approve'=>false,'waiting_for'=>[],'integrity_error'=>true];
                }
            }
            $db->commit(); return ['tasks'=>$tasks];
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    private static function one(PDO $db,string $sql,array $args): array|false { $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC); }
    private static function all(PDO $db,string $sql,array $args): array { $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC); }
}
