<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use FormSchema\SubmissionValidator;

class SubmissionValidatorTest extends TestCase
{
    private const SUBMISSION_SCHEMA = [
        'form' => [
            'pages' => [
                [
                    'key' => 'page_1',
                    'sections' => [
                        [
                            'key' => 'section_1',
                            'fields' => [
                                [
                                    'key' => 'name',
                                    'type' => 'short-text',
                                    'required' => true,
                                    'validations' => [
                                        ['rule' => 'min', 'params' => [3], 'message' => 'Name must be at least 3 chars.'],
                                    ],
                                ],
                                [
                                    'key' => 'email',
                                    'type' => 'email',
                                    'required' => false,
                                    'validations' => [
                                        ['rule' => 'email', 'params' => [], 'message' => 'Email must be valid.'],
                                    ],
                                ],
                                [
                                    'key' => 'terms',
                                    'type' => 'boolean',
                                    'required' => false,
                                    'validations' => [
                                        ['rule' => 'required_if_accepted', 'params' => ['consent'], 'message' => 'Terms required if consent accepted.'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function test_passes_valid_submission(): void
    {
        $validator = new SubmissionValidator();

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload);

        $this->assertTrue($result->isValid());
        $this->assertSame($payload, $result->valid());
    }

    public function test_fails_required_and_validation_rules(): void
    {
        $validator = new SubmissionValidator();

        $payload = [
            'name' => 'Al', // too short
            'email' => 'invalid',
        ];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('name', $result->errors());
        $this->assertArrayHasKey('email', $result->errors());
        $this->assertIsArray($result->errors()['name']);
        $this->assertIsArray($result->errors()['email']);
        $this->assertSame([], $result->valid());
    }

    public function test_honors_replacements_for_missing_values(): void
    {
        $validator = new SubmissionValidator();

        $payload = []; // missing consent, terms
        $replacements = ['consent' => true, 'terms' => 'yes', 'name' => 'John Doe'];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload, $replacements);

        $this->assertTrue($result->isValid());
        $this->assertSame([
            'name' => 'John Doe',
            'terms' => 'yes',
        ], $result->valid());
    }

    public function test_errors_returns_all_messages_per_field(): void
    {
        $validator = new SubmissionValidator();

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'sections' => [
                            [
                                'fields' => [
                                    [
                                        'key' => 'code',
                                        'type' => 'short-text',
                                        'required' => false,
                                        'validations' => [
                                            ['rule' => 'starts_with', 'params' => ['ab']],
                                            ['rule' => 'ends_with', 'params' => ['yz']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema, ['code' => 'xx']);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('code', $result->errors());
        $this->assertCount(2, $result->errors()['code']);
        $this->assertSame([], $result->valid());
    }
}
