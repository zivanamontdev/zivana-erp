<?php

/** Read-only adapter over the existing calendar, not a second calendar table. */
final class EraporCalendar
{
    public static function year(PDO $db,int $yearId): array
    {
        if ($yearId<1) throw new DomainException('Tahun ajaran tidak valid.');
        $q=$db->prepare('SELECT id,tahun_awal,tahun_akhir FROM tahun_ajaran WHERE id=?');
        $q->execute([$yearId]); $year=$q->fetch(PDO::FETCH_ASSOC);
        if (!$year || (int)$year['tahun_akhir']!==(int)$year['tahun_awal']+1) throw new DomainException('Tahun ajaran tidak tersedia atau tidak valid.');
        $q=$db->prepare('SELECT id,tahun_ajaran_id,semester,tipe,awal_periode,akhir_periode FROM periode_penilaian WHERE tahun_ajaran_id=? ORDER BY id');
        $q->execute([$yearId]); $slots=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $period=self::normalize($row);
            if (isset($slots[$period['urutan']])) throw new DomainException('Slot periode duplikat; periksa kalender.');
            $slots[$period['urutan']]=$period;
        }
        ksort($slots);
        return ['id'=>$yearId,'tahun_awal'=>(int)$year['tahun_awal'],'tahun_akhir'=>(int)$year['tahun_akhir'],
            'periode'=>array_values($slots),'slot_belum_tersedia'=>array_values(array_diff([1,2,3,4],array_keys($slots)))];
    }

    public static function normalize(array $row): array
    {
        foreach (['id','tahun_ajaran_id'] as $key) {
            if (filter_var($row[$key] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false) throw new DomainException('Identitas kalender tidak valid.');
        }
        $semester=match ($row['semester'] ?? null) {
            'ganjil'=>'GANJIL','genap'=>'GENAP',default=>throw new DomainException('Semester periode belum valid; tidak boleh ditebak.'),
        };
        $type=match ($row['tipe'] ?? null) {
            'Tengah Semester'=>'TENGAH','Akhir Semester'=>'AKHIR',default=>throw new DomainException('Tipe periode tidak valid.'),
        };
        foreach (['awal_periode','akhir_periode'] as $key) {
            $value=$row[$key] ?? null;
            $date=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value):false;
            if (!$date || $date->format('Y-m-d')!==$value) throw new DomainException('Tanggal periode tidak valid.');
        }
        if ($row['awal_periode']>$row['akhir_periode']) throw new DomainException('Rentang periode terbalik.');
        return ['id'=>(int)$row['id'],'tahun_ajaran_id'=>(int)$row['tahun_ajaran_id'],'semester'=>$semester,
            'jenis'=>$type,'urutan'=>EraporSessionPolicy::periodOrder($semester,$type),
            'awal_periode'=>$row['awal_periode'],'akhir_periode'=>$row['akhir_periode']];
    }
}
