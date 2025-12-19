<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use InvalidArgumentException;
use Rakit\Validation\Rule;

final class NumericComparisonRule extends Rule
{
    protected $message = 'The :attribute comparison is invalid.';

    protected $fillableParams = ['target'];

    public function __construct(
        private readonly string $operator,
        string $message,
    ) {
        $this->assertSupportedOperator($operator);
        $this->message = $message;
    }

    public function check($value): bool
    {
        $this->requireParameters($this->fillableParams);

        if ($this->isEmpty($value)) {
            return true;
        }

        $target = $this->parameter('target');
        if (is_string($target)) {
            $targetFieldKey = $this->fieldRefKey($target);
            if (null !== $targetFieldKey) {
                $this->setParameterText('target', $targetFieldKey);
                $target = $this->getAttribute()?->getValue($targetFieldKey);
            }
        }

        $numericValue = $this->toNumber($value);
        $numericTarget = $this->toNumber($target);

        if (null === $numericValue || null === $numericTarget) {
            return false;
        }

        return match ($this->operator) {
            '>' => $numericValue > $numericTarget,
            '>=' => $numericValue >= $numericTarget,
            '<' => $numericValue < $numericTarget,
            '<=' => $numericValue <= $numericTarget,
            default => false,
        };
    }

    private function fieldRefKey(string $param): ?string
    {
        if ( ! str_starts_with($param, '{field:') || ! str_ends_with($param, '}')) {
            return null;
        }

        $key = substr($param, 7, -1);

        return '' === $key ? null : $key;
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

    private function assertSupportedOperator(string $operator): void
    {
        $supported = ['>', '>=', '<', '<='];

        if (in_array($operator, $supported, true)) {
            return;
        }

        throw new InvalidArgumentException("Unsupported operator: {$operator}");
    }
}

