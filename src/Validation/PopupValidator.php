<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\PopupInput;
use App\Domain\PopupTrigger;
use App\Validation\Rules\DateTimeRule;
use App\Validation\Rules\IntegerRule;

/** Turns the popup form into a PopupInput. */
final class PopupValidator
{
    public const MAX_DELAY = 120;

    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: ?PopupInput, 1: array<string, string>}
     */
    public function validate(array $input): array
    {
        foreach (['name', 'heading', 'button_label', 'button_url', 'image_alt'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim((string) preg_replace('/\s+/u', ' ', $input[$field]));
            }
        }

        $result = $this->validator->validate($input, [
            'name' => 'required|max:120',
            'heading' => 'required|max:150',
            'body_html' => 'required|max:20000',
            'button_label' => 'max:60',
            'button_url' => 'max:2048',
            'trigger_type' => 'required|in:' . implode(',', PopupTrigger::values()),
            'delay_seconds' => 'integer:0,' . self::MAX_DELAY,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'image_alt' => 'max:200',
            'activate' => 'boolean',
            'show_again' => 'boolean',
        ]);
        $data = $result->data;
        $errors = $result->errors;
        if (isset($errors['body_html']) && in_array($data['body_html'] ?? null, [null, ''], true)) {
            $errors['body_html'] = 'Write the popup message.';
        }
        if (isset($errors['delay_seconds'])) {
            $errors['delay_seconds'] = 'Enter a number of seconds from 0 to ' . self::MAX_DELAY . '.';
        }

        $label = $data['button_label'] === null ? null : (string) $data['button_label'];
        $url = $data['button_url'] === null ? null : (string) $data['button_url'];
        if ($label !== null && $url === null) {
            $errors['button_url'] ??= 'Add the link the button opens.';
        } elseif ($url !== null && $label === null) {
            $errors['button_label'] ??= 'Add the button text.';
        } elseif ($url !== null && !self::isSafeLink($url)) {
            $errors['button_url'] ??= 'Use a page on this site such as /cv-help, or a full address starting with https://';
        }

        $startsAt = isset($errors['starts_at']) ? null : DateTimeRule::parse($data['starts_at'] === null ? null : (string) $data['starts_at']);
        $endsAt = isset($errors['ends_at']) ? null : DateTimeRule::parse($data['ends_at'] === null ? null : (string) $data['ends_at']);
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors['ends_at'] ??= 'The end must be after the start.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new PopupInput(
            (string) $data['name'],
            (string) $data['heading'],
            (string) $data['body_html'],
            $label,
            $url,
            PopupTrigger::from((string) $data['trigger_type']),
            IntegerRule::toInt($data['delay_seconds']) ?? 8,
            $startsAt,
            $endsAt,
            (string) ($data['image_alt'] ?? ''),
            (bool) $data['activate'],
            (bool) $data['show_again'],
        ), []];
    }

    /** A same-site path ("/cv-help", not "//evil.example") or an absolute http(s) URL. */
    private static function isSafeLink(string $url): bool
    {
        if (preg_match('#^/(?![/\\\\])[^\s]*$#', $url) === 1) {
            return true;
        }

        return preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
