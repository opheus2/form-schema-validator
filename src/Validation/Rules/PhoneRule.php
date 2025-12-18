<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class PhoneRule extends Rule
{
    protected $message = 'The :attribute must be a valid phone number.';

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        return preg_match('/^[0-9 +().-]{6,}$/', (string) $value) === 1;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

