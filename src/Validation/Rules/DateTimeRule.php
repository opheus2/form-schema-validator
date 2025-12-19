<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use DateTime;
use Rakit\Validation\Rule;

final class DateTimeRule extends Rule
{
    protected $message = 'The :attribute must be a valid date and time.';

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        if ( ! is_string($value)) {
            return false;
        }

        $value = trim($value);

        $formats = [
            'Y-m-d\\TH:i',
            'Y-m-d\\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if (false === $dt) {
                continue;
            }

            if ($dt->format($format) === $value) {
                return true;
            }
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

