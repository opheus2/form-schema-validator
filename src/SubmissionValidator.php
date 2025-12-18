<?php

declare(strict_types=1);

namespace FormSchema;

use InvalidArgumentException;

class SubmissionValidator
{
    /**
     * Validate a submission payload against a schema. Replacements are merged into the payload
     * before validation to supply contextual values not present on the form submission.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $replacements
     */
    public function validate(array $schema, array $payload, array $replacements = []): ValidationResult
    {
        $errors = [];
        $data = array_merge($payload, $replacements);

        $pages = $schema['form']['pages'] ?? [];
        foreach ($pages as $pi => $page) {
            foreach ($page['sections'] ?? [] as $si => $section) {
                foreach ($section['fields'] ?? [] as $fi => $field) {
                    $fieldPath = "form.pages[{$pi}].sections[{$si}].fields[{$fi}]";
                    $key = $field['key'] ?? null;
                    if ( ! is_string($key) || '' === $key) {
                        $errors["{$fieldPath}.key"] = 'Field key is required.';
                        continue;
                    }

                    $value = $data[$key] ?? null;
                    $required = (bool) ($field['required'] ?? false);
                    $rules = $field['validations'] ?? [];

                    $errors = array_merge(
                        $errors,
                        $this->validateField($key, $value, $required, $rules, $data)
                    );
                }
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $replacements
     */
    public function assertValid(array $schema, array $payload, array $replacements = []): void
    {
        $result = $this->validate($schema, $payload, $replacements);

        if ($result->isValid()) {
            return;
        }

        throw new InvalidArgumentException('Invalid submission: ' . json_encode($result->errors()));
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed>             $context
     *
     * @return array<string, string>
     */
    private function validateField(string $key, mixed $value, bool $required, array $rules, array $context): array
    {
        $errors = [];

        if ($required && $this->isEmpty($value)) {
            $errors[$key] = 'This field is required.';
            // continue evaluating rules for more detail
        }

        foreach ($rules as $rule) {
            $name = $rule['rule'] ?? null;
            if ( ! is_string($name)) {
                continue;
            }

            $params = $rule['params'] ?? [];
            $message = $rule['message'] ?? 'Validation failed.';

            if ($this->passes($name, $params, $value, $context)) {
                continue;
            }

            $errors[$key] = $message;
        }

        return $errors;
    }

    /**
     * @param array<int, mixed>    $params
     * @param array<string, mixed> $context
     */
    private function passes(string $rule, array $params, mixed $value, array $context): bool
    {
        $params = $this->normalizeParams($params);

        switch ($rule) {
            case 'required':
                return ! $this->isEmpty($value);

            case 'required_if':
            case 'required_unless':
            case 'required_if_accepted':
            case 'required_if_declined':
            case 'required_with':
            case 'required_with_all':
            case 'required_without':
            case 'required_without_all':
                return $this->passesRequiredVariants($rule, $params, $value, $context);

            case 'email':
                return $this->isEmpty($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'phone':
                return $this->isEmpty($value) || preg_match('/^[0-9 +().-]{6,}$/', (string) $value);

            case 'boolean':
                return $this->isEmpty($value) || is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);

            case 'numeric':
                return $this->isEmpty($value) || is_numeric($value);

            case 'string':
                return $this->isEmpty($value) || is_string($value);

            case 'min':
                $min = (float) ($params[0] ?? 0);
                return $this->isEmpty($value) || (is_numeric($value) ? (float) $value >= $min : mb_strlen((string) $value) >= $min);

            case 'max':
                $max = (float) ($params[0] ?? 0);
                return $this->isEmpty($value) || (is_numeric($value) ? (float) $value <= $max : mb_strlen((string) $value) <= $max);

            case 'between':
                $min = (float) ($params[0] ?? 0);
                $max = (float) ($params[1] ?? 0);
                return $this->isEmpty($value) || $this->between($value, $min, $max, true);

            case 'not_between':
                $min = (float) ($params[0] ?? 0);
                $max = (float) ($params[1] ?? 0);
                return $this->isEmpty($value) || ! $this->between($value, $min, $max, true);

            case 'in':
                $list = $params;
                return $this->isEmpty($value) || in_array($value, $list, true);

            case 'not_in':
                $list = $params;
                return $this->isEmpty($value) || ! in_array($value, $list, true);

            case 'before':
            case 'after':
                $target = $params[0] ?? null;
                $targetRef = $this->fieldRefKey($target);
                if (null !== $targetRef) {
                    $target = $context[$targetRef] ?? null;
                }
                $tsValue = $this->toTimestamp($value);
                $tsTarget = $this->toTimestamp($target);
                if (null === $tsValue || null === $tsTarget) {
                    return $this->isEmpty($value);
                }
                return 'before' === $rule ? $tsValue < $tsTarget : $tsValue > $tsTarget;

            case 'regex':
                $pattern = $params[0] ?? null;
                if (null === $pattern || ! is_string($pattern)) {
                    return true;
                }

                return $this->isEmpty($value) || preg_match($pattern, (string) $value) === 1;

            case 'starts_with':
                if ($this->isEmpty($value)) {
                    return true;
                }

                $prefixes = array_values(array_filter(
                    array_map(static fn (mixed $p): string => (string) $p, $params),
                    static fn (string $p): bool => '' !== $p
                ));

                if ([] === $prefixes) {
                    return true;
                }

                foreach ($prefixes as $prefix) {
                    if (str_starts_with((string) $value, $prefix)) {
                        return true;
                    }
                }

                return false;

            case 'ends_with':
                if ($this->isEmpty($value)) {
                    return true;
                }

                $suffixes = array_values(array_filter(
                    array_map(static fn (mixed $p): string => (string) $p, $params),
                    static fn (string $p): bool => '' !== $p
                ));

                if ([] === $suffixes) {
                    return true;
                }

                foreach ($suffixes as $suffix) {
                    if (str_ends_with((string) $value, $suffix)) {
                        return true;
                    }
                }

                return false;
        }

        // Unknown rule: consider it passed to avoid false negatives.
        return true;
    }

    /**
     * @param array<int, mixed>    $params
     * @param array<string, mixed> $context
     */
    private function passesRequiredVariants(string $rule, array $params, mixed $value, array $context): bool
    {
        if ( ! $this->isEmpty($value)) {
            return true;
        }

        $contextValue = fn (string $key): mixed => $context[$key] ?? null;

        return match ($rule) {
            'required_if' => (function () use ($params, $contextValue): bool {
                $otherKey = $this->normalizeFieldKey($params[0] ?? null);
                $targets = array_slice($params, 1);

                if (null === $otherKey || [] === $targets) {
                    return true;
                }

                $actual = $contextValue($otherKey);
                foreach ($targets as $target) {
                    if ($actual == $target) {
                        return false;
                    }
                }

                return true;
            })(),

            'required_unless' => (function () use ($params, $contextValue): bool {
                $otherKey = $this->normalizeFieldKey($params[0] ?? null);
                $targets = array_slice($params, 1);

                if (null === $otherKey || [] === $targets) {
                    return true;
                }

                $actual = $contextValue($otherKey);
                foreach ($targets as $target) {
                    if ($actual == $target) {
                        return true;
                    }
                }

                return false;
            })(),

            'required_if_accepted' => (function () use ($params, $contextValue): bool {
                $otherKey = $this->normalizeFieldKey($params[0] ?? null);
                if (null === $otherKey) {
                    return true;
                }

                return ! $this->isAccepted($contextValue($otherKey));
            })(),

            'required_if_declined' => (function () use ($params, $contextValue): bool {
                $otherKey = $this->normalizeFieldKey($params[0] ?? null);
                if (null === $otherKey) {
                    return true;
                }

                return ! $this->isDeclined($contextValue($otherKey));
            })(),

            'required_with' => (function () use ($params, $contextValue): bool {
                $keys = $this->normalizeFieldKeys($params);
                if ([] === $keys) {
                    return true;
                }

                foreach ($keys as $key) {
                    if ( ! $this->isEmpty($contextValue($key))) {
                        return false;
                    }
                }

                return true;
            })(),

            'required_with_all' => (function () use ($params, $contextValue): bool {
                $keys = $this->normalizeFieldKeys($params);
                if ([] === $keys) {
                    return true;
                }

                foreach ($keys as $key) {
                    if ($this->isEmpty($contextValue($key))) {
                        return true;
                    }
                }

                return false;
            })(),

            'required_without' => (function () use ($params, $contextValue): bool {
                $keys = $this->normalizeFieldKeys($params);
                if ([] === $keys) {
                    return true;
                }

                foreach ($keys as $key) {
                    if ($this->isEmpty($contextValue($key))) {
                        return false;
                    }
                }

                return true;
            })(),

            'required_without_all' => (function () use ($params, $contextValue): bool {
                $keys = $this->normalizeFieldKeys($params);
                if ([] === $keys) {
                    return true;
                }

                foreach ($keys as $key) {
                    if ( ! $this->isEmpty($contextValue($key))) {
                        return true;
                    }
                }

                return false;
            })(),

            default => true,
        };
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @return array<int, mixed>
     */
    private function normalizeParams(array $params): array
    {
        $params = array_values($params);

        if (count($params) === 1 && is_array($params[0])) {
            $params = array_values($params[0]);
        }

        return $params;
    }

    private function fieldRefKey(mixed $param): ?string
    {
        if ( ! is_string($param)) {
            return null;
        }

        if ( ! str_starts_with($param, '{field:') || ! str_ends_with($param, '}')) {
            return null;
        }

        $key = substr($param, 7, -1);

        return '' === $key ? null : $key;
    }

    private function normalizeFieldKey(mixed $param): ?string
    {
        if ( ! is_string($param)) {
            return null;
        }

        $refKey = $this->fieldRefKey($param);
        if (null !== $refKey) {
            return $refKey;
        }

        return '' === $param ? null : $param;
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    private function normalizeFieldKeys(array $params): array
    {
        $keys = [];

        foreach ($params as $param) {
            $key = $this->normalizeFieldKey($param);
            if (null === $key) {
                continue;
            }

            $keys[] = $key;
        }

        return array_values(array_unique($keys));
    }

    private function isEmpty(mixed $value): bool
    {
        return null === $value || $value === '' || $value === [];
    }

    private function between(mixed $value, float $min, float $max, bool $inclusive = false): bool
    {
        if (is_numeric($value)) {
            $val = (float) $value;
            return $inclusive ? ($val >= $min && $val <= $max) : ($val > $min && $val < $max);
        }

        $len = mb_strlen((string) $value);
        return $inclusive ? ($len >= $min && $len <= $max) : ($len > $min && $len < $max);
    }

    private function toTimestamp(mixed $value): ?int
    {
        if ($this->isEmpty($value)) {
            return null;
        }

        $ts = strtotime((string) $value);

        return false === $ts ? null : $ts;
    }

    private function isAccepted(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function isDeclined(mixed $value): bool
    {
        return in_array($value, [false, 0, '0', 'false', 'off', 'no'], true);
    }
}
