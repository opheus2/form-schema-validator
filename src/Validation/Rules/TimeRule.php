<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

final class TimeRule extends Rule
{
    protected $message = 'The :attribute must be a valid time.';

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        if ( ! is_string($value)) {
            return false;
        }

        return preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(:[0-5]\\d)?$/', $value) === 1;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

