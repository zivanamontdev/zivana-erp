<?php

/** Internal Agama autosave, no HTTP route. Actor/clock are trusted server context.
 * Changes: key nilai:<item_id> or catatan:<lingkup_id>, value/expected string|null.
 * Seven print keys map to separate stage/sublevel columns; semester comes from session.
 */
final class EraporAgamaEntry
{
    public static function save(PDO $db,int $sessionId,int $documentId,int $actorId,array $changes,?DateTimeImmutable $clock=null): array
    {
        $type='AGAMA';
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$documentId,$actorId)<1 || !$changes || count($changes)>43) throw new DomainException('Permintaan isian tidak valid.');
        $seen=[];
        foreach ($changes as $c) {
            if (!is_array($c) || !is_string($c['key'] ?? null) || isset($seen[$c['key']])) throw new DomainException('Kunci isian duplikat/tidak valid.');
            $seen[$c['key']]=true;
            foreach (['value','expected'] as $key) if (!array_key_exists($key,$c) || ($c[$key]!==null && !is_string($c[$key]))) throw new DomainException('Nilai harus teks atau NULL.');
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
                AND r.cakupan='TAHUNAN' AND r.jenis_periode IS NULL AND r.status='terkunci'
                AND d.semester='TAHUNAN' AND r.khusus_abk=0 FOR UPDATE",[$sessionId,$documentId,$type]);
            if (!$doc) throw new DomainException('Dokumen tidak tersedia dalam sesi.');
            $rubricId=(int)$doc['rubrik_id'];
            $fields=self::fields($db,$rubricId,$session['semester']);
            $q=$db->prepare('SELECT kolom_cetak,tahapan_id,tahapan_kode,subtingkat_id,subtingkat_kode FROM erapor_agama_pilihan WHERE rubrik_id=?'); $q->execute([$rubricId]);
            $gradeMap=[];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $choice) {
                $code=$choice['kolom_cetak']; unset($choice['kolom_cetak']); $gradeMap[$code]=$choice;
            }
            $grades=array_keys($gradeMap); $updated=0;
            foreach ($changes as $change) {
                $field=$fields[$change['key']] ?? null;
                if (!$field) throw new DomainException('Isian bukan milik rubrik/semester sesi.');
                $value=$change['value']; $expected=$change['expected'];
                if ($field['column']==='isi') {
                    $value=EraporSessionPolicy::textForStorage($value);
                    $expected=EraporSessionPolicy::textForStorage($expected);
                    if (($value!==null && strlen($value)>65535) || ($expected!==null && strlen($expected)>65535)) throw new DomainException('Teks melebihi kapasitas penyimpanan.');
                } else {
                    foreach ([$value,$expected] as $grade) if ($grade!==null && !in_array($grade,$grades,true)) throw new DomainException('Tahapan/subtingkat Agama tidak valid.');
                }
                // All identifiers below come from fields(), never directly from request keys.
                $keys=['sesi_id'=>$sessionId,'dokumen_id'=>$documentId,'rubrik_id'=>$rubricId]+$field['ids'];
                $where=implode(' AND ',array_map(fn($k)=>$k.'=?',array_keys($keys)));
                $old=self::one($db,'SELECT '.self::valueSql($field).' AS value FROM '.$field['table'].' WHERE '.$where.' FOR UPDATE',array_values($keys));
                $oldValue=$old?$old['value']:null;
                if ($oldValue!==$expected) {
                    if ($oldValue===$value) continue;
                    throw new DomainException('Isian telah berubah; muat ulang sebelum menyimpan.');
                }
                if ($oldValue===$value) continue;
                $values=$field['column']==='isi'?['isi'=>$value]:($value===null?[]:$gradeMap[$value]);
                if ($value===null) {
                    $db->prepare('DELETE FROM '.$field['table'].' WHERE '.$where)->execute(array_values($keys));
                } elseif ($old) {
                    $set=implode(',',array_map(fn($k)=>$k.'=?',array_keys($values)));
                    $db->prepare('UPDATE '.$field['table'].' SET '.$set.',diisi_oleh=?,diisi_pada=CURRENT_TIMESTAMP WHERE '.$where)->execute([...array_values($values),$actorId,...array_values($keys)]);
                } else {
                    $row=$keys+$values+['diisi_oleh'=>$actorId];
                    $db->prepare('INSERT INTO '.$field['table'].' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));
                }
                $db->prepare('INSERT INTO erapor_isian_log(sesi_id,dokumen_id,jenis,kunci,nilai_lama,nilai_baru,aktor_id,status_sesi) VALUES(?,?,?,?,?,?,?,?)')
                    ->execute([$sessionId,$documentId,$type,$change['key'],$oldValue===null?null:json_encode($oldValue,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
                        $value===null?null:json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$actorId,$session['status']]);
                $updated++;
            }
            $filled=0; $required=0;
            foreach ($fields as $field) {
                if (!$field['required']) continue;
                $required++;
                $keys=['sesi_id'=>$sessionId,'dokumen_id'=>$documentId,'rubrik_id'=>$rubricId]+$field['ids'];
                $where=implode(' AND ',array_map(fn($k)=>$k.'=?',array_keys($keys)));
                $row=self::one($db,'SELECT '.self::valueSql($field).' AS value FROM '.$field['table'].' WHERE '.$where,array_values($keys));
                if ($row && ($field['column']==='isi'?!EraporSessionPolicy::isBlank($row['value']):in_array($row['value'],$grades,true))) $filled++;
            }
            $db->commit(); return ['changed'=>$updated,'filled'=>$filled,'required'=>$required,'complete'=>$required>0 && $filled===$required];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        } finally { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
    }

    private static function fields(PDO $db,int $rubricId,string $semester): array
    {
        $fields=[];
        $q=$db->prepare('SELECT i.id FROM erapor_agama_item i JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? AND i.semester=? AND i.aktif=1 ORDER BY l.urutan,s.urutan,i.urutan');
        $q->execute([$rubricId,$semester]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) $fields['nilai:'.$id]=[
            'table'=>'erapor_agama_nilai','column'=>'nilai','ids'=>['item_id'=>(int)$id],'required'=>true];
        $q=$db->prepare('SELECT id,catatan_wajib FROM erapor_agama_lingkup WHERE rubrik_id=? ORDER BY urutan');
        $q->execute([$rubricId]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $fields['catatan:'.$row['id']]=[
            'table'=>'erapor_agama_catatan','column'=>'isi','ids'=>['lingkup_id'=>(int)$row['id']],'required'=>(bool)$row['catatan_wajib']];
        if (!$fields) throw new DomainException('Definisi isian tidak tersedia.');
        return $fields;
    }
    private static function valueSql(array $field): string
    {
        return $field['column']==='isi'?'isi':"CONCAT(tahapan_kode,COALESCE(CONCAT('/',subtingkat_kode),''))";
    }
    private static function one(PDO $db,string $sql,array $args): array|false
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC);
    }
}
