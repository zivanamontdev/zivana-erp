<?php

/**
 * Klien minimal Cloudflare R2 (API S3, AWS Signature V4) tanpa SDK: unggah, cek, hapus, dan URL unduh bertanda tangan
 * untuk bucket private. Kredensial dari .env (R2_*); secret tidak pernah dicatat ke log atau pesan galat.
 */
final class R2Storage
{
    private const REGION = 'auto';
    private const SERVICE = 's3';

    public function __construct(
        private string $endpoint = R2_ENDPOINT,
        private string $bucket = R2_PRIVATE_BUCKET,
        private string $accessKey = R2_ACCESS_KEY_ID,
        private string $secretKey = R2_SECRET_ACCESS_KEY,
    ) {
        if (!R2_ENABLED || $endpoint === '' || $bucket === '' || $accessKey === '' || $secretKey === '') {
            throw new RuntimeException('Penyimpanan R2 belum dikonfigurasi.');
        }
    }

    public static function enabled(): bool
    {
        return defined('R2_ENABLED') && R2_ENABLED && R2_ENDPOINT !== '' && R2_PRIVATE_BUCKET !== '' && R2_ACCESS_KEY_ID !== '' && R2_SECRET_ACCESS_KEY !== '';
    }

    public function put(string $key, string $bytes, string $contentType, string $contentDisposition = ''): void
    {
        $headers = ['content-type' => $contentType];
        if ($contentDisposition !== '') $headers['content-disposition'] = $contentDisposition;
        [$status] = $this->request('PUT', $key, $bytes, $headers);
        if ($status !== 200) throw new RuntimeException('Unggah ke R2 gagal (HTTP ' . $status . ').');
    }

    /** @return array{size:int,etag:string}|null */
    public function head(string $key): ?array
    {
        [$status, , $headers] = $this->request('HEAD', $key);
        if ($status === 404) return null;
        if ($status !== 200) throw new RuntimeException('Pemeriksaan objek R2 gagal (HTTP ' . $status . ').');
        return ['size' => (int) ($headers['content-length'] ?? 0), 'etag' => trim((string) ($headers['etag'] ?? ''), '"')];
    }

    public function get(string $key): string
    {
        [$status, $body] = $this->request('GET', $key);
        if ($status !== 200) throw new RuntimeException('Unduh dari R2 gagal (HTTP ' . $status . ').');
        return $body;
    }

    public function delete(string $key): void
    {
        [$status] = $this->request('DELETE', $key);
        if ($status !== 204 && $status !== 200 && $status !== 404) throw new RuntimeException('Hapus objek R2 gagal (HTTP ' . $status . ').');
    }

    /** URL GET bertanda tangan (maks. 7 hari); $downloadName memaksa browser mengunduh dengan nama itu. */
    public function presignedGetUrl(string $key, int $expiresSeconds = 300, string $downloadName = ''): string
    {
        $expiresSeconds = max(1, min(604800, $expiresSeconds));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $date = $now->format('Ymd'); $stamp = $now->format('Ymd\THis\Z');
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $path = $this->path($key);
        $scope = $date . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $scope,
            'X-Amz-Date' => $stamp,
            'X-Amz-Expires' => (string) $expiresSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        if ($downloadName !== '') $query['response-content-disposition'] = 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $downloadName) . '"';
        ksort($query);
        $canonicalQuery = self::query($query);
        $canonical = "GET\n{$path}\n{$canonicalQuery}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";
        $signature = $this->sign($date, "AWS4-HMAC-SHA256\n{$stamp}\n{$scope}\n" . hash('sha256', $canonical));
        return $this->endpoint . $path . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    /** @return array{0:int,1:string,2:array<string,string>} */
    private function request(string $method, string $key, string $body = '', array $headers = []): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $date = $now->format('Ymd'); $stamp = $now->format('Ymd\THis\Z');
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);
        $path = $this->path($key);
        $payloadHash = hash('sha256', $body);
        $headers = array_change_key_case($headers) + ['host' => $host, 'x-amz-content-sha256' => $payloadHash, 'x-amz-date' => $stamp];
        ksort($headers);
        $canonicalHeaders = ''; foreach ($headers as $name => $value) $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
        $signedHeaders = implode(';', array_keys($headers));
        $scope = $date . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $canonical = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $signature = $this->sign($date, "AWS4-HMAC-SHA256\n{$stamp}\n{$scope}\n" . hash('sha256', $canonical));
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;
        unset($headers['host']);

        $curl = curl_init($this->endpoint . $path);
        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_HTTPHEADER => array_map(static fn($n, $v) => $n . ': ' . $v, array_keys($headers), $headers),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                return strlen($line);
            },
        ]);
        if ($method === 'PUT') curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        if (defined('R2_CA_BUNDLE') && R2_CA_BUNDLE !== '') curl_setopt($curl, CURLOPT_CAINFO, R2_CA_BUNDLE);
        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Koneksi ke R2 gagal: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return [$status, (string) $response, $responseHeaders];
    }

    private function path(string $key): string
    {
        if ($key === '' || str_contains($key, '..')) throw new InvalidArgumentException('Kunci objek R2 tidak valid.');
        return '/' . rawurlencode($this->bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    }

    private function sign(string $date, string $stringToSign): string
    {
        $key = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $key = hash_hmac('sha256', self::REGION, $key, true);
        $key = hash_hmac('sha256', self::SERVICE, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);
        return hash_hmac('sha256', $stringToSign, $key);
    }

    private static function query(array $params): string
    {
        $pairs = [];
        foreach ($params as $name => $value) $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        return implode('&', $pairs);
    }
}
