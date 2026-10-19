<?php

/** Independent PPI submission. Original session/document IDs and values are retained; main PDF bytes never change. */
final class EraporPpiWorkflow
{
    public const LABELS = ['BELUM_DIAJUKAN'=>'Belum diajukan','MENUNGGU_WALI'=>'Menunggu Wali Kelas',
        'MENUNGGU_KEPALA'=>'Menunggu Kepala Sekolah','DISETUJUI'=>'Disetujui · belum terbit','TERBIT'=>'Terbit'];

    public static function available(PDO $db): bool
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return false;
        return (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('erapor_ppi_pengajuan','erapor_ppi_persetujuan','erapor_ppi_log','erapor_ppi_pdf')")->fetchColumn()===4
            && (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='erapor_sesi' AND column_name IN ('ppi_disertakan','ppi_terpisah')")->fetchColumn()===2;
    }

    public static function state(PDO $db,array $session): ?array
    {
        if (($session['kondisi'] ?? '')!=='ABK' || !array_key_exists('ppi_disertakan',$session)) return null;
        $row=self::one($db,'SELECT status,diajukan_pada FROM erapor_ppi_pengajuan WHERE sesi_id=?',[$session['id']]);
        $progress=self::completion($db,$session);
        $status=$row['status'] ?? 'BELUM_DIAJUKAN';
        return ['included'=>!(bool)$session['ppi_terpisah'],'separate'=>(bool)$session['ppi_terpisah'],
            'status'=>$status,'label'=>self::LABELS[$status],'submitted_at'=>$row['diajukan_pada'] ?? null,'progress'=>$progress,
            'can_submit'=>(bool)$session['ppi_terpisah'] && in_array($session['status'],['MENUNGGU_TTD','SELESAI'],true)
                && $status==='BELUM_DIAJUKAN' && $progress['complete']];
    }

    public static function completion(PDO $db,array $s): array
    {
        $rows=self::all($db,"SELECT a.id AS aspek_id,c.id AS kolom_id,n.isi
            FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id
            JOIN erapor_rubrik r ON r.id=d.rubrik_id AND r.jenis_dokumen='PPI'
            JOIN erapor_ppi_aspek a ON a.rubrik_id=r.id AND a.aktif=1
            JOIN erapor_ppi_kolom c ON c.rubrik_id=r.id AND c.wajib=1 AND c.diisi_di_sesi=1
            LEFT JOIN erapor_ppi_isian n ON n.sesi_id=sd.sesi_id AND n.dokumen_id=d.id AND n.rubrik_id=r.id AND n.aspek_id=a.id AND n.kolom_id=c.id
            WHERE sd.sesi_id=? ORDER BY a.urutan,c.bagian,c.urutan",[$s['id']]);
        if (!$rows) throw new DomainException('Definisi PPI tidak tersedia.');
        $filled=0; foreach ($rows as $r) if ($r['isi']!==null && !EraporSessionPolicy::isBlank($r['isi'])) $filled++;
        return ['required'=>count($rows),'filled'=>$filled,'complete'=>$filled===count($rows)];
    }

    public static function choose(PDO $db,int $sid,int $actor,bool $include): array
    {
        return self::transaction($db,function() use($db,$sid,$actor,$include) {
            $s=self::session($db,$sid,true); self::owner($db,$s,$actor,true);
            self::choiceWithin($db,$s,$actor,$include);
            return ['ppi'=>self::state($db,$s)];
        });
    }

    /** Caller owns the main finalization transaction. Before Finalisasi, both flags follow the PIC choice. */
    public static function choiceWithin(PDO $db,array &$s,int $actor,bool $include): void
    {
        if (!$db->inTransaction() || !array_key_exists('ppi_disertakan',$s) || $s['kondisi']!=='ABK'
            || !in_array($s['status'],['BELUM_DIISI','TELAH_DIISI'],true)) throw new DomainException('Pilihan PPI tidak dapat diubah pada status ini.');
        $child=self::one($db,'SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id=? FOR UPDATE',[$s['id']]);
        if ($child && $child['status']!=='BELUM_DIAJUKAN') throw new DomainException('PPI sudah diajukan terpisah.');
        if ((bool)$s['ppi_disertakan']===$include && (bool)$s['ppi_terpisah']===!$include) return;
        if (!$include) self::ensureWithin($db,$s);
        $db->prepare('UPDATE erapor_sesi SET ppi_disertakan=?,ppi_terpisah=? WHERE id=?')->execute([(int)$include,(int)!$include,$s['id']]);
        $s['ppi_disertakan']=(int)$include; $s['ppi_terpisah']=(int)!$include;
        if ($child || !$include) self::log($db,(int)$s['id'],$actor,$include?'PPI_DIGABUNG_PIC':'PPI_DIPISAH_PIC','BELUM_DIAJUKAN','BELUM_DIAJUKAN');
    }

    /** Head may exclude PPI before signing/preparing the immutable main PDF. Approval scope remains historically intact. */
    public static function detachByHeadWithin(PDO $db,array &$s,int $actor): void
    {
        if (!$db->inTransaction() || $s['kondisi']!=='ABK' || !array_key_exists('ppi_terpisah',$s)) throw new DomainException('PPI terpisah belum tersedia.');
        if (self::one($db,'SELECT 1 FROM erapor_publikasi_pdf WHERE sesi_id=?',[$s['id']])) throw new DomainException('PDF resmi sudah dibuat; isinya tidak dapat diubah.');
        if ($s['ppi_terpisah']) return;
        self::ensureWithin($db,$s);
        $db->prepare('UPDATE erapor_sesi SET ppi_terpisah=1 WHERE id=?')->execute([$s['id']]);
        $s['ppi_terpisah']=1;
        self::log($db,(int)$s['id'],$actor,'PPI_DIPISAH_KEPALA','BELUM_DIAJUKAN','BELUM_DIAJUKAN');
    }

    private static function ensureWithin(PDO $db,array $s): void
    {
        $doc=self::document($db,$s);
        $values=EraporTeacherForm::documentForm($db,$s,$doc)['values'];
        $db->prepare('INSERT INTO erapor_ppi_pengajuan(sesi_id,nilai_saat_pisah) VALUES(?,?) ON DUPLICATE KEY UPDATE sesi_id=sesi_id')
            ->execute([$s['id'],json_encode($values,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }

    public static function assertWritable(PDO $db,array $s): void
    {
        $row=self::one($db,'SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id=? FOR UPDATE',[$s['id']]);
        if (!$s['ppi_terpisah'] || !$row || $row['status']!=='BELUM_DIAJUKAN') throw new DomainException('Pengajuan PPI sudah terkunci.');
    }

    public static function submit(PDO $db,int $sid,int $actor): array
    {
        return self::transaction($db,function() use($db,$sid,$actor) {
            $s=self::session($db,$sid,true); self::owner($db,$s,$actor,true);
            if (empty($s['ppi_terpisah']) || !in_array($s['status'],['MENUNGGU_TTD','SELESAI'],true)) throw new DomainException('Finalisasi rapor utama tanpa PPI terlebih dahulu.');
            $row=self::one($db,'SELECT * FROM erapor_ppi_pengajuan WHERE sesi_id=? FOR UPDATE',[$sid]);
            if (!$row) throw new DomainException('PPI belum dipisahkan dari paket utama.');
            if ($row['status']!=='BELUM_DIAJUKAN') return ['result'=>'already_submitted','ppi'=>self::state($db,$s)];
            if (!self::completion($db,$s)['complete']) throw new DomainException('Lengkapi seluruh isian PPI sebelum mengajukan.');
            $wali=WaliKelas::forKelas($db,(int)$s['kelas_id']);
            if (!$wali || !self::actor($db,$s,'WALI_KELAS',$wali['user_id'],true)) throw new DomainException('Wali kelas aktif belum ditetapkan.');
            $db->prepare("UPDATE erapor_ppi_pengajuan SET status='MENUNGGU_WALI',diajukan_oleh=?,diajukan_pada=NOW() WHERE sesi_id=?")->execute([$actor,$sid]);
            self::log($db,$sid,$actor,'AJUKAN_PPI','BELUM_DIAJUKAN','MENUNGGU_WALI');
            return ['result'=>'submitted','ppi'=>self::state($db,$s)];
        });
    }

    public static function approve(PDO $db,int $sid,int $actor,string $code): array
    {
        return self::transaction($db,function() use($db,$sid,$actor,$code) {
            $s=self::session($db,$sid,true);
            if (empty($s['ppi_terpisah']) || !in_array($code,['WALI_KELAS','KEPALA_SEKOLAH'],true)) throw new DomainException('Tahap PPI tidak tersedia.');
            $who=self::actor($db,$s,$code,$actor,true);
            if (!$who) throw new DomainException('Akun tidak berwenang pada tahap PPI ini.');
            $row=self::one($db,'SELECT * FROM erapor_ppi_pengajuan WHERE sesi_id=? FOR UPDATE',[$sid]);
            if (!$row) throw new DomainException('PPI belum diajukan.');
            $old=self::one($db,'SELECT * FROM erapor_ppi_persetujuan WHERE sesi_id=? AND kode=?',[$sid,$code]);
            if ($old) {
                if ((int)$old['user_id']!==$actor) throw new DomainException('Tahap telah disetujui akun lain.');
                return ['result'=>'already_approved'];
            }
            $expected=$code==='WALI_KELAS'?'MENUNGGU_WALI':'MENUNGGU_KEPALA';
            if ($row['status']!==$expected || !self::completion($db,$s)['complete']) throw new DomainException('Urutan atau kelengkapan PPI tidak sesuai.');
            if ($code==='KEPALA_SEKOLAH' && !self::one($db,"SELECT 1 FROM erapor_ppi_persetujuan WHERE sesi_id=? AND kode='WALI_KELAS'",[$sid])) throw new DomainException('Bukti persetujuan Wali Kelas belum ada.');
            $profile=self::one($db,'SELECT * FROM erapor_profil_penandatangan WHERE user_id=? FOR UPDATE',[$actor]);
            $signer=EraporSignerSnapshot::build($who['nama'],$profile ?: null);
            $db->prepare('INSERT INTO erapor_ppi_persetujuan(sesi_id,kode,user_id,nama,nuptk,ttd_png,ttd_sha256,ttd_disetujui_pada) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$sid,$code,$actor,$signer['nama'],$signer['nuptk'],$signer['ttd_png'],$signer['ttd_sha256'],$signer['ttd_disetujui_pada']]);
            $next=$code==='WALI_KELAS'?'MENUNGGU_KEPALA':'DISETUJUI';
            $date=$code==='KEPALA_SEKOLAH'?(new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->format('Y-m-d'):null;
            $db->prepare('UPDATE erapor_ppi_pengajuan SET status=?,tanggal_pengesahan=? WHERE sesi_id=?')->execute([$next,$date,$sid]);
            self::log($db,$sid,$actor,'SETUJUI_'.$code,$expected,$next);
            if ($code==='KEPALA_SEKOLAH') {
                $package=self::package($db,$sid);$rendered=EraporPackagePdfRenderer::renderArtifact($package);
                $hash=hash('sha256',$rendered['bytes']);
                $manifest=['kind'=>'PPI','tanggal_pengesahan'=>$date,'renderer'=>EraporPackagePdfRenderer::version(),'session'=>$sid,'pages'=>$rendered['pages'],'pdf_sha256'=>$hash,
                    'values_sha256'=>hash('sha256',json_encode($package['documents'][0]['form']['values'],JSON_THROW_ON_ERROR)),
                    'signers'=>array_map(static fn($r)=>['role'=>$r['kode'],'user_id'=>(int)$r['user_id'],'signature_sha256'=>$r['ttd_sha256']],
                        self::all($db,'SELECT kode,user_id,ttd_sha256 FROM erapor_ppi_persetujuan WHERE sesi_id=? ORDER BY kode',[$sid]))];
                $db->prepare('INSERT INTO erapor_ppi_pdf(sesi_id,filename,renderer_versi,pdf_bytes,pdf_sha256,ukuran_byte,manifest) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$sid,EraporPdfFilename::ppiForSession($db,$sid),EraporPackagePdfRenderer::version(),$rendered['bytes'],$hash,strlen($rendered['bytes']),json_encode($manifest,JSON_THROW_ON_ERROR)]);
            }
            return ['result'=>'approved','status'=>$next];
        });
    }

    public static function package(PDO $db,int $sid): array
    {
        if (!$db->inTransaction()) return self::read($db,static fn()=>self::package($db,$sid));
        $s=self::session($db,$sid);
        if (empty($s['ppi_terpisah'])) throw new DomainException('PPI tidak diajukan terpisah.');
        $child=self::one($db,'SELECT status,tanggal_pengesahan FROM erapor_ppi_pengajuan WHERE sesi_id=?',[$sid]);
        if (!$child) throw new DomainException('PPI tidak tersedia.');
        $p=EraporPublication::draftPackage($db,$sid,null,true);
        $p['documents']=array_values(array_filter($p['documents'],static fn($d)=>$d['jenis_dokumen']==='PPI'));
        if (count($p['documents'])!==1) throw new DomainException('Dokumen PPI tidak tersedia.');
        $signers=[];$date=!empty($child['tanggal_pengesahan'])?self::indonesianDate($child['tanggal_pengesahan']):null;
        foreach (self::all($db,'SELECT * FROM erapor_ppi_persetujuan WHERE sesi_id=? ORDER BY id',[$sid]) as $r) {
            $png=$r['ttd_png'];
            if ($png!==null && !hash_equals((string)$r['ttd_sha256'],hash('sha256',$png))) throw new DomainException('Bukti TTD PPI tidak valid.');
            $role=$r['kode']==='WALI_KELAS'?'GURU_KELAS':'KEPALA_SEKOLAH';
            $signers[$role]=['nama'=>$r['nama'],'nuptk'=>$r['nuptk'],'ttd_data_uri'=>$png!==null?'data:image/png;base64,'.base64_encode($png):null];
        }
        $p['documents'][0]['signature_current']=['signers'=>$signers,'tempat'=>$p['school']['snapshot']['tempat_pengesahan'],'tanggal'=>$date];
        $p['standalone_ppi']=true;unset($p['exclude_ppi']);
        $p['session']['status']=$child['status']==='BELUM_DIAJUKAN'?'BELUM_DIISI':'MENUNGGU_TTD';
        return $p;
    }

    public static function artifact(PDO $db,int $sid): ?array
    {
        $row=self::one($db,'SELECT filename,pdf_bytes,pdf_sha256,ukuran_byte,manifest,renderer_versi FROM erapor_ppi_pdf WHERE sesi_id=?',[$sid]);
        if (!$row) return null;
        $bytes=is_resource($row['pdf_bytes'])?stream_get_contents($row['pdf_bytes']):$row['pdf_bytes'];
        try{$manifest=json_decode($row['manifest'],true,512,JSON_THROW_ON_ERROR);}catch(Throwable){$manifest=[];}
        if (!is_string($bytes) || !str_starts_with($bytes,'%PDF-') || strlen($bytes)!==(int)$row['ukuran_byte'] || !hash_equals($row['pdf_sha256'],hash('sha256',$bytes))
            || ($manifest['kind']??'')!=='PPI' || ($manifest['session']??0)!==$sid || ($manifest['renderer']??'')!==$row['renderer_versi']
            || ($manifest['pdf_sha256']??'')!==$row['pdf_sha256'] || !is_int($manifest['pages']??null) || $manifest['pages']<1 || $manifest['pages']>80) throw new DomainException('PDF PPI tidak lolos pemeriksaan integritas.');
        return ['filename'=>$row['filename'],'bytes'=>$bytes,'sha256'=>$row['pdf_sha256']];
    }

    public static function publish(PDO $db,int $sid,int $actor,?R2Storage $r2=null): void
    {
        $s=self::session($db,$sid);
        if (!self::actor($db,$s,'KEPALA_SEKOLAH',$actor)) throw new DomainException('Hanya Kepala Sekolah yang ditugaskan dapat menerbitkan PPI.');
        $state=self::one($db,'SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id=?',[$sid]);
        if (($state['status']??'')==='TERBIT') return;
        if (($state['status']??'')!=='DISETUJUI' || empty($s['ppi_terpisah'])) throw new DomainException('PPI belum disetujui lengkap.');
        $artifact=self::artifact($db,$sid);if (!$artifact) throw new DomainException('PDF PPI belum tersedia.');
        $r2??=new R2Storage();$key='erapor/ppi/'.$sid.'-'.bin2hex(random_bytes(16)).'.pdf';
        try {
            $r2->put($key,$artifact['bytes'],'application/pdf','attachment; filename="'.$artifact['filename'].'"');
            $head=$r2->head($key);if (!$head || $head['size']!==strlen($artifact['bytes'])) throw new RuntimeException('Verifikasi unggahan PPI gagal.');
            self::transaction($db,function() use($db,$sid,$actor,$key) {
                $session=self::session($db,$sid,true);
                if (empty($session['ppi_terpisah']) || !self::actor($db,$session,'KEPALA_SEKOLAH',$actor,true)) throw new DomainException('Penugasan penerbit PPI berubah.');
                $row=self::one($db,'SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id=? FOR UPDATE',[$sid]);
                if (!$row || $row['status']!=='DISETUJUI') throw new DomainException('Status PPI berubah; muat ulang.');
                $token=bin2hex(random_bytes(32));$url=rtrim(APP_URL,'/').BASE_PATH.'/rapor/ppi/unduh/'.$token;
                $db->prepare('UPDATE erapor_ppi_pdf SET r2_bucket=?,r2_key=?,unduh_token=?,unduh_url=?,unduh_kedaluwarsa=DATE_ADD(NOW(),INTERVAL 60 DAY),diterbitkan_oleh=?,diterbitkan_pada=NOW() WHERE sesi_id=?')
                    ->execute([R2_PRIVATE_BUCKET,$key,$token,$url,$actor,$sid]);
                $db->prepare("UPDATE erapor_ppi_pengajuan SET status='TERBIT' WHERE sesi_id=?")->execute([$sid]);
                self::log($db,$sid,$actor,'TERBITKAN_PPI','DISETUJUI','TERBIT');
            });
        } catch (Throwable $e) { try{$r2->delete($key);}catch(Throwable){} throw $e; }
    }

    public static function shareState(PDO $db,int $sid): ?array
    {
        $r=self::one($db,"SELECT p.unduh_url,p.unduh_kedaluwarsa,p.dibagikan_pada,p.dibagikan_ke,p.jumlah_unduh,m.nama_lengkap,m.nama_ayah,m.telp_ayah,m.nama_ibu,m.telp_ibu
            FROM erapor_ppi_pdf p JOIN erapor_ppi_pengajuan c ON c.sesi_id=p.sesi_id AND c.status='TERBIT'
            JOIN erapor_sesi s ON s.id=p.sesi_id JOIN murid m ON m.id=s.murid_id WHERE p.sesi_id=?",[$sid]);
        if (!$r) return null;$contacts=[];
        foreach (['AYAH'=>['nama_ayah','telp_ayah','Ayah'],'IBU'=>['nama_ibu','telp_ibu','Ibu']] as $key=>[$n,$phone,$label]) {
            $number=EraporDistribution::phone((string)$r[$phone]);if (!$number) continue;
            $text='Berikut Rapor PPI ananda '.$r['nama_lengkap'].".\nSilakan unduh melalui tautan berikut:\n".$r['unduh_url'];
            $contacts[$key]=['label'=>$label,'name'=>$r[$n],'phone'=>$number,'wa_url'=>'https://wa.me/'.$number.'?text='.rawurlencode($text)];
        }
        return ['contacts'=>$contacts,'shared_at'=>$r['dibagikan_pada'],'shared_to'=>$r['dibagikan_ke'],'expires_at'=>$r['unduh_kedaluwarsa'],'downloads'=>(int)$r['jumlah_unduh']];
    }

    public static function markShared(PDO $db,int $sid,int $actor,string $target): void
    {
        self::transaction($db,function() use($db,$sid,$actor,$target) {
            $s=self::session($db,$sid,true);self::owner($db,$s,$actor,true);$share=self::shareState($db,$sid);
            if (!$share || !isset($share['contacts'][$target])) throw new DomainException('Kontak atau PPI terbit belum tersedia.');
            $db->prepare('UPDATE erapor_ppi_pdf SET dibagikan_oleh=?,dibagikan_pada=NOW(),dibagikan_ke=? WHERE sesi_id=?')->execute([$actor,$target,$sid]);
            self::log($db,$sid,$actor,'BAGIKAN_PPI_'.$target,'TERBIT','TERBIT');
        });
    }

    public static function downloadUrl(PDO $db,string $token,bool $count,?R2Storage $r2=null): ?string
    {
        if (!preg_match('/^[0-9a-f]{64}$/D',$token)) return null;
        $r=self::one($db,"SELECT p.sesi_id,p.r2_bucket,p.r2_key,p.filename FROM erapor_ppi_pdf p JOIN erapor_ppi_pengajuan c ON c.sesi_id=p.sesi_id
            WHERE p.unduh_token=? AND p.unduh_kedaluwarsa>NOW() AND c.status='TERBIT'",[$token]);
        if (!$r) return null;
        $url=($r2??new R2Storage(bucket:$r['r2_bucket']))->presignedGetUrl($r['r2_key'],300,$r['filename']);
        if ($count) $db->prepare('UPDATE erapor_ppi_pdf SET jumlah_unduh=jumlah_unduh+1 WHERE sesi_id=?')->execute([$r['sesi_id']]);
        return $url;
    }

    public static function canView(PDO $db,int $sid,int $actor): bool
    {
        $s=self::one($db,'SELECT * FROM erapor_sesi WHERE id=?',[$sid]);if (!$s || empty($s['ppi_terpisah'])) return false;
        $role=self::one($db,'SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.is_active=1',[$actor]);
        if (!$role) return false;if ($role['nama']==='Superadmin') return true;
        if ((int)$s['guru_user_id']===$actor) { try{self::owner($db,$s,$actor);return true;}catch(DomainException){} }
        $child=self::one($db,'SELECT status FROM erapor_ppi_pengajuan WHERE sesi_id=?',[$sid]);
        if (!$child || $child['status']==='BELUM_DIAJUKAN') return false;
        return (bool)(self::actor($db,$s,'WALI_KELAS',$actor) || self::actor($db,$s,'KEPALA_SEKOLAH',$actor));
    }

    public static function review(PDO $db,int $sid,int $actor): array
    {
        if (!$db->inTransaction()) return self::read($db,static fn()=>self::review($db,$sid,$actor));
        if (!self::canView($db,$sid,$actor)) throw new DomainException('PPI tidak tersedia untuk akun ini.');
        $s=self::session($db,$sid);$state=self::state($db,$s);
        $code=match($state['status']) {'MENUNGGU_WALI'=>'WALI_KELAS','MENUNGGU_KEPALA'=>'KEPALA_SEKOLAH',default=>null};
        return ['session'=>$s,'ppi'=>$state,'student'=>self::one($db,'SELECT m.nama_lengkap,k.level_kelas,k.nama_kelas FROM murid m JOIN kelas k ON k.id=m.kelas_id WHERE m.id=?',[$s['murid_id']]),
            'period'=>self::one($db,'SELECT nama FROM periode_penilaian WHERE id=?',[$s['periode_id']]),
            'document'=>self::document($db,$s),'form'=>EraporTeacherForm::documentForm($db,$s,self::document($db,$s)),
            'approvals'=>self::all($db,'SELECT kode,nama,disetujui_pada FROM erapor_ppi_persetujuan WHERE sesi_id=? ORDER BY id',[$sid]),
            'approval_code'=>$code,'can_approve'=>$code && self::actor($db,$s,$code,$actor),
            'can_publish'=>$state['status']==='DISETUJUI' && self::actor($db,$s,'KEPALA_SEKOLAH',$actor)];
    }

    public static function inbox(PDO $db,int $actor,bool $done=false): array
    {
        if (!$db->inTransaction()) return self::read($db,static fn()=>self::inbox($db,$actor,$done));
        $out=[];
        foreach (self::all($db,"SELECT s.*,p.status AS ppi_status,m.nama_lengkap,k.nama_kelas FROM erapor_ppi_pengajuan p
            JOIN erapor_sesi s ON s.id=p.sesi_id JOIN murid m ON m.id=s.murid_id JOIN kelas k ON k.id=s.kelas_id
            WHERE s.ppi_terpisah=1 AND p.status<>'BELUM_DIAJUKAN' ORDER BY p.diajukan_pada DESC",[]) as $s) {
            if (!self::canView($db,(int)$s['id'],$actor)) continue;
            $approved=self::one($db,'SELECT 1 FROM erapor_ppi_persetujuan WHERE sesi_id=? AND user_id=?',[$s['id'],$actor]);
            $role=self::one($db,'SELECT r.nama FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?',[$actor]);
            $monitor=($role['nama']??'')==='Superadmin';
            if ($done ? (!$approved && !$monitor) : ($s['ppi_status']==='TERBIT')) continue;
            $isHead=(bool)self::actor($db,$s,'KEPALA_SEKOLAH',$actor);
            if (!$monitor && !$isHead && !self::actor($db,$s,'WALI_KELAS',$actor)) continue;
            if (!$done && $approved && !($s['ppi_status']==='DISETUJUI' && $isHead)) continue;
            $out[]=['id'=>(int)$s['id'],'student'=>$s['nama_lengkap'],'class'=>$s['nama_kelas'],'status'=>$s['ppi_status'],'label'=>self::LABELS[$s['ppi_status']]];
        }
        return $out;
    }

    private static function actor(PDO $db,array $s,string $code,int $actor,bool $lock=false): array|false
    {
        if ($code==='WALI_KELAS') $target=['kode'=>'WALI_KELAS'];
        else $target=self::one($db,"SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id=? AND kode='KEPALA_SEKOLAH'",[$s['id']]);
        return $target?EraporApprovalAccess::actor($db,$target,$s,$actor,$lock):false;
    }
    private static function owner(PDO $db,array $s,int $actor,bool $lock=false): void
    {
        if ((int)$s['guru_user_id']!==$actor) throw new DomainException('PPI bukan milik guru ini.');
        $r=self::one($db,"SELECT u.id FROM users u JOIN karyawan k ON k.id=u.karyawan_id JOIN jabatan j ON j.id=k.jabatan_id
            JOIN kelas_guru_murid a ON a.guru_id=k.id JOIN murid m ON m.id=a.murid_id AND m.kelas_id=a.kelas_id
            WHERE u.id=? AND a.murid_id=? AND a.kelas_id=?
            AND u.is_active=1 AND k.is_active=1 AND j.is_active=1 AND j.nama IN ('Guru Kelas','Guru Shadow')".($lock?' FOR UPDATE':''),[$actor,$s['murid_id'],$s['kelas_id']]);
        if (!$r) throw new DomainException('Penugasan PIC tidak aktif.');
    }
    private static function document(PDO $db,array $s): array
    {
        $r=self::one($db,"SELECT d.id,d.rubrik_id,r.jenis_dokumen FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id
            JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? AND r.jenis_dokumen='PPI'",[$s['id']]);
        if (!$r) throw new DomainException('Dokumen PPI tidak tersedia.');return $r;
    }
    private static function session(PDO $db,int $sid,bool $lock=false): array
    {
        $s=self::one($db,'SELECT * FROM erapor_sesi WHERE id=?'.($lock?' FOR UPDATE':''),[$sid]);
        if (!$s || $s['kondisi']!=='ABK' || !array_key_exists('ppi_terpisah',$s)) throw new DomainException('PPI terpisah belum tersedia.');
        if ($db->inTransaction()) EraporSessionFactory::assertPackage($db,$s);
        $p=self::one($db,'SELECT * FROM periode_penilaian WHERE id=?',[$s['periode_id']]);
        if (!$p) throw new DomainException('Periode PPI tidak tersedia.');
        $p=EraporCalendar::normalize($p);
        if ($p['tahun_ajaran_id']!==(int)$s['tahun_ajaran_id'] || $p['semester']!==$s['semester'] || $p['jenis']!==$s['jenis']) throw new DomainException('Identitas periode PPI berubah.');
        return $s;
    }
    private static function read(PDO $db,callable $work): mixed
    {
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        try{$out=$work();$db->commit();return $out;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    private static function transaction(PDO $db,callable $work): mixed
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        $lock=substr('erapor_migration_'.hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,64);
        $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if ((int)$q->fetchColumn()!==1) throw new RuntimeException('Proses rapor lain sedang berjalan.');
        try{$db->beginTransaction();$result=$work();$db->commit();return $result;}
        catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        finally{$db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
    }
    private static function log(PDO $db,int $sid,int $actor,string $action,?string $old,string $next): void
    {
        $db->prepare('INSERT INTO erapor_ppi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,?,?,?)')->execute([$sid,$actor,$action,$old,$next]);
    }
    private static function indonesianDate(string $date): string
    {
        $d=new DateTimeImmutable($date);$months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return $d->format('j').' '.$months[(int)$d->format('n')-1].' '.$d->format('Y');
    }
    private static function one(PDO $db,string $sql,array $args): array|false {$q=$db->prepare($sql);$q->execute($args);return $q->fetch(PDO::FETCH_ASSOC);}
    private static function all(PDO $db,string $sql,array $args): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
}
