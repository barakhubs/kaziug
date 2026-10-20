<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\SocialProfile;
use App\Services\Contracts\SocialProfileServiceInterface;
use App\Validation\SocialProfileValidator;

final class SocialProfilesController
{
    use AdminContext;

    public function __construct(
        private readonly SocialProfileServiceInterface $profiles,
        private readonly SocialProfileValidator $validator,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view('admin/social/index', ['profiles' => $this->profiles->all()]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null, ['is_visible' => '1', 'icon_key' => 'facebook'], []);
    }

    public function store(Request $request): Response
    {
        $result = $this->validator->validate($request->all());
        if ($result->fails()) {
            return $this->form(null, $request->all(), $result->errors, 422);
        }
        $this->profiles->create($result->data, $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/social', 'success', 'Social profile added.');
    }

    public function edit(Request $request): Response
    {
        $profile = $this->profiles->get($this->id($request));

        return $this->form($profile, [
            'platform' => $profile->platform,
            'label' => $profile->label,
            'url' => $profile->url,
            'icon_key' => $profile->icon->value,
            'is_visible' => $profile->isVisible ? '1' : '',
        ], []);
    }

    public function update(Request $request): Response
    {
        $profile = $this->profiles->get($this->id($request));
        $result = $this->validator->validate($request->all());
        if ($result->fails()) {
            return $this->form($profile, $request->all(), $result->errors, 422);
        }
        $this->profiles->update($profile->id, $result->data, $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/social', 'success', 'Social profile updated.');
    }

    public function visibility(Request $request): Response
    {
        $visible = $request->input('visible') === '1';
        $this->profiles->setVisible($this->id($request), $visible, $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/social', 'success', $visible ? 'Profile shown in the footer.' : 'Profile hidden from the footer.');
    }

    public function move(Request $request): Response
    {
        $this->profiles->move($this->id($request), $request->input('direction') === 'up' ? 'up' : 'down', $this->actor($request));

        return $this->responder->redirect('/admin/settings/social');
    }

    public function delete(Request $request): Response
    {
        $this->profiles->delete($this->id($request), $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/social', 'success', 'Social profile deleted.');
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?SocialProfile $profile, array $values, array $errors, int $status = 200): Response
    {
        return $this->responder->view('admin/social/form', ['profile' => $profile, 'values' => $values, 'errors' => $errors], $status);
    }
}
