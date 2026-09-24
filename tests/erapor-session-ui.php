<?php
// Server-rendered editor contract check; fixture values are synthetic and no DB is used.
define('ROOT_PATH', dirname(__DIR__));
define('VIEW_PATH', ROOT_PATH . '/app/views');
define('BASE_PATH', '');
$_SESSION = ['csrf_token' => 'fixture-token'];
class RoleMiddleware { public function check(...$args): bool { return true; } }
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';

function eraporUiCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$form = [
    'session' => ['id'=>41,'status'=>'BELUM_DIISI','kondisi'=>'ABK'],
    'student' => ['nama_lengkap'=>'Murid Uji','level_kelas'=>'Ranting','nama_kelas'=>'Akasia'],
    'period' => ['nama'=>'Pembagian Rapor Tengah Semester','tahun_label'=>'2025/2026','semester'=>'GANJIL'],
    'capabilities' => ['can_edit'=>true,'read_only_reason'=>null,'can_confirm_filled'=>false,'can_confirm_reception'=>false],
    'completion' => ['complete'=>false,'documents'=>[
        ['jenis'=>'RTS','required'=>1,'filled'=>0,'complete'=>false],
        ['jenis'=>'AGAMA','required'=>2,'filled'=>0,'complete'=>false],
        ['jenis'=>'UMMI','required'=>1,'filled'=>0,'complete'=>false],
        ['jenis'=>'BING','required'=>2,'filled'=>0,'complete'=>false],
        ['jenis'=>'PPI','required'=>1,'filled'=>0,'complete'=>false],
    ]],
    'documents' => [
        ['id'=>101,'nama'=>'Rapor Tengah Semester','jenis_dokumen'=>'RTS','form'=>[
            'definitions'=>[
                'areas'=>[['id'=>1,'nama'=>'KETERAMPILAN HIDUP']],
                'subareas'=>[['id'=>2,'area_id'=>1,'implisit'=>false,'huruf'=>'a','nama'=>'Perawatan Diri']],
                'groups'=>[],
                'items'=>[['id'=>3,'sub_area_id'=>2,'grup_id'=>null,'tujuan'=>'Menutup mulut saat batuk']],
                'scale'=>[
                    ['nilai'=>1,'label'=>'Baru dikenalkan','simbol'=>'slash'],
                    ['nilai'=>2,'label'=>'Mulai berkembang','simbol'=>'triangle-sm'],
                    ['nilai'=>3,'label'=>'Berkembang sesuai harapan','simbol'=>'triangle-lg'],
                    ['nilai'=>4,'label'=>'Berkembang sangat baik','simbol'=>'triangle-full'],
                ],
            ], 'values'=>[],
        ]],
        ['id'=>102,'nama'=>'Rapor Agama','jenis_dokumen'=>'AGAMA','form'=>[
            'definitions'=>[
                'scopes'=>[['id'=>4,'nomor_romawi'=>'I','nama'=>'Al-Qur’an','catatan_wajib'=>1]],
                'subscopes'=>[['id'=>5,'lingkup_id'=>4,'implisit'=>false,'huruf'=>'a','nama'=>'Tahfizh']],
                'items'=>[['id'=>6,'sub_id'=>5,'nomor'=>1,'teks'=>'Membaca surah','semester'=>'GANJIL']],
                'names'=>[],
                'scale'=>[['kolom_cetak'=>'TAHFIZH/D','label'=>'Belum berkembang']],
            ], 'values'=>[],
        ]],
        ['id'=>103,'nama'=>'Rapor Ummi','jenis_dokumen'=>'UMMI','form'=>['definitions'=>[],'values'=>[]]],
        ['id'=>104,'nama'=>'Rapor Bahasa Inggris','jenis_dokumen'=>'BING','form'=>[
            'definitions'=>[
                'items'=>[['id'=>7,'grup'=>null,'penanda_cetak'=>null,'label_cetak'=>'Attendance']],
                'comments'=>[['id'=>8,'label_cetak'=>'Speaking']],
                'scale'=>[['kode'=>'EXCELLENT','label'=>'Excellent']],
            ], 'values'=>[],
        ]],
        ['id'=>105,'nama'=>'Program Pembelajaran Individual','jenis_dokumen'=>'PPI','form'=>[
            'definitions'=>[
                'aspects'=>[['id'=>9,'nama'=>'Komunikasi','aktif'=>1]],
                'columns'=>[['id'=>10,'bagian'=>'TARGET','label_cetak'=>'Target semester','diisi_di_sesi'=>1]],
            ], 'values'=>[],
        ]],
    ],
];

$source = file_get_contents(VIEW_PATH . '/portal-guru/erapor-sesi.php');
$source = str_replace([
    "require VIEW_PATH . '/layouts/focus-header.php';",
    "require VIEW_PATH . '/layouts/focus-footer.php';",
], '', $source);
ob_start();
eval('?>' . $source);
$html = ob_get_clean();
$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

eraporUiCheck(str_contains($html, 'data-erapor-editor') && str_contains($html, 'erapor-session.js'), 'Session editor boot contract');
eraporUiCheck(str_contains($html, 'Murid Uji') && str_contains($html, 'Ranting Akasia'), 'Student/class identity stays in header');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="RTS"]')->length === 1, 'RTS grade select rendered');
eraporUiCheck(str_contains($xpath->query('//select[@data-erapor-entry="RTS"]')->item(0)->getAttribute('class'), 'font-base'), 'Unspecified form font defaults to Plus Jakarta Sans');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="RTS"]//option[@data-option-image]')->length === 4, 'RTS uses all four shared assessment SVGs');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="AGAMA"]')->length === 1, 'Agama choice mapped from rubric definitions');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="BING"]')->length === 1 && $xpath->query('//textarea[@data-erapor-entry="BING"]')->length === 1, 'BING grade and comment controls rendered');
eraporUiCheck($xpath->query('//textarea[@data-erapor-entry="PPI" and not(@disabled)]')->length === 1, 'PPI session field is editable through shared textarea component');
eraporUiCheck(str_contains($html, 'alur inisialisasi periode') && $xpath->query('//textarea[@data-erapor-entry="UMMI"]')->length === 0, 'Ummi is explicitly not presented as editable');
eraporUiCheck($xpath->query('//button[@data-erapor-confirm and @disabled]')->length === 1, 'Submission starts disabled until server confirms completeness');
eraporUiCheck($xpath->query('//details[@open]')->length === 0, 'Assessment areas start collapsed');
echo "PASS: E-Rapor session editor markup covers RTS, Agama, BING, PPI, read-only Ummi state and server-gated submission.\n";
