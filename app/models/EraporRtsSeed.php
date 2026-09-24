<?php

/** Imports the official RTS V1 unchanged. No update/upsert or legacy conversion.
 * This batch provides definitions only, not write guards for future assessments.
 */
final class EraporRtsSeed
{
    public static function load(string $file): array
    {
        $raw = file_get_contents($file);
        if ($raw === false) throw new RuntimeException('Seed unavailable.');
        $seed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::validate($seed);
        return $seed;
    }

    public static function validate(array $seed): void
    {
        $r = $seed['rubrik'] ?? [];
        foreach (['kode'=>'RTS_MONTESSORI_V1','jenis_dokumen'=>'RTS','versi'=>1,'cakupan'=>'TAHUNAN',
            'jenis_periode'=>'TENGAH','cetak_gabung_periode'=>true,'khusus_abk'=>false] as $key=>$value) {
            self::check(($r[$key] ?? null) === $value, 'RTS metadata: ' . $key);
        }
        foreach (['nama','judul_cetak'] as $key) self::text($r[$key] ?? null);
        self::check(count($r['bagian'] ?? []) === 1, 'RTS requires one section');
        $section = $r['bagian'][0];
        self::check(($section['kode'] ?? null)==='RTS' && ($section['jenis'] ?? null)==='rubrik'
            && ($section['wajib'] ?? null)===true && ($section['urutan'] ?? null)===1, 'RTS section must be required');
        self::text($section['judul'] ?? null);
        $periods = $r['kolom_periode'] ?? [];
        self::check(count($periods)===2, 'RTS requires two period columns');
        foreach (['GANJIL','GENAP'] as $index=>$semester) {
            $p=$periods[$index] ?? [];
            self::check(($p['kode'] ?? null)==='TS_'.$semester && ($p['semester'] ?? null)===$semester
                && ($p['jenis'] ?? null)==='TENGAH' && ($p['urutan'] ?? null)===$index+1, 'Invalid RTS period column');
            self::text($p['label'] ?? null);
        }
        self::check(count($r['penandatangan'] ?? [])===3, 'RTS signature roles');
        foreach (['KEPALA_SEKOLAH','GURU_KELAS','ORANG_TUA'] as $i=>$role) {
            $s=$r['penandatangan'][$i];
            self::check(($s['peran'] ?? null)===$role && ($s['urutan'] ?? null)===$i+1
                && is_bool($s['cetak_nuptk'] ?? null), 'Invalid signature');
            self::text($s['jabatan_cetak'] ?? null);
        }
        self::check(count($r['skala'] ?? [])===4, 'RTS requires four scale values');
        foreach (['BD','MB','BSH','BSB'] as $i=>$code) {
            $s=$r['skala'][$i];
            self::check(($s['kode'] ?? null)===$code && ($s['nilai'] ?? null)===$i+1, 'Invalid RTS scale');
            self::text($s['label'] ?? null); self::text($s['simbol'] ?? null);
        }
        $expected = ['keterampilan_hidup'=>47,'sensorial'=>15,'matematika'=>14,'bahasa'=>36,
            'budaya'=>31,'agama'=>14,'sosial_emosional'=>10,'sikap'=>8];
        self::check(array_column($seed['area'] ?? [],'kode')===array_keys($expected), 'RTS area order/count');
        $codes=[]; $subCodes=[]; $named=0; $implicit=0; $groups=[];
        foreach ($seed['area'] as $aIndex=>$area) {
            self::check(($area['urutan'] ?? null)===$aIndex+1, 'Area order'); self::text($area['nama'] ?? null);
            $count=0;
            foreach ($area['sub_area'] ?? [] as $sIndex=>$sub) {
                self::text($sub['kode'] ?? null);
                self::check(!isset($subCodes[$sub['kode']]), 'Duplicate subarea code'); $subCodes[$sub['kode']]=true;
                self::check(($sub['urutan'] ?? null)===$sIndex+1 && is_bool($sub['implisit'] ?? null), 'Subarea order/type');
                if ($sub['implisit']) {
                    $implicit++; self::check($sub['nama']===null && $sub['huruf']===null, 'Implicit subarea must not print title');
                } else {
                    $named++; self::text($sub['nama'] ?? null); self::text($sub['huruf'] ?? null);
                }
                self::check(!empty($sub['indikator']), 'Empty subarea');
                foreach ($sub['indikator'] as $iIndex=>$item) {
                    self::text($item['kode'] ?? null); self::text($item['tujuan'] ?? null);
                    self::check(!isset($codes[$item['kode']]), 'Duplicate indicator code'); $codes[$item['kode']]=true;
                    self::check(($item['urutan'] ?? null)===$iIndex+1, 'Indicator order');
                    self::check(array_key_exists('aparatus',$item) && ($item['aparatus']===null || is_string($item['aparatus'])), 'Invalid apparatus');
                    self::check(array_key_exists('grup',$item), 'Missing group');
                    if ($item['grup']!==null) {
                        self::text($item['grup']); $groups[$sub['kode'].'/'.$item['grup']]=true;
                    }
                    $count++;
                }
            }
            self::check($count===$expected[$area['kode']], 'Area indicator count');
        }
        self::check($named===21 && $implicit===4 && count($codes)===175, 'RTS totals');
        self::check(array_keys($groups)===['bahasa__c/Huruf Raba','budaya__b/Klasifikasi binatang'], 'RTS groups');
    }

