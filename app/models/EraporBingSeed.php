<?php

/** Definition-only BING V1 importer. Official seed text is preserved, including
 * known differences from the revised PDF. Not activated in the application.
 * Print metadata stays in the source snapshot, never evaluated as code.
 */
final class EraporBingSeed
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
        foreach (['kode'=>'BING_V1','jenis_dokumen'=>'BING','versi'=>1,'cakupan'=>'SEMESTER',
            'jenis_periode'=>null,'cetak_gabung_periode'=>false,'khusus_abk'=>false,'bahasa'=>'en',
            'orientasi_cetak'=>'portrait','ukuran_kertas'=>'A4'] as $key=>$value) {
            self::check(array_key_exists($key,$r) && $r[$key]===$value,'Invalid BING metadata: '.$key);
        }
        foreach (['nama','judul_cetak','sub_judul_cetak'] as $key) self::text($r[$key] ?? null);
        self::check(($r['lebar_cetak_cm'] ?? null)==17 && ($r['margin_cetak_cm'] ?? null)==2,'Invalid BING print dimensions');
        self::check(empty($r['kolom_periode']),'BING prints one concrete period');
        self::check(count($r['bagian'] ?? [])===2,'Two BING sections required');
        foreach (['A','B'] as $i=>$code) {
            $b=$r['bagian'][$i];
            self::check(($b['kode'] ?? null)===$code && ($b['urutan'] ?? null)===$i+1
                && ($b['jenis'] ?? null)===['rubrik','teks_bebas'][$i] && ($b['wajib'] ?? null)===true,'Invalid BING section');
            self::text($b['judul'] ?? null);
        }
        self::check(count($r['penandatangan'] ?? [])===3,'Three BING signature roles');
        foreach (['KOORDINATOR_BING','GURU_KELAS','KEPALA_SEKOLAH'] as $i=>$role) {
            $s=$r['penandatangan'][$i];
            self::check(($s['peran'] ?? null)===$role && ($s['urutan'] ?? null)===$i+1
                && ($s['cetak_nuptk'] ?? null)===($i>0) && ($s['posisi_cetak'] ?? null)===['kiri','kanan','tengah'][$i]
                && array_key_exists('prefiks',$s) && $s['prefiks']===null,'Invalid BING signature');
            self::text($s['jabatan_cetak'] ?? null);
        }
        self::check(count($seed['skala'] ?? [])===4,'Four BING grades required');
        foreach (['EXCELLENT','OUTSTANDING','GOOD','FAIR'] as $i=>$code) {
            $s=$seed['skala'][$i];
            self::check(($s['kode'] ?? null)===$code && ($s['peringkat'] ?? null)===4-$i
                && ($s['urutan_tampil'] ?? null)===$i+1,'Invalid BING scale order');
            self::text($s['label'] ?? null); self::text($s['definisi'] ?? null);
        }
        self::check(array_column($seed['indikator'] ?? [],'kode')===['attendance','written_test',
            'speaking__grammar_vocabulary','speaking__pronounciation','speaking__interactive_communication'],'Exactly five BING indicators required');
        foreach ($seed['indikator'] as $i=>$row) {
            self::check(($row['urutan'] ?? null)===$i+1 && ($row['jenis'] ?? null)==='SKALA'
                && ($row['wajib'] ?? null)===true && array_key_exists('grup',$row)
                && $row['grup']===($i<2?null:'Speaking Test Result')
                && ($row['penanda_cetak'] ?? null)===($i<2?null:['a.','b.','c.'][$i-2]),'Invalid BING indicator');
            self::text($row['label_cetak'] ?? null);
        }
        self::check(($seed['grup'] ?? null)===[['label_cetak'=>'Speaking Test Result','urutan'=>1,'sel_nilai'=>'DIARSIR']],'Speaking group is not an assessment');
        self::check(array_column($seed['komentar'] ?? [],'kode')===['speaking','reading','listening','writing'],'Four BING comments required');
        foreach ($seed['komentar'] as $i=>$row) {
            self::check(($row['urutan'] ?? null)===$i+1 && ($row['jenis'] ?? null)==='TEXTAREA'
                && ($row['wajib'] ?? null)===true && ($row['penanda_cetak'] ?? null)===['a.','b.','c.','d.'][$i],'Invalid BING comment');
            self::text($row['label_cetak'] ?? null);
        }
        self::check(array_column($seed['identitas_baris'] ?? [],'kode')===['nama','tanggal_lahir','kelas','term_semester'],'Invalid BING identity');
        foreach ($seed['identitas_baris'] as $i=>$row) {
            self::check(($row['urutan'] ?? null)===$i+1 && ($row['sumber'] ?? null)===
                ['murid.nama','murid.tanggal_lahir','murid.kelas.nama','periode'][$i],'Invalid BING identity source');
            self::text($row['label_cetak'] ?? null);
        }
        self::check(array_column($seed['teks_tetap'] ?? [],'kode')===
            ['kolom_nilai','kolom_nilai_sub','kolom_indikator','remarks_judul','remarks_pembuka'],'Invalid BING fixed text');
        foreach ($seed['teks_tetap'] as $row) self::text($row['teks'] ?? null);
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
            foreach ($seed['skala'] as $row) self::insert($db,'erapor_bing_skala',['rubrik_id'=>$id]+self::fields($row,'kode label peringkat urutan_tampil definisi'));
            foreach ($seed['indikator'] as $row) self::insert($db,'erapor_bing_indikator',['rubrik_id'=>$id]+self::fields($row,'kode urutan label_cetak grup penanda_cetak jenis wajib'));
            foreach ($seed['komentar'] as $row) self::insert($db,'erapor_bing_komentar',['rubrik_id'=>$id]+self::fields($row,'kode urutan penanda_cetak label_cetak jenis wajib'));
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
        foreach (['erapor_rubrik_bagian','erapor_rubrik_periode','erapor_bing_skala','erapor_bing_indikator','erapor_bing_komentar'] as $table) {
            $queries[]='SELECT * FROM '.$table.' WHERE rubrik_id=? ORDER BY id';
        }
        $queries[]='SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=? ORDER BY urutan';
        $queries[]='SELECT * FROM erapor_rubrik_sumber WHERE rubrik_id=?';
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
