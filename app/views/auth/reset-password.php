<?php
/**
 * [ASUMSI] Belum ada desain Figma untuk halaman ini — lihat
 * cookbook/prd.md poin asumsi #8.
 *
 * Variabel dari AuthController:
 * - $token (string)
 * - $error (string|null)
 * - $expired (bool)
 */
$pageTitle = 'Atur Ulang Kata Sandi';
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
                    <h1 class="text-headline-sm font-bold">Atur ulang kata sandi</h1>
                    <p class="text-body-sm">Buat kata sandi baru untuk akun Anda.</p>
                </div>

                <?php if ($error): ?>
                <div class="login-error"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (!$expired): ?>
                <form method="POST" action="<?= BASE_PATH ?>/reset-kata-sandi/<?= e($token) ?>" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <div class="field">
                        <label for="password" class="field-label text-caption-md">Kata Sandi Baru</label>
                        <input type="password" id="password" name="password" class="field-input" placeholder="Minimal 8 karakter" minlength="8" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password_confirmation" class="field-label text-caption-md">Ulangi Kata Sandi Baru</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="field-input" placeholder="Ulangi kata sandi baru" minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Simpan Kata Sandi Baru</button>
                </form>
                <?php else: ?>
                <a href="<?= BASE_PATH ?>/lupa-kata-sandi" class="btn btn-primary btn-block" style="text-align:center; text-decoration:none; display:block; line-height:37px;">Minta Tautan Baru</a>
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
