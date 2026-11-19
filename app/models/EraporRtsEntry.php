<?php

/** Internal RTS/RAS autosave service; not an HTTP endpoint.
 * $type='RAS' memakai tabel nilai yang sama (kunci sesi+dokumen+indikator) dan menambah narasi
 * ('key'=>'narasi','value','expected') di erapor_ras_narasi; tiap sesi menyimpan narasinya sendiri.
 * actorId and optional clock must come from trusted server context, never request fields.
 * Each change has indikator_id, nilai (0..4/null), expected (previous 0..4/null).
 * 0 = "Belum Dikenalkan" (tanda -), disimpan di erapor_rts_belum_dikenalkan; 1..4 = skala rubrik di erapor_rts_nilai.
 * Expected-value checking prevents stale autosaves silently overwriting newer values.
 */
final class EraporRtsEntry
{
    public static function save(PDO $db,int $sessionId,int $documentId,int $actorId,array $changes,?DateTimeImmutable $clock=null,string $type='RTS'): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (!in_array($type,['RTS','RAS'],true)) throw new DomainException('Jenis dokumen tidak didukung.');
        if (min($sessionId,$documentId,$actorId)<1 || !$changes || count($changes)>200) throw new DomainException('Permintaan nilai '.$type.' tidak valid.');
        $seen=[];
        foreach ($changes as $c) {
            if ($type==='RAS' && is_array($c) && array_key_exists('key',$c)) {
                if (($c['key'] ?? null)!=='narasi' || isset($seen['narasi']) || array_diff(array_keys($c),['key','value','expected'])
                    || !array_key_exists('value',$c) || !array_key_exists('expected',$c)
                    || !(is_string($c['value']) || $c['value']===null) || !(is_string($c['expected']) || $c['expected']===null)
                    || (is_string($c['value']) && mb_strlen($c['value'])>5000)) throw new DomainException('Narasi tidak valid.');
                $seen['narasi']=true; continue;
            }
            if (!is_array($c) || !is_int($c['indikator_id'] ?? null) || $c['indikator_id']<1 || isset($seen[$c['indikator_id']])) throw new DomainException('Indikator duplikat/tidak valid.');
            foreach (['nilai','expected'] as $key) if (!array_key_exists($key,$c) || !in_array($c[$key],[null,0,1,2,3,4],true)) throw new DomainException('Nilai/skala tidak valid.');
            $seen[$c['indikator_id']]=true;
        }
        $today=($clock ?? new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->setTimezone(new DateTimeZone('Asia/Makassar'))->format('Y-m-d');
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or assessment write is running.');
        try {
            $db->beginTransaction();
            $session=self::one($db,'SELECT * FROM erapor_sesi WHERE id=? FOR UPDATE',[$sessionId]);
            if (!$session || (int)$session['guru_user_id']!==$actorId) throw new DomainException('Sesi bukan milik guru ini.');
            EraporSessionFactory::assertPackage($db,$session);
            $assigned=self::one($db,"SELECT m.id FROM murid m JOIN kelas_guru_murid a ON a.murid_id=m.id AND a.kelas_id=m.kelas_id
                JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id JOIN users u ON u.karyawan_id=k.id
                WHERE m.id=? AND m.kelas_id=? AND u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow') FOR UPDATE",[$session['murid_id'],$session['kelas_id'],$actorId]);
            if (!$assigned) throw new DomainException('Penugasan guru tidak aktif atau berubah.');
            $period=self::one($db,'SELECT * FROM periode_penilaian WHERE id=? FOR UPDATE',[$session['periode_id']]);
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $p=EraporCalendar::normalize($period);
            $periodType=$type==='RAS'?'AKHIR':'TENGAH';
            if ($p['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id'] || $p['semester']!==$session['semester'] || $p['jenis']!==$session['jenis']) throw new DomainException('Identitas periode berubah.');
            EraporSessionPolicy::assertWritable($session['status'],$p['akhir_periode'],$today);
            $doc=self::one($db,"SELECT d.rubrik_id FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id
                JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? AND d.id=?
                AND r.jenis_dokumen=? AND r.cakupan='TAHUNAN' AND r.jenis_periode=?
                AND r.status='terkunci' AND d.semester='TAHUNAN' FOR UPDATE",[$sessionId,$documentId,$type,$periodType]);
            if (!$doc || $p['jenis']!==$periodType) throw new DomainException('Dokumen '.$type.' tidak tersedia dalam sesi.');
            $q=$db->prepare('SELECT i.id FROM erapor_rubrik_indikator i JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? AND i.aktif=1');
            $q->execute([$doc['rubrik_id']]); $allowed=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
            $q=$db->prepare('SELECT id,nilai FROM erapor_skala_nilai WHERE rubrik_id=?'); $q->execute([$doc['rubrik_id']]); $scale=[];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $scale[(int)$row['nilai']]=(int)$row['id'];
            $updated=0;
            foreach ($changes as $c) {
                if (array_key_exists('key',$c)) { // Narasi RAS (teks bebas; kosong = hapus baris).
                    $old=self::one($db,'SELECT isi FROM erapor_ras_narasi WHERE sesi_id=? AND dokumen_id=? FOR UPDATE',[$sessionId,$documentId]);
                    $oldValue=$old?(string)$old['isi']:null;
                    if ($oldValue!==$c['expected']) {
                        if ($oldValue===$c['value']) continue;
                        throw new DomainException('Nilai telah berubah; muat ulang sebelum menyimpan.');
                    }
                    $new=($c['value']===null || EraporSessionPolicy::isBlank($c['value']))?null:$c['value'];
                    if ($oldValue===$new) continue;
                    $args=[$sessionId,$documentId];
                    if ($new===null) $db->prepare('DELETE FROM erapor_ras_narasi WHERE sesi_id=? AND dokumen_id=?')->execute($args);
                    elseif ($old) $db->prepare('UPDATE erapor_ras_narasi SET isi=?,diisi_oleh=?,diisi_pada=CURRENT_TIMESTAMP WHERE sesi_id=? AND dokumen_id=?')->execute([$new,$actorId,...$args]);
                    else $db->prepare('INSERT INTO erapor_ras_narasi(sesi_id,dokumen_id,isi,diisi_oleh) VALUES(?,?,?,?)')->execute([...$args,$new,$actorId]);
                    $db->prepare("INSERT INTO erapor_isian_log(sesi_id,dokumen_id,jenis,kunci,nilai_lama,nilai_baru,aktor_id,status_sesi) VALUES(?,?,'RAS','narasi',?,?,?,?)")
                        ->execute([$sessionId,$documentId,$oldValue===null?null:json_encode($oldValue,JSON_UNESCAPED_UNICODE),$new===null?null:json_encode($new,JSON_UNESCAPED_UNICODE),$actorId,$session['status']]);
                    $updated++; continue;
                }
                if (!isset($allowed[$c['indikator_id']]) || ($c['nilai']!==null && $c['nilai']!==0 && !isset($scale[$c['nilai']]))) throw new DomainException('Indikator/skala bukan milik rubrik aktif.');
                $old=self::one($db,'SELECT n.skala_id,s.nilai FROM erapor_rts_nilai n JOIN erapor_skala_nilai s ON s.id=n.skala_id WHERE n.sesi_id=? AND n.dokumen_id=? AND n.indikator_id=? FOR UPDATE',[$sessionId,$documentId,$c['indikator_id']]);
                $notYet=self::one($db,'SELECT 1 FROM erapor_rts_belum_dikenalkan WHERE sesi_id=? AND dokumen_id=? AND indikator_id=? FOR UPDATE',[$sessionId,$documentId,$c['indikator_id']]);
                $oldValue=$old?(int)$old['nilai']:($notYet?0:null);
                if ($oldValue!==$c['expected']) {
                    if ($oldValue===$c['nilai']) continue; // Idempotent retry of the same requested value.
                    throw new DomainException('Nilai telah berubah; muat ulang sebelum menyimpan.');
                }
                if ($oldValue===$c['nilai']) continue;
                $args=[$sessionId,$documentId,$c['indikator_id']];
                // Satu indikator hanya punya satu jawaban: hapus bentuk lama sebelum menulis bentuk baru.
                if ($notYet) $db->prepare('DELETE FROM erapor_rts_belum_dikenalkan WHERE sesi_id=? AND dokumen_id=? AND indikator_id=?')->execute($args);
                if ($old && ($c['nilai']===null || $c['nilai']===0)) $db->prepare('DELETE FROM erapor_rts_nilai WHERE sesi_id=? AND dokumen_id=? AND indikator_id=?')->execute($args);
                if ($c['nilai']===0) {
                    $db->prepare('INSERT INTO erapor_rts_belum_dikenalkan(sesi_id,dokumen_id,indikator_id,diisi_oleh) VALUES(?,?,?,?)')->execute([...$args,$actorId]);
                } elseif ($c['nilai']!==null && $old) {
                    $db->prepare('UPDATE erapor_rts_nilai SET skala_id=?,diisi_oleh=?,diisi_pada=CURRENT_TIMESTAMP WHERE sesi_id=? AND dokumen_id=? AND indikator_id=?')->execute([$scale[$c['nilai']],$actorId,...$args]);
                } elseif ($c['nilai']!==null) {
                    $db->prepare('INSERT INTO erapor_rts_nilai(sesi_id,dokumen_id,indikator_id,skala_id,diisi_oleh) VALUES(?,?,?,?,?)')->execute([...$args,$scale[$c['nilai']],$actorId]);
                }
                $db->prepare("INSERT INTO erapor_isian_log(sesi_id,dokumen_id,jenis,kunci,nilai_lama,nilai_baru,aktor_id,status_sesi) VALUES(?,?,?,?,?,?,?,?)")
                    ->execute([$sessionId,$documentId,$type,(string)$c['indikator_id'],$oldValue===null?null:json_encode($oldValue),
                        $c['nilai']===null?null:json_encode($c['nilai']),$actorId,$session['status']]);
                $updated++;
            }
            $q=$db->prepare('SELECT COUNT(*) FROM erapor_rts_nilai n JOIN erapor_rubrik_indikator i ON i.id=n.indikator_id JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE n.sesi_id=? AND n.dokumen_id=? AND i.aktif=1 AND a.rubrik_id=?');
            $q->execute([$sessionId,$documentId,$doc['rubrik_id']]); $filled=(int)$q->fetchColumn();
            $q=$db->prepare('SELECT COUNT(*) FROM erapor_rts_belum_dikenalkan n JOIN erapor_rubrik_indikator i ON i.id=n.indikator_id JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE n.sesi_id=? AND n.dokumen_id=? AND i.aktif=1 AND a.rubrik_id=?');
            $q->execute([$sessionId,$documentId,$doc['rubrik_id']]); $filled+=(int)$q->fetchColumn();
            $required=count($allowed);
            if ($type==='RAS') { // Narasi ikut dihitung sebagai satu isian wajib.
                $required++;
                $filled+=(int)(bool)self::one($db,'SELECT 1 FROM erapor_ras_narasi WHERE sesi_id=? AND dokumen_id=?',[$sessionId,$documentId]);
            }
            $db->commit();
            return ['changed'=>$updated,'filled'=>$filled,'required'=>$required,'complete'=>$required>0 && $filled===$required];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
