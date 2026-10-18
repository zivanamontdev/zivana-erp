<?php

/**
 * Link unduh rapor untuk orang tua (dibagikan lewat WhatsApp). Publik tetapi hanya dengan token acak 256-bit yang
 * belum kedaluwarsa; diteruskan ke URL R2 bertanda tangan 5 menit sehingga bucket tetap private.
 */
final class EraporDownloadController extends Controller
{
    public function show(string $token): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        // Pratinjau tautan WhatsApp/Facebook tidak dihitung sebagai unduhan dan tidak menerima file.
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $isPreview = (bool) preg_match('/WhatsApp|facebookexternalhit|Facebot|TelegramBot|Twitterbot|Slackbot|Discordbot/i', $agent);
        try {
            $url = EraporDistribution::downloadUrl(Database::getInstance(), $token, !$isPreview);
        } catch (Throwable $e) {
            error_log('eRapor parent download failed: ' . $e->getMessage());
            $url = null;
        }
        if ($url === null) {
            http_response_code(404);
            $this->page('Tautan rapor tidak berlaku', 'Tautan ini sudah kedaluwarsa atau tidak dikenal. Silakan hubungi guru kelas untuk meminta tautan baru.');
            return;
        }
        if ($isPreview) {
            $this->page('Rapor ' . APP_NAME, 'Ketuk tautan untuk mengunduh rapor ananda.');
            return;
        }
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function page(string $title, string $message): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        $pageTitle = $title;
        echo '<!doctype html><html lang="id"><head><meta property="og:title" content="' . e($title) . '">';
        // Public pages use the app font, shared components, and generated color tokens too.
        require VIEW_PATH . '/layouts/head.php';
        echo '<style>body{margin:0;display:grid;place-items:center;min-height:100vh;background:var(--color-page-background);font-family:var(--font-family-base);color:var(--color-neutral-900)}'
            . '.erapor-download-card{max-width:22rem;margin:var(--space-4);padding:var(--space-5);background:var(--color-neutral-white);text-align:center}'
            . '.erapor-download-card h1{margin:0 0 var(--space-2)}.erapor-download-card p{margin:0;line-height:1.5}</style></head><body>';
        echo uiCard(uiText($title, 'body-md', ['tag' => 'h1', 'weight' => 'bold'])
            . uiText($message, 'body-sm', ['tag' => 'p', 'tone' => 'muted']), 'outlined', ['tag' => 'main', 'class' => 'erapor-download-card']);
        echo '</body></html>';
    }
}
