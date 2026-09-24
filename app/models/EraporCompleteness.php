<?php

/** Internal transaction-scoped read. Caller owns authorization, session lock and transaction. */
final class EraporCompleteness
{
    public static function inspect(PDO $db,array $session): array
    {
        EraporSessionFactory::assertPackage($db,$session);
        $q=$db->prepare('SELECT d.id,d.rubrik_id,d.semester,r.jenis_dokumen,r.cakupan,r.status,r.jenis_periode,r.khusus_abk,sd.wajib,sd.urutan
            FROM erapor_sesi_dokumen sd JOIN erapor_dokumen d ON d.id=sd.dokumen_id JOIN erapor_rubrik r ON r.id=d.rubrik_id
            WHERE sd.sesi_id=? ORDER BY sd.urutan');
        $q->execute([$session['id']]); $docs=$q->fetchAll(PDO::FETCH_ASSOC);
        // Incomplete/unsupported package must never become complete just because its rows are filled.
        $expected=[$session['jenis']==='TENGAH'?'RTS':'RAS','AGAMA','UMMI','BING'];
        if ($session['kondisi']==='ABK') $expected[]='PPI';
        if (array_column($docs,'jenis_dokumen')!==$expected) throw new DomainException('Komposisi paket wajib tidak lengkap.');
        $result=[]; $complete=true;
        foreach ($docs as $doc) {
            $type=$doc['jenis_dokumen']; $rid=(int)$doc['rubrik_id'];
            $annual=in_array($type,['RTS','RAS','AGAMA'],true);
            if (!$doc['wajib'] || $doc['status']!=='terkunci'
                || $doc['cakupan']!==($annual?'TAHUNAN':'SEMESTER')
                || $doc['semester']!==($annual?'TAHUNAN':$session['semester'])
                || (bool)$doc['khusus_abk']!==($type==='PPI')
                || $doc['jenis_periode']!==(in_array($type,['RTS','RAS'],true)?$session['jenis']:null)) {
                throw new DomainException('Metadata dokumen paket tidak sesuai.');
            }
            $args=[$session['id'],$doc['id'],$rid];
            $rows=[];
            if ($type==='RTS') {
                $rows=self::rows($db,"SELECT CONCAT('nilai:',i.id) AS field,i.tujuan AS label,sc.nilai AS value
                    FROM erapor_rubrik_indikator i JOIN erapor_rubrik_sub_area s ON s.id=i.sub_area_id JOIN erapor_rubrik_area a ON a.id=s.area_id
                    LEFT JOIN erapor_rts_nilai n ON n.indikator_id=i.id AND n.sesi_id=? AND n.dokumen_id=?
                    LEFT JOIN erapor_skala_nilai sc ON sc.id=n.skala_id AND sc.rubrik_id=a.rubrik_id
                    WHERE a.rubrik_id=? AND i.aktif=1 ORDER BY i.id",$args);
            } elseif ($type==='BING') {
                $rows=self::rows($db,"SELECT CONCAT('nilai:',i.id) AS field,i.label_cetak AS label,sc.kode AS value
                    FROM erapor_bing_indikator i LEFT JOIN erapor_bing_nilai n ON n.indikator_id=i.id AND n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=i.rubrik_id
                    LEFT JOIN erapor_bing_skala sc ON sc.rubrik_id=i.rubrik_id AND sc.kode=n.pilihan_kode
                    WHERE i.rubrik_id=? AND i.wajib=1 ORDER BY i.urutan",$args);
                $rows=array_merge($rows,self::rows($db,"SELECT CONCAT('komentar:',i.id) AS field,i.label_cetak AS label,n.isi AS value
                    FROM erapor_bing_komentar i LEFT JOIN erapor_bing_isian n ON n.komentar_id=i.id AND n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=i.rubrik_id
                    WHERE i.rubrik_id=? AND i.wajib=1 ORDER BY i.urutan",$args));
            } elseif ($type==='PPI') {
                $rows=self::rows($db,"SELECT CONCAT(a.id,':',c.id) AS field,CONCAT(a.nama,' - ',c.label_cetak) AS label,n.isi AS value
                    FROM erapor_ppi_aspek a JOIN erapor_ppi_kolom c ON c.rubrik_id=a.rubrik_id
                    LEFT JOIN erapor_ppi_isian n ON n.aspek_id=a.id AND n.kolom_id=c.id AND n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=a.rubrik_id
                    WHERE a.rubrik_id=? AND a.aktif=1 AND c.wajib=1 AND c.diisi_di_sesi=1 ORDER BY a.urutan,c.bagian,c.urutan",$args);
            } elseif ($type==='UMMI') {
                $note=self::rows($db,'SELECT isi FROM erapor_ummi_catatan WHERE sesi_id=? AND dokumen_id=? AND rubrik_id=?',$args);
                $rows=[['field'=>'catatan','label'=>'Catatan Guru','value'=>$note[0]['isi'] ?? null]];
            } elseif ($type==='AGAMA') {
                $rows=self::rows($db,"SELECT CONCAT('nilai:',i.id) AS field,i.teks AS label,p.kolom_cetak AS value
                    FROM erapor_agama_item i JOIN erapor_agama_sub s ON s.id=i.sub_id JOIN erapor_agama_lingkup l ON l.id=s.lingkup_id
                    LEFT JOIN erapor_agama_nilai n ON n.item_id=i.id AND n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=l.rubrik_id
                    LEFT JOIN erapor_agama_pilihan p ON p.rubrik_id=l.rubrik_id AND p.tahapan_id=n.tahapan_id AND p.tahapan_kode=n.tahapan_kode
                        AND p.subtingkat_id <=> n.subtingkat_id AND p.subtingkat_kode <=> n.subtingkat_kode
                    WHERE l.rubrik_id=? AND i.semester=? AND i.aktif=1 ORDER BY i.id",[...$args,$session['semester']]);
                $rows=array_merge($rows,self::rows($db,"SELECT CONCAT('catatan:',l.id) AS field,l.nama AS label,n.isi AS value
                    FROM erapor_agama_lingkup l LEFT JOIN erapor_agama_catatan n ON n.lingkup_id=l.id AND n.sesi_id=? AND n.dokumen_id=? AND n.rubrik_id=l.rubrik_id
                    WHERE l.rubrik_id=? AND l.catatan_wajib=1 ORDER BY l.urutan",$args));
            } else throw new DomainException('Rubrik belum didukung: '.$type);
            if (!$rows) throw new DomainException('Definisi wajib kosong: '.$type);
            $missing=[];
            foreach ($rows as $row) if ($row['value']===null || EraporSessionPolicy::isBlank((string)$row['value'])) $missing[]=['key'=>$row['field'],'label'=>$row['label']];
            $done=$missing===[]; $complete=$complete && $done;
            $result[]=['dokumen_id'=>(int)$doc['id'],'jenis'=>$type,'required'=>count($rows),'filled'=>count($rows)-count($missing),'complete'=>$done,'missing'=>$missing];
        }
        return ['complete'=>$complete,'documents'=>$result];
    }
    private static function rows(PDO $db,string $sql,array $args): array
    {
        $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
