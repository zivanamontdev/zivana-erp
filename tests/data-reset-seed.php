<?php
// Pure checks for DataResetSeeder seed data and table classification; no database is touched.
define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/models/DataResetSeeder.php';
$checks = 0;
function resetCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException('Data reset check failed: ' . $message); $checks++; }

resetCheck(!array_intersect(DataResetSeeder::KEEP_TABLES, DataResetSeeder::CLEAR_TABLES), 'keep and clear lists are disjoint');
foreach (['erapor_rubrik', 'erapor_migrations', 'role_permissions', 'jabatan', 'erapor_alur_penyetuju'] as $table) resetCheck(in_array($table, DataResetSeeder::KEEP_TABLES, true), "$table kept");
foreach (['murid', 'erapor_sesi', 'erapor_publikasi_pdf', 'rapor', 'users'] as $table) resetCheck(in_array($table, DataResetSeeder::CLEAR_TABLES, true), "$table cleared");

$seed = DataResetSeeder::seedData();
resetCheck(count($seed['years']) === 2 && (int) $seed['years']['2026']['is_active'] === 1 && (int) $seed['years']['2025']['is_active'] === 0, 'two academic years, 2026/2027 active');
resetCheck(count($seed['periods']) === 8, 'four periods per year');
$slots = array_map(fn($p) => $p['tahun'] . $p['semester'] . $p['tipe'], $seed['periods']);
resetCheck(count(array_unique($slots)) === 8, 'no duplicate year/semester/type period');

$names = [];
foreach ($seed['students'] as $s) {
    $r = $s['row'];
    $names[] = $r['nama_lengkap'];
    resetCheck(preg_match('/^\d{16}$/', $r['nik']) === 1 && preg_match('/^\d{10}$/', $r['nisn']) === 1, 'NIK 16 and NISN 10 digits: ' . $r['nama_lengkap']);
    resetCheck(in_array($s['kondisi'], ['Reguler', 'Berkebutuhan Khusus'], true), 'condition uses form values');
    resetCheck(in_array($r['status'], ['bersekolah', 'tanpa_keterangan', 'tamat', 'berhenti'], true), 'valid status');
    resetCheck(($s['kondisi'] === 'Berkebutuhan Khusus') === ($r['jenis_kebutuhan'] !== null), 'ABK has jenis kebutuhan');
    resetCheck(str_starts_with($r['telp_ayah'], '08110000') && str_starts_with($r['telp_ibu'], '08110000'), 'placeholder phone numbers only');
    resetCheck(!str_contains($r['nama_lengkap'], '[') , 'no bracket tags');
    if ($s['guru'] !== null) resetCheck(isset($seed['staff'][$s['guru']]) && $r['status'] === 'bersekolah', 'assigned students are active');
}
$active = array_filter($seed['students'], fn($s) => $s['row']['status'] === 'bersekolah');
resetCheck(count(array_filter($active, fn($s) => $s['kondisi'] === 'Reguler')) >= 1 && count(array_filter($active, fn($s) => $s['kondisi'] === 'Berkebutuhan Khusus')) >= 1, 'active Regular and ABK students');
resetCheck(count(array_unique(array_column($active, 'guru'))) === count($active), 'one active student per teacher');
resetCheck(count(array_unique(array_map(fn($s) => $s['kelas'], $active))) === 4, 'every class level has an active student');
resetCheck(count(array_filter($seed['staff'], fn($s) => $s['jabatan'] === 'Kepala Sekolah')) === 1, 'exactly one principal for the approval stage');

// Fictional names must not match people in the specification samples (e.g. the PPI example file).
$haystack = strtolower(implode("\n", array_merge(scandir(ROOT_PATH . '/eRapor_Zivana_Spesifikasi'),
    array_map(fn($f) => (string) file_get_contents($f), glob(ROOT_PATH . '/eRapor_Zivana_Spesifikasi/*.{md,json}', GLOB_BRACE)))));
foreach (array_merge($names, array_column($seed['staff'], 'nama')) as $name) {
    // Pasangan dua kata berurutan (mis. "muhammad ibrahim") menangkap nama sampel tanpa menandai nama tokoh umum seperti "Khadijah".
    $words = preg_split('/\s+/', trim(strtolower(preg_replace(['/,.*$/', '/[^a-z\s]/i'], ['', ' '], $name))));
    for ($i = 0; $i + 1 < count($words); $i++) {
        $pair = $words[$i] . ' ' . $words[$i + 1];
        resetCheck(!str_contains($haystack, $pair), "seed name '$pair' does not appear in spec samples");
    }
}
echo "PASS: $checks data reset seed checks (classification, realistic identifiers, Regular/ABK coverage, no sample-student names).\n";
