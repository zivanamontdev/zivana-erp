<?php

/** Pure preflight only. Caller must load trusted student/calendar/catalog data.
 * No insert, activation, permission bypass or legacy report conversion.
 */
final class EraporPackagePlan
{
    /** Form murid menyimpan 'Reguler'/'Berkebutuhan Khusus'; seed/impor lama memakai 'Regular'/'ABK'. */
    public static function normalizeCondition(string $value): ?string
    {
        $value=mb_strtolower(trim($value));
        if (in_array($value,['regular','reguler'],true)) return 'Regular';
        if (in_array($value,['abk','anak berkebutuhan khusus','berkebutuhan khusus','abk (anak berkebutuhan khusus)'],true)) return 'ABK';
        return null;
    }

    public static function build(int $studentId,string $condition,array $legacyPeriod,array $catalog,array $composition): array
    {
        if ($studentId<1 || !in_array($condition,['Regular','ABK'],true)) throw new DomainException('Identitas/kondisi murid tidak valid.');
        $period=EraporCalendar::normalize($legacyPeriod);
        $byCode=[];
        foreach ($catalog as $rubric) {
            $code=$rubric['kode'] ?? '';
            if (!is_string($code) || $code==='' || isset($byCode[$code])) throw new DomainException('Kode katalog kosong atau duplikat.');
            $byCode[$code]=$rubric;
        }
        $documents=[]; $missing=[]; $types=[];
        foreach ($composition as $entry) {
            if (!array_key_exists('periode',$entry) || !array_key_exists('kode',$entry)
                || ($entry['kode']!==null && (!is_string($entry['kode']) || $entry['kode']===''))
                || !in_array($entry['periode'] ?? null,[null,'TENGAH','AKHIR'],true)
                || !is_bool($entry['khusus_abk'] ?? null)
                || !in_array($entry['cakupan'] ?? null,['TAHUNAN','SEMESTER'],true)
                || !in_array($entry['jenis'] ?? null,['RTS','RAS','AGAMA','UMMI','BING','PPI'],true)) {
                throw new DomainException('Konfigurasi paket tidak valid.');
            }
            if ($entry['periode']!==null && $entry['periode']!==$period['jenis']) continue;
            if ($entry['khusus_abk'] && $condition!=='ABK') continue;
            if (isset($types[$entry['jenis']])) throw new DomainException('Jenis dokumen paket duplikat.');
            $types[$entry['jenis']]=true;
            $rubric=$byCode[$entry['kode'] ?? ''] ?? null;
            if (!$rubric) { $missing[]=$entry['jenis']; continue; }
            $id=filter_var($rubric['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false || ($rubric['jenis_dokumen'] ?? null)!==$entry['jenis']
                || ($rubric['cakupan'] ?? null)!==$entry['cakupan']
                || !in_array($rubric['khusus_abk'] ?? null,[0,1,'0','1',false,true],true)
                || (bool)$rubric['khusus_abk']!==$entry['khusus_abk']
                || !array_key_exists('jenis_periode',$rubric) || $rubric['jenis_periode']!==$entry['periode']
                || !in_array($rubric['status'] ?? null,['draft','terkunci'],true)) {
                throw new DomainException('Rubrik tidak cocok atau tidak tersedia: '.$entry['jenis']);
            }
            $documents[]=['rubrik_id'=>$id,'jenis'=>$entry['jenis'],'urutan'=>count($documents)+1,'wajib'=>true,
                'identitas'=>['murid_id'=>$studentId,'tahun_ajaran_id'=>$period['tahun_ajaran_id'],
                    'rubrik_id'=>$id,'semester'=>$entry['cakupan']==='TAHUNAN'?'TAHUNAN':$period['semester']]];
        }
        if ($types===[]) throw new DomainException('Konfigurasi paket kosong.');
        // Never return a usable partial package when one required rubric is absent.
        return ['sesi'=>['murid_id'=>$studentId,'periode_id'=>$period['id']],
            'periode'=>$period,'dapat_dibentuk'=>$missing===[],
            'rubrik_belum_tersedia'=>$missing,'dokumen'=>$missing===[]?$documents:[]];
    }
}
