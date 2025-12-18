<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class AfterRule extends Rule
{
    protected $message = 'The :attribute must be a date after :time.';

    protected $fillableParams = ['time'];

    public function check($value): bool
    {
        $this->requireParameters($this->fillableParams);

        if ($this->isEmpty($value)) {
            return true;
        }

        $target = $this->parameter('time');
        if (is_string($target)) {
            $targetFieldKey = $this->fieldRefKey($target);
            if (null !== $targetFieldKey) {
                $target = $this->getAttribute()?->getValue($targetFieldKey);
            }
        }

        $tsValue = $this->toTimestamp($value);
        $tsTarget = $this->toTimestamp($target);
        if (null === $tsValue || null === $tsTarget) {
            return false;
        }

        return $tsValue > $tsTarget;
    }

    private function fieldRefKey(string $param): ?string
    {
        if ( ! str_starts_with($param, '{field:') || ! str_ends_with($param, '}')) {
            return null;
        }

        $key = substr($param, 7, -1);

        return '' === $key ? null : $key;
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $ts = strtotime((string) $value);

        return false === $ts ? null : $ts;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

