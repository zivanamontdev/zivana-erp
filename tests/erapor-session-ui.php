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
        ['jenis'=>'UMMI','required'=>0,'filled'=>0,'complete'=>true],
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
                    ['nilai'=>0,'label'=>'Belum Dikenalkan','simbol'=>'-'],
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
        ['id'=>103,'nama'=>'Rapor Ummi','jenis_dokumen'=>'UMMI','form'=>[
            'definitions'=>[
                'initialization_required'=>false,
                'volumes'=>[
                    ['id'=>11,'nama'=>'PRA TK','urutan'=>1,'hanya_pra_tk'=>1],
                    ['id'=>12,'nama'=>'I','urutan'=>2,'hanya_pra_tk'=>0],
                ],
                'items'=>[
                    ['id'=>13,'jilid_id'=>11,'jilid_nama'=>'PRA TK','teks'=>'Materi Pra TK','hanya_pra_tk'=>1],
                    ['id'=>14,'jilid_id'=>12,'jilid_nama'=>'I','teks'=>'Materi Jilid I','hanya_pra_tk'=>0],
                ],
                'scale'=>[
                    ['kode'=>'A+','label'=>'A+','peringkat'=>12],
                    ['kode'=>'A','label'=>'A','peringkat'=>11],
                ],
            ],
            'values'=>[
                'mulai_pra_tk'=>false,
                'catatan'=>'Catatan periode Ummi',
                'bacaan:14'=>'A+',
                'tes:0123456789abcdef0123456789abcdef'=>['urutan'=>1,'tanggal_tes'=>'2026-09-24','jilid'=>'I','nilai'=>'A'],
            ],
        ]],
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
$initialState = $xpath->query('//*[@data-erapor-editor]//script[@data-erapor-initial-state]');
eraporUiCheck($initialState->length === 1 && json_decode($initialState->item(0)->textContent, true) !== null,
    'Initial state JSON lives inside the editor root (erapor-session.js scopes its lookup to that root)');
eraporUiCheck(str_contains($html, 'Murid Uji') && str_contains($html, 'Ranting Akasia'), 'Student/class identity stays in header');
$rtsGroup = $xpath->query('//div[@role="radiogroup" and @data-erapor-entry="RTS" and @data-erapor-radio="true" and @data-erapor-indicator-id="3"]');
eraporUiCheck($rtsGroup->length === 1, 'RTS answer is one radio group per tujuan');
eraporUiCheck($xpath->query('.//input[@type="radio"]', $rtsGroup->item(0))->length === 5
    && $xpath->query('.//input[@type="radio" and @value="0"]', $rtsGroup->item(0))->length === 1, 'RTS offers five options including "-" Belum Dikenalkan');
eraporUiCheck($xpath->query('.//img', $rtsGroup->item(0))->length === 4, 'RTS radios show the four shared assessment SVGs plus the dash');
eraporUiCheck($xpath->query('//ul[contains(@class,"erapor-scale-legend")]/li')->length === 5, 'RTS page header explains every symbol');
eraporUiCheck($xpath->query('//section[@data-erapor-page]')->length === 5 && $xpath->query('//section[@data-erapor-page and @hidden]')->length === 4, 'One page per report type; only the first is shown');
eraporUiCheck($xpath->query('//*[@data-erapor-question]//*[@data-erapor-entry="RTS"]')->length === 1, 'Each assessment question has its own card');
eraporUiCheck($xpath->query('//nav[@data-erapor-pager]//button[@data-erapor-next]')->length === 1 && $xpath->query('//nav[@data-erapor-pager]//button[@data-erapor-prev]')->length === 1, 'Pager offers previous/next navigation');
eraporUiCheck($xpath->query('//section[@data-erapor-type="UMMI" and @data-erapor-optional="true"]')->length === 1, 'Ummi page is optional');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="AGAMA"]')->length === 1, 'Agama choice mapped from rubric definitions');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="BING"]')->length === 1 && $xpath->query('//textarea[@data-erapor-entry="BING"]')->length === 1, 'BING grade and comment controls rendered');
eraporUiCheck($xpath->query('//textarea[@data-erapor-entry="PPI" and not(@disabled)]')->length === 1, 'PPI session field is editable through shared textarea component');
eraporUiCheck($xpath->query('//textarea[@data-erapor-entry="UMMI" and @data-erapor-key="catatan" and @aria-required="false"]')->length === 1, 'Ummi period note is optional and uses shared textarea component');
eraporUiCheck($xpath->query('//select[@data-erapor-entry="UMMI" and @data-erapor-key="bacaan:14"]')->length === 1, 'Ummi reading grade uses shared select component');
eraporUiCheck($xpath->query('//input[@data-erapor-pra-toggle and @type="checkbox"]')->length === 1 && $xpath->query('//details[@data-ummi-pra-tk="true" and @hidden]')->length === 1, 'Ummi PRA TK toggle hides its volume without removing its fields');
eraporUiCheck($xpath->query('//div[@data-erapor-entry="UMMI_TEST" and @data-erapor-key="tes:0123456789abcdef0123456789abcdef"]')->length === 1, 'Ummi dynamic test row renders server-saved values');
eraporUiCheck($xpath->query('//input[@data-ummi-test-field="jilid" and @value="I"]')->length === 1
    && $xpath->query('//select[@data-ummi-test-field="nilai"]/option[@value="A" and @selected]')->length === 1,
    'Ummi test fields preserve saved jilid and grade values');
