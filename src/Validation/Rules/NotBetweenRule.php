<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Traits\SizeTrait;

class NotBetweenRule extends Rule
{
    use SizeTrait;

    protected $message = 'The :attribute must not be between :min and :max.';

    protected $fillableParams = ['min', 'max'];

    public function check($value): bool
    {
        $this->requireParameters($this->fillableParams);

        if ($this->isEmpty($value)) {
            return true;
        }

        $min = $this->getBytesSize($this->parameter('min'));
        $max = $this->getBytesSize($this->parameter('max'));
        $valueSize = $this->getValueSize($value);

        if ( ! is_numeric($valueSize)) {
            return false;
        }

        return ! ($valueSize >= $min && $valueSize <= $max);
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

