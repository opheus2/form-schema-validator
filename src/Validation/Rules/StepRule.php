<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

final class StepRule extends Rule
{
    protected $message = 'The :attribute must be in increments of :step.';

    public function fillParameters(array $params): Rule
    {
        $step = array_shift($params);
        $base = array_shift($params);

        $this->params['step'] = $step;
        $this->params['base'] = $base ?? 0;

        return $this;
    }

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        $step = $this->toNumber($this->parameter('step'));
        if (null === $step || $step <= 0) {
            return false;
        }

        $base = $this->toNumber($this->parameter('base')) ?? 0.0;
        $numericValue = $this->toNumber($value);
        if (null === $numericValue) {
            return false;
        }

        $quotient = ($numericValue - $base) / $step;
        $rounded = round($quotient);

        return abs($quotient - $rounded) < 1e-9;
    }

    private function toNumber(mixed $value): ?float
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed) {
                return null;
            }

            return is_numeric($trimmed) ? (float) $trimmed : null;
        }

        return null;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

