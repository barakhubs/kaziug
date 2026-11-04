<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\NotFoundException;
use App\Domain\Popup;
use App\Domain\PopupInput;
use App\Repositories\Contracts\PopupRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\PopupServiceInterface;

final class PopupService implements PopupServiceInterface
{
    public function __construct(
        private readonly PopupRepositoryInterface $popups,
        private readonly HtmlSanitizer $sanitizer,
        private readonly ImageInspector $images,
        private readonly TransactionManagerInterface $tx,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function all(): array
    {
        return $this->popups->all();
    }

    public function get(int $id): Popup
    {
        return $this->popups->findById($id) ?? throw new NotFoundException('Popup not found.');
    }

    public function create(PopupInput $input, ?string $imageBytes, Actor $actor): Popup
    {
        [$body, $mime] = $this->prepare($input, $imageBytes);

        $id = $this->tx->transactional(function () use ($input, $body, $mime, $imageBytes): int {
            $id = $this->popups->insert($input, $body);
            if ($mime !== null && $imageBytes !== null) {
                $this->popups->putImage($id, $mime, $imageBytes);
            }
            if ($input->activate) {
                $this->popups->activate($id);
            }

            return $id;
        });
        $this->audit->record($actor, 'popup.created', 'popup', $id, ['name' => $input->name, 'active' => $input->activate]);

        return $this->get($id);
    }

    public function update(int $id, PopupInput $input, ?string $imageBytes, bool $removeImage, Actor $actor): void
    {
        $current = $this->get($id);
        [$body, $mime] = $this->prepare($input, $imageBytes);

        $this->tx->transactional(function () use ($id, $input, $body, $mime, $imageBytes, $removeImage, $current): void {
            $this->popups->update($id, $input, $body, $input->showAgain);
            if ($mime !== null && $imageBytes !== null) {
                $this->popups->putImage($id, $mime, $imageBytes);
            } elseif ($removeImage) {
                $this->popups->removeImage($id);
            }
            if ($input->activate && !$current->isActive) {
                $this->popups->activate($id);
            } elseif (!$input->activate && $current->isActive) {
                $this->popups->deactivate($id);
            }
        });
        $this->audit->record($actor, 'popup.updated', 'popup', $id, array_filter([
            'name' => $input->name,
            'active' => $input->activate,
            'shown_again' => $input->showAgain ? true : null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function activate(int $id, Actor $actor): void
    {
        $popup = $this->get($id);
        $previous = $this->popups->findActive();
        $this->tx->transactional(fn () => $this->popups->activate($id));
        $this->audit->record($actor, 'popup.activated', 'popup', $id, array_filter([
            'name' => $popup->name,
            'replaced' => $previous !== null && $previous->id !== $id ? $previous->id : null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function deactivate(int $id, Actor $actor): void
    {
        $popup = $this->get($id);
        $this->popups->deactivate($id);
        $this->audit->record($actor, 'popup.deactivated', 'popup', $id, ['name' => $popup->name]);
    }

    public function delete(int $id, Actor $actor): void
    {
        $popup = $this->get($id);
        $this->popups->delete($id);
        $this->audit->record($actor, 'popup.deleted', 'popup', $id, ['name' => $popup->name]);
    }

    public function image(int $id): ?array
    {
        return $this->popups->image($id);
    }

    /** @return array{0: string, 1: ?string} the sanitised body and the new image's MIME type (null when none) */
    private function prepare(PopupInput $input, ?string $imageBytes): array
    {
        $body = $this->sanitizer->sanitize($input->bodyHtml);
        if (trim(strip_tags($body)) === '') {
            throw new DomainException('Write the popup message.', 'body_html');
        }

        return [$body, $imageBytes === null ? null : $this->images->assertAcceptablePopupImage($imageBytes)];
    }
}
