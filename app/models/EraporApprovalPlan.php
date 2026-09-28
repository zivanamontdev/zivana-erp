<?php

/** Pure validation/projection for reception, not approval authority or persistence.
 * Input rows must come from trusted configuration. No automatic account selection.
 * Snapshot output records document scope so later config changes cannot broaden it.
 */
final class EraporApprovalPlan
{
    public static function build(array $documents,array $flows,array $scopeRows): array
    {
        if (!$documents) throw new DomainException('Paket dokumen kosong.');
        $docs=[]; $rubrics=[]; $types=[];
        foreach ($documents as $d) {
            foreach (['id','rubrik_id'] as $key) self::positive($d[$key] ?? null);
            if (!in_array($d['jenis_dokumen'] ?? null,['RTS','RAS','AGAMA','UMMI','BING','PPI'],true)
                || isset($docs[(int)$d['id']]) || isset($rubrics[(int)$d['rubrik_id']]) || isset($types[$d['jenis_dokumen']])) throw new DomainException('Identitas dokumen duplikat/tidak valid.');
            $docs[(int)$d['id']]=$d; $rubrics[(int)$d['rubrik_id']]=$d; $types[$d['jenis_dokumen']]=true;
        }
        $byId=[]; $codes=[];
        // V2 (ada tahap Wali Kelas): Koordinator (1) -> Wali Kelas (2) -> Kepala Sekolah (3); Koordinator Agama/Al-Qur'an
        // mencakup Ummi + Agama. V1 (snapshot sesi lama tanpa Wali Kelas): Koordinator (1) -> Kepala Sekolah (2), Quran = Ummi.
        $v2=in_array('WALI_KELAS',array_column($flows,'kode'),true);
        $ranks=$v2?['KOORDINATOR_QURAN'=>1,'KOORDINATOR_BING'=>1,'WALI_KELAS'=>2,'KEPALA_SEKOLAH'=>3]:['KOORDINATOR_QURAN'=>1,'KOORDINATOR_BING'=>1,'KEPALA_SEKOLAH'=>2];
        $coordinatorTypes=['KOORDINATOR_QURAN'=>$v2?['UMMI','AGAMA']:['UMMI'],'KOORDINATOR_BING'=>['BING']];
        foreach ($flows as $f) {
            self::positive($f['id'] ?? null);
            if (!in_array($f['kode'] ?? null,['KOORDINATOR_QURAN','KOORDINATOR_BING','WALI_KELAS','KEPALA_SEKOLAH'],true)
                || !in_array($f['aktif'] ?? null,[0,1,'0','1',false,true],true)
                || isset($byId[(int)$f['id']]) || isset($codes[$f['kode']])) throw new DomainException('Alur penyetuju duplikat/tidak valid.');
            self::positive($f['urutan'] ?? null);
            if (!is_string($f['label'] ?? null) || trim($f['label'])==='') throw new DomainException('Label penyetuju kosong.');
            $rank=$ranks[$f['kode']];
            if ((int)$f['urutan']!==$rank || ($f['cakupan'] ?? null)!==($rank>1?'SEMUA':'TERBATAS')) throw new DomainException('Urutan/cakupan penyetuju tidak sesuai.');
            $byId[(int)$f['id']]=$f; $codes[$f['kode']]=$f;
        }
        $scopes=[];
        foreach ($scopeRows as $s) {
            self::positive($s['penyetuju_id'] ?? null); self::positive($s['rubrik_id'] ?? null);
            $id=(int)$s['penyetuju_id']; $rid=(int)$s['rubrik_id'];
            if (!isset($byId[$id]) || $byId[$id]['cakupan']!=='TERBATAS' || isset($scopes[$id][$rid])) throw new DomainException('Mapping cakupan tidak valid.');
            $scopes[$id][$rid]=true;
            if (isset($rubrics[$rid])) {
                if (!in_array($rubrics[$rid]['jenis_dokumen'],$coordinatorTypes[$byId[$id]['kode']],true)) throw new DomainException('Koordinator tidak boleh menyetujui dokumen bidang lain.');
            }
        }
        $required=$v2?['WALI_KELAS'=>array_keys($docs),'KEPALA_SEKOLAH'=>array_keys($docs)]:['KEPALA_SEKOLAH'=>array_keys($docs)];
        foreach ($coordinatorTypes as $code=>$types) {
            foreach ($docs as $id=>$d) if (in_array($d['jenis_dokumen'],$types,true)) $required[$code][]=$id;
        }
        $output=[];
        foreach ($required as $code=>$ids) {
            $f=$codes[$code] ?? null;
            if (!$f || !(bool)$f['aktif']) throw new DomainException('Alur wajib belum aktif: '.$code);
            if ($f['cakupan']==='TERBATAS') foreach ($ids as $id) {
                if (!isset($scopes[(int)$f['id']][(int)$docs[$id]['rubrik_id']])) throw new DomainException('Mapping dokumen penyetuju belum lengkap: '.$code);
            }
            $output[]=['penyetuju_id'=>(int)$f['id'],'kode'=>$code,'label'=>$f['label'],'urutan'=>(int)$f['urutan'],
                'cakupan'=>$f['cakupan'],'status'=>'MENUNGGU','dokumen_ids'=>$ids];
        }
        usort($output,fn($a,$b)=>[$a['urutan'],$a['kode']]<=>[$b['urutan'],$b['kode']]);
        return $output;
    }
    private static function positive(mixed $value): void
    {
        if (filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) throw new DomainException('ID/urutan penyetuju tidak valid.');
    }
}
