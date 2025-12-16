<?php

declare(strict_types=1);

namespace FormSchema;

use InvalidArgumentException;

class SchemaValidator
{
    private const ALLOWED_FIELD_TYPES = [
        'short-text',
        'text',
        'medium-text',
        'long-text',
        'file',
        'image',
        'video',
        'document',
        'options',
        'date',
        'time',
        'datetime',
        'number',
        'boolean',
        'tag',
        'rating',
        'url',
        'email',
        'phone',
        'address',
        'country',
        'divider',
        'spacing',
        'hidden',
    ];

    public function validate(array $schema): ValidationResult
    {
        $errors = [];

        if ( ! isset($schema['form']) || ! is_array($schema['form'])) {
            return new ValidationResult(['form' => 'Schema must include a form array.']);
        }

        $form = $schema['form'];
        $pages = $form['pages'] ?? null;
        if ( ! is_array($pages) || empty($pages)) {
            $errors['form.pages'] = 'Form must include at least one page.';
        } else {
            foreach ($pages as $pi => $page) {
                $errors = array_merge($errors, $this->validatePage($page, $pi));
            }
        }

        return new ValidationResult($errors);
    }

    public function assertValid(array $schema): void
    {
        $result = $this->validate($schema);
        if ($result->isValid()) {
            return;
        }

        throw new InvalidArgumentException('Invalid form schema: ' . json_encode($result->errors()));
    }

    private function validatePage(array $page, int $index): array
    {
        $errors = [];
        $path = "form.pages[{$index}]";

        if (empty($page['key'])) {
            $errors["{$path}.key"] = 'Page key is required.';
        }

        $sections = $page['sections'] ?? null;
        if ( ! is_array($sections)) {
            $errors["{$path}.sections"] = 'Sections must be an array.';
            return $errors;
        }

        foreach ($sections as $si => $section) {
            $errors = array_merge($errors, $this->validateSection($section, "{$path}.sections[{$si}]"));
        }

        return $errors;
    }

    private function validateSection(array $section, string $path): array
    {
        $errors = [];

        if (empty($section['key'])) {
            $errors["{$path}.key"] = 'Section key is required.';
        }

        $fields = $section['fields'] ?? null;
        if ( ! is_array($fields)) {
            $errors["{$path}.fields"] = 'Fields must be an array.';

            return $errors;
        }

        foreach ($fields as $fi => $field) {
            $errors = array_merge($errors, $this->validateField($field, "{$path}.fields[{$fi}]"));
        }

        return $errors;
    }

    private function validateField(array $field, string $path): array
    {
        $errors = [];

        if (empty($field['key'])) {
            $errors["{$path}.key"] = 'Field key is required.';
        }

        $type = $field['type'] ?? null;
        if ( ! is_string($type) || ! in_array($type, self::ALLOWED_FIELD_TYPES, true)) {
            $errors["{$path}.type"] = 'Field type is invalid or missing.';
        }

        if (('options' === $type) && empty($field['option_properties']['data'])) {
            $errors["{$path}.option_properties.data"] = 'Options field requires option_properties.data.';
        }

        return $errors;
    }
}
