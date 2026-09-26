<?php
// PilotDataImport dengan file .xlsx sintetis (data fiktif; data murid asli tidak pernah masuk repository).
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/models/PilotDataImport.php';
$checks = 0;
function pilotCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException('Pilot import check failed: ' . $message); $checks++; }

function buildXlsx(array $rows): string
{
    $cells = '';
    foreach ($rows as $r => $row) {
        $cells .= '<row r="' . ($r + 1) . '">';
        foreach ($row as $c => $value) {
            $ref = chr(65 + $c) . ($r + 1);
            $cells .= is_float($value) || is_int($value)
                ? '<c r="' . $ref . '"><v>' . $value . '</v></c>'
                : '<c r="' . $ref . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $value) . '</t></is></c>';
        }
        $cells .= '</row>';
    }
    $path = tempnam(sys_get_temp_dir(), 'pilot') . '.xlsx';
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $cells . '</sheetData></worksheet>');
    $zip->close();
    return $path;
}

$header = ['No', 'Nama Anak', 'NISN', 'Kelas', 'Nama Guru', 'Keterangan', 'Jenis Kelamin', 'Tempat', 'Tanggal Lahir', 'Agama', 'Anak Ke-',
    'Jumlah Bersaudara', 'Status Dalam Keluarga', 'Nama Ayah', 'Nama Ibu', 'Alamat', 'Pekerjaan Ayah', 'Pekerjaan Ibu'];
$file = buildXlsx([
    ['Data Piloting E-Rapor Zivana'],
    $header,
    [1, 'Contoh Anak Satu', 3.210000001E9, 'Ranting Uji', 'Guru Contoh, S.Pd.', 'Reguler', 'Laki-laki', 'Makassar', '18 Mei 2021', 'Islam', 1, 1, 'Anak Kandung', 'Ayah Satu', 'Ibu Satu', 'Jl. Contoh 1', 'Wiraswasta', 'IRT'],
    [2, 'Contoh Anak Dua', '-', 'Daun Coba', 'Guru Contoh, S.Pd.', 'ABK', 'Perempuan', 'Gowa', 44559, 'Islam', 2, 3, 'Anak Kandung', 'Ayah Dua', 'Ibu Dua', 'Jl. Contoh 2', 'PNS', 'IRT'],
    [3, 'Contoh Anak Tiga', '-', 'Ranting Uji', 'Guru Kedua', 'Reguler', 'Perempuan', 'Maros', '2022-09-12', 'Islam', 1, 1, 'Anak Kandung', 'Ayah Tiga', 'Ibu Tiga', '', 'Pengusaha', 'Dokter'],
]);
$d = PilotDataImport::fromXlsx($file, 'contoh.test', '2026-07-01');
pilotCheck(count($d['students']) === 3 && count($d['teachers']) === 2 && count($d['classes']) === 2, 'counts');
pilotCheck(array_keys($d['classes']) === ['Ranting Uji', 'Daun Coba'] && $d['classes']['Daun Coba'] === ['level' => 'Daun', 'nama' => 'Coba'], 'class level/name split');
pilotCheck(array_column($d['teachers'], 'email') === ['guru.contoh@contoh.test', 'guru.kedua@contoh.test'], 'teacher emails without degree');
[$a, $b, $c] = array_column($d['students'], 'row');
pilotCheck($a['nisn'] === '3210000001' && $b['nisn'] === null, 'scientific NISN and dash');
pilotCheck($a['tanggal_lahir'] === '2021-05-18' && $b['tanggal_lahir'] === '2021-12-29' && $c['tanggal_lahir'] === '2022-09-12', 'three date formats');
pilotCheck($d['students'][1]['kondisi'] === 'Berkebutuhan Khusus' && $d['students'][0]['kondisi'] === 'Reguler', 'condition mapping');
pilotCheck($a['jenis_kelamin'] === 'L' && $b['jenis_kelamin'] === 'P' && $b['anak_ke'] === 2 && $b['jumlah_saudara'] === 3, 'gender and numbers');
pilotCheck($c['alamat'] === '-' && $a['tanggal_masuk_sekolah'] === '2026-07-01' && $a['nama_panggilan'] === 'Contoh', 'defaults for missing fields');
unlink($file);

foreach ([
    'kolom hilang' => [['Nama Anak', 'Nama Guru']],
    'kelas tanpa level' => [$header, [1, 'X', '-', 'Akasia', 'G', 'Reguler', 'Laki-laki', 'M', '1 Mei 2021', 'Islam', 1, 1, '', 'A', 'I', 'J', 'P', 'Q']],
    'tanggal salah' => [$header, [1, 'X', '-', 'Ranting A', 'G', 'Reguler', 'Laki-laki', 'M', '31 Februari 2021', 'Islam', 1, 1, '', 'A', 'I', 'J', 'P', 'Q']],
] as $case => $rows) {
    $bad = buildXlsx($rows);
    try { PilotDataImport::fromXlsx($bad, 'contoh.test', '2026-07-01'); pilotCheck(false, "$case rejected"); }
    catch (DomainException) { pilotCheck(true, $case); }
    unlink($bad);
}
echo "PASS: $checks pilot data import checks (synthetic xlsx: headers, classes, emails, NISN, dates, validation).\n";
