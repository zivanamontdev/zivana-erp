<?php

/** Trusted employee/profile rows only. No upload or consent endpoint here. */
final class EraporSignerSnapshot
{
    public static function build(string $name, ?array $profile): array
    {
        if (preg_match('//u',$name)!==1 || EraporSessionPolicy::isBlank($name) || mb_strlen($name)>255) {
            throw new DomainException('Nama penandatangan tidak valid.');
        }
        $nuptk=EraporSessionPolicy::textForStorage($profile['nuptk'] ?? null);
        if ($nuptk!==null && !preg_match('/^[0-9]{16}$/D',$nuptk)) throw new DomainException('NUPTK harus 16 digit atau kosong.');
        $png=$profile['ttd_png'] ?? null;
        $consent=$profile['ttd_disetujui_pada'] ?? null;
        $hash=null;
        if ($png!==null) {
            if (!is_string($png) || strlen($png)>2097152 || !str_starts_with($png,"\x89PNG\r\n\x1a\n")) throw new DomainException('Gambar tanda tangan harus PNG maksimal 2 MB.');
            $size=@getimagesizefromstring($png);
            if (!$size || $size[2]!==IMAGETYPE_PNG || $size[0]<1 || $size[1]<1 || $size[0]>4096 || $size[1]>4096) throw new DomainException('Dimensi gambar tanda tangan tidak valid.');
            $date=is_string($consent)?DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$consent):false;
            if (!$date || $date->format('Y-m-d H:i:s')!==$consent) throw new DomainException('Persetujuan pemilik tanda tangan belum tersedia.');
            $hash=hash('sha256',$png);
        } elseif ($consent!==null) {
            throw new DomainException('Persetujuan tanda tangan tanpa gambar.');
        }
        return ['nama'=>$name,'nuptk'=>$nuptk,'ttd_png'=>$png,'ttd_sha256'=>$hash,'ttd_disetujui_pada'=>$consent];
    }
}
