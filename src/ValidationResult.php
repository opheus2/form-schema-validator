<?php

declare(strict_types=1);

namespace FormSchema;

class ValidationResult
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $errors;

    /**
     * @var array<string, mixed>
     */
    private array $valid;

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, mixed>  $valid
     */
    public function __construct(array $errors = [], array $valid = [])
    {
        $this->errors = $this->normalizeErrors($errors);
        $this->valid = $valid;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function valid(): array
    {
        if ( ! $this->isValid()) {
            return [];
        }

        return $this->valid;
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, array<int, string>>
     */
    private function normalizeErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $field => $value) {
            if (is_string($value)) {
                $normalized[$field] = [$value];

                continue;
            }

            if ( ! is_array($value)) {
                continue;
            }

            $messages = [];
            foreach ($value as $candidate) {
                if (is_string($candidate)) {
                    $messages[] = $candidate;
                }
            }

            if ([] !== $messages) {
                $normalized[$field] = array_values($messages);
            }
        }

        return $normalized;
    }
}
