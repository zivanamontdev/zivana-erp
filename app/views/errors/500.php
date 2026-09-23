<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Terjadi Kesalahan</title>
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/tokens.css">
    <style>
        <?= colorCssVariables() ?>
        body { font-size: var(--font-size-body-sm); font-family: var(--font-family-base); display: flex; align-items: center; justify-content: center; min-height: 100svh; margin: 0; background: var(--color-neutral-50); }
        .box { text-align: center; box-sizing: border-box; width: min(100%, calc(40 * var(--ui-unit))); padding: var(--page-gutter-block) var(--page-gutter-inline); overflow-wrap: anywhere; }
        h1 { color: var(--color-red-600); font-size: var(--font-size-headline-lg); }
        p { color: var(--color-neutral-500); }
        a { color: var(--color-red-600); }
        pre { text-align: left; background: var(--color-neutral-100); padding: var(--space-4); border-radius: var(--radius-md); overflow-x: auto; font-size: var(--font-size-caption-md); }
    </style>
</head>
<body>
    <div class="box">
        <h1>500 — Terjadi Kesalahan</h1>
        <p>Terjadi kesalahan pada server. Tim kami sudah diberi tahu.</p>
        <?php if (defined('APP_DEBUG') && APP_DEBUG && isset($exception)): ?>
        <pre><?= htmlspecialchars($exception->getMessage() . "\n\n" . $exception->getTraceAsString()) ?></pre>
        <?php endif; ?>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login">Kembali ke Login</a>
    </div>
</body>
</html>
