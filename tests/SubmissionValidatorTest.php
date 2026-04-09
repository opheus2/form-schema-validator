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

    public function test_valid_excludes_unknown_payload_keys(): void
    {
        $validator = new SubmissionValidator();

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'ignored_key' => 'should not be returned',
        ];

        $result = $validator->validate(self::SUBMISSION_SCHEMA, $payload);

        $this->assertTrue($result->isValid());
        $this->assertSame([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $result->valid());
    }

    public function test_address_field_optional_sub_properties_are_validated_without_rule_not_found_errors(): void
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
                                        'key' => 'address',
                                        'type' => 'address',
                                        'required' => true,
                                        'address_properties' => [
                                            'address_line_1' => ['required' => true],
                                            'address_line_2' => ['required' => false],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $payload = [
            'address' => [
                'address_line_1' => '123 Example Street',
            ],
        ];

        $result = $validator->validate($schema, $payload);

        $this->assertTrue($result->isValid());
        $this->assertSame($payload, $result->valid());
    }

    public function test_hidden_field_is_not_validated_when_never_shown(): void
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
                                        'key' => 'visible_name',
                                        'type' => 'short-text',
                                        'required' => true,
                                    ],
                                    [
                                        'key' => 'internal_note',
                                        'type' => 'short-text',
                                        'hidden' => true,
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema, [
            'visible_name' => 'Jane',
        ]);

        $this->assertTrue($result->isValid());
        $this->assertSame([
            'visible_name' => 'Jane',
        ], $result->valid());
    }

    public function test_conditional_show_hide_controls_required_validation(): void
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
                                        'key' => 'biller_type',
                                        'type' => 'options',
                                        'required' => true,
                                        'option_properties' => [
                                            'type' => 'tabs',
                                            'data' => [
                                                ['key' => 'personal', 'value' => 'Personal'],
                                                ['key' => 'business', 'value' => 'Business'],
                                            ],
                                        ],
                                        'conditionals' => [
                                            [
                                                'when' => [
                                                    'field' => 'biller_type',
                                                    'operator' => 'is',
                                                    'value' => 'personal',
                                                ],
                                                'then' => [
                                                    'action' => 'show',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'phone'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'biller_type',
                                                    'operator' => 'is',
                                                    'value' => 'personal',
                                                ],
                                                'then' => [
                                                    'action' => 'hide',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'payment_type'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'biller_type',
                                                    'operator' => 'is',
                                                    'value' => 'business',
                                                ],
                                                'then' => [
                                                    'action' => 'show',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'payment_type'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'biller_type',
                                                    'operator' => 'is',
                                                    'value' => 'business',
                                                ],
                                                'then' => [
                                                    'action' => 'hide',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'phone'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'key' => 'phone',
                                        'type' => 'phone',
                                        'required' => true,
                                        'hidden' => true,
                                    ],
                                    [
                                        'key' => 'payment_type',
                                        'type' => 'options',
                                        'required' => true,
                                        'hidden' => true,
                                        'option_properties' => [
                                            'type' => 'select',
                                            'data' => [
                                                ['key' => 'paybill', 'value' => 'Paybill'],
                                                ['key' => 'till', 'value' => 'Till'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $personalResult = $validator->validate($schema, [
            'biller_type' => 'personal',
            'phone' => '+254711000000',
        ]);

        $this->assertTrue($personalResult->isValid());
        $this->assertArrayNotHasKey('payment_type', $personalResult->errors());

        $businessValidResult = $validator->validate($schema, [
            'biller_type' => 'business',
            'payment_type' => 'paybill',
        ]);

        $this->assertTrue($businessValidResult->isValid());
        $this->assertArrayNotHasKey('phone', $businessValidResult->errors());

        $businessInvalidResult = $validator->validate($schema, [
            'biller_type' => 'business',
        ]);

        $this->assertFalse($businessInvalidResult->isValid());
        $this->assertArrayHasKey('payment_type', $businessInvalidResult->errors());
        $this->assertArrayNotHasKey('phone', $businessInvalidResult->errors());
    }

    public function test_conditional_is_and_is_not_use_rakit_backed_equality(): void
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
                                        'key' => 'status',
                                        'type' => 'short-text',
                                        'required' => true,
                                        'conditionals' => [
                                            [
                                                'when' => [
                                                    'field' => 'status',
                                                    'operator' => 'is',
                                                    'value' => 'active',
                                                ],
                                                'then' => [
                                                    'action' => 'show',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'activation_code'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'status',
                                                    'operator' => 'is_not',
                                                    'value' => 'active',
                                                ],
                                                'then' => [
                                                    'action' => 'hide',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'activation_code'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'key' => 'activation_code',
                                        'type' => 'short-text',
                                        'required' => true,
                                        'hidden' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $activeResult = $validator->validate($schema, [
            'status' => 'active',
            'activation_code' => 'ABC-123',
        ]);

        $this->assertTrue($activeResult->isValid());

        $inactiveResult = $validator->validate($schema, [
            'status' => 'inactive',
        ]);

        $this->assertTrue($inactiveResult->isValid());
        $this->assertArrayNotHasKey('activation_code', $inactiveResult->errors());
    }

    public function test_length_gte_and_length_lte_support_strings_and_arrays(): void
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
                                        'key' => 'username',
                                        'type' => 'short-text',
                                        'required' => true,
                                        'conditionals' => [
                                            [
                                                'when' => [
                                                    'field' => 'username',
                                                    'operator' => 'length_gte',
                                                    'value' => 5,
                                                ],
                                                'then' => [
                                                    'action' => 'show',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'profile_note'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'username',
                                                    'operator' => 'length_lte',
                                                    'value' => 4,
                                                ],
                                                'then' => [
                                                    'action' => 'hide',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'profile_note'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'key' => 'profile_note',
                                        'type' => 'short-text',
                                        'required' => true,
                                        'hidden' => true,
                                    ],
                                    [
                                        'key' => 'tags',
                                        'type' => 'options',
                                        'required' => true,
                                        'option_properties' => [
                                            'type' => 'checkbox',
                                            'data' => [
                                                ['key' => 'one', 'value' => 'One'],
                                                ['key' => 'two', 'value' => 'Two'],
                                                ['key' => 'three', 'value' => 'Three'],
                                            ],
                                        ],
                                        'conditionals' => [
                                            [
                                                'when' => [
                                                    'field' => 'tags',
                                                    'operator' => 'length_gte',
                                                    'value' => 2,
                                                ],
                                                'then' => [
                                                    'action' => 'show',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'tag_summary'],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'when' => [
                                                    'field' => 'tags',
                                                    'operator' => 'length_lte',
                                                    'value' => 1,
                                                ],
                                                'then' => [
                                                    'action' => 'hide',
                                                    'targets' => [
                                                        ['type' => 'field', 'key' => 'tag_summary'],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'key' => 'tag_summary',
                                        'type' => 'short-text',
                                        'required' => true,
                                        'hidden' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $stringVisibleResult = $validator->validate($schema, [
            'username' => 'alpha',
            'profile_note' => 'Visible after five chars',
            'tags' => ['one', 'two'],
            'tag_summary' => 'Two tags selected',
        ]);

        $this->assertTrue($stringVisibleResult->isValid());

        $stringHiddenResult = $validator->validate($schema, [
            'username' => 'abcd',
            'tags' => ['one'],
        ]);

        $this->assertTrue($stringHiddenResult->isValid());
        $this->assertArrayNotHasKey('profile_note', $stringHiddenResult->errors());
        $this->assertArrayNotHasKey('tag_summary', $stringHiddenResult->errors());
    }
}
