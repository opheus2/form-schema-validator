<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

final class EmailDomainsRule extends Rule
{
    protected $message = 'The :attribute domain is not allowed.';

    protected $fillableParams = ['allowed', 'disallowed'];

    public function check($value): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        if ( ! is_string($value)) {
            return false;
        }

        $domain = $this->emailDomain($value);
        if (null === $domain) {
            return false;
        }

        $allowed = $this->normalizeDomains($this->parameter('allowed'));
        $disallowed = $this->normalizeDomains($this->parameter('disallowed'));

        if ([] !== $allowed && ! in_array($domain, $allowed, true)) {
            $this->message = 'The :attribute must be an email address from an allowed domain.';

            return false;
        }

        if ([] !== $disallowed && in_array($domain, $disallowed, true)) {
            $this->message = 'The :attribute must not be an email address from a disallowed domain.';

            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeDomains(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if ( ! is_array($value)) {
            return [];
        }

        $domains = [];
        foreach ($value as $domain) {
            if ( ! is_string($domain)) {
                continue;
            }

            $domain = strtolower(trim($domain));
            if ('' === $domain) {
                continue;
            }

            $domains[] = $domain;
        }

        return array_values(array_unique($domains));
    }

    private function emailDomain(string $email): ?string
    {
        $at = strrpos($email, '@');
        if (false === $at) {
            return null;
        }

        $domain = strtolower(trim(substr($email, $at + 1)));

        return '' === $domain ? null : $domain;
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_string($value)) {
            return '' === trim($value);
        }

        return null === $value || [] === $value;
    }
}

