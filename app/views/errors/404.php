<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Halaman Tidak Ditemukan</title>
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/tokens.css">
    <style>
        <?= colorCssVariables() ?>
        body { font-size: var(--font-size-body-sm); font-family: var(--font-family-base); display: flex; align-items: center; justify-content: center; min-height: 100svh; margin: 0; background: var(--color-neutral-50); }
        .box { box-sizing: border-box; width: min(100%, calc(40 * var(--ui-unit))); padding: var(--page-gutter-block) var(--page-gutter-inline); text-align: center; overflow-wrap: anywhere; }
        h1 { color: var(--color-neutral-800); font-size: var(--font-size-headline-lg); }
        p { color: var(--color-neutral-500); }
        a { color: var(--color-red-600); }
    </style>
</head>
<body>
    <div class="box">
        <h1>404 — Halaman Tidak Ditemukan</h1>
        <p>URL yang diminta tidak ada.</p>
        <?php // /login mengarahkan akun yang sudah masuk ke halaman awal sesuai izinnya (GuestMiddleware). ?>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login"><?= empty($_SESSION['user_id']) ? 'Kembali ke Login' : 'Kembali ke Beranda' ?></a>
    </div>
</body>
</html>
