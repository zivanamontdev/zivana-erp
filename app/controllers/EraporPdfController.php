<?php

/**
 * PDF paket eRapor satu sesi. Setelah seluruh persetujuan: artefak resmi yang tersimpan (tidak dirender ulang).
 * Sebelumnya: pratinjau draf dari nilai terkini dengan tanda DRAF. Akses: EraporPdfAccess (guru pemilik,
 * penyetuju berwenang atas seluruh paket, Superadmin); selain itu 404 agar keberadaan sesi tidak bocor.
 */
final class EraporPdfController extends Controller
{
    public function show(string $id): void
    {
        if (!defined('ERAPOR_API_ENABLED') || !ERAPOR_API_ENABLED) $this->notFound();
        $this->middleware(AuthMiddleware::class);
        $sessionId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $db = Database::getInstance();
        if ($sessionId === false || !EraporPdfAccess::canView($db, (int) $sessionId, (int) $_SESSION['user_id'])) $this->notFound();
        $download = (string) $this->input('unduh', '') === '1';
        try {
            $official = EraporPdfAccess::officialArtifact($db, (int) $sessionId);
            if ($official) {
                $this->send($official['bytes'], $official['filename'], $download);
            }
            $package = EraporPublication::draftPackage($db, (int) $sessionId);
            $this->send(EraporPackagePdfRenderer::render($package), 'draf-erapor-' . (int) $sessionId . '.pdf', $download);
        } catch (DomainException $e) {
            http_response_code(422);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'PDF belum dapat dibuat: ' . $e->getMessage();
            exit;
        }
    }

    private function send(string $bytes, string $filename, bool $download): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
        echo $bytes;
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        require VIEW_PATH . '/errors/404.php';
        exit;
    }
}
