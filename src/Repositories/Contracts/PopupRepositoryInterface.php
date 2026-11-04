<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Popup;
use App\Domain\PopupInput;

interface PopupRepositoryInterface
{
    /** @return list<Popup> every popup, the active one first, then most recently edited */
    public function all(): array;

    public function findById(int $id): ?Popup;

    /** The active popup, whatever its dates (callers check Popup::isShowing()). */
    public function findActive(): ?Popup;

    /** @param string $bodyHtml already sanitised */
    public function insert(PopupInput $input, string $bodyHtml): int;

    /**
     * @param string $bodyHtml already sanitised
     * @param bool $bumpVersion true to show the popup again to visitors who saw it
     */
    public function update(int $id, PopupInput $input, string $bodyHtml, bool $bumpVersion): void;

    /** Makes $id the only active popup. Run inside a transaction. */
    public function activate(int $id): void;

    public function deactivate(int $id): void;

    /** Replaces the image and bumps its version. */
    public function putImage(int $id, string $mime, string $bytes): void;

    public function removeImage(int $id): void;

    /** @return array{mime_type: string, content: string}|null */
    public function image(int $id): ?array;

    public function delete(int $id): void;
}
