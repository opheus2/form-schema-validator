<?php

declare(strict_types=1);

namespace FormSchema;

use InvalidArgumentException;
use FormSchema\Validation\Rules\AfterRule;
use FormSchema\Validation\Rules\BeforeRule;
use FormSchema\Validation\Rules\EndsWithRule;
use FormSchema\Validation\Rules\NotBetweenRule;
use FormSchema\Validation\Rules\PhoneRule;
use FormSchema\Validation\Rules\RegexRule;
use FormSchema\Validation\Rules\RequiredIfAcceptedRule;
use FormSchema\Validation\Rules\RequiredIfDeclinedRule;
use FormSchema\Validation\Rules\RequiredWithAllNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithoutAllNonEmptyRule;
use FormSchema\Validation\Rules\RequiredWithoutNonEmptyRule;
use FormSchema\Validation\Rules\StartsWithRule;
use FormSchema\Validation\Rules\StringRule;
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
}
