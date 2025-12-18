<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class StartsWithRule extends Rule
{
    protected $message = 'The :attribute must start with :prefixes.';

    public function fillParameters(array $params): Rule
    {
        if (count($params) === 1 && is_array($params[0])) {
            $params = $params[0];
        }

        $this->params['prefixes'] = $params;

        return $this;
    }

    public function check($value): bool
    {
        $this->requireParameters(['prefixes']);

        if ($this->isEmpty($value)) {
            return true;
        }

        $prefixes = array_values(array_filter(
            array_map(static fn (mixed $p): string => (string) $p, (array) $this->parameter('prefixes')),
            static fn (string $p): bool => '' !== $p
        ));

        if ([] === $prefixes) {
            return true;
        }

        $this->setParameterText('prefixes', implode(', ', $prefixes));

        foreach ($prefixes as $prefix) {
            if (str_starts_with((string) $value, $prefix)) {
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

