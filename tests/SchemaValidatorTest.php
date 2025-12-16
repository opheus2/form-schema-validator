<?php

declare(strict_types=1);

use FormSchema\SchemaValidator;
use FormSchema\ValidationResult;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    public function testFailsWhenFormIsMissing(): void
    {
        $validator = new SchemaValidator();

        $result = $validator->validate([]);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('form', $result->errors());
    }

    public function testValidatesPresenceOfPagesSectionsAndFields(): void
    {
        $validator = new SchemaValidator();

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

    public function testRejectsInvalidFieldType(): void
    {
        $validator = new SchemaValidator();

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
}
