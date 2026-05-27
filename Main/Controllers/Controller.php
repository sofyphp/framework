<?php

declare(strict_types=1);

namespace Main\Controllers;

use Sofy\Http\Response;

abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::view($template, $data, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function response(string $content, int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }

    protected function notFound(string $message = 'Not Found'): Response
    {
        return new Response($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): Response
    {
        return Response::json(['error' => $message], 401);
    }

    protected function forbidden(string $message = 'Forbidden'): Response
    {
        return Response::json(['error' => $message], 403);
    }

    protected function noContent(): Response
    {
        return new Response('', 204);
    }
}
