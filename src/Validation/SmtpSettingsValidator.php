<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\SmtpSettings;

final class SmtpSettingsValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function validate(array $input): array
    {
        $result = $this->validator->validate($input, [
            'host' => 'required|max:255',
            'port' => 'required|integer:1,65535',
            'encryption' => 'required|in:' . implode(',', array_keys(SmtpSettings::ENCRYPTIONS)),
            'username' => 'max:255',
            'password' => 'max:500',
            'from_name' => 'max:100',
            'from_email' => 'required|email',
            'reply_to' => 'email',
        ]);
        $errors = $result->errors;
        $data = $result->data;

        if (!isset($errors['host']) && preg_match('/^[A-Za-z0-9.\-]+$|^\[?[0-9a-fA-F:.]+\]?$/', (string) $data['host']) !== 1) {
            $errors['host'] = 'Enter a host name such as smtp.example.com.';
        }
        // Header values must never contain line breaks (header injection).
        foreach (['from_name', 'username'] as $field) {
            if (!isset($errors[$field]) && preg_match('/[\r\n]/', (string) ($data[$field] ?? '')) === 1) {
                $errors[$field] = 'Line breaks are not allowed.';
            }
        }
        if ($errors !== []) {
            return [[], $errors];
        }

        return [[
            'host' => strtolower((string) $data['host']),
            'port' => (int) $data['port'],
            'encryption' => (string) $data['encryption'],
            'username' => (string) ($data['username'] ?? ''),
            'from_name' => (string) ($data['from_name'] ?? ''),
            'from_email' => strtolower((string) $data['from_email']),
            'reply_to' => strtolower((string) ($data['reply_to'] ?? '')),
        ], []];
    }
}
