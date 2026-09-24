<?php

/** Definition-only import of official Ummi/PPI V1. Never updates existing rubrics.
 * Source snapshots retain print metadata/identity mappings not normalized into tables.
 * They are data, not executable expressions or dynamic database column names.
 */
final class EraporUmmiPpiSeed
{
    public static function load(string $file): array
    {
        $raw=file_get_contents($file);
        if ($raw===false) throw new RuntimeException('Seed unavailable.');
        $seed=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        self::validate($seed);
        return $seed;
    }

    public static function validate(array $seed): void
    {
        $r=$seed['rubrik'] ?? [];
        $type=$r['jenis_dokumen'] ?? null;
        self::check(in_array($type,['UMMI','PPI'],true),'Unsupported rubric');
        foreach (['kode'=>$type.'_V1','versi'=>1,'cakupan'=>'SEMESTER','jenis_periode'=>null,
            'cetak_gabung_periode'=>$type==='UMMI','khusus_abk'=>$type==='PPI'] as $key=>$value) {
            self::check(array_key_exists($key,$r) && $r[$key]===$value,'Invalid metadata: '.$key);
        }
        self::text($r['nama'] ?? null); self::text($r['judul_cetak'] ?? null);
        self::check(count($r['bagian'] ?? [])===3,'Three sections required');
        $types=$type==='UMMI'?['rubrik','daftar_catatan','teks_bebas']:['identitas','matriks','matriks'];
        $required=$type==='UMMI'?[false,false,true]:[false,true,true];
        foreach ($r['bagian'] as $i=>$b) {
            self::check(($b['kode'] ?? null)===['A','B','C'][$i] && ($b['jenis'] ?? null)===$types[$i]
                && ($b['wajib'] ?? null)===$required[$i] && ($b['urutan'] ?? $i+1)===$i+1,'Invalid section');
            self::text($b['judul'] ?? null);
        }
        self::check(count($r['penandatangan'] ?? [])===2,'Two signature roles required');
        foreach (['KEPALA_SEKOLAH',$type==='UMMI'?'KOORDINATOR_QURAN':'GURU_KELAS'] as $i=>$role) {
            $s=$r['penandatangan'][$i];
            self::check(($s['peran'] ?? null)===$role && ($s['urutan'] ?? null)===$i+1
                && ($s['cetak_nuptk'] ?? null)===true,'Invalid signature');
            self::text($s['jabatan_cetak'] ?? null);
            foreach (['prefiks','posisi_cetak'] as $key) self::check(array_key_exists($key,$s)
                && ($s[$key]===null || is_string($s[$key])),'Invalid signature text');
        }
        if ($type==='UMMI') self::validateUmmi($seed); else self::validatePpi($seed);
    }

    private static function validateUmmi(array $seed): void
    {
        $r=$seed['rubrik'];
        self::check(count($r['kolom_periode'] ?? [])===2,'Ummi requires two period columns');
        foreach (['TENGAH','AKHIR'] as $i=>$kind) {
            $p=$r['kolom_periode'][$i];
            self::check(($p['kode'] ?? null)===$kind.'_SEMESTER' && ($p['jenis'] ?? null)===$kind
                && ($p['urutan'] ?? null)===$i+1 && array_key_exists('semester',$p) && $p['semester']===null,'Invalid Ummi period');
            self::text($p['label'] ?? null);
        }
        $scale=$r['skala']['huruf']['nilai'] ?? [];
        self::check(count($scale)===12,'Twelve letter grades required');
        foreach (['A+','A','A-','B+','B','B-','C+','C','C-','D+','D','D-'] as $i=>$code) {
            self::check(($scale[$i]['kode'] ?? null)===$code && ($scale[$i]['label'] ?? null)===$code
                && ($scale[$i]['peringkat'] ?? null)===12-$i,'Invalid letter grade');
        }
        self::check(array_column($seed['jilid'] ?? [],'kode')===array_map(fn($s)=>'ummi__'.$s,
            ['pra_tk','i','ii','iii','iv','v','vi']),'Invalid jilid order');
        $codes=[];
        foreach ($seed['jilid'] as $i=>$j) {
            self::check(($j['urutan'] ?? null)===$i+1 && ($j['hanya_pra_tk'] ?? null)===($i===0),'Invalid PRA TK flag/order');
            self::text($j['nama'] ?? null);
            self::check(count($j['materi'] ?? [])===[2,3,4,5,3,6,4][$i],'Invalid material count');
            foreach ($j['materi'] as $n=>$m) {
                self::text($m['kode'] ?? null); self::text($m['teks'] ?? null);
                self::check(!isset($codes[$m['kode']]) && str_starts_with($m['kode'],$j['kode'].'__')
                    && ($m['urutan'] ?? null)===$n+1,'Duplicate/misplaced material');
                $codes[$m['kode']]=true;
            }
        }
    }

