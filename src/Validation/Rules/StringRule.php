<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class StringRule extends Rule
{
    protected $message = 'The :attribute must be a string.';

    public function check($value): bool
    {
        return $this->isEmpty($value) || is_string($value);
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