    public static function apply(PDO $db, array $seed): string
    {
        self::validate($seed);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) {
            throw new RuntimeException('Seeder requires a dedicated MySQL connection.');
        }
        // Same lock as migration runner: cannot seed while definitions are being created.
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or seed is running.');
        try {
            $db->beginTransaction();
            $hash=hash('sha256',json_encode($seed,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            $q=$db->prepare('SELECT r.id,h.source_sha256,h.content_sha256 FROM erapor_rubrik r
                LEFT JOIN erapor_seed_history h ON h.rubrik_id=r.id WHERE r.kode=? FOR UPDATE');
            $q->execute([$seed['rubrik']['kode']]); $existing=$q->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                self::check(is_string($existing['source_sha256']), 'Untracked rubric collision');
                self::check(hash_equals($existing['source_sha256'],$hash), 'Seed changed: use a new rubric version, never overwrite');
                self::check(hash_equals($existing['content_sha256'],self::fingerprint($db,(int)$existing['id'])), 'Stored rubric drift: manual inspection required');
                $db->commit(); return 'already_seeded';
            }
            $r=$seed['rubrik'];
            $id=self::insert($db,'erapor_rubrik',array_intersect_key($r,array_flip([
                'kode','jenis_dokumen','nama','judul_cetak','versi','cakupan','jenis_periode','cetak_gabung_periode','khusus_abk'])));
            foreach ($r['bagian'] as $row) self::insert($db,'erapor_rubrik_bagian',['rubrik_id'=>$id]+$row);
            foreach ($r['kolom_periode'] as $row) self::insert($db,'erapor_rubrik_periode',['rubrik_id'=>$id]+$row);
            foreach ($r['penandatangan'] as $row) self::insert($db,'erapor_rubrik_penandatangan',['rubrik_id'=>$id]+$row);
            foreach ($r['skala'] as $row) self::insert($db,'erapor_skala_nilai',[
                'rubrik_id'=>$id,'nilai'=>$row['nilai'],'kode'=>$row['kode'],'label'=>$row['label'],
                'simbol'=>$row['simbol'],'urutan'=>$row['nilai']]);
            foreach ($seed['area'] as $area) {
                $areaId=self::insert($db,'erapor_rubrik_area',['rubrik_id'=>$id,'kode'=>$area['kode'],'nama'=>$area['nama'],'urutan'=>$area['urutan']]);
                foreach ($area['sub_area'] as $sub) {
                    $subId=self::insert($db,'erapor_rubrik_sub_area',['area_id'=>$areaId]+array_intersect_key($sub,
                        array_flip(['kode','huruf','nama','implisit','urutan'])));
                    $groups=[];
                    foreach ($sub['indikator'] as $item) {
                        $groupId=null;
                        if ($item['grup']!==null) {
                            if (!isset($groups[$item['grup']])) $groups[$item['grup']]=self::insert($db,'erapor_rubrik_grup',[
                                'sub_area_id'=>$subId,'nama'=>$item['grup'],'urutan'=>$item['urutan']]);
                            $groupId=$groups[$item['grup']];
                        }
                        self::insert($db,'erapor_rubrik_indikator',['sub_area_id'=>$subId,'grup_id'=>$groupId]+
                            array_intersect_key($item,array_flip(['kode','tujuan','aparatus','urutan'])));
                    }
                }
            }
            self::insert($db,'erapor_seed_history',['rubrik_id'=>$id,'source_sha256'=>$hash,'content_sha256'=>self::fingerprint($db,$id)]);
            $db->commit(); return 'seeded';
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        } finally {
            $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]);
        }
    }

    private static function insert(PDO $db,string $table,array $row): int
    {
        // Identifiers are internal, never accepted from requests or a seed extension.
        foreach (array_keys($row) as $column) self::check((bool)preg_match('/^[a-z_][a-z0-9_]*$/D',$column), 'Invalid column');
        $q=$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
        $q->execute(array_map(fn($v)=>is_bool($v)?(int)$v:$v,array_values($row)));
        return (int)$db->lastInsertId();
    }

    private static function fingerprint(PDO $db,int $id): string
    {
        $queries=[
            'SELECT id,kode,jenis_dokumen,nama,judul_cetak,versi,cakupan,jenis_periode,cetak_gabung_periode,khusus_abk FROM erapor_rubrik WHERE id=?',
            'SELECT * FROM erapor_rubrik_bagian WHERE rubrik_id=? ORDER BY id',
            'SELECT * FROM erapor_rubrik_periode WHERE rubrik_id=? ORDER BY id',
            'SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=? ORDER BY urutan',
            'SELECT * FROM erapor_skala_nilai WHERE rubrik_id=? ORDER BY id',
            'SELECT * FROM erapor_rubrik_area WHERE rubrik_id=? ORDER BY id',
            'SELECT s.* FROM erapor_rubrik_sub_area s JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? ORDER BY s.id',
            'SELECT g.* FROM erapor_rubrik_grup g JOIN erapor_rubrik_sub_area s ON s.id=g.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? ORDER BY g.id',
            'SELECT i.* FROM erapor_rubrik_indikator i JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? ORDER BY i.id',
        ];
        $all=[];
        foreach ($queries as $sql) {
            $q=$db->prepare($sql); $q->execute([$id]);
            $rows=[];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[]=array_map(fn($v)=>$v===null?null:(string)$v,$row);
            $all[]=$rows;
        }
        return hash('sha256',serialize($all));
    }

    private static function text(mixed $value): void { self::check(is_string($value) && trim($value)!=='', 'Required seed text missing'); }
    private static function check(bool $ok,string $message): void { if (!$ok) throw new DomainException($message); }
}
