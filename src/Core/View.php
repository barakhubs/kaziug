<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renders plain PHP templates from views/. Templates receive their data as local
 * variables, escape output with e(), and can include partials with $this->insert().
 * A template may pick its layout with $this->layout('layouts/public').
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $basePath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @return array<string, mixed> */
    public function shared(): array
    {
        return $this->shared;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $data = [...$this->shared, ...$data];
        $scope = new ViewScope($this, $data);
        $content = $scope->renderFile($this->path($template));

        $layout = $scope->chosenLayout();
        if ($layout === null) {
            return $content;
        }

        return $this->render($layout, [...$data, ...$scope->sections(), 'content' => $content]);
    }

    public function path(string $template): string
    {
        if (preg_match('#^[a-z0-9_/\-]+$#i', $template) !== 1 || str_contains($template, '..')) {
            throw new RuntimeException("Invalid template name: {$template}");
        }
        $file = $this->basePath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        return $file;
    }
}
