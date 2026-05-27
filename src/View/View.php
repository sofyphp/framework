<?php

declare(strict_types=1);

namespace Sofy\View;

use Closure;
use Sofy\Core\Application;
use RuntimeException;

class View
{
    private string $viewPath;

    /** @var array<string, string> */
    private static array $pathCache = [];

    public function __construct(string $viewPath)
    {
        $this->viewPath = rtrim($viewPath, '/');
    }

    public static function make(string $template, array $data = []): string
    {
        $view = new static(Application::getInstance()->viewPath());
        return $view->render($template, $data);
    }

    public function render(string $template, array $data = []): string
    {
        $path = $this->resolvePath($template);
        return $this->renderFile($path, $data);
    }

    private function renderFile(string $path, array $data): string
    {
        $fn = Closure::bind(function () use ($path, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            include $path;
            return (string) ob_get_clean();
        }, $this, static::class);

        return $fn();
    }

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function resolvePath(string $template): string
    {
        if (isset(self::$pathCache[$template])) {
            return self::$pathCache[$template];
        }

        $path = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($path)) {
            throw new RuntimeException("View [$template] not found.");
        }

        return self::$pathCache[$template] = $path;
    }
}
