<?php

declare(strict_types=1);

use FormSchema\SubmissionValidator;
use PHPUnit\Framework\TestCase;

class SubmissionValidationRulesTest extends TestCase
{
    private function validator(): SubmissionValidator
    {
        return new SubmissionValidator();
    }

    private function schemaForField(array $field): array
    {
        return [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [$field],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testStringRules(): void
    {
        $field = [
            'key' => 'text',
            'type' => 'short-text',
            'required' => true,
            'validations' => [
                ['rule' => 'min', 'params' => [3]],
                ['rule' => 'max', 'params' => [5]],
                ['rule' => 'between', 'params' => [3, 5]],
                ['rule' => 'starts_with', 'params' => ['he']],
                ['rule' => 'ends_with', 'params' => ['lo']],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['text' => 'hello']);
        $this->assertTrue($result->isValid());
    }

    public function testNumericRules(): void
    {
        $field = [
            'key' => 'age',
            'type' => 'number',
            'required' => true,
            'validations' => [
                ['rule' => 'numeric'],
                ['rule' => 'min', 'params' => [18]],
                ['rule' => 'max', 'params' => [30]],
                ['rule' => 'between', 'params' => [18, 30]],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['age' => 25]);
        $this->assertTrue($result->isValid());
    }

    public function testNumericGreaterAndLessThanRules(): void
    {
        $schema = $this->schemaForField([
            'key' => 'age',
            'type' => 'number',
            'required' => true,
            'validations' => [
                ['rule' => 'numeric'],
                ['rule' => 'gt', 'params' => [18]],
                ['rule' => 'lt', 'params' => [30]],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['age' => 19])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['age' => 29])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['age' => 18])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['age' => 30])->isValid());
    }

    public function testNumericGreaterOrEqualAndLessOrEqualRules(): void
    {
        $schema = $this->schemaForField([
            'key' => 'age',
            'type' => 'number',
            'required' => true,
            'validations' => [
                ['rule' => 'numeric'],
                ['rule' => 'gte', 'params' => [18]],
                ['rule' => 'lte', 'params' => [30]],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['age' => 18])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['age' => 30])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['age' => 17])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['age' => 31])->isValid());
    }

    public function testNumericComparisonRulesSupportFieldRefs(): void
    {
        $schema = $this->schemaForField([
            'key' => 'b',
            'type' => 'number',
            'required' => true,
            'validations' => [
                ['rule' => 'numeric'],
                ['rule' => 'gt', 'params' => ['{field:a}']],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['a' => 5, 'b' => 6])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['a' => 5, 'b' => 5])->isValid());
    }

