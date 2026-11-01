<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DomainException;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SettingsServiceInterface;
use App\Validation\SiteSettingsValidator;

final class SiteSettingsController
{
    use AdminContext;

    public function __construct(
        private readonly SettingsServiceInterface $settings,
        private readonly SettingsReaderInterface $reader,
        private readonly SiteSettingsValidator $validator,
        private readonly LogoUploadReader $uploads,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->form($this->reader->site()->toArray(), []);
    }

    public function update(Request $request): Response
    {
        [$values, $errors] = $this->validator->validate($request->all());
        $logoBytes = $this->uploads->read($request->file('logo'), $errors);
        if ($errors !== []) {
            return $this->form([...$this->reader->site()->toArray(), ...$request->all()], $errors, 422);
        }

        $actor = $this->actor($request);
        try {
            if ($logoBytes !== null) {
                $this->settings->uploadLogo($logoBytes, $actor);
            } elseif ($request->input('remove_logo') === '1') {
                $this->settings->removeLogo($actor);
            }
        } catch (DomainException $e) {
            return $this->form([...$this->reader->site()->toArray(), ...$request->all()], [$e->field ?? 'logo' => $e->getMessage()], 422);
        }
        $this->settings->update($values, $actor);

        return $this->responder->redirectWith($request, '/admin/settings', 'success', 'Site settings saved.');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/settings/site', ['values' => $values, 'errors' => $errors], $status);
    }
}
