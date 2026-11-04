<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Popup;
use App\Repositories\Contracts\PopupRepositoryInterface;
use App\Services\Contracts\PopupReaderInterface;

final class PopupReader implements PopupReaderInterface
{
    public function __construct(private readonly PopupRepositoryInterface $popups)
    {
    }

    public function current(): ?Popup
    {
        $active = $this->popups->findActive();

        return $active?->isShowing() ? $active : null;
    }

    public function currentImage(int $id): ?array
    {
        return $this->current()?->id === $id ? $this->popups->image($id) : null;
    }
}
