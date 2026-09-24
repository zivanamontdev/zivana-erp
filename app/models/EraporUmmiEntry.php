<?php

/** Internal Ummi writes, no HTTP route. Actor/clock must be trusted server context.
 * Keys: catatan, mulai_pra_tk, bacaan:<material id>, tes:<32 lowercase hex token>.
 * Changes carry value + expected. Test values contain urutan,tanggal_tes,jilid,nilai.
 * Empty batch explicitly initializes the period flag; this is a WRITE, not a GET.
 */
final class EraporUmmiEntry
{
    public static function save(PDO $db,int $sessionId,int $documentId,int $actorId,array $changes,?DateTimeImmutable $clock=null): array
    {
        $type='UMMI';
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$documentId,$actorId)<1 || count($changes)>200) throw new DomainException('Permintaan Ummi tidak valid.');
        $seen=[];
        foreach ($changes as $c) {
            if (!is_array($c) || !is_string($c['key'] ?? null) || isset($seen[$c['key']])
                || !array_key_exists('value',$c) || !array_key_exists('expected',$c)) throw new DomainException('Kunci Ummi duplikat/tidak valid.');
            $seen[$c['key']]=true;
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
            if ($p['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id'] || $p['semester']!==$session['semester'] || $p['jenis']!==$session['jenis']) throw new DomainException('Identitas periode berubah.');
            EraporSessionPolicy::assertWritable($session['status'],$p['akhir_periode'],$today);
            $doc=self::one($db,"SELECT d.rubrik_id FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id
                JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? AND d.id=? AND r.jenis_dokumen=?
                AND r.cakupan='SEMESTER' AND r.jenis_periode IS NULL AND r.status='terkunci'
                AND d.semester=? AND r.khusus_abk=? FOR UPDATE",[$sessionId,$documentId,$type,$session['semester'],0]);
            if (!$doc) throw new DomainException('Dokumen tidak tersedia dalam sesi.');

            $rubricId=(int)$doc['rubrik_id'];
            $base=['sesi_id'=>$sessionId,'dokumen_id'=>$documentId,'rubrik_id'=>$rubricId];
            $q=$db->prepare('SELECT kode FROM erapor_skala_huruf WHERE rubrik_id=?'); $q->execute([$rubricId]); $grades=$q->fetchAll(PDO::FETCH_COLUMN);
            $q=$db->prepare('SELECT m.id FROM erapor_ummi_materi m JOIN erapor_ummi_jilid j ON j.id=m.jilid_id WHERE j.rubrik_id=? AND m.aktif=1'); $q->execute([$rubricId]); $materials=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
            $flag=self::one($db,'SELECT mulai_pra_tk FROM erapor_ummi_periode WHERE sesi_id=? AND dokumen_id=? FOR UPDATE',[$sessionId,$documentId]);
            $initialized=false;
            if (!$flag) {
                $initial=false;
                if ($session['jenis']==='AKHIR') {
                    $previous=self::one($db,"SELECT up.mulai_pra_tk FROM erapor_ummi_periode up JOIN erapor_sesi s ON s.id=up.sesi_id
                        WHERE up.dokumen_id=? AND s.murid_id=? AND s.tahun_ajaran_id=? AND s.semester=? AND s.jenis='TENGAH' FOR UPDATE",
                        [$documentId,$session['murid_id'],$session['tahun_ajaran_id'],$session['semester']]);
                    $initial=$previous?(bool)$previous['mulai_pra_tk']:false;
                }
                self::insert($db,'erapor_ummi_periode',$base+['mulai_pra_tk'=>(int)$initial,'diisi_oleh'=>$actorId]);
                self::audit($db,$sessionId,$documentId,'mulai_pra_tk',null,$initial,$actorId,$session['status']);
                $initialized=true;
            }
            $updated=0;
            foreach ($changes as $change) {
                $key=$change['key']; $ids=[]; $kind=$key;
                if ($key==='catatan') { $table='erapor_ummi_catatan'; }
                elseif ($key==='mulai_pra_tk') { $table='erapor_ummi_periode'; }
                elseif (preg_match('/^bacaan:([1-9][0-9]*)$/D',$key,$m) && isset($materials[$m[1]])) {
                    $kind='bacaan'; $table='erapor_ummi_bacaan'; $ids=['materi_id'=>(int)$m[1]];
                } elseif (preg_match('/^tes:([a-f0-9]{32})$/D',$key,$m)) {
                    $kind='tes'; $table='erapor_ummi_tes'; $ids=['token'=>$m[1]];
                } else throw new DomainException('Isian bukan milik rubrik Ummi.');
                $value=self::normalize($kind,$change['value'],$grades);
                $expected=self::normalize($kind,$change['expected'],$grades);
                $keys=$base+$ids;
                $where=implode(' AND ',array_map(fn($k)=>$k.'=?',array_keys($keys)));
                $old=self::one($db,'SELECT * FROM '.$table.' WHERE '.$where.' FOR UPDATE',array_values($keys));
                $oldValue=$old?match($kind) {
                    'catatan'=>$old['isi'],'bacaan'=>$old['nilai'],'mulai_pra_tk'=>(bool)$old['mulai_pra_tk'],
                    'tes'=>['urutan'=>(int)$old['urutan'],'tanggal_tes'=>$old['tanggal_tes'],'jilid'=>$old['jilid'],'nilai'=>$old['nilai']],
                }:null;
                if ($oldValue!==$expected) {
                    if ($oldValue===$value) continue;
                    throw new DomainException('Isian telah berubah; muat ulang sebelum menyimpan.');
                }
                if ($oldValue===$value) continue;
                if ($value===null) {
                    $db->prepare('DELETE FROM '.$table.' WHERE '.$where)->execute(array_values($keys));
                } else {
                    $values=match($kind) {'catatan'=>['isi'=>$value],'bacaan'=>['nilai'=>$value],'mulai_pra_tk'=>['mulai_pra_tk'=>(int)$value],'tes'=>$value};
                    if ($old) {
                        $set=implode(',',array_map(fn($k)=>$k.'=?',array_keys($values)));
                        $db->prepare('UPDATE '.$table.' SET '.$set.',diisi_oleh=?,diisi_pada=CURRENT_TIMESTAMP WHERE '.$where)->execute([...array_values($values),$actorId,...array_values($keys)]);
                    } else self::insert($db,$table,$keys+$values+['diisi_oleh'=>$actorId]);
                }
                self::audit($db,$sessionId,$documentId,$key,$oldValue,$value,$actorId,$session['status']); $updated++;
            }
            $note=self::one($db,'SELECT isi FROM erapor_ummi_catatan WHERE sesi_id=? AND dokumen_id=?',[$sessionId,$documentId]);
            $complete=$note && !EraporSessionPolicy::isBlank($note['isi']);
            $flag=self::one($db,'SELECT mulai_pra_tk FROM erapor_ummi_periode WHERE sesi_id=? AND dokumen_id=?',[$sessionId,$documentId]);
            $db->commit();
            return ['changed'=>$updated,'initialized'=>$initialized,'filled'=>$complete?1:0,'required'=>1,'complete'=>(bool)$complete,'mulai_pra_tk'=>(bool)$flag['mulai_pra_tk']];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack(); throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }

    private static function normalize(string $kind,mixed $value,array $grades): mixed
    {
        if ($kind==='mulai_pra_tk') {
            if (!is_bool($value)) throw new DomainException('Sakelar PRA TK harus boolean.');
            return $value;
        }
        if ($value===null) return null;
        if ($kind==='catatan') {
            if (!is_string($value)) throw new DomainException('Catatan harus berupa teks.');
            $value=EraporSessionPolicy::textForStorage($value);
            if ($value!==null && strlen($value)>65535) throw new DomainException('Teks melebihi kapasitas penyimpanan.');
            return $value;
        }
        if ($kind==='bacaan') {
            if (!is_string($value) || !in_array($value,$grades,true)) throw new DomainException('Skala Ummi tidak valid.');
            return $value;
        }
        if (!is_array($value) || count($value)!==4 || !is_int($value['urutan'] ?? null) || $value['urutan']<1 || $value['urutan']>2147483647
            || !is_string($value['tanggal_tes'] ?? null) || !is_string($value['jilid'] ?? null)
            || !is_string($value['nilai'] ?? null) || !in_array($value['nilai'],$grades,true)) throw new DomainException('Data tes Ummi tidak valid.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value['tanggal_tes']);
        if (!$date || $date->format('Y-m-d')!==$value['tanggal_tes'] || $value['tanggal_tes']<'1000-01-01') throw new DomainException('Tanggal tes tidak valid.');
        $jilid=EraporSessionPolicy::textForStorage($value['jilid']);
        if ($jilid===null || preg_match_all('/./us',$jilid)>150) throw new DomainException('Jilid tes tidak valid.');
        return ['urutan'=>$value['urutan'],'tanggal_tes'=>$value['tanggal_tes'],'jilid'=>$jilid,'nilai'=>$value['nilai']];
    }
    private static function audit(PDO $db,int $session,int $doc,string $key,mixed $old,mixed $new,int $actor,string $status): void
    {
        $db->prepare("INSERT INTO erapor_isian_log(sesi_id,dokumen_id,jenis,kunci,nilai_lama,nilai_baru,aktor_id,status_sesi) VALUES(?,?,'UMMI',?,?,?,?,?)")
            ->execute([$session,$doc,$key,$old===null?null:json_encode($old,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                $new===null?null:json_encode($new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$actor,$status]);
    }
    private static function insert(PDO $db,string $table,array $row): void
    {
        $db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
