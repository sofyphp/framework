<?php

declare(strict_types=1);

namespace Sofy\Storage;

/**
 * S3-compatible storage driver.
 *
 * Works with AWS S3, MinIO, DigitalOcean Spaces, Cloudflare R2, and any
 * other service that implements the S3 API.
 *
 * Requires only ext-curl and ext-openssl — no third-party SDK needed.
 *
 * Config keys:
 *   key      — AWS access key ID
 *   secret   — AWS secret access key
 *   region   — e.g. "us-east-1"
 *   bucket   — bucket name
 *   endpoint — (optional) custom endpoint for MinIO/DO/R2
 *   url      — (optional) public base URL for url() method
 */
class S3Driver implements DiskInterface
{
    private string  $key;
    private string  $secret;
    private string  $region;
    private string  $bucket;
    private string  $endpoint;   // e.g. https://s3.us-east-1.amazonaws.com
    private ?string $publicUrl;  // e.g. https://my-bucket.s3.amazonaws.com

    public function __construct(array $config)
    {
        $this->key    = $config['key'];
        $this->secret = $config['secret'];
        $this->region = $config['region'] ?? 'us-east-1';
        $this->bucket = $config['bucket'];

        $custom = $config['endpoint'] ?? '';
        $this->endpoint = $custom !== ''
            ? rtrim($custom, '/')
            : "https://s3.{$this->region}.amazonaws.com";

        $url = $config['url'] ?? '';
        $this->publicUrl = $url !== '' ? rtrim($url, '/') : null;
    }

    // ── DiskInterface ─────────────────────────────────────────────────────────

    public function put(string $path, string $contents): bool
    {
        $mime = $this->guessMime($path);
        $res  = $this->request('PUT', $path, $contents, ['Content-Type' => $mime]);
        return $res['status'] === 200;
    }

    public function get(string $path): ?string
    {
        $res = $this->request('GET', $path);
        return $res['status'] === 200 ? $res['body'] : null;
    }

    public function exists(string $path): bool
    {
        return $this->request('HEAD', $path)['status'] === 200;
    }

    public function missing(string $path): bool
    {
        return !$this->exists($path);
    }

