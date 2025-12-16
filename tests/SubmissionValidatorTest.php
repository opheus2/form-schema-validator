<?php

declare(strict_types=1);

use FormSchema\SubmissionValidator;
use PHPUnit\Framework\TestCase;

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

    public function testPassesValidSubmission(): void
    {
        $validator = new SubmissionValidator();

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload);

        $this->assertTrue($result->isValid());
    }

    public function testFailsRequiredAndValidationRules(): void
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
    }

    public function testHonorsReplacementsForMissingValues(): void
    {
        $validator = new SubmissionValidator();

        $payload = []; // missing consent, terms
        $replacements = ['consent' => true, 'terms' => 'yes', 'name' => 'John Doe'];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload, $replacements);

        $this->assertTrue($result->isValid());
    }
}