    public function testBooleanRule(): void
    {
        $field = [
            'key' => 'active',
            'type' => 'boolean',
            'validations' => [
                ['rule' => 'boolean'],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['active' => true]);
        $this->assertTrue($result->isValid());
    }

    public function testEmailRule(): void
    {
        $field = [
            'key' => 'email',
            'type' => 'email',
            'validations' => [
                ['rule' => 'email'],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['email' => 'user@example.com']);
        $this->assertTrue($result->isValid());
    }

    public function testInAndNotInRules(): void
    {
        $field = [
            'key' => 'color',
            'type' => 'short-text',
            'validations' => [
                ['rule' => 'in', 'params' => ['red', 'blue']],
                ['rule' => 'not_in', 'params' => ['green']],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['color' => 'red']);
        $this->assertTrue($result->isValid());
    }

    public function testRegexRule(): void
    {
        $field = [
            'key' => 'slug',
            'type' => 'short-text',
            'validations' => [
                ['rule' => 'regex', 'params' => ['/^[-a-z0-9]+$/i']],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['slug' => 'abc-123']);
        $this->assertTrue($result->isValid());
    }

    public function testDateComparisons(): void
    {
        $field = [
            'key' => 'start',
            'type' => 'date',
            'validations' => [
                ['rule' => 'before', 'params' => ['2025-01-01']],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['start' => '2024-12-31']);
        $this->assertTrue($result->isValid());
    }

    public function testRequiredVariants(): void
    {
        $field = [
            'key' => 'comment',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_if', 'params' => ['flag', true]],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['flag' => false]);
        $this->assertTrue($result->isValid()); // not required when flag is false

        $failed = $this->validator()->validate($schema, ['flag' => true]);
        $this->assertFalse($failed->isValid());
    }

    public function testRequiredVariantsSupportMultipleParamsAndFieldRefs(): void
    {
        $field = [
            'key' => 'comment',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_if', 'params' => ['{field:flag}', 'yes', 'y']],
            ],
        ];

        $schema = $this->schemaForField($field);

        $notRequired = $this->validator()->validate($schema, ['flag' => 'no']);
        $this->assertTrue($notRequired->isValid());

        $required = $this->validator()->validate($schema, ['flag' => 'yes']);
        $this->assertFalse($required->isValid());
        $this->assertArrayHasKey('comment', $required->errors());
    }

    public function testRequiredWithAndWithoutSupportMultipleFieldsAndFieldRefs(): void
    {
        // required_with: required when any present
        $schemaRequiredWith = $this->schemaForField([
            'key' => 'note',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_with', 'params' => ['{field:a}', '{field:b}']],
            ],
        ]);
        $this->assertTrue($this->validator()->validate($schemaRequiredWith, [])->isValid());
        $this->assertFalse($this->validator()->validate($schemaRequiredWith, ['a' => 1])->isValid());
        $this->assertTrue($this->validator()->validate($schemaRequiredWith, ['a' => 1, 'note' => 'x'])->isValid());

        // required_with_all: required only when all present
        $schemaRequiredWithAll = $this->schemaForField([
            'key' => 'note',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_with_all', 'params' => ['{field:a}', '{field:b}']],
            ],
        ]);
        $this->assertTrue($this->validator()->validate($schemaRequiredWithAll, ['a' => 1])->isValid());
        $this->assertFalse($this->validator()->validate($schemaRequiredWithAll, ['a' => 1, 'b' => 2])->isValid());

        // required_without: required when any missing
        $schemaRequiredWithout = $this->schemaForField([
            'key' => 'note',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_without', 'params' => ['{field:a}', '{field:b}']],
            ],
        ]);
        $this->assertFalse($this->validator()->validate($schemaRequiredWithout, ['a' => 1])->isValid()); // b missing -> required
        $this->assertTrue($this->validator()->validate($schemaRequiredWithout, ['a' => 1, 'b' => 2])->isValid());

        // required_without_all: required when all missing
        $schemaRequiredWithoutAll = $this->schemaForField([
            'key' => 'note',
            'type' => 'text',
            'validations' => [
                ['rule' => 'required_without_all', 'params' => ['{field:a}', '{field:b}']],
            ],
        ]);
        $this->assertFalse($this->validator()->validate($schemaRequiredWithoutAll, [])->isValid());
        $this->assertTrue($this->validator()->validate($schemaRequiredWithoutAll, ['a' => 1])->isValid());
    }

    public function testStartsWithAndEndsWithSupportMultipleValues(): void
    {
        $schema = $this->schemaForField([
            'key' => 'phrase',
            'type' => 'text',
            'validations' => [
                ['rule' => 'starts_with', 'params' => ['he', 'yo']],
                ['rule' => 'ends_with', 'params' => ['lo', 'ld']],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['phrase' => 'hello'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['phrase' => 'nope'])->isValid());
    }

    public function testBeforeAndAfterSupportFieldRefs(): void
    {
        $schema = $this->schemaForField([
            'key' => 'end',
            'type' => 'date',
            'validations' => [
                ['rule' => 'after', 'params' => ['{field:start}']],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['start' => '2024-01-01', 'end' => '2024-01-02'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['start' => '2024-01-02', 'end' => '2024-01-01'])->isValid());
    }

    public function testEndsWithFailsWhenInvalid(): void
    {
        $field = [
            'key' => 'phrase',
            'type' => 'text',
            'validations' => [
                ['rule' => 'ends_with', 'params' => ['world']],
            ],
        ];

        $schema = $this->schemaForField($field);
        $result = $this->validator()->validate($schema, ['phrase' => 'hello']);
        $this->assertFalse($result->isValid());
    }
}
