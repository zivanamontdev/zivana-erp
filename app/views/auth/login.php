<?php
/**
 * Halaman Login — satu-satunya halaman yang TIDAK memakai App Shell
 * (tanpa sidebar). Lihat cookbook/design-system.md bagian 5.1.
 *
 * Variabel dari AuthController::showLogin():
 * - $error (string|null)
 */
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/login.css">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-left">
            <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="<?= e(APP_NAME) ?>" class="login-logo">

            <div class="login-form-block">
                <div class="login-form-heading">
                    <h1 class="text-headline-sm font-bold">Selamat datang kembali!</h1>
                    <p class="text-body-sm">Masukkan email dan kata sandi untuk mengakses akun</p>
                </div>

                <?php if ($error): ?>
                <div class="login-error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_PATH ?>/login" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

                    <div class="field">
                        <label for="email" class="field-label text-caption-md">Email</label>
                        <input type="email" id="email" name="email" class="field-input" placeholder="Isi email anda" required autofocus>
                    </div>

                    <div class="field">
                        <label for="password" class="field-label text-caption-md">Kata Sandi</label>
                        <div class="field-input-wrapper">
                            <input type="password" id="password" name="password" class="field-input" placeholder="Isi kata sandi anda" required>
                            <button type="button" class="field-icon-toggle" data-password-toggle aria-label="Tampilkan kata sandi">
                                <?= icon('icon_search') /* [ASUMSI] belum ada icon "eye" di assets/icons, pakai search sementara sebagai placeholder toggle */ ?>
                            </button>
                        </div>
                    </div>

                    <div class="login-form-meta">
                        <label class="login-remember">
                            <input type="checkbox" name="remember_me" value="1">
                            <span class="text-body-sm">Ingat Saya</span>
                        </label>
                        <a href="<?= BASE_PATH ?>/lupa-kata-sandi" class="login-forgot text-body-sm">Lupa kata sandi?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                </form>
            </div>

            <div class="login-footer text-caption-md">
                <span>Copyright &copy; Yayasan Zivana Insan Mandiri</span>
                <a href="#">Privacy Policy</a>
            </div>
        </div>

        <div class="login-right">
            <img src="<?= BASE_PATH ?>/assets/images/login-Z.svg" alt="" class="login-right-decoration" aria-hidden="true">
            <div class="login-right-content">
                <h2 class="text-headline-md font-bold">Dengan Mudah Mengelola Rapor Kelas</h2>
                <p class="text-body-md">Lihat agenda pengisian rapor yang sedang berjalan, pantau siapa saja yang belum diisi, dan selesaikan sebelum tenggat.</p>
                <img src="<?= BASE_PATH ?>/assets/images/login-table-preview.svg" alt="Pratinjau dashboard Portal Guru" class="login-preview-card">
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>/assets/js/login.js"></script>
</body>
</html>
