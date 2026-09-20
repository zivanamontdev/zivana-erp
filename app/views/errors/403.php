<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>403 — Akses Ditolak</title>
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/tokens.css">
    <style>
        body { font-family: var(--font-family-base); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: var(--color-neutral-50); }
        .box { text-align: center; }
        h1 { color: var(--color-red-600); font-size: var(--font-size-headline-lg); }
        p { color: var(--color-neutral-500); }
        a { color: var(--color-red-600); }
    </style>
</head>
<body>
    <div class="box">
        <h1>403 — Akses Ditolak</h1>
        <p>Anda tidak punya izin untuk mengakses halaman ini.</p>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login">Kembali ke Login</a>
    </div>
</body>
</html>
