<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * The $this available inside a template. Kept separate from View so templates can only
 * reach the helpers below, not the renderer's internals.
 */
final class ViewScope
{
    private ?string $layout = null;

    /** @var array<string, string> */
    private array $sections = [];

    private ?string $openSection = null;

    /** @param array<string, mixed> $data */
    public function __construct(
        private readonly View $view,
        private readonly array $data,
    ) {
    }

    public function renderFile(string $__file): string
    {
        $__data = $this->data;
        $__render = function () use ($__file, $__data): void {
            extract($__data, EXTR_SKIP);
            include $__file;
        };

        $level = ob_get_level();
        ob_start();
        try {
            $__render->call($this);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    /** Renders a partial; it sees the parent's data plus $data. */
    public function insert(string $template, array $data = []): string
    {
        $scope = new self($this->view, [...$this->data, ...$data]);

        return $scope->renderFile($this->view->path($template));
    }

    /** Captures output until end() into a named variable for the layout (e.g. "head"). */
    public function start(string $name): void
    {
        $this->openSection = $name;
        ob_start();
    }

    public function end(): void
    {
        if ($this->openSection !== null) {
            $this->sections[$this->openSection] = (string) ob_get_clean();
            $this->openSection = null;
        }
    }

    public function chosenLayout(): ?string
    {
        return $this->layout;
    }

    /** @return array<string, string> */
    public function sections(): array
    {
        return $this->sections;
    }
}
