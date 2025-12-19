<?php

declare(strict_types=1);

namespace FormSchema;

use InvalidArgumentException;
use FormSchema\Validation\Rules\AfterRule;
use FormSchema\Validation\Rules\BeforeRule;
use FormSchema\Validation\Rules\BooleanRule;
use FormSchema\Validation\Rules\DateTimeRule;
use FormSchema\Validation\Rules\EmailDomainsRule;
use FormSchema\Validation\Rules\EndsWithRule;
use FormSchema\Validation\Rules\FileConstraintsRule;
use FormSchema\Validation\Rules\NotBetweenRule;
use FormSchema\Validation\Rules\NumericComparisonRule;
use FormSchema\Validation\Rules\PhoneRule;
use FormSchema\Validation\Rules\RegexRule;
use FormSchema\Validation\Rules\RequiredIfAcceptedRule;
use FormSchema\Validation\Rules\RequiredIfDeclinedRule;
use FormSchema\Validation\Rules\RequiredWithAllNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithoutAllNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithoutNonEmptyRule;
use FormSchema\Validation\Rules\StepRule;
use FormSchema\Validation\Rules\StartsWithRule;
use FormSchema\Validation\Rules\StringRule;
use FormSchema\Validation\Rules\TimeRule;
use Rakit\Validation\Rule;
use Rakit\Validation\RuleNotFoundException;
use Rakit\Validation\Validator;

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
        $rules = [];
        $messages = [];

        $validator = $this->makeValidator();

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

                    $fieldRules = [];

                    if ((bool) ($field['required'] ?? false)) {
                        $fieldRules[] = 'required';
                        $messages["{$key}:required"] = 'This field is required.';
                    }

                    $extraRules = [];
                    $this->applyConstraints(
                        $validator,
                        $field,
                        $key,
                        $data,
                        $fieldRules,
                        $extraRules,
                    );

                    $validations = $field['validations'] ?? [];
                    if (is_array($validations)) {
                        foreach ($validations as $rule) {
                            $name = $rule['rule'] ?? null;
                            if ( ! is_string($name) || '' === $name) {
                                continue;
                            }

                            $params = $this->normalizeParams($rule['params'] ?? []);
                            $mapped = $this->toRakitRule($validator, $name, $params);
                            if (null === $mapped) {
                                continue;
                            }

                            $fieldRules[] = $mapped;

                            $message = $rule['message'] ?? null;
                            if (is_string($message) && '' !== $message) {
                                $messages["{$key}:{$name}"] = $message;
                            }
                        }
                    }

                    if ([] !== $fieldRules) {
                        $rules[$key] = $fieldRules;
                    }

                    foreach ($extraRules as $extraKey => $extraRuleList) {
                        if ([] === $extraRuleList) {
                            continue;
                        }

                        $rules[$extraKey] = array_merge($rules[$extraKey] ?? [], $extraRuleList);
                    }
                }
            }
        }

        if ([] !== $rules) {
            $validation = $validator->make($data, $rules, $messages);
            $validation->validate();

            if ($validation->fails()) {
                $errors = array_merge($errors, $validation->errors()->firstOfAll());
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

    private function makeValidator(): Validator
    {
        $validator = new Validator();
        $validator->allowRuleOverride(true);

        $validator->setValidator('boolean', new BooleanRule());
        $validator->setValidator('after', new AfterRule());
        $validator->setValidator('before', new BeforeRule());
        $validator->setValidator('regex', new RegexRule());
        $validator->setValidator('required_with', new RequiredWithNonEmptyRule());
        $validator->setValidator('required_with_all', new RequiredWithAllNonEmptyRule());
        $validator->setValidator('required_without', new RequiredWithoutNonEmptyRule());
        $validator->setValidator('required_without_all', new RequiredWithoutAllNonEmptyRule());

        $validator->addValidator('phone', new PhoneRule());
        $validator->addValidator('string', new StringRule());
        $validator->addValidator('not_between', new NotBetweenRule());
        $validator->addValidator('starts_with', new StartsWithRule());
        $validator->addValidator('ends_with', new EndsWithRule());
        $validator->addValidator('required_if_accepted', new RequiredIfAcceptedRule());
        $validator->addValidator('required_if_declined', new RequiredIfDeclinedRule());
        $validator->addValidator('gt', new NumericComparisonRule('>', 'The :attribute must be greater than :target.'));
        $validator->addValidator('gte', new NumericComparisonRule('>=', 'The :attribute must be greater than or equal to :target.'));
        $validator->addValidator('lt', new NumericComparisonRule('<', 'The :attribute must be less than :target.'));
        $validator->addValidator('lte', new NumericComparisonRule('<=', 'The :attribute must be less than or equal to :target.'));
        $validator->addValidator('time', new TimeRule());
        $validator->addValidator('datetime', new DateTimeRule());
        $validator->addValidator('email_domains', new EmailDomainsRule());
        $validator->addValidator('step', new StepRule());

        return $validator;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function toRakitRule(Validator $validator, string $rule, array $params): Rule|string|null
    {
        $params = $this->normalizeParams($params);

        switch ($rule) {
            case 'in':
            case 'not_in':
                if ([] === $params) {
                    return null;
                }

                /** @var \Rakit\Validation\Rules\In|\Rakit\Validation\Rules\NotIn $ruleObject */
                $ruleObject = $validator($rule, ...$params);
                $ruleObject->strict(true);

                return $ruleObject;

            case 'required_if':
            case 'required_unless':
                $fieldKey = $this->normalizeFieldKey($params[0] ?? null);
                $targets = array_slice($params, 1);
                if (null === $fieldKey || [] === $targets) {
                    return null;
                }

                return $validator($rule, $fieldKey, ...$targets);

            case 'required_if_accepted':
            case 'required_if_declined':
                $fieldKey = $this->normalizeFieldKey($params[0] ?? null);
                if (null === $fieldKey) {
                    return null;
                }

                return $validator($rule, $fieldKey);

            case 'required_with':
            case 'required_with_all':
            case 'required_without':
            case 'required_without_all':
                $fieldKeys = $this->normalizeFieldKeys($params);
                if ([] === $fieldKeys) {
                    return null;
                }

                return $validator($rule, ...$fieldKeys);

            case 'min':
            case 'max':
            case 'gt':
            case 'gte':
            case 'lt':
            case 'lte':
            case 'before':
            case 'after':
            case 'regex':
                if ( ! isset($params[0])) {
                    return null;
                }

                return $validator($rule, $params[0]);

            case 'between':
            case 'not_between':
                if ( ! isset($params[0], $params[1])) {
                    return null;
                }

                return $validator($rule, $params[0], $params[1]);

            case 'starts_with':
            case 'ends_with':
                if ([] === $params) {
                    return null;
                }

                return $validator($rule, ...$params);

            default:
                try {
                    return $validator($rule, ...$params);
                } catch (RuleNotFoundException) {
                    return null;
                }
        }
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

    /**
     * @param array<string, mixed> $field
     * @param array<string, mixed> $data
     * @param array<int, Rule|string> $fieldRules
     * @param array<string, array<int, Rule|string>> $extraRules
     */
    private function applyConstraints(
        Validator $validator,
        array $field,
        string $key,
        array &$data,
        array &$fieldRules,
        array &$extraRules,
    ): void {
        $type = $field['type'] ?? null;
        if ( ! is_string($type) || '' === $type) {
            return;
        }

        if (in_array($type, ['divider', 'spacing'], true)) {
            return;
        }

        $constraints = $field['constraints'] ?? [];
        if ( ! is_array($constraints)) {
            $constraints = [];
        }

        if (in_array($type, ['file', 'image', 'video', 'document'], true)) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->normalizeFileValue($data[$key]);
            }

            $fieldRules[] = new FileConstraintsRule(
                $this->normalizeStringList($constraints['accept'] ?? []),
                (bool) ($constraints['allow_multiple'] ?? false),
                $this->toIntOrNull($constraints['min'] ?? null),
                $this->toIntOrNull($constraints['max'] ?? null),
                $this->toIntOrNull($constraints['max_file_size'] ?? null),
                $this->toIntOrNull($constraints['max_total_size'] ?? null),
            );

            return;
        }

        // Type-driven baseline validators
        switch ($type) {
            case 'short-text':
            case 'text':
            case 'medium-text':
            case 'long-text':
            case 'address':
            case 'country':
                $fieldRules[] = 'string';
                break;

            case 'email':
                $fieldRules[] = 'email';
                $fieldRules[] = 'string';
                break;

            case 'phone':
                $fieldRules[] = 'phone';
                $fieldRules[] = 'string';
                break;

            case 'url':
                $fieldRules[] = 'url';
                $fieldRules[] = 'string';
                break;

            case 'number':
            case 'rating':
                $fieldRules[] = 'numeric';
                break;

            case 'boolean':
                $fieldRules[] = 'boolean';
                break;

            case 'date':
                $fieldRules[] = 'date';
                break;

            case 'time':
                $fieldRules[] = 'time';
                break;

            case 'datetime':
                $fieldRules[] = 'datetime';
                break;

            case 'tag':
                $fieldRules[] = 'array';
                break;
        }

        // Text length constraints
        if (array_key_exists('min_length', $constraints) && is_numeric($constraints['min_length'])) {
            $rule = $validator('min', $constraints['min_length']);
            $fieldRules[] = $rule;
        }

        if (array_key_exists('max_length', $constraints) && is_numeric($constraints['max_length'])) {
            $rule = $validator('max', $constraints['max_length']);
            $fieldRules[] = $rule;
        }

        // Numeric/count constraints
        if (array_key_exists('min', $constraints) && is_numeric($constraints['min'])) {
            if (in_array($type, ['number', 'rating', 'tag', 'options'], true)) {
                $fieldRules[] = $validator('min', $constraints['min']);
            }
        }

        if (array_key_exists('max', $constraints) && is_numeric($constraints['max'])) {
            if (in_array($type, ['number', 'rating', 'tag', 'options'], true)) {
                $fieldRules[] = $validator('max', $constraints['max']);
            }
        }

        // Step constraint (numbers only)
        if ('number' === $type && array_key_exists('step', $constraints) && is_numeric($constraints['step'])) {
            $base = (array_key_exists('min', $constraints) && is_numeric($constraints['min']))
                ? $constraints['min']
                : 0;

            $fieldRules[] = $validator('step', $constraints['step'], $base);
        }

        // Email domain constraints
        if ('email' === $type) {
            $allowed = $this->normalizeStringList($constraints['allowed_domains'] ?? []);
            $disallowed = $this->normalizeStringList($constraints['disallowed_domains'] ?? []);

            if ([] !== $allowed || [] !== $disallowed) {
                $fieldRules[] = $validator('email_domains', $allowed, $disallowed);
            }
        }

        // Country allow/exclude constraints
        if ('country' === $type) {
            $allowCountries = $this->normalizeStringList($constraints['allow_countries'] ?? []);
            if ([] !== $allowCountries) {
                $rule = $this->toRakitRule($validator, 'in', $allowCountries);
                if (null !== $rule) {
                    $fieldRules[] = $rule;
                }
            }

            $excludeCountries = $this->normalizeStringList($constraints['exclude_countries'] ?? []);
            if ([] !== $excludeCountries) {
                $rule = $this->toRakitRule($validator, 'not_in', $excludeCountries);
                if (null !== $rule) {
                    $fieldRules[] = $rule;
                }
            }
        }

        // Options selection constraints
        if ('options' === $type) {
            $optionProps = $field['option_properties'] ?? null;
            if (is_array($optionProps)) {
                $optionType = $optionProps['type'] ?? 'select';
                if ( ! is_string($optionType) || '' === $optionType) {
                    $optionType = 'select';
                }

                $allowedKeys = [];
                $optionData = $optionProps['data'] ?? [];
                if (is_array($optionData)) {
                    foreach ($optionData as $opt) {
                        if (is_array($opt) && isset($opt['key']) && is_string($opt['key']) && '' !== $opt['key']) {
                            $allowedKeys[] = $opt['key'];
                        }
                    }
                }

                $allowedKeys = array_values(array_unique($allowedKeys));

                if ([] !== $allowedKeys) {
                    if (in_array($optionType, ['select', 'radio'], true)) {
                        $rule = $this->toRakitRule($validator, 'in', $allowedKeys);
                        if (null !== $rule) {
                            $fieldRules[] = $rule;
                        }
                    } elseif (in_array($optionType, ['multi-select', 'checkbox'], true)) {
                        $fieldRules[] = 'array';

                        $maxSelect = $this->toIntOrNull($optionProps['max_select'] ?? null);
                        if (null !== $maxSelect) {
                            $fieldRules[] = $validator('max', $maxSelect);
                        }

                        $rule = $this->toRakitRule($validator, 'in', $allowedKeys);
                        if (null !== $rule) {
                            $extraRules["{$key}.*"] ??= [];
                            $extraRules["{$key}.*"][] = $rule;
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if ( ! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if ( ! is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ('' === $item) {
                continue;
            }

            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed) {
                return null;
            }

            return is_numeric($trimmed) ? (int) $trimmed : null;
        }

        return null;
    }

    private function normalizeFileValue(mixed $value): mixed
    {
        if ( ! is_array($value)) {
            return $value;
        }

        if (array_key_exists('error', $value) && is_numeric($value['error']) && (int) $value['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (array_keys($value) !== range(0, count($value) - 1)) {
            return $value;
        }

        $filtered = [];
        foreach ($value as $item) {
            if (is_array($item) && array_key_exists('error', $item) && is_numeric($item['error']) && (int) $item['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $filtered[] = $item;
        }

        return [] === $filtered ? null : $filtered;
    }
}
