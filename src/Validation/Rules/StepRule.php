<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class StepRule extends Rule
{
    protected $message = 'The :attribute value is not aligned with the required step of :step.';

    protected $fillableParams = ['step'];

    public function check($value): bool
    {
        $this->requireParameters($this->fillableParams);

        if ($this->isEmpty($value)) {
            return true;
        }

        $step = $this->toNumber($this->parameter('step'));
        $numericValue = $this->toNumber($value);

        if (null === $step || $step <= 0 || null === $numericValue) {
            return false;
        }

        $ratio = $numericValue / $step;
        $nearest = round($ratio);

        return abs($ratio - $nearest) < 1.0E-9;
    }

    private function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = mb_trim($value);
            if ('' === $trimmed || ! is_numeric($trimmed)) {
                return null;
            }

            return (float) $trimmed;
        }

        return null;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === mb_trim($value);
        }

        return null === $value || [] === $value;
    }
}
