<?php

declare(strict_types=1);

namespace Sofy\Http\Client;

use JsonException;
use RuntimeException;

class HttpClient
{
    private string $baseUrl    = '';
    private array  $headers    = [];
    private int    $timeout    = 30;
    private string $bodyFormat = 'json'; // 'json' | 'form'
    /** @var array{0:string,1:string}|null */
    private ?array $basicAuth  = null;

    // ── Configuration ─────────────────────────────────────────────────────────

    public function baseUrl(string $url): static
    {
        $clone = clone $this;
        $clone->baseUrl = rtrim($url, '/');
        return $clone;
    }

    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = array_merge($this->headers, $headers);
        return $clone;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => "$type $token"]);
    }

    public function withBasicAuth(string $user, string $password): static
    {
        $clone = clone $this;
        $clone->basicAuth = [$user, $password];
        return $clone;
    }

    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    public function asJson(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'json';
        return $clone->withHeaders([
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ]);
    }

    public function asForm(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'form';
        return $clone->withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }

    // ── HTTP methods ──────────────────────────────────────────────────────────

    public function get(string $url, array $query = []): HttpResponse
    {
        return $this->send('GET', $url, [], $query);
    }

    public function post(string $url, array $data = []): HttpResponse
    {
        return $this->send('POST', $url, $data);
    }

    public function put(string $url, array $data = []): HttpResponse
    {
        return $this->send('PUT', $url, $data);
    }

    public function patch(string $url, array $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, $data);
    }

    public function delete(string $url, array $data = []): HttpResponse
    {
        return $this->send('DELETE', $url, $data);
    }

    // ── Core ──────────────────────────────────────────────────────────────────

    private function send(string $method, string $url, array $data = [], array $query = []): HttpResponse
    {
        $resolvedUrl = $this->resolveUrl($url);

        if (!empty($query)) {
            $sep = str_contains($resolvedUrl, '?') ? '&' : '?';
            $resolvedUrl .= $sep . http_build_query($query);
        }

        [$body, $bodyHeaders] = $this->prepareBody($data);

        $allHeaders = array_merge($this->headers, $bodyHeaders);
        $curlHeaders = [];
        foreach ($allHeaders as $name => $value) {
            $curlHeaders[] = "$name: $value";
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $resolvedUrl,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $curlHeaders ?: ['Expect:'],
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if ($this->basicAuth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->basicAuth[0] . ':' . $this->basicAuth[1]);
        }

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
            static function ($ch, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[trim($name)] = trim($value);
                }
                return $len;
            }
        );

        $responseBody = curl_exec($ch);
        $status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException("HTTP $method $resolvedUrl — $error");
        }

        return new HttpResponse($status, (string) $responseBody, $responseHeaders);
    }

    private function resolveUrl(string $url): string
    {
        if ($this->baseUrl === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return $this->baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * @return array{0: string|null, 1: array<string, string>}
     * @throws RuntimeException
     */
    private function prepareBody(array $data): array
    {
        if (empty($data)) {
            return [null, []];
        }

        if ($this->bodyFormat === 'json') {
            try {
                $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                throw new RuntimeException('Failed to encode request body: ' . $e->getMessage(), 0, $e);
            }
            return [$body, [
                'Content-Type'   => 'application/json',
                'Content-Length' => (string) strlen($body),
            ]];
        }

        $body = http_build_query($data);
        return [$body, [
            'Content-Type'   => 'application/x-www-form-urlencoded',
            'Content-Length' => (string) strlen($body),
        ]];
    }
}
