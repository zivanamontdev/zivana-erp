<?php
// Isolated rendering test, without layout authentication or database writes.
define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', '');
require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';
require ROOT_PATH . '/app/core/Model.php';
require ROOT_PATH . '/app/models/Kelas.php';
$_SESSION = ['csrf_token' => 'fixture'];
$source = file_get_contents(ROOT_PATH . '/app/views/admin/murid/form.php');
$source = str_replace([
    "require VIEW_PATH . '/layouts/shell-header.php';",
    "require VIEW_PATH . '/layouts/shell-footer.php';",
], '', $source);
foreach (['tambah', 'ubah', 'detail'] as $mode) {
    $muridId = 1;
    $murid = ['nama_lengkap' => 'Nama <contoh>', 'status_kondisi' => 'Reguler', 'tanggal_lahir' => '2020-01-01'];
    $old = $errors = [];
    $canEdit = true;
    ob_start();
    eval('?>' . $source);
    $html = ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $x = new DOMXPath($dom);
    if ($x->query('//*[@data-tab-target]')->length !== 3) throw new Exception('Tabs');
    if (!str_contains($html, 'Nama &lt;contoh&gt;')) throw new Exception('Escaping');
    if ($mode === 'detail') {
        if ($x->query('//dl[contains(@class,"ui-data-card")]')->length !== 28) throw new Exception('Detail cards');
        if ($x->query('//input|//select|//textarea')->length) throw new Exception('No detail inputs');
    } else {
        if ($x->query('//*[@data-datepicker]')->length !== 2) throw new Exception('Dates');
        if ($x->query('//*[@data-ui-select]')->length !== 5) throw new Exception('Selects');
        if ($x->query('//input[@type="number"]')->length !== 2) throw new Exception('Numbers');
        if ($x->query('//textarea[@name="alamat"]')->length !== 1) throw new Exception('Address');
        if (!$x->query('//input[@name="csrf_token"]')->length) throw new Exception('CSRF');
    }
}
(new Sabberworm\CSS\Parser(file_get_contents(ROOT_PATH . '/public/assets/css/components.css')))->parse();
echo "PASS: create/edit/detail UI rendering, card count, input types, tabs and CSS.\n";
