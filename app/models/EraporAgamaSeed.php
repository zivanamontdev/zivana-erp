<?php

/** Official AGAMA V1 definitions only; no student values or legacy conversion.
 * Source metadata is retained unchanged. Narratives/PDF rendering are separate tasks.
 */
final class EraporAgamaSeed
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
        foreach (['kode'=>'AGAMA_V1','jenis_dokumen'=>'AGAMA','versi'=>1,'cakupan'=>'TAHUNAN',
            'jenis_periode'=>null,'cetak_gabung_periode'=>true,'khusus_abk'=>false] as $key=>$value) {
            self::check(array_key_exists($key,$r) && $r[$key]===$value,'Invalid Agama metadata: '.$key);
        }
        self::text($r['nama'] ?? null); self::text($r['judul_cetak'] ?? null);
        self::check(count($r['bagian'] ?? [])===2,'Two Agama sections required');
        foreach (['I-VI','VII'] as $i=>$code) {
            $row=$r['bagian'][$i];
            self::check(($row['kode'] ?? null)===$code && ($row['wajib'] ?? null)===true
                && ($row['jenis'] ?? null)===['rubrik','teks_bebas'][$i],'Invalid Agama section');
            self::text($row['judul'] ?? null);
        }
        self::check(count($r['kolom_periode'] ?? [])===2,'Two Agama print periods');
        foreach (['TENGAH','AKHIR'] as $i=>$kind) {
            $p=$r['kolom_periode'][$i];
            self::check(($p['kode'] ?? null)===$kind.'_SEMESTER' && ($p['jenis'] ?? null)===$kind
                && ($p['urutan'] ?? null)===$i+1 && array_key_exists('semester',$p) && $p['semester']===null,'Invalid Agama period');
            self::text($p['label'] ?? null);
        }
        self::check(count($r['penandatangan'] ?? [])===3,'Three Agama signature roles');
        foreach (['KEPALA_SEKOLAH','GURU_KELAS','ORANG_TUA'] as $i=>$role) {
            $s=$r['penandatangan'][$i];
            self::check(($s['peran'] ?? null)===$role && ($s['urutan'] ?? null)===$i+1
                && ($s['cetak_nuptk'] ?? null)===($i<2),'Invalid Agama signature');
            self::text($s['jabatan_cetak'] ?? null);
            foreach (['prefiks','posisi_cetak'] as $k) self::check(array_key_exists($k,$s) && $s[$k]===null,'Invalid signature metadata');
        }
        $scale=$r['skala'] ?? [];
        self::check(($scale['jenis'] ?? null)==='tahapan_bertingkat' && ($scale['bentuk_isian'] ?? null)==='dropdown','Agama requires stage dropdown');
        self::check(array_column($scale['tahapan'] ?? [],'kode')===['TELADAN','TALQIN','TAHFIZH','TAFHIM','TADIB'],'Five Agama stages');
        foreach ($scale['tahapan'] as $i=>$stage) {
            self::check(($stage['urutan'] ?? null)===$i+1,'Invalid stage order');
            self::text($stage['label'] ?? null); self::text($stage['definisi'] ?? null);
            if ($i===2) {
                self::check(array_column($stage['subtingkat'] ?? [],'kode')===['D','J','M'],'Tahfizh needs D/J/M');
                foreach ($stage['subtingkat'] as $level) foreach (['label','arti','definisi'] as $k) self::text($level[$k] ?? null);
            } else self::check(array_key_exists('subtingkat',$stage) && $stage['subtingkat']===null,'Only Tahfizh has sublevels');
        }
        self::check(count($scale['pilihan_dropdown'] ?? [])===7,'Seven Agama choices');
        foreach (['TELADAN','TALQIN','TAHFIZH','TAHFIZH','TAHFIZH','TAFHIM','TADIB'] as $i=>$stage) {
            $p=$scale['pilihan_dropdown'][$i]; $level=[null,null,'D','J','M',null,null][$i];
            self::check(($p['tahapan'] ?? null)===$stage && ($p['urutan'] ?? null)===$i+1
                && array_key_exists('subtingkat',$p) && $p['subtingkat']===$level
                && ($p['kolom_cetak'] ?? null)===$stage.($level===null?'':'/'.$level),'Invalid choice mapping');
            self::text($p['label'] ?? null);
        }
        $codes=['i_aqidah_tauhid','ii_fiqih_ibadah','iii_akhlaq','iv_alquran_dan_hadits','v_asmaul_husna','vi_kisah_sahabat_rasulullah'];
        self::check(array_column($seed['ruang_lingkup'] ?? [],'kode')===$codes,'Six Agama scopes required');
        $seen=[]; $subCodes=[]; $total=['GANJIL'=>0,'GENAP'=>0]; $names=0;
        foreach ($seed['ruang_lingkup'] as $i=>$scope) {
            self::check(($scope['urutan'] ?? null)===$i+1 && ($scope['nomor_romawi'] ?? null)===['I','II','III','IV','V','VI'][$i],'Invalid scope order');
            self::text($scope['nama'] ?? null);
            self::check(count($scope['sub'] ?? [])===($i===3?3:1),'Invalid subarea count');
            $counts=['GANJIL'=>0,'GENAP'=>0];
            foreach ($scope['sub'] as $j=>$sub) {
                self::text($sub['kode'] ?? null);
                self::check(!isset($subCodes[$sub['kode']]),'Duplicate subarea code'); $subCodes[$sub['kode']]=true;
                self::check(($sub['urutan'] ?? null)===$j+1 && ($sub['implisit'] ?? null)===($i!==3),'Invalid subarea');
                if ($i===3) {
                    self::check(($sub['huruf'] ?? null)===['A','B','C'][$j],'Invalid subarea letter'); self::text($sub['nama'] ?? null);
                } else self::check(array_key_exists('huruf',$sub) && $sub['huruf']===null && array_key_exists('nama',$sub) && $sub['nama']===null,'Implicit subarea must not print title');
                self::check(count($sub['item'] ?? [])===[6,9,10,10,10,8][$i],'Invalid item count');
                foreach ($sub['item'] as $n=>$item) {
                    self::text($item['kode'] ?? null); self::text($item['teks'] ?? null);
                    $semester=$n<($i===3?5:[4,4,5,5,5,4][$i])?'GANJIL':'GENAP';
                    self::check(!isset($seen[$item['kode']]) && str_starts_with($item['kode'],$sub['kode'].'__')
                        && ($item['urutan'] ?? null)===$n+1 && ($item['semester'] ?? null)===$semester,'Invalid/duplicate semester item');
                    $seen[$item['kode']]=true; $counts[$semester]++; $total[$semester]++;
                    self::check(!isset($item['nomor']) || (is_int($item['nomor']) && $item['nomor']>0),'Invalid printed number');
                    if ($i===4) {
                        $expected=$n===9?9:10;
                        self::check(count($item['nama'] ?? [])===$expected && ($item['jumlah_nama'] ?? null)===$expected,'Invalid Asmaul group');
                        foreach ($item['nama'] as $name) self::text($name);
                        self::check(implode(', ',$item['nama'])===$item['teks'],'Asmaul text/name mismatch');
                        $names+=$expected;
                    } else self::check(!array_key_exists('nama',$item) && !array_key_exists('jumlah_nama',$item),'Only Asmaul has name groups');
                }
            }
            self::check(array_values($counts)===[[4,2],[4,5],[5,5],[15,15],[5,5],[4,4]][$i],'Invalid scope semester totals');
        }
        self::check(count($seen)===73 && $names===99 && $total===['GANJIL'=>37,'GENAP'=>36],'Agama totals');
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
            $stages=[]; $levels=[];
            foreach ($r['skala']['tahapan'] as $row) {
                $stage=self::insert($db,'erapor_agama_tahapan',['rubrik_id'=>$id]+self::fields($row,'kode label definisi urutan'));
                $stages[$row['kode']]=$stage;
                foreach ($row['subtingkat'] ?? [] as $i=>$level) {
                    $levels[$level['kode']]=self::insert($db,'erapor_agama_subtingkat',
                        ['tahapan_id'=>$stage,'tahapan_kode'=>$row['kode'],'urutan'=>$i+1]+self::fields($level,'kode label arti definisi'));
                }
            }
            foreach ($r['skala']['pilihan_dropdown'] as $row) self::insert($db,'erapor_agama_pilihan',[
                'rubrik_id'=>$id,'tahapan_id'=>$stages[$row['tahapan']],'tahapan_kode'=>$row['tahapan'],
                'subtingkat_id'=>$row['subtingkat']===null?null:$levels[$row['subtingkat']],
                'subtingkat_kode'=>$row['subtingkat']]+self::fields($row,'label kolom_cetak urutan'));
            foreach ($seed['ruang_lingkup'] as $row) {
                $lingkup=self::insert($db,'erapor_agama_lingkup',['rubrik_id'=>$id]+self::fields($row,'kode nomor_romawi nama urutan'));
                foreach ($row['sub'] as $sub) {
                    $subId=self::insert($db,'erapor_agama_sub',['lingkup_id'=>$lingkup]+self::fields($sub,'kode huruf nama implisit urutan'));
                    foreach ($sub['item'] as $item) {
                        $itemId=self::insert($db,'erapor_agama_item',['sub_id'=>$subId]+self::fields($item,'kode teks semester urutan nomor'));
                        foreach ($item['nama'] ?? [] as $i=>$name) self::insert($db,'erapor_agama_item_nama',[
                            'item_id'=>$itemId,'nama'=>$name,'urutan'=>$i+1]);
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

    private static function fingerprint(PDO $db,int $id): string
    {
        $queries=['SELECT id,kode,jenis_dokumen,nama,judul_cetak,versi,cakupan,jenis_periode,cetak_gabung_periode,khusus_abk FROM erapor_rubrik WHERE id=?'];
        foreach (['erapor_rubrik_bagian','erapor_rubrik_periode','erapor_agama_lingkup','erapor_agama_tahapan','erapor_agama_pilihan'] as $table) {
            $queries[]='SELECT * FROM '.$table.' WHERE rubrik_id=? ORDER BY id';
        }
        $queries[]='SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=? ORDER BY urutan';
        $queries[]='SELECT * FROM erapor_rubrik_sumber WHERE rubrik_id=?';
        $queries[]='SELECT s.* FROM erapor_agama_sub s JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? ORDER BY s.id';
        $queries[]='SELECT i.* FROM erapor_agama_item i JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? ORDER BY i.id';
        $queries[]='SELECT n.* FROM erapor_agama_item_nama n JOIN erapor_agama_item i ON i.id=n.item_id JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? ORDER BY n.id';
        $queries[]='SELECT s.* FROM erapor_agama_subtingkat s JOIN erapor_agama_tahapan t ON t.id=s.tahapan_id WHERE t.rubrik_id=? ORDER BY s.id';
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