    private static function validatePpi(array $seed): void
    {
        $r=$seed['rubrik'];
        self::check(empty($r['kolom_periode']) && !isset($r['skala']),'PPI has no grade scale/period columns');
        self::check(($r['orientasi_cetak'] ?? null)==='portrait' && ($r['ukuran_kertas'] ?? null)==='A4'
            && ($r['lebar_cetak_cm'] ?? null)==17 && ($r['margin_cetak_cm'] ?? null)==2,'Invalid PPI print format');
        self::check(array_column($seed['identitas_baris'] ?? [],'kode')===['nama','usia','kelas','diagnosa','sekolah'],'Invalid PPI identity');
        foreach ($seed['identitas_baris'] as $i=>$row) {
            self::check(($row['urutan'] ?? null)===$i+1,'Invalid identity order');
            self::text($row['label'] ?? null); self::text($row['sumber'] ?? null);
        }
        self::check(array_column($seed['aspek'] ?? [],'kode')===['NAM','FM','KOG','BHS','SOSEM'],'Five PPI aspects required');
        foreach ($seed['aspek'] as $i=>$a) {
            self::check(($a['urutan'] ?? null)===$i+1,'Invalid aspect order'); self::text($a['nama'] ?? null);
        }
        self::check(array_column($seed['kolom'] ?? [],'kode')===['kekuatan','tantangan','tujuan_jangka_panjang',
            'tujuan_jangka_pendek','strategi','media','hasil_capaian_guru','hasil_capaian_orangtua'],'Invalid PPI columns');
        foreach ($seed['kolom'] as $i=>$c) {
            self::check(($c['bagian'] ?? null)===($i<2?'B':'C') && ($c['urutan'] ?? null)===($i<2?$i+1:$i-1)
                && ($c['wajib'] ?? null)===($i<6) && ($c['diisi_di_sesi'] ?? null)===($i<6),'PPI outcomes must remain outside session');
            self::check(($c['jenis'] ?? null)==='TEXTAREA' && ($c['render'] ?? null)==='BULLET_PER_BARIS'
                && ($c['cetak'] ?? null)===true && is_numeric($c['lebar_cetak_cm'] ?? null) && $c['lebar_cetak_cm']>0,'Invalid PPI rendering');
            self::text($c['label_cetak'] ?? null);
            self::check(array_key_exists('grup_cetak',$c) && ($c['grup_cetak']===null || is_string($c['grup_cetak'])),'Invalid column group');
        }
    }

