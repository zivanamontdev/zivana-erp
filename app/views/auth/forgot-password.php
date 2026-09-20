<?php
/**
 * [ASUMSI] Belum ada desain Figma untuk halaman ini — lihat
 * cookbook/prd.md poin asumsi #8. Gaya visual mengikuti Login apa adanya.
 *
 * Variabel dari AuthController:
 * - $message (string|null) — pesan generik setelah submit
 * - $debugLink (string|null) — HANYA terisi saat APP_DEBUG true, untuk
 *   memudahkan testing lokal tanpa SMTP asli
 */
$pageTitle = 'Lupa Kata Sandi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/login.css">
</head>
<body class="login-body">
    <div class="login-wrapper login-wrapper-single">
        <div class="login-left">
            <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="<?= e(APP_NAME) ?>" class="login-logo">

            <div class="login-form-block">
                <div class="login-form-heading">
                    <h1 class="text-headline-sm font-bold">Lupa kata sandi?</h1>
                    <p class="text-body-sm">Masukkan email akun Anda, kami akan kirimkan tautan untuk membuat kata sandi baru.</p>
                </div>

                <?php if ($message): ?>
                <div class="login-info"><?= e($message) ?></div>
                <?php endif; ?>

                <?php if ($debugLink): ?>
                <div class="login-info login-debug-link">
                    <strong>[Mode Debug]</strong> SMTP belum dikonfigurasi, tautan reset:<br>
                    <a href="<?= e($debugLink) ?>"><?= e($debugLink) ?></a>
                </div>
                <?php endif; ?>

                <?php if (!$message): ?>
                <form method="POST" action="<?= BASE_PATH ?>/lupa-kata-sandi" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <div class="field">
                        <label for="email" class="field-label text-caption-md">Email</label>
                        <input type="email" id="email" name="email" class="field-input" placeholder="Isi email anda" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Kirim Tautan Reset</button>
                </form>
                <?php endif; ?>

                <a href="<?= BASE_PATH ?>/login" class="login-forgot text-body-sm">&larr; Kembali ke Login</a>
            </div>

            <div class="login-footer text-caption-md">
                <span>Copyright &copy; Yayasan Zivana Insan Mandiri</span>
                <a href="#">Privacy Policy</a>
            </div>
        </div>
    </div>
</body>
</html>
