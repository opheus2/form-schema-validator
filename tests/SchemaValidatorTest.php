<?php

declare(strict_types=1);

use FormSchema\SchemaValidator;
use FormSchema\ValidationResult;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    public function test_fails_when_form_is_missing(): void
    {
        $validator = new SchemaValidator;

        $result = $validator->validate([]);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('form', $result->errors());
    }

    public function test_validates_presence_of_pages_sections_and_fields(): void
    {
        $validator = new SchemaValidator;

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [
                                    [
                                        'key' => 'field_1',
                                        'type' => 'short-text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema);

        $this->assertTrue($result->isValid());
    }

    public function test_rejects_invalid_field_type(): void
    {
        $validator = new SchemaValidator;

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [
                                    [
                                        'key' => 'field_1',
                                        'type' => 'invalid-type',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('form.pages[0].sections[0].fields[0].type', $result->errors());
    }

    public function test_accepts_banner_field_type(): void
    {
        $validator = new SchemaValidator;

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [
                                    [
                                        'key' => 'field_1',
                                        'type' => 'banner',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema);

        $this->assertTrue($result->isValid());
    }

    public function test_options_field_accepts_dynamic_source_without_static_data(): void
    {
        $validator = new SchemaValidator;

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [
                                    [
                                        'key' => 'bank_code',
                                        'type' => 'options',
                                        'option_properties' => [
                                            'data' => [],
                                            'source' => [
                                                'enabled' => true,
                                                'endpoint' => 'https://example.com/api/options',
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

        $result = $validator->validate($schema);

        $this->assertTrue($result->isValid());
    }

    public function test_options_field_requires_static_data_or_dynamic_source(): void
    {
        $validator = new SchemaValidator;

        $schema = [
            'form' => [
                'pages' => [
                    [
                        'key' => 'page_1',
                        'sections' => [
                            [
                                'key' => 'section_1',
                                'fields' => [
                                    [
                                        'key' => 'bank_code',
                                        'type' => 'options',
                                        'option_properties' => [
                                            'data' => [],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $validator->validate($schema);

        $this->assertFalse($result->isValid());
        $this->assertSame(
            ['Options field requires option_properties.data or a dynamic source.'],
            $result->errors()['form.pages[0].sections[0].fields[0].option_properties.data'] ?? []
        );
    }
}
