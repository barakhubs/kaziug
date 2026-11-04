<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\Popup;
use App\Domain\PopupInput;

/** Back-office management of marketing popups. The public site reads through PopupReaderInterface. */
interface PopupServiceInterface
{
    /** @return list<Popup> */
    public function all(): array;

    public function get(int $id): Popup;

    /** @param string|null $imageBytes an uploaded image, or null for none */
    public function create(PopupInput $input, ?string $imageBytes, Actor $actor): Popup;

    /**
     * @param string|null $imageBytes a replacement image, or null to keep the current one
     * @param bool $removeImage drop the current image (ignored when a new one is uploaded)
     */
    public function update(int $id, PopupInput $input, ?string $imageBytes, bool $removeImage, Actor $actor): void;

    /** Makes this the only active popup. */
    public function activate(int $id, Actor $actor): void;

    public function deactivate(int $id, Actor $actor): void;

    public function delete(int $id, Actor $actor): void;

    /** @return array{mime_type: string, content: string}|null */
    public function image(int $id): ?array;
}
