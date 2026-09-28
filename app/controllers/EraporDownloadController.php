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
        echo '<!doctype html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta property="og:title" content="' . e($title) . '"><title>' . e($title) . '</title>'
            . '<link rel="stylesheet" href="' . BASE_PATH . '/assets/css/tokens.css?v=' . filemtime(ROOT_PATH . '/public/assets/css/tokens.css') . '">'
            . '<style>body{margin:0;display:grid;place-items:center;min-height:100vh;background:var(--color-red-50);font-family:var(--font-family-base);color:var(--color-neutral-900)}'
            . 'main{max-width:22rem;margin:var(--space-4);padding:var(--space-5);border-radius:1rem;background:var(--color-neutral-white);text-align:center}'
            . 'h1{font-size:var(--font-size-body-md);margin:0 0 var(--space-2)}p{margin:0;color:var(--color-neutral-600);font-size:var(--font-size-body-sm);line-height:1.5}</style>'
            . '</head><body><main><h1>' . e($title) . '</h1><p>' . e($message) . '</p></main></body></html>';
    }
}
