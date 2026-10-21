<?php

/** Versioned INTERNAL design previews. Originals, approval rows, R2 objects and public links are never updated. */
final class EraporPdfRevision
{
    public const MIGRATION='20261021_erapor_pdf_revisi';
    private const ERROR_CODES=[
        'ARCHIVED_SIGNATURE_INTEGRITY_FAILED','ARCHIVED_SOURCE_IMMUTABLE','ARCHIVED_SOURCE_INTEGRITY_FAILED',
        'HISTORICAL_APPROVAL_INCOMPLETE','HISTORICAL_DOCUMENTS_MISMATCH','HISTORICAL_ENDORSEMENT_DATE_MISMATCH',
        'HISTORICAL_ENDORSEMENT_DATE_UNVERIFIED','HISTORICAL_MANIFEST_INCOMPLETE','HISTORICAL_RENDERER_UNSUPPORTED',
        'HISTORICAL_SESSION_NOT_FINAL','HISTORICAL_SIGNERS_MISMATCH','HISTORICAL_SOURCE_HASH_MISMATCH',
        'NO_OFFICIAL_ARTIFACT','ORIGINAL_INTEGRITY_FAILED','PPI_SOURCE_NOT_ARCHIVED','REVISION_ACTION_INVALID',
        'REVISION_API_DISABLED','REVISION_BUSY','REVISION_CHECK_STALE','REVISION_CONFIRM_REQUIRED','REVISION_COOLDOWN',
        'REVISION_DISABLE_API_FOR_MIGRATION','REVISION_FORBIDDEN','REVISION_INTEGRITY_FAILED','REVISION_KIND_INVALID',
        'REVISION_NOT_FOUND','REVISION_REASON_REQUIRED','REVISION_REVIEW_REQUIRED','REVISION_SCHEMA_MISSING',
        'REVISION_SCHEMA_PARTIAL','REVISION_SCHEMA_UNVERIFIED','REVISION_SELECTION_CHANGED','REVISION_SESSION_REQUIRED',
        'REVISION_SOURCE_MISMATCH','REVISION_TOKEN_INVALID','SOURCE_ORIGINAL_MISMATCH','SOURCE_TOO_LARGE',
    ];
    public static function safeCode(Throwable $error,string $fallback='REVISION_OPERATION_BLOCKED'): string
    {
        return in_array($error->getMessage(),self::ERROR_CODES,true)?$error->getMessage():$fallback;
    }

