<?php

/** One safe download name for previews, official artifacts, and parent/R2 downloads. */
final class EraporPdfFilename
{
    public static function build(string $student, string $kind, string $semester, string $year, bool $draft = false): string
    {
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $student) : $student;
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii !== false ? $ascii : $student) ?? '', '-');
        $name = rtrim(substr($name, 0, 100), '-') ?: 'Murid';
        $year = trim(preg_replace('/[^0-9]+/', '-', $year) ?? '', '-');
        return ($draft ? 'Draf-' : '') . 'Rapor-' . $name . '-' . ($kind === 'AKHIR' ? 'AS' : 'TS')
            . '-' . ($semester === 'GENAP' ? 'Genap' : 'Ganjil') . ($year !== '' ? '-' . $year : '') . '.pdf';
    }

    public static function forSession(PDO $db, int $sessionId, bool $draft = false): string
    {
        $q = $db->prepare('SELECT m.nama_lengkap,s.jenis,s.semester,t.tahun_awal,t.tahun_akhir
            FROM erapor_sesi s JOIN murid m ON m.id=s.murid_id JOIN tahun_ajaran t ON t.id=s.tahun_ajaran_id WHERE s.id=?');
        $q->execute([$sessionId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new DomainException('Identitas rapor tidak tersedia.');
        return self::build($row['nama_lengkap'], $row['jenis'], $row['semester'], $row['tahun_awal'] . '/' . $row['tahun_akhir'], $draft);
    }
}
