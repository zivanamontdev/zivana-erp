<?php
/**
 * Layout "fokus" — TANPA sidebar/topbar app-shell. Dipakai HANYA untuk
 * Pengisian Rapor (Portal Guru): dikonfirmasi dari assets/ss/Portal
 * Guru - halaman_pengisian_rapor(desktop_mode + mobile_mode).svg bahwa
 * halaman ini sengaja tidak punya sidebar/header sama sekali (mode
 * fokus supaya guru tidak terdistraksi saat mengisi rapor) — beda dari
 * SEMUA halaman lain di aplikasi yang pakai layouts/shell-header.php.
 *
 * Variabel WAJIB: $pageTitle (dipakai untuk <title> tab browser saja,
 * TIDAK dirender sebagai H1 di sini — tiap view yang pakai layout ini
 * merender H1-nya sendiri di dalam .focus-card).
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
</head>
<body>
<div class="focus-shell">
    <div class="focus-column">
