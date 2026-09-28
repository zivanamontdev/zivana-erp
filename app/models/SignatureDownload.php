<?php

/**
 * Unduh gambar tanda tangan dari tautan (Google Drive "file/d/<id>/view" atau URL PNG langsung) untuk seed pilot,
 * lalu potong area kosong dan perkecil agar pas di kolom tanda tangan PDF. Gambar tidak pernah disimpan ke disk/repo.
 */
final class SignatureDownload
{
    private const MAX_DOWNLOAD = 5242880;
    private const MAX_WIDTH = 600;
    private const MAX_HEIGHT = 300;

    public static function fetch(string $url): string
    {
        if (preg_match('~drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:.*&)?id=)([A-Za-z0-9_-]{10,})~', $url, $m)) {
            $url = 'https://drive.google.com/uc?export=download&id=' . $m[1];
        }
        if (!preg_match('~^https://~', $url)) throw new DomainException('Tautan tanda tangan harus HTTPS.');
        $bytes = self::download($url);
        if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) throw new DomainException('Berkas tanda tangan bukan PNG (pastikan tautan Drive dapat diakses publik).');
        return self::trim($bytes);
    }

    private static function download(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS]);
            $bytes = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if (!is_string($bytes) || $status !== 200) throw new RuntimeException('Unduhan gagal' . ($error !== '' ? ': ' . $error : ' (HTTP ' . $status . ')'));
        } else {
            $bytes = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]));
            if (!is_string($bytes)) throw new RuntimeException('Unduhan gagal.');
        }
        if ($bytes === '' || strlen($bytes) > self::MAX_DOWNLOAD) throw new DomainException('Ukuran berkas tanda tangan tidak valid (maks. 5 MB).');
        return $bytes;
    }

    /** Potong ke area bertinta (piksel tidak transparan & tidak putih) + margin, lalu perkecil ke maks 600×300. */
    private static function trim(string $bytes): string
    {
        $src = @imagecreatefromstring($bytes);
        if (!$src instanceof GdImage) throw new DomainException('PNG tanda tangan tidak dapat dibaca.');
        $w = imagesx($src); $h = imagesy($src);
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        $step = max(1, (int) floor(max($w, $h) / 800)); // sampel cukup rapat untuk garis tanda tangan
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorat($src, $x, $y);
                $alpha = ($c >> 24) & 0x7F; $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                if ($alpha > 100 || ($r > 235 && $g > 235 && $b > 235)) continue;
                $minX = min($minX, $x); $maxX = max($maxX, $x); $minY = min($minY, $y); $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0) { imagedestroy($src); throw new DomainException('Gambar tanda tangan kosong.'); }
        $pad = (int) round(max($w, $h) * 0.02) + $step;
        $minX = max(0, $minX - $pad); $minY = max(0, $minY - $pad);
        $cw = min($w, $maxX + $pad + 1) - $minX; $ch = min($h, $maxY + $pad + 1) - $minY;
        $scale = min(1, self::MAX_WIDTH / $cw, self::MAX_HEIGHT / $ch);
        $tw = max(1, (int) round($cw * $scale)); $th = max(1, (int) round($ch * $scale));
        $dst = imagecreatetruecolor($tw, $th);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, $minX, $minY, $tw, $th, $cw, $ch);
        ob_start(); imagepng($dst, null, 6); $out = (string) ob_get_clean();
        imagedestroy($src); imagedestroy($dst);
        return $out;
    }
}
