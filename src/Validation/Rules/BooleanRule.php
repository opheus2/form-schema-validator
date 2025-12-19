<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

final class BooleanRule extends Rule
{
    protected $message = 'The :attribute must be a boolean.';

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        if (is_bool($value)) {
            return true;
        }

        if (is_int($value)) {
            return in_array($value, [0, 1], true);
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array(
                $normalized,
                ['true', 'false', '0', '1', 'y', 'n', 'yes', 'no', 'on', 'off'],
                true,
            );
        }

        return false;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}