eraporUiCheck($xpath->query('//button[@data-erapor-add-test="103"]')->length === 1 && $xpath->query('//template[@data-ummi-test-template]')->length === 1, 'Ummi supports dynamic test creation through shared controls');
eraporUiCheck($xpath->query('//input[@data-erapor-entry="UMMI" and @data-erapor-key="mulai_pra_tk"]')->length === 1 && str_contains($source, 'data-erapor-ummi-init'), 'Ummi editor includes explicit, user-triggered initialization behavior');
eraporUiCheck(!str_contains($html, 'Hafalan'), 'Ummi editor does not add the excluded memorization section');
// Tombol tetap aktif: klik saat belum lengkap menandai kartu wajib yang kosong; server tetap memutuskan.
eraporUiCheck($xpath->query('//button[@data-erapor-confirm and not(@disabled)]')->length === 2, 'Submit (header + last page) stays clickable to reveal missing answers');
eraporUiCheck($xpath->query('//details[@open]')->length === 0, 'Ummi volumes start collapsed');

$form['documents'][2]['form']['definitions']['initialization_required'] = true;
$form['documents'][2]['form']['values'] = ['mulai_pra_tk'=>null,'catatan'=>null];
ob_start();
eval('?>' . $source);
$uninitializedHtml = ob_get_clean();
$uninitializedDom = new DOMDocument();
@$uninitializedDom->loadHTML($uninitializedHtml);
$uninitializedXPath = new DOMXPath($uninitializedDom);
eraporUiCheck($uninitializedXPath->query('//button[@data-erapor-ummi-init]')->length === 1
    && $uninitializedXPath->query('//input[@data-erapor-entry="UMMI"]')->length === 0,
    'Uninitialized Ummi exposes only the explicit initialize action and no editable fields');

$form['session']['status'] = 'TELAH_DIISI';
$form['capabilities']['can_confirm_filled'] = false;
$form['capabilities']['can_confirm_reception'] = true;
ob_start();
eval('?>' . $source);
$receptionHtml = ob_get_clean();
$receptionDom = new DOMDocument();
@$receptionDom->loadHTML($receptionHtml);
$receptionXPath = new DOMXPath($receptionDom);
eraporUiCheck($receptionXPath->query('//button[@data-erapor-confirm-reception and not(@disabled)]')->length === 2
    && $receptionXPath->query('//button[@data-erapor-confirm]')->length === 0,
    'After completion, teacher receives the separate server-gated permanent reception action');

$form['session']['status'] = 'MENUNGGU_TTD';
$form['capabilities']['can_edit'] = false;
$form['capabilities']['can_confirm_reception'] = false;
ob_start();
eval('?>' . $source);
$pendingHtml = ob_get_clean();
$pendingDom = new DOMDocument();
@$pendingDom->loadHTML($pendingHtml);
$pendingXPath = new DOMXPath($pendingDom);
eraporUiCheck($pendingXPath->query('//button[@data-erapor-confirm or @data-erapor-confirm-reception]')->length === 0
    && str_contains($pendingHtml, 'Menunggu proses persetujuan'),
    'Once received, teacher cannot submit again and sees the pending approval state');

$script = file_get_contents(ROOT_PATH . '/public/assets/js/erapor-session.js');
eraporUiCheck(str_contains($script, "'/konfirmasi-penerimaan'")
    && str_contains($script, 'data-erapor-confirm-reception')
    && str_contains($script, 'tidak dapat diubah'),
    'Client routes reception separately and warns about the irreversible lock');
// SPEK_RUBRIK_RTS 6: sesi Tengah Genap menampilkan nilai TS Ganjil hanya-baca di baris yang sama.
eraporUiCheck($xpath->query('//*[@data-erapor-reference]')->length === 0, 'No TS Ganjil reference outside Tengah Genap');
$form['documents'][0]['reference'] = ['label' => 'TS Ganjil', 'values' => ['nilai:3' => 3]];
ob_start();
eval('?>' . $source);
$refHtml = ob_get_clean();
$refDom = new DOMDocument();
@$refDom->loadHTML($refHtml);
$refXpath = new DOMXPath($refDom);
$reference = $refXpath->query('//*[@data-erapor-question]//*[@data-erapor-reference]');
eraporUiCheck($reference->length === 1 && str_contains($reference->item(0)->textContent, 'Berkembang sesuai harapan')
    && $refXpath->query('//*[@data-erapor-reference]//img')->length === 1, 'TS Ganjil value shown with symbol beside the Genap options');
eraporUiCheck($refXpath->query('//*[@data-erapor-reference]//select|//*[@data-erapor-reference][@data-erapor-key]')->length === 0, 'TS Ganjil reference is not an editable/autosaved field');
unset($form['documents'][0]['reference']);

echo "PASS: E-Rapor session editor markup covers RTS, Agama, BING, PPI, Ummi, and server-gated submission.\n";
