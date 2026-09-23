<?php
define('ROOT_PATH', dirname(__DIR__));
define('BASE_PATH', '');
require ROOT_PATH . '/app/helpers/functions.php';
require ROOT_PATH . '/app/helpers/ui.php';
$index = 0;
$periode = ['id'=>7, 'nama'=>'Rapor Tengah Semester Ganjil', 'awal_periode'=>'2025-09-01', 'akhir_periode'=>'2025-09-30', 'sesi'=>[
    ['rapor'=>[['id'=>11,'nama_lengkap'=>'A Murid','status'=>'belum_diisi'], ['id'=>12,'nama_lengkap'=>'B Murid','status'=>'menunggu_persetujuan']]],
    ['rapor'=>[['id'=>13,'nama_lengkap'=>'C Murid','status'=>'disetujui']]],
]];
function renderPeriod(array $periode, int $index): string {
    ob_start(); require ROOT_PATH . '/app/views/components/report-period-table.php'; return ob_get_clean();
}
function checkPeriod(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$html = renderPeriod($periode, $index);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);
checkPeriod($xpath->query('//section')->length === 1 && $xpath->query('//table')->length === 1, 'One period surface/table, no nested session cards');
checkPeriod($xpath->query('//tbody/tr')->length === 3, 'All session rows retained');
checkPeriod($xpath->query('//a')->length === 2 && $xpath->query('//a[@href="/rapor-murid/11"]')->length === 0, 'Draft not clickable');
checkPeriod($xpath->query('//a[@href="/rapor-murid/12"]')->length === 1 && $xpath->query('//a[@href="/rapor-murid/13"]')->length === 1, 'Pending and approved whole-row preview links');
checkPeriod(!str_contains($html, 'badge') && str_contains($html, '01/09/2025 - 30/09/2025'), 'Plain statuses/count and actual period dates');
checkPeriod(str_contains($html, 'aria-expanded="true"'), 'First period expanded');
$periode['sesi'] = [];
$empty = renderPeriod($periode, 1);
checkPeriod(str_contains($empty, 'Belum ada murid aktif') && str_contains($empty, 'aria-expanded="false"'), 'Empty and collapsed state');
echo "PASS: single period table, all session rows, full-row preview links, draft guard, plain status, dates and empty/collapsed states.\n";
