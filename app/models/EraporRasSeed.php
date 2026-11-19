<?php

/** Imports the official RAS V1 (Rapor Akhir Semester) unchanged. No update/upsert.
 * Skema rubrik sama dengan RTS, jadi penulisan memakai EraporRtsSeed::applyRubric().
 */
final class EraporRasSeed
{
    private const AREAS = ['ras_nilai_agama_dan_moral'=>6,'ras_fisik_motorik'=>13,'ras_kognitif'=>9,'ras_bahasa'=>15,'ras_sosial_emosional'=>13,'ras_seni'=>6];

    public static function load(string $file): array
    {
        $raw = file_get_contents($file);
        if ($raw === false) throw new RuntimeException('Seed unavailable.');
        $seed = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::validate($seed);
        return $seed;
    }

    public static function validate(array $seed): void
    {
        $r = $seed['rubrik'] ?? [];
        foreach (['kode'=>'RAS_MONTESSORI_V1','jenis_dokumen'=>'RAS','versi'=>1,'cakupan'=>'TAHUNAN',
            'jenis_periode'=>'AKHIR','cetak_gabung_periode'=>true,'khusus_abk'=>false] as $key=>$value) {
            self::check(($r[$key] ?? null) === $value, 'RAS metadata: ' . $key);
        }
        foreach (['nama','judul_cetak'] as $key) self::text($r[$key] ?? null);
        self::check(count($r['bagian'] ?? []) === 2, 'RAS requires two sections');
        foreach ([['RAS','rubrik'],['NARASI','teks_bebas']] as $i=>[$code,$kind]) {
            $s=$r['bagian'][$i] ?? [];
            self::check(($s['kode'] ?? null)===$code && ($s['jenis'] ?? null)===$kind && ($s['wajib'] ?? null)===true && ($s['urutan'] ?? null)===$i+1, 'RAS section ' . $code);
            self::text($s['judul'] ?? null);
        }
        $periods = $r['kolom_periode'] ?? [];
        self::check(count($periods)===2, 'RAS requires two period columns');
        foreach (['GANJIL','GENAP'] as $index=>$semester) {
            $p=$periods[$index] ?? [];
            self::check(($p['kode'] ?? null)==='AS_'.$semester && ($p['semester'] ?? null)===$semester
                && ($p['jenis'] ?? null)==='AKHIR' && ($p['urutan'] ?? null)===$index+1, 'Invalid RAS period column');
            self::text($p['label'] ?? null);
        }
        self::check(count($r['penandatangan'] ?? [])===3, 'RAS signature roles');
        foreach (['KEPALA_SEKOLAH','GURU_KELAS','ORANG_TUA'] as $i=>$role) {
            $s=$r['penandatangan'][$i];
            self::check(($s['peran'] ?? null)===$role && ($s['urutan'] ?? null)===$i+1 && is_bool($s['cetak_nuptk'] ?? null), 'Invalid RAS signature');
            self::text($s['jabatan_cetak'] ?? null);
        }
        self::check(count($r['skala'] ?? [])===4, 'RAS requires four scale values');
        foreach (['BD','MB','BSH','BSB'] as $i=>$code) {
            $s=$r['skala'][$i];
            self::check(($s['kode'] ?? null)===$code && ($s['nilai'] ?? null)===$i+1, 'Invalid RAS scale');
            self::text($s['label'] ?? null); self::text($s['simbol'] ?? null);
        }
        self::check(array_column($seed['area'] ?? [],'kode')===array_keys(self::AREAS), 'RAS area order/count');
        $codes=[]; $subCodes=[];
        foreach ($seed['area'] as $aIndex=>$area) {
            self::check(($area['urutan'] ?? null)===$aIndex+1, 'Area order'); self::text($area['nama'] ?? null);
            $count=0;
            foreach ($area['sub_area'] ?? [] as $sIndex=>$sub) {
                self::text($sub['kode'] ?? null);
                self::check(!isset($subCodes[$sub['kode']]), 'Duplicate subarea code'); $subCodes[$sub['kode']]=true;
                self::check(($sub['urutan'] ?? null)===$sIndex+1 && is_bool($sub['implisit'] ?? null), 'Subarea order/type');
                if ($sub['implisit']) self::check($sub['nama']===null && $sub['huruf']===null, 'Implicit subarea must not print title');
                else { self::text($sub['nama'] ?? null); self::text($sub['huruf'] ?? null); }
                self::check(!empty($sub['indikator']), 'Empty subarea');
                foreach ($sub['indikator'] as $iIndex=>$item) {
                    self::text($item['kode'] ?? null); self::text($item['tujuan'] ?? null);
                    self::check(str_starts_with($item['kode'],'ras_'), 'RAS indicator code prefix');
                    self::check(!isset($codes[$item['kode']]), 'Duplicate indicator code'); $codes[$item['kode']]=true;
                    self::check(($item['urutan'] ?? null)===$iIndex+1, 'Indicator order');
                    self::check(array_key_exists('aparatus',$item) && ($item['aparatus']===null || is_string($item['aparatus'])), 'Invalid apparatus');
                    self::check(array_key_exists('grup',$item) && $item['grup']===null, 'RAS has no groups');
                    $count++;
                }
            }
            self::check($count===self::AREAS[$area['kode']], 'Area indicator count');
        }
        self::check(count($codes)===62, 'RAS totals');
    }

    public static function apply(PDO $db, array $seed): string
    {
        self::validate($seed);
        return EraporRtsSeed::applyRubric($db, $seed);
    }

    private static function text(mixed $value): void { self::check(is_string($value) && trim($value)!=='', 'Required seed text missing'); }
    private static function check(bool $ok,string $message): void { if (!$ok) throw new DomainException($message); }
}
