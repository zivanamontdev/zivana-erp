<?php
/** Login: spesifikasi cookbook/design-system.md 5.1. */
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/login.css?v=<?= filemtime(ROOT_PATH . '/public/assets/css/login.css') ?>">
</head>
<body class="login-body">
    <main class="login-wrapper login-wrapper-split">
        <section class="login-left" aria-label="Masuk ke akun">
            <img src="<?= BASE_PATH ?>/assets/images/logo-colored.png" alt="Sekolah Zivana Montessori" class="login-logo" width="131" height="48">
            <div class="login-main">
              <div class="login-form-block">
                <header class="login-form-heading">
                    <?= uiText('Selamat datang kembali!', 'display-xs', ['tag' => 'h1', 'weight' => 'bold', 'tone' => 'heading', 'align' => 'center']) ?>
                    <?= uiText('Masukkan email dan kata sandi untuk mengakses akun', 'body-sm', ['tag' => 'p', 'tone' => 'muted', 'align' => 'center']) ?>
                </header>
                <?php if (!empty($error)): ?>
                    <div class="login-error" role="alert"><?= e($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_PATH ?>/login" class="login-form">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <?= uiField('email', 'Email', [
                        'type' => 'email', 'variant' => 'login', 'font' => 'geist',
                        'placeholder' => 'Isi email anda', 'required' => true, 'autocomplete' => 'username',
                    ]) ?>
                    <?= uiField('password', 'Kata Sandi', [
                        'type' => 'password', 'variant' => 'login', 'font' => 'geist',
                        'placeholder' => 'Isi kata sandi anda', 'required' => true,
                        'autocomplete' => 'current-password', 'icon' => 'icon_eye', 'iconToggle' => true,
                    ]) ?>
                    <div class="login-form-meta">
                        <?= uiCheckbox('remember_me', 'Ingat Saya', false, ['variant' => 'login', 'tone' => 'secondary']) ?>
                        <a href="<?= BASE_PATH ?>/lupa-kata-sandi" class="login-forgot text-caption-md">Lupa kata sandi?</a>
                    </div>
                    <?= uiButton('Masuk', 'primary', [
                        'type' => 'submit',
                        'fullWidth' => true,
                        'marginVertical' => 0,
                        'class' => 'login-submit',
                    ]) ?>
                </form>
              </div>
            </div>
            <footer class="login-footer text-body-sm">
                <span>Copyright &copy; Yayasan Zivana Insan Mandiri</span>
                <a href="#">Privacy Policy</a>
            </footer>
        </section>
        <section class="login-right ui-card ui-card--brand" aria-label="Pratinjau pengelolaan rapor">
            <div class="login-right-decoration" aria-hidden="true">
                <?php require ROOT_PATH . '/public/assets/images/login-Z.svg'; ?>
            </div>
            <div class="login-right-content">
                <?= uiText('Dengan Mudah Mengelola Rapor Kelas', 'display-xs', ['tag' => 'h2', 'weight' => 'bold', 'tone' => 'inverse']) ?>
                <?= uiText('Lihat agenda pengisian rapor yang sedang berjalan, pantau siapa saja yang belum diisi, dan selesaikan sebelum tenggat.', 'body-sm', ['tag' => 'p', 'tone' => 'inverse']) ?>
                <?php require VIEW_PATH . '/auth/partials/report-preview.php'; ?>
            </div>
        </section>
    </main>
    <script src="<?= BASE_PATH ?>/assets/js/login.js?v=<?= filemtime(ROOT_PATH . '/public/assets/js/login.js') ?>"></script>
</body>
</html>
