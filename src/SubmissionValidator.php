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
                $prefix = (string) ($params[0] ?? '');
                return $this->isEmpty($value) || str_starts_with((string) $value, $prefix);

            case 'ends_with':
                $suffix = (string) ($params[0] ?? '');
                return $this->isEmpty($value) || str_ends_with((string) $value, $suffix);
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
        $contextValue = fn (mixed $key): mixed => $context[$key] ?? null;

        return match ($rule) {
            'required_if' => $this->isEmpty($value)
                ? ! ($contextValue($params[0] ?? null) == ($params[1] ?? null))
                : true,

            'required_unless' => $this->isEmpty($value)
                ? ($contextValue($params[0] ?? null) == ($params[1] ?? null))
                : true,

            'required_if_accepted' => $this->isEmpty($value)
                ? ! $this->isAccepted($contextValue($params[0] ?? null))
                : true,

            'required_if_declined' => $this->isEmpty($value)
                ? ! $this->isDeclined($contextValue($params[0] ?? null))
                : true,

            'required_with' => $this->isEmpty($value)
                ? empty(array_filter((array) ($params[0] ?? []), fn ($k) => ! $this->isEmpty($contextValue($k)))) // if any present, fail
                : true,

            'required_with_all' => $this->isEmpty($value)
                ? count(array_filter((array) ($params[0] ?? []), fn ($k) => ! $this->isEmpty($contextValue($k)))) !== count((array) ($params[0] ?? []))
                : true,

            'required_without' => $this->isEmpty($value)
                ? ! empty(array_filter((array) ($params[0] ?? []), fn ($k) => $this->isEmpty($contextValue($k))))
                : true,

            'required_without_all' => $this->isEmpty($value)
                ? count(array_filter((array) ($params[0] ?? []), fn ($k) => ! $this->isEmpty($contextValue($k)))) > 0
                : true,

            default => true,
        };
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
