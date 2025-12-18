<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class EndsWithRule extends Rule
{
    protected $message = 'The :attribute must end with :suffixes.';

    public function fillParameters(array $params): Rule
    {
        if (count($params) === 1 && is_array($params[0])) {
            $params = $params[0];
        }

        $this->params['suffixes'] = $params;

        return $this;
    }

    public function check($value): bool
    {
        $this->requireParameters(['suffixes']);

        if ($this->isEmpty($value)) {
            return true;
        }

        $suffixes = array_values(array_filter(
            array_map(static fn (mixed $p): string => (string) $p, (array) $this->parameter('suffixes')),
            static fn (string $p): bool => '' !== $p
        ));

        if ([] === $suffixes) {
            return true;
        }

        $this->setParameterText('suffixes', implode(', ', $suffixes));

        foreach ($suffixes as $suffix) {
            if (str_ends_with((string) $value, $suffix)) {
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