    public static function apply(PDO $db,array $seed): string
    {
        self::validate($seed);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) {
            throw new RuntimeException('Seeder requires a dedicated MySQL connection.');
        }
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Another migration or seed is running.');
        try {
            $db->beginTransaction();
            $json=json_encode($seed,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $hash=hash('sha256',$json);
            $q=$db->prepare('SELECT r.id,h.source_sha256,h.content_sha256 FROM erapor_rubrik r
                LEFT JOIN erapor_seed_history h ON h.rubrik_id=r.id WHERE r.kode=? FOR UPDATE');
            $q->execute([$seed['rubrik']['kode']]); $existing=$q->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                self::check(is_string($existing['source_sha256']),'Untracked rubric collision');
                self::check(hash_equals($existing['source_sha256'],$hash),'Seed changed: use a new rubric version');
                self::check(hash_equals($existing['content_sha256'],self::fingerprint($db,(int)$existing['id'])),'Stored rubric drift');
                $db->commit(); return 'already_seeded';
            }
            $r=$seed['rubrik'];
            $id=self::insert($db,'erapor_rubrik',self::fields($r,'kode jenis_dokumen nama judul_cetak versi cakupan jenis_periode cetak_gabung_periode khusus_abk'));
            foreach ($r['bagian'] as $i=>$row) self::insert($db,'erapor_rubrik_bagian',['rubrik_id'=>$id,'urutan'=>$i+1]+self::fields($row,'kode judul jenis wajib'));
            foreach ($r['kolom_periode'] ?? [] as $row) self::insert($db,'erapor_rubrik_periode',['rubrik_id'=>$id]+self::fields($row,'kode label urutan semester jenis'));
            foreach ($r['penandatangan'] as $row) self::insert($db,'erapor_rubrik_penandatangan',['rubrik_id'=>$id]+self::fields($row,'urutan peran jabatan_cetak prefiks cetak_nuptk posisi_cetak'));
            self::insert($db,'erapor_rubrik_sumber',['rubrik_id'=>$id,'seed_json'=>$json]);
            if ($r['jenis_dokumen']==='UMMI') {
                foreach ($r['skala']['huruf']['nilai'] as $row) self::insert($db,'erapor_skala_huruf',['rubrik_id'=>$id]+self::fields($row,'kode label peringkat'));
                foreach ($seed['jilid'] as $row) {
                    $j=self::insert($db,'erapor_ummi_jilid',['rubrik_id'=>$id]+self::fields($row,'kode nama urutan hanya_pra_tk'));
                    foreach ($row['materi'] as $m) self::insert($db,'erapor_ummi_materi',['jilid_id'=>$j]+self::fields($m,'kode teks urutan'));
                }
            } else {
                foreach ($seed['aspek'] as $row) self::insert($db,'erapor_ppi_aspek',['rubrik_id'=>$id]+self::fields($row,'kode nama urutan'));
                foreach ($seed['kolom'] as $row) self::insert($db,'erapor_ppi_kolom',['rubrik_id'=>$id]+
                    self::fields($row,'bagian kode urutan label_cetak grup_cetak jenis render cetak wajib diisi_di_sesi lebar_cetak_cm'));
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

    private static function fingerprint(PDO $db,int $id): string
    {
        $queries=['SELECT id,kode,jenis_dokumen,nama,judul_cetak,versi,cakupan,jenis_periode,cetak_gabung_periode,khusus_abk FROM erapor_rubrik WHERE id=?'];
        foreach (['erapor_rubrik_bagian','erapor_rubrik_periode','erapor_ummi_jilid','erapor_skala_huruf','erapor_ppi_aspek','erapor_ppi_kolom'] as $table) {
            $queries[]='SELECT * FROM '.$table.' WHERE rubrik_id=? ORDER BY id';
        }
        $queries[]='SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=? ORDER BY urutan';
        $queries[]='SELECT * FROM erapor_rubrik_sumber WHERE rubrik_id=?';
        $queries[]='SELECT m.* FROM erapor_ummi_materi m JOIN erapor_ummi_jilid j ON j.id=m.jilid_id WHERE j.rubrik_id=? ORDER BY m.id';
        $all=[];
        foreach ($queries as $sql) {
            $q=$db->prepare($sql); $q->execute([$id]); $rows=[];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[]=array_map(fn($v)=>$v===null?null:(string)$v,$row);
            $all[]=$rows;
        }
        return hash('sha256',serialize($all));
    }

    private static function fields(array $row,string $names): array { return array_intersect_key($row,array_flip(explode(' ',$names))); }
    private static function insert(PDO $db,string $table,array $row): int
    {
        // Table and column identifiers come exclusively from this class, never from seed keys.
        $q=$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
        $q->execute(array_map(fn($v)=>is_bool($v)?(int)$v:$v,array_values($row)));
        return (int)$db->lastInsertId();
    }
    private static function text(mixed $value): void { self::check(is_string($value) && trim($value)!=='','Required seed text missing'); }
    private static function check(bool $ok,string $message): void { if (!$ok) throw new DomainException($message); }
}
