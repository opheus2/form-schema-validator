<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class RegexRule extends Rule
{
    protected $message = 'The :attribute is not valid format.';

    protected $fillableParams = ['regex'];

    public function check($value): bool
    {
        $this->requireParameters($this->fillableParams);

        if ($this->isEmpty($value)) {
            return true;
        }

        $regex = $this->parameter('regex');
        if ( ! is_string($regex) || '' === $regex) {
            return true;
        }

        return preg_match($regex, (string) $value) === 1;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

