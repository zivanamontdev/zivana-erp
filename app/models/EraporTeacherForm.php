<?php

/** Authorized read model only. No session creation, Ummi initialization, signature bytes or writes.
 * Future HTTP adapter supplies authenticated actor and applies module RBAC.
 */
final class EraporTeacherForm
{
    public static function read(PDO $db,int $sessionId,int $actorId,?DateTimeImmutable $clock=null): array
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $db->inTransaction()) throw new RuntimeException('Dedicated MySQL connection required.');
        if (min($sessionId,$actorId)<1) throw new DomainException('Identitas sesi tidak valid.');
        // All statements observe one consistent version, even if autosave commits concurrently.
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
        try {
            $session=self::rows($db,'SELECT * FROM erapor_sesi WHERE id=?',[$sessionId])[0] ?? null;
            if (!$session || (int)$session['guru_user_id']!==$actorId) throw new DomainException('Sesi bukan milik guru ini.');
            $student=self::rows($db,"SELECT m.id,m.nama_lengkap,kls.level_kelas,kls.nama_kelas FROM murid m
                JOIN kelas kls ON kls.id=m.kelas_id JOIN kelas_guru_murid a ON a.murid_id=m.id AND a.kelas_id=m.kelas_id
                JOIN karyawan k ON k.id=a.guru_id JOIN jabatan j ON j.id=k.jabatan_id JOIN users u ON u.karyawan_id=k.id
                WHERE m.id=? AND m.kelas_id=? AND u.id=? AND u.is_active=1 AND k.is_active=1 AND j.is_active=1
                AND j.nama IN ('Guru Kelas','Guru Shadow')",[$session['murid_id'],$session['kelas_id'],$actorId])[0] ?? null;
            if (!$student) throw new DomainException('Penugasan guru tidak aktif atau berubah.');
            $period=self::rows($db,'SELECT p.*,t.tahun_awal,t.tahun_akhir FROM periode_penilaian p JOIN tahun_ajaran t ON t.id=p.tahun_ajaran_id WHERE p.id=?',[$session['periode_id']])[0] ?? null;
            if (!$period) throw new DomainException('Periode tidak tersedia.');
            $periodRow=$period;
            $period=EraporCalendar::normalize($periodRow);
            $period['nama']=(string)$periodRow['nama'];
            $period['tahun_label']=(int)$periodRow['tahun_awal'].'/'.(int)$periodRow['tahun_akhir'];
            if ($period['tahun_ajaran_id']!==(int)$session['tahun_ajaran_id'] || $period['semester']!==$session['semester'] || $period['jenis']!==$session['jenis']) throw new DomainException('Identitas periode berubah.');
            $completion=EraporCompleteness::inspect($db,$session);
            $today=($clock ?? new DateTimeImmutable('now',new DateTimeZone('Asia/Makassar')))->setTimezone(new DateTimeZone('Asia/Makassar'))->format('Y-m-d');
            if (!in_array($session['status'],EraporSessionPolicy::STATES,true)) throw new DomainException('Status sesi tidak valid.');
            $locked=!in_array($session['status'],['BELUM_DIISI','TELAH_DIISI'],true);
            $reason=$locked?'STATUS_TERKUNCI':($today>$period['akhir_periode']?'TENGGAT_BERAKHIR':null);
            $docs=self::rows($db,'SELECT d.id,d.rubrik_id,r.kode,r.nama,r.jenis_dokumen,sd.urutan FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_rubrik r ON r.id=d.rubrik_id WHERE sd.sesi_id=? ORDER BY sd.urutan',[$sessionId]);
            foreach ($docs as &$doc) $doc['form']=self::documentForm($db,$session,$doc);
            unset($doc);
            $result=['session'=>['id'=>$sessionId,'status'=>$session['status'],'kondisi'=>$session['kondisi']],
                'student'=>$student,'period'=>$period,'documents'=>$docs,'completion'=>$completion,
                'capabilities'=>['can_edit'=>$reason===null,'read_only_reason'=>$reason,
                    'can_confirm_filled'=>$session['status']==='BELUM_DIISI' && $completion['complete'],
                    'can_confirm_reception'=>$session['status']==='TELAH_DIISI' && $completion['complete']]];
            $db->commit(); return $result;
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    /** Reusable read-only rubric projection; callers must already authorize the session/document scope. */
    public static function documentForm(PDO $db,array $s,array $d): array
    {
        $rid=(int)$d['rubrik_id']; $args=[$s['id'],$d['id'],$rid]; $defs=[]; $values=[];
        // Identifiers below are fixed server-owned lists, never request input.
        $tables=match ($d['jenis_dokumen']) {
            'RTS'=>['areas'=>['erapor_rubrik_area','urutan'],'scale'=>['erapor_skala_nilai','urutan']],
            'BING'=>['items'=>['erapor_bing_indikator','urutan'],'comments'=>['erapor_bing_komentar','urutan'],'scale'=>['erapor_bing_skala','urutan_tampil']],
            'PPI'=>['aspects'=>['erapor_ppi_aspek','urutan'],'columns'=>['erapor_ppi_kolom','bagian,urutan']],
            'UMMI'=>['volumes'=>['erapor_ummi_jilid','urutan'],'scale'=>['erapor_skala_huruf','peringkat']],
            'AGAMA'=>['scopes'=>['erapor_agama_lingkup','urutan'],'scale'=>['erapor_agama_pilihan','urutan'],'stages'=>['erapor_agama_tahapan','urutan']],
            default=>throw new DomainException('Rubrik belum didukung.'),
        };
        foreach ($tables as $key=>[$table,$order]) $defs[$key]=self::rows($db,'SELECT * FROM '.$table.' WHERE rubrik_id=? ORDER BY '.$order,[$rid]);
        switch ($d['jenis_dokumen']) {
            case 'RTS':
                $defs['subareas']=self::rows($db,'SELECT s.* FROM erapor_rubrik_sub_area s JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? ORDER BY a.urutan,s.urutan',[$rid]);
                $defs['groups']=self::rows($db,'SELECT g.* FROM erapor_rubrik_grup g JOIN erapor_rubrik_sub_area s ON s.id=g.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? ORDER BY a.urutan,s.urutan,g.urutan',[$rid]);
                $defs['items']=self::rows($db,'SELECT i.* FROM erapor_rubrik_indikator i JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id WHERE a.rubrik_id=? AND i.aktif=1 ORDER BY a.urutan,s.urutan,i.urutan',[$rid]);
                foreach (self::rows($db,'SELECT n.indikator_id,sc.nilai FROM erapor_rts_nilai n JOIN erapor_skala_nilai sc ON sc.id=n.skala_id AND sc.rubrik_id=? WHERE n.sesi_id=? AND n.dokumen_id=?',[$rid,$s['id'],$d['id']]) as $v) $values['nilai:'.$v['indikator_id']]=(int)$v['nilai'];
                break;
            case 'BING':
                foreach (self::rows($db,'SELECT indikator_id,pilihan_kode FROM erapor_bing_nilai WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args) as $v) $values['nilai:'.$v['indikator_id']]=$v['pilihan_kode'];
                foreach (self::rows($db,'SELECT komentar_id,isi FROM erapor_bing_isian WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args) as $v) $values['komentar:'.$v['komentar_id']]=$v['isi'];
                break;
            case 'PPI':
                $defs['aspects']=array_values(array_filter($defs['aspects'],fn($r)=>(bool)$r['aktif']));
                $defs['columns']=array_values(array_filter($defs['columns'],fn($r)=>(bool)$r['diisi_di_sesi']));
                foreach (self::rows($db,'SELECT n.aspek_id,n.kolom_id,n.isi FROM erapor_ppi_isian n JOIN erapor_ppi_kolom c ON c.id=n.kolom_id AND c.rubrik_id=n.rubrik_id WHERE n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=? AND c.diisi_di_sesi=1',$args) as $v) $values[$v['aspek_id'].':'.$v['kolom_id']]=$v['isi'];
                break;
            case 'AGAMA':
                $defs['subscopes']=self::rows($db,'SELECT s.* FROM erapor_agama_sub s JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? ORDER BY l.urutan,s.urutan',[$rid]);
                $defs['items']=self::rows($db,'SELECT i.* FROM erapor_agama_item i JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? AND i.semester=? AND i.aktif=1 ORDER BY l.urutan,s.urutan,i.urutan',[$rid,$s['semester']]);
                $defs['names']=self::rows($db,'SELECT n.* FROM erapor_agama_item_nama n JOIN erapor_agama_item i ON i.id=n.item_id JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id WHERE l.rubrik_id=? AND i.semester=? AND i.aktif=1 ORDER BY l.urutan,s.urutan,i.urutan,n.urutan',[$rid,$s['semester']]);
                foreach (self::rows($db,'SELECT n.item_id,p.kolom_cetak FROM erapor_agama_nilai n JOIN erapor_agama_pilihan p ON p.rubrik_id=n.rubrik_id AND p.tahapan_id=n.tahapan_id AND p.subtingkat_id <=> n.subtingkat_id JOIN erapor_agama_item i ON i.id=n.item_id WHERE n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=? AND i.semester=?',[...$args,$s['semester']]) as $v) $values['nilai:'.$v['item_id']]=$v['kolom_cetak'];
                foreach (self::rows($db,'SELECT lingkup_id,isi FROM erapor_agama_catatan WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args) as $v) $values['catatan:'.$v['lingkup_id']]=$v['isi'];
                break;
            case 'UMMI':
                $defs['items']=self::rows($db,'SELECT m.*,j.nama AS jilid_nama,j.urutan AS jilid_urutan,j.hanya_pra_tk FROM erapor_ummi_materi m JOIN erapor_ummi_jilid j ON j.id=m.jilid_id WHERE j.rubrik_id=? AND m.aktif=1 ORDER BY j.urutan,m.urutan',[$rid]);
                foreach (self::rows($db,'SELECT materi_id,nilai FROM erapor_ummi_bacaan WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args) as $v) $values['bacaan:'.$v['materi_id']]=$v['nilai'];
                $values['catatan']=self::rows($db,'SELECT isi FROM erapor_ummi_catatan WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args)[0]['isi'] ?? null;
                $flag=self::rows($db,'SELECT mulai_pra_tk FROM erapor_ummi_periode WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args)[0] ?? null;
                $values['mulai_pra_tk']=$flag===null?null:(bool)$flag['mulai_pra_tk'];
                $defs['initialization_required']=$flag===null; // Never initialize on GET; no guessed inherited value.
                foreach (self::rows($db,'SELECT token,urutan,tanggal_tes,jilid,nilai FROM erapor_ummi_tes WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=? ORDER BY urutan,token',$args) as $v) $values['tes:'.$v['token']]=['urutan'=>(int)$v['urutan'],'tanggal_tes'=>$v['tanggal_tes'],'jilid'=>$v['jilid'],'nilai'=>$v['nilai']];
                break;
        }
        return ['definitions'=>$defs,'values'=>$values];
    }
    private static function rows(PDO $db,string $sql,array $args): array
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
