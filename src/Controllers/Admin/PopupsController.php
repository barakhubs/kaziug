<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DomainException;
use App\Domain\Popup;
use App\Services\Contracts\PopupServiceInterface;
use App\Services\ImageInspector;
use App\Validation\PopupValidator;

final class PopupsController
{
    use AdminContext;

    public function __construct(
        private readonly PopupServiceInterface $popups,
        private readonly PopupValidator $validator,
        private readonly LogoUploadReader $uploads,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view('admin/popups/index', ['popups' => $this->popups->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, ['trigger_type' => 'delay', 'delay_seconds' => '8', 'activate' => '1'], []);
    }

    public function store(Request $request): Response
    {
        [$input, $errors] = $this->validator->validate($request->all());
        $image = $this->uploads->read($request->file('image'), $errors, 'image', ImageInspector::MAX_COVER_BYTES);
        if ($input === null || $errors !== []) {
            return $this->form(null, $request->all(), $errors, 422);
        }

        try {
            $popup = $this->popups->create($input, $image, $this->actor($request));
        } catch (DomainException $e) {
            return $this->form(null, $request->all(), [$e->field ?? 'name' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, '/admin/popups', 'success', $popup->isActive ? 'Popup created and active.' : 'Popup created (inactive).');
    }

    public function edit(Request $request): Response
    {
        $popup = $this->popups->get($this->id($request));

        return $this->form($popup, self::valuesFrom($popup), []);
    }

    public function update(Request $request): Response
    {
        $popup = $this->popups->get($this->id($request));
        [$input, $errors] = $this->validator->validate($request->all());
        $image = $this->uploads->read($request->file('image'), $errors, 'image', ImageInspector::MAX_COVER_BYTES);
        if ($input === null || $errors !== []) {
            return $this->form($popup, $request->all(), $errors, 422);
        }

        try {
            $this->popups->update($popup->id, $input, $image, $request->input('remove_image') === '1', $this->actor($request));
        } catch (DomainException $e) {
            return $this->form($popup, $request->all(), [$e->field ?? 'name' => $e->getMessage()], 422);
        }

        return $this->responder->redirectWith($request, '/admin/popups', 'success', 'Popup saved.');
    }

    public function activate(Request $request): Response
    {
        $this->popups->activate($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/popups', 'success', 'Popup activated. Any other popup was switched off.');
    }

    public function deactivate(Request $request): Response
    {
        $this->popups->deactivate($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/popups', 'success', 'Popup switched off.');
    }

    public function delete(Request $request): Response
    {
        $this->popups->delete($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/popups', 'success', 'Popup deleted.');
    }

    /** @return array<string, string> */
    private static function valuesFrom(Popup $popup): array
    {
        return [
            'name' => $popup->name,
            'heading' => $popup->heading,
            'body_html' => $popup->bodyHtml,
            'button_label' => $popup->buttonLabel ?? '',
            'button_url' => $popup->buttonUrl ?? '',
            'trigger_type' => $popup->trigger->value,
            'delay_seconds' => (string) $popup->delaySeconds,
            'starts_at' => $popup->startsAt?->format('Y-m-d\TH:i') ?? '',
            'ends_at' => $popup->endsAt?->format('Y-m-d\TH:i') ?? '',
            'image_alt' => $popup->imageAlt,
            'activate' => $popup->isActive ? '1' : '',
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?Popup $popup, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/popups/form', ['popup' => $popup, 'values' => $values, 'errors' => $errors], $status);
    }
}