    public function delete(string|array $path): bool
    {
        $ok = true;
        foreach ((array) $path as $p) {
            $res = $this->request('DELETE', $p);
            // 204 No Content = success; 404 = already gone (treat as ok)
            if ($res['status'] !== 204 && $res['status'] !== 404) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function copy(string $from, string $to): bool
    {
        $source = '/' . $this->bucket . '/' . ltrim($from, '/');
        $res    = $this->request('PUT', $to, '', ['x-amz-copy-source' => $source]);
        return $res['status'] === 200;
    }

    public function move(string $from, string $to): bool
    {
        return $this->copy($from, $to) && $this->delete($from);
    }

    public function size(string $path): int
    {
        $res = $this->request('HEAD', $path);
        return (int) ($res['headers']['content-length'] ?? 0);
    }

    public function lastModified(string $path): int
    {
        $res = $this->request('HEAD', $path);
        $raw = $res['headers']['last-modified'] ?? '';
        return $raw !== '' ? (int) strtotime($raw) : 0;
    }

    public function mimeType(string $path): string|false
    {
        $res = $this->request('HEAD', $path);
        return $res['headers']['content-type'] ?? false;
    }

    public function files(string $directory = ''): array
    {
        $prefix  = $directory !== '' ? rtrim($directory, '/') . '/' : '';
        $objects = $this->listObjects($prefix, '/');

        return array_map(
            fn(string $key) => ltrim(substr($key, strlen($prefix)), '/'),
            $objects['keys'],
        );
    }

    public function directories(string $directory = ''): array
    {
        $prefix  = $directory !== '' ? rtrim($directory, '/') . '/' : '';
        $objects = $this->listObjects($prefix, '/');

        return array_map(
            fn(string $p) => rtrim(ltrim(substr($p, strlen($prefix)), '/'), '/'),
            $objects['prefixes'],
        );
    }

    /** S3 has no real directories — creating a zero-byte placeholder is optional. */
    public function makeDirectory(string $path): bool
    {
        $key = rtrim($path, '/') . '/';
        $res = $this->request('PUT', $key, '');
        return $res['status'] === 200;
    }

    public function deleteDirectory(string $path): bool
    {
        $prefix  = rtrim($path, '/') . '/';
        $objects = $this->listObjects($prefix);
        $keys    = $objects['keys'];

        if (empty($keys)) {
            return true;
        }

        $ok = true;
        foreach ($keys as $key) {
            $res = $this->request('DELETE', $key);
            if ($res['status'] !== 204 && $res['status'] !== 404) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        if ($this->publicUrl !== null) {
            return $this->publicUrl . '/' . $path;
        }
        return "https://{$this->bucket}.s3.{$this->region}.amazonaws.com/{$path}";
    }

    // ── S3 list helper ────────────────────────────────────────────────────────

    /**
     * @return array{keys: string[], prefixes: string[]}
     */
    private function listObjects(string $prefix = '', string $delimiter = ''): array
    {
        $query = ['list-type' => '2'];
        if ($prefix !== '')    $query['prefix']    = $prefix;
        if ($delimiter !== '') $query['delimiter']  = $delimiter;

        $res  = $this->request('GET', '', '', [], $query);
        $keys = [];
        $prefixes = [];

        if ($res['status'] === 200) {
            $xml = @simplexml_load_string($res['body']);
            if ($xml !== false) {
                foreach ($xml->Contents as $obj) {
                    $keys[] = (string) $obj->Key;
                }
                foreach ($xml->CommonPrefixes as $cp) {
                    $prefixes[] = (string) $cp->Prefix;
                }
            }
        }

        return ['keys' => $keys, 'prefixes' => $prefixes];
    }

    // ── AWS Signature V4 ──────────────────────────────────────────────────────

    /**
     * Sign and execute an S3 request.
     *
     * @param  string $method      HTTP method
     * @param  string $objectKey   Object key inside the bucket ('' = bucket root)
     * @param  string $body        Request body
     * @param  array  $extraHeaders Additional headers (key => value)
     * @param  array  $queryParams  Query string parameters
     * @return array{status: int, headers: array<string,string>, body: string}
     */
    private function request(
        string $method,
        string $objectKey,
        string $body        = '',
        array  $extraHeaders = [],
        array  $queryParams  = [],
    ): array {
        $objectKey = ltrim($objectKey, '/');
        $uri       = '/' . $this->bucket . ($objectKey !== '' ? '/' . $objectKey : '');

        $datetime  = gmdate('Ymd\THis\Z');
        $date      = substr($datetime, 0, 8);
        $bodyHash  = hash('sha256', $body);
        $host      = parse_url($this->endpoint, PHP_URL_HOST);

        // Canonical headers (lowercase, sorted)
        $headers = array_change_key_case($extraHeaders, CASE_LOWER);
        $headers['host']                 = $host;
        $headers['x-amz-date']           = $datetime;
        $headers['x-amz-content-sha256'] = $bodyHash;
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "$k:$v\n";
        }
        $signedHeaders = implode(';', array_keys($headers));

        // Canonical query string (sorted, URL-encoded)
        $canonicalQuery = '';
        if ($queryParams !== []) {
            ksort($queryParams);
            $pairs = [];
            foreach ($queryParams as $k => $v) {
                $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            }
            $canonicalQuery = implode('&', $pairs);
        }

        // Canonical request
        $canonicalRequest = implode("\n", [
            $method,
            $uri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaders,
            $bodyHash,
        ]);

        // String to sign
        $scope        = "$date/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $kDate    = hash_hmac('sha256', $date,          'AWS4' . $this->secret, true);
        $kRegion  = hash_hmac('sha256', $this->region,  $kDate,    true);
        $kService = hash_hmac('sha256', 's3',            $kRegion,  true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $headers['authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->key, $scope, $signedHeaders, $signature,
        );

        // Build URL
        $url = $this->endpoint . $uri;
        if ($canonicalQuery !== '') {
            $url .= '?' . $canonicalQuery;
        }

        return $this->curl($method, $url, $headers, $body);
    }

    /**
     * @param  array<string,string> $headers
     * @return array{status: int, headers: array<string,string>, body: string}
     */
    private function curl(string $method, string $url, array $headers, string $body): array
    {
        $ch = curl_init();

        // Build header lines
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }

        $raw      = (string) curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headLen  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headLen);
        $respBody   = substr($raw, $headLen);

        // Parse response headers into lowercase key => value map
        $parsedHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (!str_contains($line, ':')) continue;
            [$k, $v] = explode(':', $line, 2);
            $parsedHeaders[strtolower(trim($k))] = trim($v);
        }

        return [
            'status'  => $httpCode,
            'headers' => $parsedHeaders,
            'body'    => $respBody,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'pdf'         => 'application/pdf',
            'json'        => 'application/json',
            'xml'         => 'application/xml',
            'zip'         => 'application/zip',
            'txt'         => 'text/plain',
            'html'        => 'text/html',
            'css'         => 'text/css',
            'js'          => 'application/javascript',
            'mp4'         => 'video/mp4',
            'mp3'         => 'audio/mpeg',
            default       => 'application/octet-stream',
        };
    }
}
