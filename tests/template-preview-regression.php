<?php
// Run: php tests/template-preview-regression.php; no database access.
declare(strict_types=1);
define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', '');
require ROOT_PATH . '/vendor/autoload.php';
require ROOT_PATH . '/app/helpers/functions.php';
function verifyPreview(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
foreach (['Tengah', 'Akhir'] as $semester) {
    $template = ['nama' => 'Rapor Montessori ' . $semester . ' Semester'];
    ob_start();
    require ROOT_PATH . '/app/views/admin/template-rapor/_preview-pages.php';
    $html = ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    verifyPreview($xpath->query('//article[@class="template-paper"]')->length === 4, 'Four sheets required.');
    verifyPreview($xpath->query('//div[@class="template-preview-label"]')->length === 4, 'Each sheet needs its page label.');
    verifyPreview($xpath->query('//h2[contains(., "' . strtoupper($semester) . ' SEMESTER")]')->length === 4, 'Semester headings must match route.');
    verifyPreview($xpath->query('(//article)[1]//tbody/tr[td]')->length === 20, 'Page one must contain all twenty self-care items.');
    verifyPreview($xpath->query('(//article)[1]//tbody/tr[td][count(td)=4]')->length === 20, 'Apparatus and both score columns required.');
    verifyPreview($xpath->query('(//article)[2]//table')->length === 3, 'Second page needs life-skills and sensory tables.');
    verifyPreview(str_contains($html, 'Halaman 4 dari 4'), 'Last page label missing.');
    verifyPreview(str_contains($html, 'tabindex="0"'), 'Scrollable region must be keyboard accessible.');
}
$css = file_get_contents(ROOT_PATH . '/public/assets/css/template-preview.css');
(new Sabberworm\CSS\Parser($css))->parse();
verifyPreview(!preg_match('/#[0-9a-f]{3,8}\b/i', $css), 'Colors must use the color bank.');
echo "PASS: four-page preview for both semesters, fixture content, CSS syntax and color tokens.\n";
