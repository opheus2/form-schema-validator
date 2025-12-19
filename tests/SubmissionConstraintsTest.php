<?php

declare(strict_types=1);

use FormSchema\SubmissionValidator;
use PHPUnit\Framework\TestCase;

class SubmissionConstraintsTest extends TestCase
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

    public function testTextConstraintsValidateLength(): void
    {
        $schema = $this->schemaForField([
            'key' => 'bio',
            'type' => 'medium-text',
            'constraints' => [
                'min_length' => 5,
                'max_length' => 6,
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, [])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['bio' => 'abcd'])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['bio' => 'abcde'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['bio' => 'abcdefg'])->isValid());
    }

    public function testNumberConstraintsValidateNumericRangeAndStep(): void
    {
        $schema = $this->schemaForField([
            'key' => 'amount',
            'type' => 'number',
            'constraints' => [
                'min' => 10,
                'max' => 1000,
                'step' => 2,
            ],
        ]);

        $this->assertFalse($this->validator()->validate($schema, ['amount' => 'abc'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['amount' => 9])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['amount' => 12])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['amount' => 1001])->isValid());
    }

    public function testTagConstraintsValidateCount(): void
    {
        $schema = $this->schemaForField([
            'key' => 'tags',
            'type' => 'tag',
            'constraints' => [
                'min' => 1,
                'max' => 3,
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, [])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['tags' => ['a']])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['tags' => ['a', 'b', 'c', 'd']])->isValid());
    }

    public function testEmailConstraintsValidateDomains(): void
    {
        $schema = $this->schemaForField([
            'key' => 'email',
            'type' => 'email',
            'constraints' => [
                'allowed_domains' => ['google.com'],
                'disallowed_domains' => ['gmail.com'],
                'max_length' => 50,
            ],
        ]);

        $this->assertFalse($this->validator()->validate($schema, ['email' => 'not-an-email'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['email' => 'user@example.com'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['email' => 'user@gmail.com'])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['email' => 'user@google.com'])->isValid());
    }

    public function testCountryConstraintsValidateAllowAndExclude(): void
    {
        $schema = $this->schemaForField([
            'key' => 'country',
            'type' => 'country',
            'constraints' => [
                'allow_countries' => ['NG', 'US'],
                'exclude_countries' => ['RU', 'CN'],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['country' => 'US'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['country' => 'CA'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['country' => 'RU'])->isValid());
    }

    public function testFileConstraintsValidateAcceptCountAndSize(): void
    {
        $schema = $this->schemaForField([
            'key' => 'image',
            'type' => 'image',
            'constraints' => [
                'accept' => ['image/jpeg'],
                'allow_multiple' => true,
                'min' => 1,
                'max' => 2,
                'max_file_size' => 10000,
                'max_total_size' => 15000,
            ],
        ]);

        $file1 = [
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/a',
            'size' => 5000,
            'error' => 0,
        ];
        $file2 = [
            'name' => 'b.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/b',
            'size' => 6000,
            'error' => 0,
        ];
        $invalidType = [
            'name' => 'a.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/a',
            'size' => 5000,
            'error' => 0,
        ];
        $tooLarge = [
            'name' => 'a.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/a',
            'size' => 20000,
            'error' => 0,
        ];
        $maxSize = [
            'name' => 'max.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/max',
            'size' => 10000,
            'error' => 0,
        ];

        $this->assertTrue($this->validator()->validate($schema, ['image' => [$file1]])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['image' => [$file1, $file2]])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['image' => [$invalidType]])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['image' => [$tooLarge]])->isValid());
        // count too large
        $this->assertFalse($this->validator()->validate($schema, ['image' => [$file1, $file2, $file2]])->isValid());
        // total size too large (10000 + 6000 = 16000 > 15000)
        $this->assertFalse($this->validator()->validate($schema, ['image' => [$maxSize, $file2]])->isValid());
    }

    public function testOptionsConstraintsValidateAllowedValuesAndMaxSelect(): void
    {
        $schema = $this->schemaForField([
            'key' => 'choice',
            'type' => 'options',
            'option_properties' => [
                'type' => 'multi-select',
                'max_select' => 2,
                'data' => [
                    ['key' => 'a', 'value' => 'A'],
                    ['key' => 'b', 'value' => 'B'],
                    ['key' => 'c', 'value' => 'C'],
                ],
            ],
        ]);

        $this->assertTrue($this->validator()->validate($schema, ['choice' => ['a', 'b']])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['choice' => ['a', 'b', 'c']])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['choice' => ['a', 'nope']])->isValid());
    }

    public function testOptionalBooleanFieldCanBeMissing(): void
    {
        $schema = $this->schemaForField([
            'key' => 'agree',
            'type' => 'boolean',
        ]);

        $this->assertTrue($this->validator()->validate($schema, [])->isValid());
        $this->assertTrue($this->validator()->validate($schema, ['agree' => 'on'])->isValid());
        $this->assertFalse($this->validator()->validate($schema, ['agree' => 'maybe'])->isValid());
    }
}
