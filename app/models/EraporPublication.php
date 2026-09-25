<?php

/** Prepares one immutable signed PDF artifact inside the final approval transaction. */
final class EraporPublication
{
    private const AGAMA_NARRATIVE_VERSION = 'AGAMA_NARASI_V1';

    public static function prepareWithin(PDO $db,int $sessionId,int $actorId,?DateTimeImmutable $clock=null): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || !$db->inTransaction() || min($sessionId,$actorId)<1) {
            throw new RuntimeException('Publication requires the locked approval transaction.');
        }
        $session=self::one($db,'SELECT * FROM erapor_sesi WHERE id=? FOR UPDATE',[$sessionId]);
        if (!$session || $session['status']!=='MENUNGGU_TTD') throw new DomainException('Sesi tidak siap untuk menyiapkan PDF.');
        $approvals=self::all($db,'SELECT * FROM erapor_sesi_penyetuju WHERE sesi_id=? ORDER BY urutan,id FOR UPDATE',[$sessionId]);
        if (!$approvals || count(array_filter($approvals,fn($r)=>$r['status']==='DISETUJUI'))!==count($approvals)) {
            throw new DomainException('Seluruh persetujuan wajib selesai sebelum membuat PDF.');
        }
        $head=null;
        foreach ($approvals as $row) if ($row['kode']==='KEPALA_SEKOLAH') $head=$row;
        $headEvidence=$head?self::one($db,'SELECT * FROM erapor_persetujuan_snapshot WHERE sesi_penyetuju_id=? AND sesi_id=?',[$head['id'],$sessionId]):null;
        if (!$headEvidence || (int)$headEvidence['user_id']!==$actorId) throw new DomainException('PDF final hanya disiapkan dalam persetujuan Kepala Sekolah.');
        EraporApprove::assertSnapshotScope($db,$session,$approvals);
        foreach ($approvals as $row) {
            $evidence=self::one($db,"SELECT s.log_id FROM erapor_persetujuan_snapshot s JOIN erapor_sesi_log l ON l.id=s.log_id
                AND l.sesi_id=s.sesi_id AND l.aktor_id=s.user_id AND l.aksi='SETUJUI'
                WHERE s.sesi_penyetuju_id=? AND s.sesi_id=?",[$row['id'],$sessionId]);
            if (!$evidence) throw new DomainException('Bukti salah satu persetujuan tidak lengkap.');
        }
        $existing=self::one($db,'SELECT pdf_sha256,sumber_sha256,ukuran_byte,pdf_bytes,manifest,status_kirim FROM erapor_publikasi_pdf WHERE sesi_id=? FOR UPDATE',[$sessionId]);
        if ($existing) {
            $bytes=is_resource($existing['pdf_bytes'])?stream_get_contents($existing['pdf_bytes']):$existing['pdf_bytes'];
            try { $manifest=json_decode($existing['manifest'],true,512,JSON_THROW_ON_ERROR); } catch (Throwable) { $manifest=[]; }
            $manifestValid=is_array($manifest) && (int)($manifest['sesi_id'] ?? 0)===$sessionId
                && ($manifest['tanggal_pengesahan'] ?? null)===$session['tanggal_pengesahan']
                && is_string($manifest['pdf_sha256'] ?? null) && hash_equals($existing['pdf_sha256'],$manifest['pdf_sha256'])
                && is_string($manifest['source_sha256'] ?? null) && hash_equals($existing['sumber_sha256'],$manifest['source_sha256'])
                && is_int($manifest['page_count'] ?? null) && $manifest['page_count']>=1 && $manifest['page_count']<=80;
            if (!is_string($bytes) || strlen($bytes)!==(int)$existing['ukuran_byte'] || !hash_equals($existing['pdf_sha256'],hash('sha256',$bytes)) || !str_starts_with($bytes,'%PDF-') || !$manifestValid) {
                throw new DomainException('Artefak PDF tersimpan tidak lolos pemeriksaan integritas.');
            }
            return ['result'=>'already_prepared','sha256'=>$existing['pdf_sha256'],'size'=>(int)$existing['ukuran_byte'],'delivery_status'=>$existing['status_kirim']];
        }
        $completion=EraporCompleteness::inspect($db,$session);
        if (!$completion['complete']) throw new DomainException('Paket tidak lagi lengkap. PDF tidak disiapkan.');
        $now=($clock ?? new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->setTimezone(new DateTimeZone('Asia/Makassar'));
        $data=self::capture($db,$session,$approvals,$now);
        $rendered=EraporPackagePdfRenderer::renderArtifact($data); $pdf=$rendered['bytes'];
        $sourceJson=json_encode(self::manifestSource($data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $sourceHash=hash('sha256',$sourceJson); $pdfHash=hash('sha256',$pdf);
        $manifestDocuments=array_map(static function(array $d): array {
            $entry=[
                'id'=>(int)$d['id'],'rubrik_id'=>(int)$d['rubrik_id'],'kode'=>$d['kode'],'jenis'=>$d['jenis_dokumen'],
                'versi'=>(int)$d['versi'],'seed_sha256'=>$d['seed_sha256'],
            ];
            if ($d['jenis_dokumen']==='AGAMA') $entry['narrative_template_version']=$d['narrative_version'];
            return $entry;
        },$data['documents']);
        $manifest=[
            'renderer'=>EraporPackagePdfRenderer::version(),'page_count'=>$rendered['pages'],'sesi_id'=>$sessionId,'murid_id'=>(int)$session['murid_id'],
            'periode_id'=>(int)$session['periode_id'],'tanggal_pengesahan'=>$now->format('Y-m-d'),
            'school'=>$data['school']['snapshot'],'documents'=>$manifestDocuments,
            'signers'=>self::signerManifest($data),'source_sha256'=>$sourceHash,'pdf_sha256'=>$pdfHash,
        ];
        $manifestJson=json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $filename='erapor-'.$sessionId.'-'.$now->format('Ymd').'.pdf';
        $insert=$db->prepare("INSERT INTO erapor_publikasi_pdf
            (sesi_id,disiapkan_oleh,renderer_versi,filename,ukuran_byte,pdf_sha256,sumber_sha256,manifest,pdf_bytes,status_kirim)
            VALUES(?,?,?,?,?,?,?,?,?,'SIAP_DIKIRIM')");
        $insert->bindValue(1,$sessionId,PDO::PARAM_INT); $insert->bindValue(2,$actorId,PDO::PARAM_INT);
        $insert->bindValue(3,EraporPackagePdfRenderer::version()); $insert->bindValue(4,$filename);
        $insert->bindValue(5,strlen($pdf),PDO::PARAM_INT); $insert->bindValue(6,$pdfHash); $insert->bindValue(7,$sourceHash);
        $insert->bindValue(8,$manifestJson); $insert->bindValue(9,$pdf,PDO::PARAM_LOB); $insert->execute();
        $date=$now->format('Y-m-d');
        $currentDate=$session['tanggal_pengesahan'] ?? null;
        if ($currentDate!==null && $currentDate!==$date) throw new DomainException('Tanggal pengesahan sesi telah berbeda.');
        if ($currentDate===null) $db->prepare('UPDATE erapor_sesi SET tanggal_pengesahan=? WHERE id=? AND status=\'MENUNGGU_TTD\' AND tanggal_pengesahan IS NULL')->execute([$date,$sessionId]);
        $db->prepare("INSERT INTO erapor_sesi_log(sesi_id,aktor_id,aksi,status_lama,status_baru) VALUES(?,?,'PDF_DISIAPKAN','MENUNGGU_TTD','MENUNGGU_TTD')")
            ->execute([$sessionId,$actorId]);
        return ['result'=>'prepared','sha256'=>$pdfHash,'size'=>strlen($pdf),'delivery_status'=>'SIAP_DIKIRIM'];
    }

    private static function capture(PDO $db,array $session,array $approvals,DateTimeImmutable $now): array
    {
        if ($session['jenis']!=='TENGAH') throw new DomainException('Rapor Akhir Semester belum memiliki rubrik resmi; paket tidak dapat diterbitkan.');
        $period=self::one($db,'SELECT p.*,ta.tahun_awal,ta.tahun_akhir FROM periode_penilaian p JOIN tahun_ajaran ta ON ta.id=p.tahun_ajaran_id WHERE p.id=?',[$session['periode_id']]);
        $student=self::one($db,'SELECT m.id,m.nama_lengkap,m.nama_panggilan,m.nisn,m.tanggal_lahir,m.tempat_lahir,m.status_kondisi,m.jenis_kebutuhan,k.level_kelas,k.nama_kelas
            FROM murid m LEFT JOIN kelas k ON k.id=m.kelas_id WHERE m.id=?',[$session['murid_id']]);
        $school=self::one($db,'SELECT * FROM sekolah ORDER BY id LIMIT 1',[]);
        if (!$period || !$student || !$school) throw new DomainException('Data periode, murid, atau sekolah tidak tersedia.');
        $cal=EraporCalendar::normalize($period);
        if ($cal['jenis']!=='TENGAH' || $cal['semester']!==$session['semester'] || $cal['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id']) throw new DomainException('Periode sesi berubah atau bukan periode Tengah.');
        $address=(string)$school['alamat'];
        $place=self::extractPlace($address);
        if ($place==='') throw new DomainException('Kota pengesahan belum tersedia pada Data Sekolah.');
        $logo=ROOT_PATH.'/public/assets/images/logo-colored.png';
        if (!is_file($logo)) throw new DomainException('Logo Data Sekolah tidak tersedia untuk PDF.');
        $logoData='data:image/png;base64,'.base64_encode(file_get_contents($logo));
        $schoolSnapshot=['id'=>(int)$school['id'],'nama_legal'=>$school['nama_legal'],'nama_komersial'=>$school['nama_komersial'],
            'alamat'=>$address,'tempat_pengesahan'=>$place,'npsn'=>$school['npsn']];
        $student['usia']=self::age($student['tanggal_lahir'],$now);
        $student['nama_kelas']=trim(($student['level_kelas'] ?? '').' '.($student['nama_kelas'] ?? ''));
        $sourceSession=$db->prepare("SELECT s.*,p.semester AS period_semester,p.tipe AS period_type,p.nama AS period_name
            FROM erapor_sesi_dokumen sd JOIN erapor_sesi s ON s.id=sd.sesi_id
            JOIN periode_penilaian p ON p.id=s.periode_id
            WHERE sd.dokumen_id=? AND (s.status='SELESAI' OR s.id=?) ORDER BY s.tahun_ajaran_id,s.semester,p.tipe");
        $docs=self::all($db,'SELECT d.id,d.rubrik_id,r.kode,r.nama,r.jenis_dokumen,r.judul_cetak,r.versi,r.status
            FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id
            JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? ORDER BY sd.urutan',[$session['id']]);
        $expected=['RTS','AGAMA','UMMI','BING']; if ($session['kondisi']==='ABK') $expected[]='PPI';
        if (array_column($docs,'jenis_dokumen')!==$expected) throw new DomainException('Paket dokumen tidak cocok dengan kondisi murid.');
        $documents=[];
        foreach ($docs as $doc) {
            if ($doc['status']!=='terkunci') throw new DomainException('Rubrik yang dipakai belum dikunci.');
            $doc['form']=EraporTeacherForm::documentForm($db,$session,$doc);
            $defs=$doc['form']['definitions']; $periodValues=[]; $tests=[]; $signatures=[]; $signatureCurrent=null;
            $sourceSession->execute([$doc['id'],$session['id']]); $related=$sourceSession->fetchAll(PDO::FETCH_ASSOC);
            foreach ($related as $relatedSession) {
                $relatedForm=EraporTeacherForm::documentForm($db,$relatedSession,$doc);
                $key=match($doc['jenis_dokumen']) {
                    'RTS'=>'TS_'.$relatedSession['period_semester'],
                    'AGAMA'=>$relatedSession['period_type'].'_'.$relatedSession['period_semester'],
                    'UMMI'=>$relatedSession['period_type'],
                    default=>$relatedSession['id']===$session['id']?'CURRENT':null,
                };
                if ($key!==null && ($doc['jenis_dokumen']!=='RTS' || $relatedSession['period_type']==='TENGAH')) {
                    $periodValues[$key]=$relatedForm['values'];
                    if ($doc['jenis_dokumen']==='UMMI') foreach ($relatedForm['values'] as $valueKey=>$value) if (str_starts_with($valueKey,'tes:') && is_array($value)) $tests[$key][]=$value;
                }
                $block=self::signatureBlock($db,$relatedSession,$approvals,(int)$session['id']===(int)$relatedSession['id']?$now:null,$schoolSnapshot['tempat_pengesahan']);
                if ((int)$relatedSession['id']===(int)$session['id']) { $signatureCurrent=$block; $doc['form']=$relatedForm; }
                if ($doc['jenis_dokumen']==='RTS' && $relatedSession['period_type']==='TENGAH') $signatures[$relatedSession['period_semester']]=$block;
            }
            $doc['period_values']=$periodValues; $doc['signature_periods']=$signatures;
            $doc['signature_current']=$signatureCurrent; $doc['signers']=self::all($db,'SELECT * FROM erapor_rubrik_penandatangan WHERE rubrik_id=? ORDER BY urutan',[$doc['rubrik_id']]);
            $doc['tests']=$tests; $doc['items']=$defs['items'] ?? [];
            $doc['show_pra_tk']=!empty($doc['form']['values']['mulai_pra_tk']);
            if ($doc['jenis_dokumen']==='AGAMA') {
                $doc['all_items']=self::all($db,"SELECT i.*,s.lingkup_id,s.huruf AS sub_huruf,s.nama AS sub_nama,s.implisit AS sub_implisit
                    FROM erapor_agama_item i JOIN erapor_agama_sub s ON s.id=i.sub_id
                    JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? AND i.aktif=1
                    ORDER BY CASE i.semester WHEN 'GANJIL' THEN 1 ELSE 2 END,l.urutan,s.urutan,i.urutan",[$doc['rubrik_id']]);
                $seenGenap=false;
                foreach ($doc['all_items'] as $item) {
                    if ($item['semester']==='GENAP') $seenGenap=true;
                    elseif ($seenGenap) throw new DomainException('Urutan capaian Agama tidak konsisten antarsemester.');
                }
                $doc['item_names']=[];
                foreach (self::all($db,'SELECT n.item_id,n.nama FROM erapor_agama_item_nama n JOIN erapor_agama_item i ON i.id=n.item_id JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? ORDER BY n.urutan',[$doc['rubrik_id']]) as $name) $doc['item_names'][(int)$name['item_id']][]=$name['nama'];
                $notes=[]; foreach ($defs['scopes'] ?? [] as $scope) $notes[]=$doc['form']['values']['catatan:'.$scope['id']] ?? '';
                $doc['narrative_version']=self::AGAMA_NARRATIVE_VERSION;
                $doc['narrative']=self::agamaNarrative($student['nama_lengkap'],$period['semester'],$notes);
            }
            if ($doc['jenis_dokumen']==='PPI') $doc['all_columns']=self::all($db,"SELECT * FROM erapor_ppi_kolom WHERE rubrik_id=? AND bagian='C' AND cetak=1 ORDER BY urutan",[$doc['rubrik_id']]);
            // RTS stores the immutable source hash in seed history; newer rubrics also keep JSON.
            $seed=self::one($db,'SELECT source_sha256 FROM erapor_seed_history WHERE rubrik_id=?',[$doc['rubrik_id']]);
            if (!$seed) throw new DomainException('Snapshot sumber rubrik tidak tersedia.');
            $doc['seed_sha256']=$seed['source_sha256'];
            unset($doc['status']); $documents[]=$doc;
        }
        return ['session'=>$session,'period'=>['jenis'=>$cal['jenis'],'semester'=>$cal['semester'],
            'semester_label'=>$cal['semester']==='GANJIL'?'GANJIL':'GENAP',
            'label'=>($cal['jenis']==='TENGAH'?'Tengah':'Akhir').' Semester '.($cal['semester']==='GANJIL'?'Ganjil':'Genap'),
            'tahun_label'=>(int)$period['tahun_awal'].'/'.(int)$period['tahun_akhir']],
            'student'=>$student,'school'=>['snapshot'=>$schoolSnapshot,'logo_data_uri'=>$logoData],
            'published_at'=>$now->format('Y-m-d H:i:s'),'published_date'=>self::indonesianDate($now),
            'documents'=>$documents];
    }

    private static function extractPlace(string $address): string
    {
        $address=trim(preg_replace('/\s+/u',' ',$address)??$address);
        if ($address==='') return '';
        if (preg_match('/\b(?:Kota|Kabupaten)\s+([^,;]+)/iu',$address,$match)===1) {
            $place=trim($match[1]);
        } else {
            $place='';
            foreach (array_reverse(array_map('trim',explode(',',$address))) as $segment) {
                $candidate=trim(preg_replace('/\b\d{5,6}\b/u','',$segment)??$segment," \t\n\r\0\x0B-");
                if ($candidate!=='' && !preg_match('/^(?:kec(?:amatan)?\.?|prov(?:insi)?\.?)\b/iu',$candidate)) {
                    $place=$candidate;
                    break;
                }
            }
        }
        $place=trim(preg_replace('/\s+\d{5,6}\s*$/u','',$place)??$place);
        if ($place==='' || mb_strlen($place)>100 || preg_match('/[\r\n<>]/u',$place)) return '';
        return $place;
    }

    private static function signatureBlock(PDO $db,array $session,array $currentApprovals,?DateTimeImmutable $currentDate,string $place): array
    {
        $signers=[]; $teacher=self::one($db,'SELECT guru_nama AS nama,guru_nuptk AS nuptk,guru_ttd_png AS ttd_png,guru_ttd_sha256 AS ttd_sha256,guru_ttd_disetujui_pada AS consent_at FROM erapor_sesi_penerimaan WHERE sesi_id=?',[$session['id']]);
        if ($teacher) $signers['GURU_KELAS']=self::normalizeSigner($teacher);
        $approvals=self::all($db,'SELECT a.id,a.sesi_id,a.kode,s.nama,s.nuptk,s.ttd_png,s.ttd_sha256,s.ttd_disetujui_pada AS consent_at
            FROM erapor_sesi_penyetuju a JOIN erapor_persetujuan_snapshot s ON s.sesi_penyetuju_id=a.id AND s.sesi_id=a.sesi_id WHERE a.sesi_id=? AND a.status=\'DISETUJUI\'',[$session['id']]);
        foreach ($approvals as $row) {
            if ((int)($row['sesi_id'] ?? 0)!==(int)$session['id']) continue;
            $role=match($row['kode'] ?? '') {'KEPALA_SEKOLAH'=>'KEPALA_SEKOLAH','KOORDINATOR_QURAN'=>'KOORDINATOR_QURAN','KOORDINATOR_BING'=>'KOORDINATOR_BING',default=>null};
            if ($role) $signers[$role]=self::normalizeSigner($row);
        }
        $date=$currentDate?self::indonesianDate($currentDate):(!empty($session['tanggal_pengesahan'])?self::indonesianDate(new DateTimeImmutable($session['tanggal_pengesahan'])):null);
        return ['signers'=>$signers,'tempat'=>$date?$place:'','tanggal'=>$date];
    }

    private static function normalizeSigner(array $row): array
    {
        $png=$row['ttd_png'] ?? null; $hash=$row['ttd_sha256'] ?? null;
        if ($png!==null && (!is_string($png) || strlen($png)>2097152 || !hash_equals((string)$hash,hash('sha256',$png)) || !str_starts_with($png,"\x89PNG\r\n\x1a\n"))) {
            throw new DomainException('Snapshot tanda tangan tidak lolos pemeriksaan integritas.');
        }
        return ['nama'=>$row['nama'] ?? '', 'nuptk'=>$row['nuptk'] ?? null,
            'ttd_data_uri'=>$png!==null?'data:image/png;base64,'.base64_encode($png):null,
            'ttd_sha256'=>$hash,'consent_at'=>$row['consent_at'] ?? null];
    }

    private static function agamaNarrative(string $name,string $semester,array $notes): string
    {
        if (count($notes)!==6) throw new DomainException('Enam catatan Agama wajib tersedia untuk narasi cetak.');
        foreach ($notes as $note) if (!is_string($note) || EraporSessionPolicy::isBlank($note)) throw new DomainException('Narasi Agama belum lengkap.');
        $semesterText=$semester==='GANJIL'?'ganjil':'genap';
        return 'Pencapaian perkembangan Ananda '.$name.' di semester '.$semesterText.' ini secara umum berkembang sesuai harapan. Kini Ananda '.$name.' telah menunjukkan kemajuan, seperti aqidah tauhid yaitu '.$notes[0].'. Fiqih ibadah yaitu '.$notes[1].'. Akhlaq yaitu '.$notes[2].'. Al-Qur\'an dan hadits yaitu '.$notes[3].'. Asmaul husna yaitu '.$notes[4].' dan kisah sahabat yaitu '.$notes[5].'. Secara keseluruhan, Ananda mulai memahami nilai-nilai Islam yang terkandung di dalamnya dan berusaha menerapkannya dalam kegiatan sehari-hari. Dengan dukungan dari guru dan orang tua, Ananda diharapkan semakin tumbuh menjadi anak yang mengenal dan mencintai Allah, mencintai Rasulullah, serta terbiasa menjalankan ajaran Islam dengan penuh kesadaran dan kegembiraan. Semangat, Ananda '.$name.'!';
    }

    private static function age(string $birthDate,DateTimeImmutable $on): string
    {
        try { $birth=new DateTimeImmutable($birthDate); } catch (Throwable) { return '-'; }
        if ($birth>$on) return '-'; $diff=$birth->diff($on);
        return $diff->y.' tahun '.$diff->m.' bulan';
    }
    private static function indonesianDate(DateTimeImmutable $date): string
    {
        $months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return (int)$date->format('j').' '.$months[(int)$date->format('n')-1].' '.$date->format('Y');
    }
    private static function manifestSource(array $data): array
    {
        return ['renderer'=>EraporPackagePdfRenderer::version(),'session'=>$data['session']['id'],
            'period'=>$data['period'],'student'=>$data['student'],'school'=>$data['school']['snapshot'],
            'documents'=>array_map(static fn($d)=>['id'=>$d['id'],'rubrik_id'=>$d['rubrik_id'],'kode'=>$d['kode'],'versi'=>$d['versi'],'seed_sha256'=>$d['seed_sha256'],'period_values'=>$d['period_values'],'form'=>$d['form'],'narrative_template_version'=>$d['narrative_version'] ?? null,'narrative'=>$d['narrative'] ?? null],$data['documents']),
            'published_at'=>$data['published_at']];
    }
    private static function signerManifest(array $data): array
    {
        $out=[]; foreach ($data['documents'] as $doc) foreach (['signature_current','signature_periods'] as $key) {
            $blocks=$key==='signature_current'?['CURRENT'=>$doc[$key] ?? null]:($doc[$key] ?? []);
            foreach ($blocks as $period=>$block) if (is_array($block)) foreach ($block['signers'] ?? [] as $role=>$signer) {
                $out[]=['document'=>$doc['kode'],'period'=>$period,'role'=>$role,'name'=>$signer['nama'] ?? '',
                    'nuptk'=>$signer['nuptk'] ?? null,'signature_sha256'=>$signer['ttd_sha256'] ?? null,'consent_at'=>$signer['consent_at'] ?? null];
            }
        }
        return $out;
    }
    private static function one(PDO $db,string $sql,array $args): array|false { $q=$db->prepare($sql); $q->execute($args); return $q->fetch(PDO::FETCH_ASSOC); }
    private static function all(PDO $db,string $sql,array $args): array { $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC); }
}
