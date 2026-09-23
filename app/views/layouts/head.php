<?php
/**
 * Partial <head> — dipakai oleh layouts/shell-header.php dan halaman Login.
 * Font wajib: Plus Jakarta Sans (Regular 400 + Bold 700), lihat
 * cookbook/design-system.md bagian 1.2.
 *
 * Variabel opsional yang bisa di-set sebelum require file ini:
 * - $pageTitle (string) — judul tab browser, default APP_NAME
 */
$pageTitle = $pageTitle ?? APP_NAME;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(APP_NAME) ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;700&family=Plus+Jakarta+Sans:wght@400;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/tokens.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/tokens.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/components.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/app-shell.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/app-shell.css') ?>">
<style id="zivana-color-tokens">
<?= colorCssVariables() ?>
</style>
