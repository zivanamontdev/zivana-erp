<?php

/** Legacy recovery is accepted ONLY after an exact original source hash and signer-manifest match.
 * This never edits business data and never treats current profiles as historical signature evidence.
 */
final class EraporHistoricalPdfSource
{
    public static function recover(PDO $db,int $sid,array $original): array
    {
        if ($original['kind']!=='RAPOR') throw new DomainException('PPI_SOURCE_NOT_ARCHIVED');
        $m=$original['manifest'];$version=$original['renderer'];
        if (!preg_match('/^erapor-print-v([5-9]|10|11)$/D',$version,$v)) throw new DomainException('HISTORICAL_RENDERER_UNSUPPORTED');
        $date=$m['tanggal_pengesahan']??'';
        $clock=is_string($date)?DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Asia/Makassar')):false;
        if (!$clock || $clock->format('Y-m-d')!==$date || !is_array($m['school']??null) || !is_array($m['signers']??null)) throw new DomainException('HISTORICAL_MANIFEST_INCOMPLETE');
        $rows=self::all($db,"SELECT a.kode,a.status,s.user_id,s.log_id FROM erapor_sesi_penyetuju a
            LEFT JOIN erapor_persetujuan_snapshot s ON s.sesi_penyetuju_id=a.id AND s.sesi_id=a.sesi_id
            LEFT JOIN erapor_sesi_log l ON l.id=s.log_id AND l.sesi_id=a.sesi_id AND l.aktor_id=s.user_id AND l.aksi='SETUJUI'
            WHERE a.sesi_id=? AND (a.status<>'DISETUJUI' OR s.log_id IS NULL OR l.id IS NULL)",[$sid]);
        if ($rows || !self::all($db,"SELECT 1 FROM erapor_sesi_penyetuju a JOIN erapor_persetujuan_snapshot s ON s.sesi_penyetuju_id=a.id AND s.sesi_id=a.sesi_id
            WHERE a.sesi_id=? AND a.kode='KEPALA_SEKOLAH' AND s.user_id=?",[$sid,$original['actor']])) throw new DomainException('HISTORICAL_APPROVAL_INCOMPLETE');
        // Cut off later published semesters; never add values that did not exist in the original package.
        $p=EraporPublication::historicalPackage($db,$sid,$clock->setTime(12,0),$original['created_epoch']);
        // MySQL's JSON column reorders object keys; the original source digest used this fixed PHP order.
        $school=[];
        foreach(['id','nama_legal','nama_komersial','alamat','tempat_pengesahan','npsn'] as $key) {
            if(!array_key_exists($key,$m['school']))throw new DomainException('HISTORICAL_MANIFEST_INCOMPLETE');
            $school[$key]=$m['school'][$key];
        }
        $p['school']['snapshot']=$school;
        foreach ($p['documents'] as &$doc) {
            $targets=['signature_current'=>['CURRENT'=>$doc['signature_current']??null],'signature_periods'=>$doc['signature_periods']??[]];
            foreach ($targets as $key=>$blocks) {
                foreach ($blocks as $period=>&$block) {
                    if (!is_array($block)) continue;
                    // An older renderer may deliberately have omitted the teacher role. Do not introduce it now.
                    foreach (array_keys($block['signers']??[]) as $role) {
                        $found=false;
                        foreach ($m['signers'] as $s) if (($s['document']??null)===$doc['kode'] && ($s['period']??null)===$period && ($s['role']??null)===$role) {$found=true;break;}
                        if (!$found) unset($block['signers'][$role]);
                    }
                    $block['tempat']=!empty($block['tanggal'])?($m['school']['tempat_pengesahan']??''):'';
                }
                unset($block);
                $doc[$key]=$key==='signature_current'?($blocks['CURRENT']??null):$blocks;
            }
        }
        unset($doc);
        // Endorsement dates of older RTS semesters are not part of the old source projection.
        // Cross-check their immutable artifact manifests instead of trusting a mutable session date.
        foreach($p['documents'] as $doc) foreach($doc['signature_periods']??[] as $semester=>$block) {
            $expected=$date;
            if($semester!==$p['session']['semester']) {
                $related=self::all($db,"SELECT s.id FROM erapor_sesi_dokumen sd JOIN erapor_sesi s ON s.id=sd.sesi_id
                    JOIN erapor_publikasi_pdf a ON a.sesi_id=s.id WHERE sd.dokumen_id=? AND s.semester=? AND s.jenis='TENGAH' AND UNIX_TIMESTAMP(a.dibuat_pada)<=?",[$doc['id'],$semester,$original['created_epoch']]);
                if(count($related)!==1)throw new DomainException('HISTORICAL_ENDORSEMENT_DATE_UNVERIFIED');
                $prior=EraporPdfRevision::original($db,(int)$related[0]['id'],'RAPOR');$expected=$prior['manifest']['tanggal_pengesahan']??'';
            }
            if(!is_string($expected)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$expected))throw new DomainException('HISTORICAL_ENDORSEMENT_DATE_UNVERIFIED');
            $d=new DateTimeImmutable($expected,new DateTimeZone('Asia/Makassar'));
            $months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            $label=(int)$d->format('j').' '.$months[(int)$d->format('n')-1].' '.$d->format('Y');
            if(($block['tanggal']??null)!==$label)throw new DomainException('HISTORICAL_ENDORSEMENT_DATE_MISMATCH');
        }
        if (self::sorted(EraporPublication::signerManifest($p))!==self::sorted($m['signers'])) throw new DomainException('HISTORICAL_SIGNERS_MISMATCH');
        $descriptors=array_map(static function($d){$o=['id'=>(int)$d['id'],'rubrik_id'=>(int)$d['rubrik_id'],'kode'=>$d['kode'],'jenis'=>$d['jenis_dokumen'],'versi'=>(int)$d['versi'],'seed_sha256'=>$d['seed_sha256']];if($d['jenis_dokumen']==='AGAMA')$o['narrative_template_version']=$d['narrative_version'];return $o;},$p['documents']);
        if (self::canonical($descriptors)!==self::canonical($m['documents']??[])) throw new DomainException('HISTORICAL_DOCUMENTS_MISMATCH');
        $candidates=[$p];
        // A student can move class after publication. The session retains the original class ID.
        // It is only a candidate: the complete original source digest MUST still match exactly.
        $classes=self::all($db,'SELECT level_kelas,nama_kelas FROM kelas WHERE id=?',[$p['session']['kelas_id']]);
        if($classes) {
            $class=$classes[0];$label=trim(($class['level_kelas']??'').' '.($class['nama_kelas']??''));
            if(($p['student']['level_kelas']??null)!==$class['level_kelas'] || $p['student']['nama_kelas']!==$label) {
                $historical=$p;$historical['student']['level_kelas']=$class['level_kelas'];$historical['student']['nama_kelas']=$label;$candidates[]=$historical;
            }
        }
        foreach($candidates as $candidate) {
            $projection=EraporPublication::manifestSource($candidate);$projection['renderer']=$version;
            if ((int)$v[1]<9) unset($projection['exclude_ppi']);
            if ((int)$v[1]<8) unset($projection['watermark_sha256']);
            unset($projection['published_at']);
            $prefix=substr(self::json($projection),0,-1).',"published_at":';
            $context=hash_init('sha256');hash_update($context,$prefix);
            // The old manifest omitted the timestamp. Search only its approval date, hashing the large prefix once.
            // No searching/guessing names, assessment values, signatures or dates outside that recorded day.
            for ($h=0;$h<24;$h++) for ($min=0;$min<60;$min++) for ($sec=0;$sec<60;$sec++) {
                $stamp=$date.' '.sprintf('%02d:%02d:%02d',$h,$min,$sec);
                $try=hash_copy($context);hash_update($try,'"'.$stamp.'"}');
                if (hash_equals($original['source_sha'],hash_final($try))) {
                    $candidate['published_at']=$stamp;
                    $candidate['session']['tanggal_pengesahan']=$date;
                    return $candidate;
                }
            }
        }
        throw new DomainException('HISTORICAL_SOURCE_HASH_MISMATCH');
    }

    private static function sorted(array $rows): array
    {
        // PDO may stringify IDs, but signer identities/consent/hash must be byte-for-byte equal.
        usort($rows,static fn($a,$b)=>strcmp(($a['document']??'').'|'.($a['period']??'').'|'.($a['role']??''),($b['document']??'').'|'.($b['period']??'').'|'.($b['role']??'')));
        return self::canonical($rows);
    }
    private static function canonical(array $value): array
    {
        if(!array_is_list($value))ksort($value,SORT_STRING);
        foreach($value as &$v)if(is_array($v))$v=self::canonical($v);unset($v);
        return $value;
    }
    private static function json(array $data): string {return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function all(PDO $db,string $sql,array $args): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
}
