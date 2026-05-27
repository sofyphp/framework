<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Core\Application;
use Sofy\Http\Request;
use Sofy\Http\Response;

class Pipeline
{
    private Request $request;
    private array   $middleware = [];

    public function send(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function then(callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $destination
        );

        return $pipeline($this->request);
    }

    private function carry(): callable
    {
        return function (callable $next, string|MiddlewareInterface $middleware): callable {
            return function (Request $request) use ($next, $middleware): Response {
                if (is_string($middleware)) {
                    $middleware = Application::getInstance()->get($middleware);
                }
                return $middleware->handle($request, $next);
            };
        };
    }
}
