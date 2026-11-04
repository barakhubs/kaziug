<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Popup;

/** What the public site may see of the popups. */
interface PopupReaderInterface
{
    /** The popup to offer visitors now: the active one, if it is inside its date window. */
    public function current(): ?Popup;

    /**
     * The image of the popup currently showing; null for any other popup.
     * @return array{mime_type: string, content: string}|null
     */
    public function currentImage(int $id): ?array;
}