    public static function available(PDO $db): bool
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return false;
        $q=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('erapor_pdf_sumber','erapor_pdf_revisi','erapor_pdf_aktif','erapor_pdf_revisi_log')");
        $count=(int)$q->fetchColumn();if ($count===0) return false;
        if ($count!==4) throw new DomainException('REVISION_SCHEMA_PARTIAL');
        $row=self::one($db,'SELECT sha256,status FROM erapor_migrations WHERE id=?',[self::MIGRATION]);
        $plan=EraporMigrationRunner::plan(ROOT_PATH.'/database/migrations/'.self::MIGRATION.'.sql');
        if (!$row || $row['status']!=='complete' || !hash_equals($plan['sha256'],$row['sha256'])) throw new DomainException('REVISION_SCHEMA_UNVERIFIED');
        return true;
    }

    /** Called only in the original final-approval transaction, after inserting the official artifact. */
    public static function archiveWithin(PDO $db,string $kind,array $package,string $sourceHash,string $pdfHash): void
    {
        if (!$db->inTransaction()) throw new RuntimeException('Snapshot requires the approval transaction.');
        self::kind($kind);if (!self::available($db)) return; // Backward-compatible deploy before additive migration.
        $sid=(int)($package['session']['id']??0);$original=self::original($db,$sid,$kind);
        $expected=$kind==='RAPOR'?hash('sha256',self::json(EraporPublication::manifestSource($package))):hash('sha256',self::json($package));
        if (!hash_equals($expected,$sourceHash) || !hash_equals($pdfHash,$original['sha']) || $sourceHash!==$original['source_sha']) throw new DomainException('SOURCE_ORIGINAL_MISMATCH');
        self::saveSource($db,$sid,$kind,$package,$original,'SAAT_PERSETUJUAN');
    }

    /** Safe metadata only; may run inside the existing diagnostic READ ONLY transaction. */
    public static function readiness(PDO $db,int $actor,int $sid,string $kind='RAPOR'): array
    {
        self::admin($db,$actor);self::kind($kind);
        return self::read($db,static function()use($db,$sid,$kind):array {
            if (!self::available($db)) return ['ready'=>false,'code'=>'REVISION_SCHEMA_MISSING'];
            try {
                $o=self::original($db,$sid,$kind);$s=self::source($db,$sid,$kind,$o);
                return ['ready'=>true,'code'=>'SOURCE_VERIFIED','method'=>$s['method'],'package_sha256'=>$s['sha'],
                    'original_sha256'=>$o['sha'],'original_renderer'=>$o['renderer'],'renderer'=>EraporPackagePdfRenderer::version(),
                    'design_sha256'=>self::designHash(),'document_count'=>count($s['package']['documents'])];
            } catch (DomainException $e) {
                return ['ready'=>false,'code'=>self::safeCode($e,'HISTORICAL_SOURCE_UNVERIFIABLE')];
            }
        });
    }

    /** Prepare is an explicit write. No pointer is activated and no R2/network operation occurs. */
    public static function prepare(PDO $db,int $actor,int $sid,string $kind,string $packageHash,string $designHash,string $reason): int
    {
        self::kind($kind);self::reason($reason);
        return self::write($db,$actor,static function()use($db,$actor,$sid,$kind,$packageHash,$designHash,$reason):int {
            self::requireSchema($db);$o=self::original($db,$sid,$kind);$s=self::source($db,$sid,$kind,$o);
            if (!hash_equals($s['sha'],$packageHash) || !hash_equals(self::designHash(),$designHash)) throw new DomainException('REVISION_CHECK_STALE');
            $old=self::one($db,'SELECT id FROM erapor_pdf_revisi WHERE sesi_id=? AND jenis=? AND paket_sha256=? AND desain_sha256=?',[$sid,$kind,$s['sha'],$designHash]);
            if ($old) {self::revision($db,$sid,$kind,(int)$old['id']);return (int)$old['id'];}
            self::saveSource($db,$sid,$kind,$s['package'],$o,$s['method']);
            $pdf=EraporPackagePdfRenderer::renderArtifact($s['package']);
            if(!hash_equals($designHash,self::designHash()))throw new DomainException('REVISION_CHECK_STALE');
            $q=$db->prepare('INSERT INTO erapor_pdf_revisi(sesi_id,jenis,paket_sha256,pdf_asli_sha256,desain_sha256,renderer_versi,filename,pdf_bytes,pdf_sha256,ukuran_byte,halaman,dibuat_oleh) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$sid,$kind,$s['sha'],$o['sha'],$designHash,EraporPackagePdfRenderer::version(),self::packageFilename($s['package'],$kind),$pdf['bytes'],hash('sha256',$pdf['bytes']),strlen($pdf['bytes']),$pdf['pages'],$actor]);
            $id=(int)$db->lastInsertId();self::audit($db,$actor,$sid,$kind,$id,'SIAPKAN',$reason);return $id;
        });
    }

    /** Optimistic pointer check prevents an old tab overwriting another operator's selection. Idempotent retry. */
    public static function activate(PDO $db,int $actor,int $sid,string $kind,int $id,string $expected,string $pdfHash,string $reason): void
    {
        self::kind($kind);self::reason($reason);
        self::write($db,$actor,static function()use($db,$actor,$sid,$kind,$id,$expected,$pdfHash,$reason):void {
            self::requireSchema($db);$r=self::revision($db,$sid,$kind,$id);
            if (!hash_equals($r['sha256'],$pdfHash)) throw new DomainException('REVISION_CHECK_STALE');
            $current=self::selection($db,$sid,$kind);if ($current===(string)$id) return;
            if ($current!==$expected) throw new DomainException('REVISION_SELECTION_CHANGED');
            $db->prepare('INSERT INTO erapor_pdf_aktif(sesi_id,jenis,revisi_id,diaktifkan_oleh) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE revisi_id=VALUES(revisi_id),diaktifkan_oleh=VALUES(diaktifkan_oleh),diaktifkan_pada=CURRENT_TIMESTAMP')->execute([$sid,$kind,$id,$actor]);
            self::audit($db,$actor,$sid,$kind,$id,'AKTIFKAN',$reason);
        });
    }

    public static function restore(PDO $db,int $actor,int $sid,string $kind,string $expected,string $reason): void
    {
        self::kind($kind);self::reason($reason);
        self::write($db,$actor,static function()use($db,$actor,$sid,$kind,$expected,$reason):void {
            self::requireSchema($db);self::original($db,$sid,$kind);
            $current=self::selection($db,$sid,$kind);if ($current==='asli') return;
            if ($current!==$expected) throw new DomainException('REVISION_SELECTION_CHANGED');
            $db->prepare('DELETE FROM erapor_pdf_aktif WHERE sesi_id=? AND jenis=?')->execute([$sid,$kind]);
            self::audit($db,$actor,$sid,$kind,(int)$current,'KEMBALI_ASLI',$reason);
        });
    }

    /** Used ONLY by already-authorized internal preview controllers, never by distribution/public downloads. */
    public static function activeArtifact(PDO $db,int $sid,string $kind='RAPOR'): ?array
    {
        self::kind($kind);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return null;
        return self::read($db,static function()use($db,$sid,$kind):?array {
            if (!self::available($db)) return null;
            $id=self::selection($db,$sid,$kind);return $id==='asli'?null:self::revision($db,$sid,$kind,(int)$id);
        });
    }
    public static function activePackage(PDO $db,int $sid): ?array
    {
        $r=self::activeArtifact($db,$sid);return $r['package']??null;
    }

    public static function reviewArtifact(PDO $db,int $actor,int $sid,string $kind,int $id): array
    {
        self::admin($db,$actor);
        return self::read($db,static function()use($db,$sid,$kind,$id):array {self::requireSchema($db);return self::revision($db,$sid,$kind,$id);});
    }

    /** Internal helper; HTTP callers use reviewArtifact, which independently verifies Superadmin. */
    public static function revision(PDO $db,int $sid,string $kind,int $id): array
    {
        self::kind($kind);$r=self::one($db,'SELECT * FROM erapor_pdf_revisi WHERE id=? AND sesi_id=? AND jenis=?',[$id,$sid,$kind]);
        if (!$r) throw new DomainException('REVISION_NOT_FOUND');
        $o=self::original($db,$sid,$kind);$s=self::archived($db,$sid,$kind,$o);
        if (!$s || !hash_equals($s['sha'],$r['paket_sha256']) || !hash_equals($o['sha'],$r['pdf_asli_sha256'])) throw new DomainException('REVISION_SOURCE_MISMATCH');
        $bytes=self::bytes($r['pdf_bytes']);
        if (!self::validPdf($bytes,$r['pdf_sha256'],(int)$r['ukuran_byte']) || (int)$r['halaman']<1 || (int)$r['halaman']>80) throw new DomainException('REVISION_INTEGRITY_FAILED');
        return ['id'=>$id,'filename'=>$r['filename'],'bytes'=>$bytes,'sha256'=>$r['pdf_sha256'],'pages'=>(int)$r['halaman'],
            'renderer'=>$r['renderer_versi'],'package'=>$s['package'],'source'=>'revision'];
    }

    public static function original(PDO $db,int $sid,string $kind): array
    {
        self::kind($kind);
        $table=$kind==='RAPOR'?'erapor_publikasi_pdf':'erapor_ppi_pdf';$time=$kind==='RAPOR'?'dibuat_pada':'created_at';
        $r=self::one($db,"SELECT *,UNIX_TIMESTAMP($time) AS epoch FROM $table WHERE sesi_id=?",[$sid]);
        if (!$r) throw new DomainException('NO_OFFICIAL_ARTIFACT');
        $bytes=self::bytes($r['pdf_bytes']);
        if (!self::validPdf($bytes,$r['pdf_sha256'],(int)$r['ukuran_byte'])) throw new DomainException('ORIGINAL_INTEGRITY_FAILED');
        try {$m=json_decode($r['manifest'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new DomainException('HISTORICAL_MANIFEST_INCOMPLETE');}
        $id=$kind==='RAPOR'?($m['sesi_id']??0):($m['session']??0);$pages=$kind==='RAPOR'?($m['page_count']??null):($m['pages']??null);
        if (!is_array($m) || (int)$id!==$sid || ($m['pdf_sha256']??null)!==$r['pdf_sha256'] || ($m['renderer']??null)!==$r['renderer_versi'] || !is_int($pages) || $pages<1 || $pages>80
            || ($kind==='PPI' && ($m['kind']??'')!=='PPI') || ($kind==='RAPOR' && ($m['source_sha256']??null)!==$r['sumber_sha256'])) throw new DomainException('HISTORICAL_MANIFEST_INCOMPLETE');
        return ['kind'=>$kind,'filename'=>$r['filename'],'bytes'=>$bytes,'sha'=>$r['pdf_sha256'],'manifest'=>$m,'renderer'=>$r['renderer_versi'],
            'source_sha'=>$kind==='RAPOR'?$r['sumber_sha256']:($m['source_sha256']??null),'actor'=>(int)($r['disiapkan_oleh']??0),'created_epoch'=>(int)$r['epoch']];
    }

    public static function selection(PDO $db,int $sid,string $kind): string
    {
        self::kind($kind);$r=self::one($db,'SELECT revisi_id FROM erapor_pdf_aktif WHERE sesi_id=? AND jenis=?',[$sid,$kind]);return $r?(string)$r['revisi_id']:'asli';
    }
    public static function history(PDO $db,int $actor,int $sid,string $kind): array
    {
        self::admin($db,$actor);self::kind($kind);if (!self::available($db)) return [];
        $q=$db->prepare('SELECT r.id,r.renderer_versi,r.halaman,r.pdf_sha256,r.dibuat_pada,a.revisi_id IS NOT NULL AS aktif FROM erapor_pdf_revisi r LEFT JOIN erapor_pdf_aktif a ON a.sesi_id=r.sesi_id AND a.jenis=r.jenis AND a.revisi_id=r.id WHERE r.sesi_id=? AND r.jenis=? ORDER BY r.id DESC');$q->execute([$sid,$kind]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function packageFilename(array $p,string $kind='RAPOR'): string
    {
        self::kind($kind);$name=EraporPdfFilename::build($p['student']['nama_lengkap'],$p['period']['jenis'],$p['period']['semester'],$p['period']['tahun_label']);
        return 'Revisi-Desain-'.($kind==='PPI'?'PPI-'.substr($name,strlen('Rapor-')):$name);
    }
    public static function designHash(): string
    {
        $files=['app/models/EraporPackagePdfRenderer.php','app/models/EraporPrintFont.php','app/helpers/functions.php','config/colors.php','vendor/dompdf/dompdf/VERSION','public/assets/css/rapor-document-pdf.css','public/assets/fonts/quicksand/Quicksand-Regular.ttf','public/assets/fonts/quicksand/Quicksand-Bold.ttf','public/assets/fonts/quicksand/Quicksand-Regular.ufm','public/assets/fonts/quicksand/Quicksand-Bold.ufm','public/assets/fonts/quicksand/installed-fonts.json'];$parts=[];
        foreach ($files as $f) {$data=@file_get_contents(ROOT_PATH.'/'.$f);if (!is_string($data)) throw new RuntimeException('Berkas desain PDF tidak tersedia.');if(!str_ends_with($f,'.ttf'))$data=str_replace("\r\n","\n",$data);$parts[$f]=hash('sha256',$data);}
        return hash('sha256',self::json($parts));
    }

    private static function source(PDO $db,int $sid,string $kind,array $o): array
    {
        $saved=self::archived($db,$sid,$kind,$o);if ($saved) return $saved;
        $p=EraporHistoricalPdfSource::recover($db,$sid,$o);self::packageValid($p,$sid,$kind);
        return ['package'=>$p,'sha'=>hash('sha256',self::json($p)),'method'=>'HASH_HISTORIS'];
    }
    private static function archived(PDO $db,int $sid,string $kind,array $o): ?array
    {
        $r=self::one($db,'SELECT * FROM erapor_pdf_sumber WHERE sesi_id=? AND jenis=?',[$sid,$kind]);if (!$r) return null;
        if (!hash_equals($r['paket_sha256'],hash('sha256',$r['paket_json'])) || $r['pdf_asli_sha256']!==$o['sha'] || $r['sumber_sha256']!==$o['source_sha']) throw new DomainException('ARCHIVED_SOURCE_INTEGRITY_FAILED');
        try {$p=json_decode($r['paket_json'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new DomainException('ARCHIVED_SOURCE_INTEGRITY_FAILED');}
        self::packageValid($p,$sid,$kind);
        return ['package'=>$p,'sha'=>$r['paket_sha256'],'method'=>$r['metode']];
    }
    private static function saveSource(PDO $db,int $sid,string $kind,array $p,array $o,string $method): void
    {
        self::packageValid($p,$sid,$kind);$json=self::json($p);if (strlen($json)>33554432) throw new DomainException('SOURCE_TOO_LARGE');
        $sha=hash('sha256',$json);$old=self::archived($db,$sid,$kind,$o);
        if ($old) {if (!hash_equals($sha,$old['sha'])) throw new DomainException('ARCHIVED_SOURCE_IMMUTABLE');return;}
        $db->prepare('INSERT INTO erapor_pdf_sumber(sesi_id,jenis,paket_json,paket_sha256,sumber_sha256,pdf_asli_sha256,metode) VALUES(?,?,?,?,?,?,?)')->execute([$sid,$kind,$json,$sha,$o['source_sha'],$o['sha'],$method]);
    }
    private static function packageValid(mixed $p,int $sid,string $kind): void
    {
        if (!is_array($p) || (int)($p['session']['id']??0)!==$sid || !empty($p['template']) || !empty($p['scoped']) || empty($p['documents']) || (($kind==='PPI')!==!empty($p['standalone_ppi']))) throw new DomainException('ARCHIVED_SOURCE_INTEGRITY_FAILED');
        foreach ($p['documents'] as $d) foreach (array_merge([$d['signature_current']??null],array_values($d['signature_periods']??[])) as $b) if (is_array($b)) foreach ($b['signers']??[] as $s) {
            if (!empty($s['ttd_data_uri'])) {
                $uri=$s['ttd_data_uri'];$png=is_string($uri)&&str_starts_with($uri,'data:image/png;base64,')?base64_decode(substr($uri,22),true):false;
                // Standalone PPI used to omit the per-signer hash in its render package; its full package is now pinned.
                if (!is_string($png) || !str_starts_with($png,"\x89PNG\r\n\x1a\n") || (isset($s['ttd_sha256']) && !hash_equals($s['ttd_sha256'],hash('sha256',$png)))) throw new DomainException('ARCHIVED_SIGNATURE_INTEGRITY_FAILED');
            }
        }
    }
    private static function admin(PDO $db,int $actor,bool $lock=false): void
    {
        $r=self::one($db,"SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1".($lock?' FOR UPDATE':''),[$actor]);
        if (!$r || $r['nama']!=='Superadmin') throw new DomainException('REVISION_FORBIDDEN');
    }
    private static function write(PDO $db,int $actor,callable $work): mixed
    {
        self::admin($db,$actor);if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$db->inTransaction())throw new RuntimeException('Dedicated MySQL connection required.');
        $name=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$name]);if((int)$q->fetchColumn()!==1)throw new DomainException('REVISION_BUSY');
        try {$db->beginTransaction();self::admin($db,$actor,true);$result=$work();$db->commit();return $result;}
        finally {if($db->inTransaction())$db->rollBack();$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$name]);}
    }
    private static function read(PDO $db,callable $work): mixed
    {
        if ($db->inTransaction()) return $work();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        try{return $work();}finally{if($db->inTransaction())$db->rollBack();}
    }
    private static function requireSchema(PDO $db): void {if(!self::available($db))throw new DomainException('REVISION_SCHEMA_MISSING');}
    private static function kind(string $kind): void {if(!in_array($kind,['RAPOR','PPI'],true))throw new DomainException('REVISION_KIND_INVALID');}
    private static function reason(string $text): void {if(preg_match('//u',$text)!==1||mb_strlen(trim($text))<12||mb_strlen($text)>500)throw new DomainException('REVISION_REASON_REQUIRED');}
    private static function audit(PDO $db,int $actor,int $sid,string $kind,int $id,string $action,string $reason): void {$db->prepare('INSERT INTO erapor_pdf_revisi_log(sesi_id,jenis,revisi_id,aktor_id,aksi,alasan) VALUES(?,?,?,?,?,?)')->execute([$sid,$kind,$id,$actor,$action,trim($reason)]);}
    private static function json(array $data): string {return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function bytes(mixed $value): mixed {return is_resource($value)?stream_get_contents($value):$value;}
    private static function validPdf(mixed $bytes,string $sha,int $size): bool {return is_string($bytes)&&$size>5&&$size<=33554432&&strlen($bytes)===$size&&str_starts_with($bytes,'%PDF-')&&hash_equals($sha,hash('sha256',$bytes));}
    private static function one(PDO $db,string $sql,array $args): array|false {$q=$db->prepare($sql);$q->execute($args);return $q->fetch(PDO::FETCH_ASSOC);}
}
