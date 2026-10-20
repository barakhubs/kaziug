<?php

declare(strict_types=1);

namespace App\Validation;

use App\Validation\Rules\IntegerRule;

final class SiteSettingsValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>} values typed for storage, errors
     */
    public function validate(array $input): array
    {
        $result = $this->validator->validate($input, [
            'site_name' => 'required|max:80',
            'tagline' => 'max:120',
            'contact_email' => 'email',
            'physical_address' => 'max:500',
            'footer_text' => 'max:500',
            'jobs_per_page' => 'required|integer:1,100',
            'meta_description' => 'max:300',
            'maintenance_mode' => 'boolean',
            'whatsapp_number' => 'max:30',
            'cv_service_price' => 'integer:0,100000000',
        ]);
        $errors = $result->errors;
        $data = $result->data;

        // WhatsApp links need the full international number: country code first, no leading 0.
        $whatsapp = preg_replace('/[\s\-().]/', '', (string) ($data['whatsapp_number'] ?? '')) ?? '';
        if ($whatsapp !== '' && !isset($errors['whatsapp_number']) && preg_match('/^\+?[1-9]\d{7,14}$/', $whatsapp) !== 1) {
            $errors['whatsapp_number'] = 'Use the international format with the country code, e.g. +256 772 123456.';
        }
        if ($errors !== []) {
            return [[], $errors];
        }

        return [[
            'site_name' => (string) $data['site_name'],
            'tagline' => (string) ($data['tagline'] ?? ''),
            'contact_email' => strtolower((string) ($data['contact_email'] ?? '')),
            'physical_address' => (string) ($data['physical_address'] ?? ''),
            'footer_text' => (string) ($data['footer_text'] ?? ''),
            'jobs_per_page' => (int) IntegerRule::toInt($data['jobs_per_page']),
            'meta_description' => (string) ($data['meta_description'] ?? ''),
            'maintenance_mode' => (bool) $data['maintenance_mode'],
            'whatsapp_number' => ltrim($whatsapp, '+'),
            'cv_service_price' => $data['cv_service_price'] === null ? 100000 : (int) IntegerRule::toInt($data['cv_service_price']),
        ], []];
    }
}
