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

        $pattern = $this->normalizePattern($regex);

        if (false === @preg_match($pattern, '')) {
            return false;
        }

        return 1 === preg_match($pattern, (string) $value);
    }

    private function normalizePattern(string $pattern): string
    {
        $trimmed = mb_trim($pattern);

        if ('' === $trimmed) {
            return '/^$/';
        }

        if ($this->isDelimitedRegex($trimmed)) {
            return $trimmed;
        }

        return $this->wrapRegex($trimmed);
    }

    private function isDelimitedRegex(string $pattern): bool
    {
        if (mb_strlen($pattern) < 3) {
            return false;
        }

        $delimiter = $pattern[0];
        if (ctype_alnum($delimiter) || '\\' === $delimiter || ' ' === $delimiter) {
            return false;
        }

        $last = mb_strrpos($pattern, $delimiter);

        return false !== $last && $last > 0;
    }

    private function wrapRegex(string $pattern): string
    {
        $delimiters = ['/', '#', '~', '%', '!'];

        foreach ($delimiters as $delimiter) {
            if ( ! str_contains($pattern, $delimiter)) {
                return $delimiter . $pattern . $delimiter;
            }
        }

        return '/' . str_replace('/', '\\/', $pattern) . '/';
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === mb_trim($value);
        }

        return null === $value || [] === $value;
    }
}
