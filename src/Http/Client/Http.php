<?php

declare(strict_types=1);

namespace Sofy\Http\Client;

/**
 * Статический фасад для HttpClient.
 *
 * Http::get('https://api.example.com/users', ['page' => 1]);
 * Http::withToken('...')->post('/users', ['name' => 'John']);
 * Http::baseUrl('https://api.example.com')->withToken('...')->get('/users');
 */
class Http
{
    public static function new(): HttpClient
    {
        return new HttpClient();
    }

    public static function baseUrl(string $url): HttpClient
    {
        return static::new()->baseUrl($url);
    }

    public static function withHeaders(array $headers): HttpClient
    {
        return static::new()->withHeaders($headers);
    }

    public static function withToken(string $token, string $type = 'Bearer'): HttpClient
    {
        return static::new()->withToken($token, $type);
    }

    public static function withBasicAuth(string $user, string $password): HttpClient
    {
        return static::new()->withBasicAuth($user, $password);
    }

    public static function withTimeout(int $seconds): HttpClient
    {
        return static::new()->withTimeout($seconds);
    }

    public static function asJson(): HttpClient
    {
        return static::new()->asJson();
    }

    public static function asForm(): HttpClient
    {
        return static::new()->asForm();
    }

    public static function get(string $url, array $query = []): HttpResponse
    {
        return static::new()->get($url, $query);
    }

    public static function post(string $url, array $data = []): HttpResponse
    {
        return static::new()->post($url, $data);
    }

    public static function put(string $url, array $data = []): HttpResponse
    {
        return static::new()->put($url, $data);
    }

    public static function patch(string $url, array $data = []): HttpResponse
    {
        return static::new()->patch($url, $data);
    }

    public static function delete(string $url, array $data = []): HttpResponse
    {
        return static::new()->delete($url, $data);
    }
}
