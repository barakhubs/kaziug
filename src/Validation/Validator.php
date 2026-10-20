<?php

declare(strict_types=1);

namespace App\Validation;

use App\Validation\Rules\BooleanRule;
use App\Validation\Rules\DateTimeRule;
use App\Validation\Rules\EmailRule;
use App\Validation\Rules\InRule;
use App\Validation\Rules\IntegerRule;
use App\Validation\Rules\MaxRule;
use App\Validation\Rules\MinRule;
use App\Validation\Rules\RequiredRule;
use App\Validation\Rules\SameRule;
use App\Validation\Rules\SlugRule;
use App\Validation\Rules\UrlRule;
use InvalidArgumentException;

/**
 * Validates input arrays against rule strings: ['email' => 'required|email|max:254'].
 * Empty optional fields skip their remaining rules and come back as null.
 * New rules are added with register() without touching this class (Open/Closed).
 */
final class Validator
{
    /** @var array<string, RuleInterface> */
    private array $rules = [];

    public function __construct()
    {
        foreach ([
            new RequiredRule(), new EmailRule(), new MaxRule(), new MinRule(), new IntegerRule(),
            new InRule(), new UrlRule(), new SameRule(), new BooleanRule(), new DateTimeRule(), new SlugRule(),
        ] as $rule) {
            $this->register($rule);
        }
    }

    public function register(RuleInterface $rule): void
    {
        $this->rules[$rule->name()] = $rule;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $definitions field => "rule|rule:param"
     */
    public function validate(array $input, array $definitions): ValidationResult
    {
        $data = [];
        $errors = [];

        foreach ($definitions as $field => $definition) {
            $value = $input[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $ruleList = explode('|', $definition);
            $isBoolean = in_array('boolean', $ruleList, true);

            if (!$isBoolean && !in_array('required', $ruleList, true) && ($value === null || $value === '')) {
                $data[$field] = null;
                continue;
            }

            foreach ($ruleList as $ruleString) {
                [$name, $parameter] = array_pad(explode(':', $ruleString, 2), 2, null);
                $rule = $this->rules[$name] ?? throw new InvalidArgumentException("Unknown validation rule: {$name}");
                $message = $rule->check($value, $parameter, $input);
                if ($message !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }

            $data[$field] = $isBoolean ? BooleanRule::toBool($value) : $value;
        }

        return new ValidationResult($data, $errors);
    }
}
