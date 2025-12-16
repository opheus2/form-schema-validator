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
