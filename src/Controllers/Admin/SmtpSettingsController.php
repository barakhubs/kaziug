<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\SmtpSettings;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use App\Validation\SmtpSettingsValidator;
use App\Validation\Validator;

final class SmtpSettingsController
{
    use AdminContext;

    public function __construct(
        private readonly SmtpSettingsServiceInterface $smtp,
        private readonly SmtpSettingsValidator $validator,
        private readonly Validator $plainValidator,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $settings = $this->smtp->settings();

        return $this->form($settings, self::values($settings), [], (string) $this->admin($request)->email);
    }

    public function update(Request $request): Response
    {
        [$fields, $errors] = $this->validator->validate($request->all());
        if ($errors !== []) {
            // The password is never echoed back, even after a failed submit.
            $values = $request->all();
            unset($values['password']);

            return $this->form($this->smtp->settings(), $values, $errors, $this->admin($request)->email, 422);
        }

        $this->smtp->update($fields, $request->string('password'), $request->input('clear_password') === '1', $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/smtp', 'success', 'SMTP settings saved. Send a test email to check them.');
    }

    public function test(Request $request): Response
    {
        $input = $this->plainValidator->validate($request->all(), ['test_to' => 'required|email']);
        if ($input->fails()) {
            return $this->responder->redirectWith($request, '/admin/settings/smtp', 'error', 'Enter a valid address to send the test email to.');
        }

        $result = $this->smtp->sendTest((string) $input->data['test_to'], $this->actor($request));

        return $this->responder->redirectWith($request, '/admin/settings/smtp', $result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** @return array<string, mixed> */
    private static function values(SmtpSettings $s): array
    {
        return [
            'host' => $s->host, 'port' => (string) $s->port, 'encryption' => $s->encryption, 'username' => $s->username,
            'from_name' => $s->fromName, 'from_email' => $s->fromEmail, 'reply_to' => $s->replyTo,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(SmtpSettings $settings, array $values, array $errors, string $adminEmail, int $status = 200): Response
    {
        return $this->responder->view('admin/settings/smtp', [
            'smtp' => $settings,
            'values' => $values,
            'errors' => $errors,
            'adminEmail' => $adminEmail,
        ], $status);
    }
}
