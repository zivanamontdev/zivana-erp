<?php

/** Scoped, read-only projection for an explicitly assigned eRapor approver. */
final class EraporApprovalReview
{
    public static function read(PDO $db,int $sessionId,int $approvalId,int $actorId,bool $monitor=false): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$approvalId,$actorId)<1) throw new DomainException('Identitas tinjauan tidak valid.');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $result=self::within($db,$sessionId,$approvalId,$actorId,true,$monitor);
            $db->commit(); return $result;
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    /** Caller owns a repeatable-read, read-only transaction. */
    public static function within(PDO $db,int $sessionId,int $approvalId,int $actorId,bool $includeValues,bool $monitor=false): array
    {
        if (!$db->inTransaction()) throw new RuntimeException('Approval projection requires a transaction.');
        $session=self::one($db,'SELECT * FROM erapor_sesi WHERE id=?',[$sessionId]);
        $target=self::one($db,'SELECT a.id,a.sesi_id,a.penyetuju_id,a.kode,a.label,a.urutan,a.cakupan,a.status
            FROM erapor_sesi_penyetuju a WHERE a.id=? AND a.sesi_id=?',[$approvalId,$sessionId]);
        // SELESAI tetap bisa ditinjau (baca saja) sebagai riwayat penyetuju; persetujuan hanya untuk baris MENUNGGU.
        if (!$session || !$target || !in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)) throw new DomainException('Tinjauan persetujuan tidak tersedia.');
        // Mode pantau (Superadmin) boleh melihat, tidak pernah menyetujui.
        $actor=$monitor?['nama'=>'Superadmin','jabatan'=>'Superadmin']:EraporApprovalAccess::actor($db,$target,$session,$actorId);
        if (!$actor) throw new DomainException('Akun tidak berwenang atas tinjauan ini.');

        EraporSessionFactory::assertPackage($db,$session);
        $approvals=self::all($db,'SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id=? ORDER BY urutan,id',[$sessionId]);
        EraporApprove::assertSnapshotScope($db,$session,$approvals);
        $targetRow=null;
        foreach ($approvals as $row) if ((int)$row['id']===$approvalId) $targetRow=$row;
        if (!$targetRow) throw new DomainException('Baris persetujuan tidak termasuk sesi.');
        $waiting=[];
        foreach ($approvals as $row) if ((int)$row['urutan']<(int)$targetRow['urutan'] && $row['status']!=='DISETUJUI') $waiting[]=$row['label'];

        $student=self::one($db,'SELECT m.id,m.nama_lengkap,m.nisn,m.status_kondisi,k.level_kelas,k.nama_kelas
            FROM murid m LEFT JOIN kelas k ON k.id=m.kelas_id WHERE m.id=?',[$session['murid_id']]);
        $periodRow=self::one($db,'SELECT p.*,t.tahun_awal,t.tahun_akhir FROM periode_penilaian p
            JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id WHERE p.id=?',[$session['periode_id']]);
        if (!$student || !$periodRow) throw new DomainException('Identitas murid/periode tidak tersedia.');
        $period=EraporCalendar::normalize($periodRow);
        $period['nama']=(string)$periodRow['nama'];
        $period['tahun_label']=(int)$periodRow['tahun_awal'].'/'.(int)$periodRow['tahun_akhir'];
        $completion=EraporCompleteness::inspect($db,$session);
        if (!$completion['complete']) throw new DomainException('Paket menunggu persetujuan tidak lagi lengkap.');

        $documents=self::all($db,'SELECT d.id,d.rubrik_id,r.kode,r.nama,r.jenis_dokumen,sd.urutan
            FROM erapor_sesi_penyetuju_dokumen p
            JOIN erapor_sesi_dokumen sd ON sd.sesi_id=p.sesi_id AND sd.dokumen_id=p.dokumen_id
            JOIN erapor_dokumen d ON d.id=sd.dokumen_id
            JOIN erapor_rubrik r ON r.id=d.rubrik_id
            WHERE p.sesi_penyetuju_id=? AND p.sesi_id=? ORDER BY sd.urutan',[$approvalId,$sessionId]);
        if (!$documents) throw new DomainException('Cakupan dokumen persetujuan kosong.');
        if ($includeValues) foreach ($documents as &$document) {
            $document['form']=EraporTeacherForm::documentForm($db,$session,$document);
            $document['display_rows']=self::displayRows($document);
        }
        unset($document);

        return [
            'session'=>['id'=>$sessionId,'status'=>$session['status'],'semester'=>$session['semester'],'jenis'=>$session['jenis'],'kondisi'=>$session['kondisi']],
            'student'=>$student,'period'=>$period,'approval'=>$targetRow,'approvals'=>$approvals,
            'documents'=>$documents,'completion'=>$completion,
            'waiting_for'=>$waiting,
            'can_approve'=>!$monitor && $targetRow['status']==='MENUNGGU' && $waiting===[],
            'all_approved'=>count(array_filter($approvals,fn($r)=>$r['status']!=='DISETUJUI'))===0,
        ];
    }

    /**
     * Baris tinjauan per dokumen. 'group' = bagian yang dilipat (Area RTS, Lingkup Agama, Jilid Ummi, ...),
     * 'sub' = subjudul di dalam bagian (Sub-area RTS, Sub-lingkup Agama); keduanya mengikuti halaman pengisian.
     */
    private static function displayRows(array $document): array
    {
        $type=$document['jenis_dokumen']; $defs=$document['form']['definitions']; $values=$document['form']['values']; $rows=[];
        $scaleKey=match($type) {'RTS'=>'nilai','AGAMA'=>'kolom_cetak','BING'=>'kode','UMMI'=>'kode',default=>null};
        $scales=[];
        if ($scaleKey!==null) foreach ($defs['scale'] ?? [] as $scale) $scales[(string)$scale[$scaleKey]]=(string)$scale['label'];
        $append=static function(string $label,mixed $value,string $group='',string $sub='') use (&$rows): void {
            $value=is_string($value) ? trim($value) : $value;
            $rows[]=['label'=>$label,'value'=>($value===null || $value==='')?'Belum dinilai':(string)$value,'group'=>$group,'sub'=>$sub];
        };
        if ($type==='RTS') {
            $areas=[]; foreach ($defs['areas'] ?? [] as $area) $areas[(int)$area['id']]=mb_convert_case((string)$area['nama'],MB_CASE_TITLE,'UTF-8');
            $subs=[]; foreach ($defs['subareas'] ?? [] as $sub) $subs[(int)$sub['id']]=$sub;
            foreach ($defs['items'] as $item) {
                $sub=$subs[(int)$item['sub_area_id']] ?? null;
                $subLabel=$sub && !(bool)$sub['implisit'] ? trim(($sub['huruf'] ? $sub['huruf'].'. ' : '').$sub['nama']) : '';
                $value=$values['nilai:'.$item['id']] ?? null;
                $append((string)$item['tujuan'],$scales[(string)$value] ?? null,$sub ? ($areas[(int)$sub['area_id']] ?? '') : '',$subLabel);
            }
        } elseif ($type==='AGAMA') {
            $scopes=[]; foreach ($defs['scopes'] ?? [] as $scope) $scopes[(int)$scope['id']]=$scope['nomor_romawi'].'. '.$scope['nama'];
            $subs=[]; foreach ($defs['subscopes'] ?? [] as $sub) $subs[(int)$sub['id']]=$sub;
            foreach ($defs['items'] as $item) {
                $sub=$subs[(int)$item['sub_id']] ?? null;
                $subLabel=$sub && !(bool)$sub['implisit'] ? trim(($sub['huruf'] ? $sub['huruf'].'. ' : '').$sub['nama']) : '';
                $label=($item['nomor'] ? $item['nomor'].'. ' : '').$item['teks'];
                $value=$values['nilai:'.$item['id']] ?? null;
                $append((string)$label,$scales[(string)$value] ?? null,$sub ? ($scopes[(int)$sub['lingkup_id']] ?? '') : '',$subLabel);
            }
            // Satu narasi Laporan Perkembangan Agama (disimpan pada catatan lingkup wajib pertama).
            foreach ($defs['scopes'] as $scope) if (!empty($scope['catatan_wajib'])) { $append('Laporan Perkembangan Agama',$values['catatan:'.$scope['id']] ?? null,'Laporan Perkembangan Agama'); break; }
        } elseif ($type==='BING') {
            foreach ($defs['items'] as $item) {
                $label=($item['penanda_cetak'] ? $item['penanda_cetak'].' ' : '').$item['label_cetak'];
                $value=$values['nilai:'.$item['id']] ?? null;
                $append((string)$label,$scales[(string)$value] ?? null,'Learning Achievement',trim((string)($item['grup'] ?? '')));
            }
            foreach ($defs['comments'] as $comment) $append((string)$comment['label_cetak'],$values['komentar:'.$comment['id']] ?? null,'Teacher Comments');
        } elseif ($type==='PPI') {
            foreach ($defs['aspects'] as $aspect) foreach ($defs['columns'] as $column) {
                $append((string)$column['label_cetak'],$values[$aspect['id'].':'.$column['id']] ?? null,(string)$aspect['nama']);
            }
        } elseif ($type==='UMMI') {
            $withPra=!empty($values['mulai_pra_tk']);
            foreach ($defs['items'] as $item) {
                if (!empty($item['hanya_pra_tk']) && !$withPra) continue;
                $value=$values['bacaan:'.$item['id']] ?? null;
                $append((string)$item['teks'],$scales[(string)$value] ?? null,'Jilid '.$item['jilid_nama']);
            }
            $tests=[];
            foreach ($values as $key=>$test) if (str_starts_with($key,'tes:') && is_array($test)) $tests[]=$test;
            usort($tests,static fn($a,$b)=>[(int)$a['urutan'],$a['tanggal_tes']]<=>[(int)$b['urutan'],$b['tanggal_tes']]);
            foreach ($tests as $i=>$test) $append(($i+1).'. '.$test['tanggal_tes'].' · '.$test['jilid'],$scales[(string)$test['nilai']] ?? $test['nilai'],'Nilai Tes Kenaikan Jilid');
            $append('Catatan Guru',$values['catatan'] ?? null,'Catatan Guru');
        }
        return $rows;
    }

    private static function one(PDO $db,string $sql,array $args): array|false { $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC); }
    private static function all(PDO $db,string $sql,array $args): array { $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC); }
}
