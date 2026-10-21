<?php

/** Maintenance-only PDF probe. All application SQL runs in a read-only transaction; no artifacts are saved. */
final class EraporPdfDiagnostics
{
    private static string $stage = 'initialisation';

    public static function stage(): string { return self::$stage; }

    public static function run(PDO $db, int $operator, array $input, ?callable $renderer = null): array
    {
        self::assertAdmin($db, $operator);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $db->inTransaction()) {
            throw new RuntimeException('Diagnosis memerlukan koneksi MySQL khusus di luar transaksi.');
        }
        $mode = $input['mode'] ?? '';
        $sid = filter_var($input['session_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $subject = filter_var($input['actor_id'] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $type = $input['document_type'] ?? '';
        $semester = $input['semester'] ?? '';
        if (!in_array($mode, ['actor','render','template','readiness'], true)
            || ($mode !== 'template' && $sid === false)
            || ($mode === 'actor' && $subject === false)
            || ($mode === 'template' && (!in_array($type,['RTS','AGAMA','UMMI','BING','PPI'],true) || !in_array($semester,['GANJIL','GENAP'],true)))) {
            throw new DomainException('Pilihan diagnosis tidak valid.');
        }
        self::$stage = 'initialisation';
        $report = ['format'=>1,'id'=>bin2hex(random_bytes(12)),'created_at'=>gmdate('c'),
            'mode'=>$mode,'session_id'=>$mode === 'template' ? null : $sid,
            'actor_id'=>$mode === 'actor' ? $subject : null,'ok'=>false,'read_only'=>true,
            'environment'=>self::environment($db),'steps'=>[],'warnings'=>[]];
        $observe = static function(string $stage) use (&$report): void {
            self::$stage = $stage;
            if (end($report['steps']) !== $stage && count($report['steps']) < 120) $report['steps'][] = $stage;
        };
        set_error_handler(static function(int $level,string $message,string $file,int $line) use (&$report): bool {
            if (count($report['warnings']) < 8) $report['warnings'][] = [
                'stage'=>self::$stage,'message'=>self::safeMessage($message),'file'=>self::safeFile($file),'line'=>$line];
            return true; // Do not leak warnings into the protected HTML or JSON response.
        });
        $started = false;
        try {
            $observe('read_only_transaction');
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->exec('SET TRANSACTION READ ONLY');
            $db->beginTransaction(); $started = true;
            if ($mode === 'template') {
                $observe('template_capture');
                $package = EraporPublication::templatePackage($db, $type === 'PPI' ? 'ABK' : 'Regular', $semester, $type);
                $report['path'] = 'template';
            } else {
                $observe('session_lookup');
                $q=$db->prepare('SELECT * FROM erapor_sesi WHERE id=?'); $q->execute([$sid]); $session=$q->fetch(PDO::FETCH_ASSOC);
                if (!$session) throw new DomainException('Sesi tidak tersedia.');
                $report['condition']=$session['kondisi']; $report['status']=$session['status'];
                if ($mode === 'readiness') {
                    $observe('official_artifact');
                    $official=EraporPdfAccess::officialArtifact($db,$sid);
                    $report['official']=['exists'=>$official!==null,'integrity_checked'=>true,'sha256'=>$official?hash('sha256',$official['bytes']):null];
                    if (EraporPdfRevision::available($db)) {
                        $report['revision']=EraporPdfRevision::readiness($db,$operator,$sid);
                        $report['revision']['message']=$report['revision']['ready']?'Sumber terverifikasi. Revisi harus disiapkan dan dibandingkan terpisah melalui halaman Revisi PDF; belum ada file diubah.':'Revisi diblokir: '.$report['revision']['code'].'. PDF asli tidak diubah.';
                    } else {
                        $report['revision']=['ready'=>false,'code'=>$official?'HISTORICAL_SOURCE_NOT_ARCHIVED':'NO_OFFICIAL_ARTIFACT',
                            'message'=>$official?'PDF asli aman. Tabel revisi belum dipasang; paket historis belum diverifikasi.':'Belum ada PDF resmi untuk diperiksa.'];
                    }
                    $report['ok']=true; return $report;
                }
                $scope=null;
                if ($mode === 'actor') {
                    $observe('actor_access');
                    $full=EraporPdfAccess::canView($db,$sid,$subject);
                    $report['access']=$full?'full':'scoped';
                    if (!$full) {
                        $scope=EraporPdfAccess::scopedDocumentIds($db,$sid,$subject);
                        if ($scope===[]) throw new DomainException('Akun tidak berwenang melihat PDF sesi ini.');
                    }
                    if ($full) {
                        $observe('official_artifact');
                        $official=EraporPdfAccess::previewArtifact($db,$sid);
                        if ($official) {
                            $report['official']=['exists'=>true,'integrity_checked'=>true,'sha256'=>hash('sha256',$official['bytes'])];
                            $report['path']=($official['source']??'')==='revision'?'stored_revision':'stored_official'; $report['ok']=true;
                            if(isset($official['id']))$report['revision_id']=$official['id'];
                            $report['pdf']=['bytes'=>strlen($official['bytes']),'sha256'=>hash('sha256',$official['bytes'])];
                            return $report;
                        }
                    }
                }
                // Scoped previews do not read/validate stored bytes in the actual endpoint either.
                // Forced fresh render must also remain usable when investigating a corrupt stored artifact.
                $hasOfficial=EraporPdfAccess::hasOfficial($db,$sid);
                $report['official']=['exists'=>$hasOfficial,'integrity_checked'=>false];
                $report['path']=$scope!==null?'fresh_scoped':'fresh_full';
                $observe('package_capture');
                $frozen=$scope!==null?EraporPdfRevision::activePackage($db,$sid):null;
                $package=$frozen??EraporPublication::draftPackage($db,$sid,null,false,$observe);
                if($frozen!==null){$report['path']='frozen_scoped';$observe('active_frozen_source');}
                if ($scope!==null) {
                    $observe('scope_filter');
                    $package['documents']=array_values(array_filter($package['documents'],static fn($d)=>in_array((int)$d['id'],$scope,true)));
                    if (!$package['documents']) throw new DomainException('Tidak ada dokumen dalam cakupan akun.');
                    $package['scoped']=true;
                    if ($hasOfficial) unset($package['draft']);
                }
            }
            $report['documents']=array_column($package['documents'],'jenis_dokumen');
            $report['watermark']=EraporPackagePdfRenderer::watermarkEnabled($package);
            $observe('font_files');
            $options=EraporPrintFont::options();
            foreach (['Quicksand-Regular.ttf','Quicksand-Bold.ttf','Quicksand-Regular.ufm','Quicksand-Bold.ufm','installed-fonts.json'] as $file) {
                if (!is_readable($options['fontDir'].'/'.$file)) throw new RuntimeException('Berkas font cetak tidak tersedia: '.$file);
            }
            $registry=json_decode(file_get_contents($options['fontDir'].'/installed-fonts.json'),true,512,JSON_THROW_ON_ERROR);
            if (($registry['quicksand']['normal']??'')!=='Quicksand-Regular' || ($registry['quicksand']['bold']??'')!=='Quicksand-Bold') {
                throw new RuntimeException('Registry font Quicksand tidak sesuai.');
            }
            $report['fonts']=['default_family'=>$options['defaultFont'],'source_files_readable'=>true,'relative_registry_valid'=>true];
            $observe('renderer');
            $rendered=$renderer ? $renderer($package,$observe) : EraporPackagePdfRenderer::renderArtifact($package,$observe);
            if (!is_string($rendered['bytes'] ?? null) || !str_starts_with($rendered['bytes'],'%PDF-')) throw new RuntimeException('Renderer tidak menghasilkan PDF valid.');
            $report['pdf']=['bytes'=>strlen($rendered['bytes']),'pages'=>$rendered['pages'],'sha256'=>hash('sha256',$rendered['bytes']),
                'renderer'=>EraporPackagePdfRenderer::version()];
            $observe('complete'); $report['ok']=true;
        } catch (Throwable $error) {
            $report['error']=self::safeError($error);
        } finally {
            if ($started && $db->inTransaction()) $db->rollBack();
            restore_error_handler();
        }
        return $report;
    }

    public static function safeError(Throwable $error): array
    {
        $frames=[];
        foreach (array_slice($error->getTrace(),0,8) as $frame) $frames[]=[
            'file'=>self::safeFile((string)($frame['file']??'')),'line'=>(int)($frame['line']??0),
            'call'=>self::safeSymbol((string)($frame['class']??'').(string)($frame['type']??'').(string)($frame['function']??''))];
        $code=(string)$error->getCode();
        return ['stage'=>self::$stage,'class'=>self::safeSymbol(get_class($error)),'code'=>preg_match('/^[A-Za-z0-9]{1,8}$/',$code)?$code:'[redacted]',
            'message'=>self::safeMessage($error->getMessage()),'file'=>self::safeFile($error->getFile()),'line'=>$error->getLine(),'trace'=>$frames];
    }

    public static function safeMessage(string $text): string
    {
        foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','SMTP_USER','SMTP_PASS','R2_ACCESS_KEY_ID','R2_SECRET_ACCESS_KEY',
            'R2_ENDPOINT','ERAPOR_PDF_DIAGNOSTIC_TOKEN','ERAPOR_PDF_REVISION_TOKEN','ERAPOR_MIGRATION_TOKEN','DATA_RESET_TOKEN'] as $key) {
            if (defined($key) && is_string(constant($key)) && strlen(constant($key))>=3) $text=str_replace(constant($key),'[redacted]',$text);
        }
        if (defined('ROOT_PATH')) $text=str_replace([ROOT_PATH,str_replace('/','\\',ROOT_PATH)],'[project]',$text);
        $text=preg_replace('~data:[^\s]+|https?://[^\s]+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}~u','[redacted]',$text) ?? '';
        $text=preg_replace('~\b[a-f0-9]{32,}\b~i','[redacted]',$text) ?? '';
        $text=preg_replace('~\b[A-Za-z0-9+/=_-]{48,}\b~','[redacted]',$text) ?? '';
        $text=preg_replace('~([\'"`]).*?\1~s','[quoted value redacted]',$text) ?? '';
        $text=preg_replace('~(?:[A-Za-z]:[\\\\/]|/(?:home|var|tmp|Users|srv|usr)/)[^\s:;,)]*~','[path redacted]',$text) ?? '';
        // Never export arbitrary application/driver text: it can contain an unquoted student's value.
        if (preg_match('/^SQLSTATE\\[([A-Z0-9]+)\\](?::[^:]*:)?\\s*([0-9]{3,5})?/',$text,$sql)) {
            return 'SQLSTATE['.$sql[1].']'.(!empty($sql[2])?' · driver '.$sql[2]:'').' · detail driver disembunyikan; lihat lokasi dan tahap.';
        }
        $fixed=['Sesi tidak tersedia.','Akun tidak berwenang melihat PDF sesi ini.','Tidak ada dokumen dalam cakupan akun.',
            'Pilihan diagnosis tidak valid.','Renderer tidak menghasilkan PDF valid.','Diagnosis hanya untuk Superadmin.',
            'Diagnosis memerlukan koneksi MySQL khusus di luar transaksi.',
            'Snapshot tanda tangan tidak lolos pemeriksaan integritas.','Logo watermark tidak tersedia.',
            'Logo watermark finalisasi tidak tersedia.','Gambar watermark tidak valid.',
            'Susunan paket atau rubrik PDF belum lengkap.','Paket dokumen tidak cocok dengan kondisi murid.',
            'Data periode, murid, atau sekolah tidak tersedia.','Rubrik yang dipakai belum dikunci.',
            'Kota pengesahan belum tersedia pada Data Sekolah.','Logo Data Sekolah tidak tersedia untuk PDF.',
            'Snapshot sumber rubrik tidak tersedia.','PDF final menunggu spesifikasi Rapor Akhir Semester.',
            'PDF paket kosong atau melebihi batas 32 MB.','Jumlah halaman PDF berada di luar batas yang diperiksa.',
            'Artefak PDF tersimpan tidak lolos pemeriksaan integritas.','Registry font Quicksand tidak sesuai.',
            'File sementara watermark tidak dapat dibuat.'];
        if (in_array($text,$fixed,true) || preg_match('/^Berkas font cetak tidak tersedia: (?:Quicksand-(?:Regular|Bold)\\.(?:ttf|ufm)|installed-fonts\\.json)$/',$text)) return $text;
        if (preg_match('/^Undefined (array key|variable|property)/',$text,$undefined)) return 'Undefined '.$undefined[1].' [value redacted]';
        if (preg_match('/^(?:Call to undefined (?:function|method) |Call to a member function |Class .* not found|Allowed memory size of [0-9]+ bytes exhausted|Maximum execution time of [0-9]+ seconds exceeded)/',$text)
            || preg_match('/^[A-Za-z0-9_\\\\:]+\\(\\): (?:Argument #[0-9]+ .* must be of type |Return value must be of type )/',$text)) return mb_substr($text,0,1200);
        return 'Pesan rinci disembunyikan untuk menjaga data. Kelas, kode, lokasi dan tahap tersedia.';
    }

    private static function safeSymbol(string $value): string
    {
        $plain=str_replace(['\\','::','->','{closure}'],'',$value);
        return ($value==='{closure}' || preg_match('/^[A-Za-z0-9_]+$/D',$plain))?substr($value,0,180):'[internal]';
    }

    private static function safeFile(string $file): string
    {
        $file=str_replace('\\','/',$file);$root=str_replace('\\','/',dirname(__DIR__,2)).'/';
        return str_starts_with($file,$root)?substr($file,strlen($root)):basename($file);
    }

    private static function environment(PDO $db): array
    {
        $functions=[];foreach (['imagecreatefrompng','imagepng','getimagesize','getimagesizefromstring','mb_convert_encoding'] as $fn) $functions[$fn]=function_exists($fn);
        $server=(string)$db->getAttribute(PDO::ATTR_SERVER_VERSION);
        preg_match('/^[0-9]+(?:\\.[0-9]+){1,3}/',$server,$version);
        return ['php'=>PHP_VERSION,'database_version'=>($version[0]??'unknown').(stripos($server,'MariaDB')!==false?' MariaDB':' MySQL'),
            'dompdf'=>class_exists(\Composer\InstalledVersions::class)?\Composer\InstalledVersions::getPrettyVersion('dompdf/dompdf'):null,
            'memory_limit'=>(string)ini_get('memory_limit'),'max_execution_time'=>(int)ini_get('max_execution_time'),
            'allow_url_fopen'=>(bool)ini_get('allow_url_fopen'),'temp_writable'=>is_writable(sys_get_temp_dir()),'functions'=>$functions,
            'image_extensions'=>['gd'=>extension_loaded('gd'),'imagick'=>extension_loaded('imagick'),'gmagick'=>extension_loaded('gmagick')],
            'renderer'=>EraporPackagePdfRenderer::version(),
            'watermark_input'=>method_exists(EraporPackagePdfRenderer::class,'watermarkFile')?'validated_local_png':'legacy_canvas_input'];
    }

    private static function assertAdmin(PDO $db,int $actor): void
    {
        $q=$db->prepare('SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1');$q->execute([$actor]);
        if ($q->fetchColumn()!=='Superadmin') throw new DomainException('Diagnosis hanya untuk Superadmin.');
    }
}
