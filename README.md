# Form Schema Validator (PHP)

Lightweight, framework-agnostic validators for the form-builder schema and user submissions.

## Requirements

- PHP `^8.2`

## Installation

```bash
composer require form-builder/form-schema-validator
```

### Local/path install (optional)

If you are developing against this package inside a monorepo, you can install it via a Composer `path` repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "path/to/form-builder/package/php"
    }
  ],
  "require": {
    "form-builder/form-schema-validator": "*"
  }
}
```

## Usage

### API

- `FormSchema\SchemaValidator`
  - `validate(array $schema): ValidationResult`
  - `assertValid(array $schema): void`
- `FormSchema\SubmissionValidator`
  - `validate(array $schema, array $payload, array $replacements = []): ValidationResult`
  - `assertValid(array $schema, array $payload, array $replacements = []): void`
- `FormSchema\ValidationResult`
  - `isValid(): bool`
  - `errors(): array<string, string>`

### Quick start

```php
use FormSchema\SubmissionValidator;

$schema = [
    'form' => [
        'pages' => [
            [
                'sections' => [
                    [
                        'fields' => [
                            [
                                'key' => 'age',
                                'type' => 'number',
                                'required' => true,
                                'constraints' => ['min' => 18],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

$payload = ['age' => 17];

$result = (new SubmissionValidator())->validate($schema, $payload);

if (! $result->isValid()) {
    var_dump($result->errors()); // ['age' => '...']
}
```

### Schema validation

```php
use FormSchema\SchemaValidator;

$validator = new SchemaValidator();

$result = $validator->validate($schema);

if (! $result->isValid()) {
    // handle $result->errors()
}

// or throw on failure
$validator->assertValid($schema);
```

The validator checks that pages, sections, and fields exist with supported field types, and that option fields define options. Extend it to fit additional rules as your schema evolves.

### Submission validation

Validate a user-submitted payload against a schema (field validations + required flags + field constraints):

```php
use FormSchema\SubmissionValidator;

$validator = new SubmissionValidator();

$schema = [/* form schema with pages/sections/fields */];
$payload = request()->all();
$context = ['external_token' => 'abc123']; // optional replacements merged (and overriding) before validation

$result = $validator->validate($schema, $payload, $context);

if (! $result->isValid()) {
    // handle $result->errors()
}
```

Each field may define `validations` as an array of rules:

```php
'validations' => [
    ['rule' => 'min', 'params' => [3], 'message' => 'Must be at least 3.'],
    ['rule' => 'required_if', 'params' => ['other_field', true], 'message' => 'Required when other_field is true.'],
],
```

All rules (except `required` and the `required_*` variants) treat empty values as "pass" (i.e. optional fields only validate when present).

#### Field references in rule params

Some rules support pointing at another field value instead of a literal:

```php
['rule' => 'gt', 'params' => ['{field:min_age}']],
```

The `{field:...}` syntax is also supported for `before` / `after`.

#### Field constraints

In addition to `validations`, the validator will also enforce `constraints` by field type, for example:

- Text-like fields (`short-text`, `text`, `medium-text`, `long-text`, `address`) validate `constraints.min_length` / `constraints.max_length`
- `number` validates `constraints.min` / `constraints.max` / `constraints.step`
- `tag` validates `constraints.min` / `constraints.max` as tag count
- `email` validates domain allow/deny lists via `constraints.allowed_domains` / `constraints.disallowed_domains` (and `constraints.max_length`)
- `country` validates `constraints.allow_countries` / `constraints.exclude_countries`
- File inputs (`file`, `image`, `video`, `document`) validate `constraints.accept`, `allow_multiple`, `min`, `max`, `max_file_size`, `max_total_size`

#### Supported field validations

| Rule | Params | Notes |
| --- | --- | --- |
| `required` | — | Value must be non-empty (`null`, `''`, `[]` are empty). |
| `required_if` | `[otherKey, otherValue, ...]` | Required when `context[otherKey] ==` any provided value. |
| `required_unless` | `[otherKey, otherValue, ...]` | Required unless `context[otherKey] ==` any provided value. |
| `required_if_accepted` | `[otherKey]` | Required when `context[otherKey]` is accepted (`true`, `1`, `'1'`, `'true'`, `'on'`, `'yes'`). |
| `required_if_declined` | `[otherKey]` | Required when `context[otherKey]` is declined (`false`, `0`, `'0'`, `'false'`, `'off'`, `'no'`). |
| `required_with` | `[otherKey, ...]` | Required when any referenced context key is non-empty. |
| `required_with_all` | `[otherKey, ...]` | Required when all referenced context keys are non-empty. |
| `required_without` | `[otherKey, ...]` | Required when any referenced context key is empty. |
| `required_without_all` | `[otherKey, ...]` | Required when all referenced context keys are empty. |
| `email` | — | Valid email (`FILTER_VALIDATE_EMAIL`). |
| `phone` | — | Matches `/^[0-9 +().-]{6,}$/`. |
| `boolean` | — | Accepts `true/false`, `0/1`, `'0'/'1'`, `'true'/'false'`, `'yes'/'no'`, `'on'/'off'`, `'y'/'n'`. |
| `string` | — | Must be a string. |
| `numeric` | — | Must be numeric (`is_numeric`). |
| `gt` | `[target]` | Number must be `> target` (supports `{field:otherKey}`). |
| `gte` | `[target]` | Number must be `>= target` (supports `{field:otherKey}`). |
| `lt` | `[target]` | Number must be `< target` (supports `{field:otherKey}`). |
| `lte` | `[target]` | Number must be `<= target` (supports `{field:otherKey}`). |
| `min` | `[min]` | Numbers: `>= min`. Strings: `length >= min`. |
| `max` | `[max]` | Numbers: `<= max`. Strings: `length <= max`. |
| `between` | `[min, max]` | Inclusive range for numbers or string length. |
| `not_between` | `[min, max]` | Outside inclusive range for numbers or string length. |
| `in` | `[value, ...]` | Strict match (`in_array(..., true)`). |
| `not_in` | `[value, ...]` | Strict non-match. |
| `date` | — | Validates `Y-m-d` by default. |
| `time` | — | Validates `HH:mm` or `HH:mm:ss`. |
| `datetime` | — | Validates `YYYY-MM-DDTHH:mm` (or seconds) and `YYYY-MM-DD HH:mm` variants. |
| `before` | `[date]` | `strtotime` comparison vs target date (supports `{field:otherKey}` as the date source). |
| `after` | `[date]` | `strtotime` comparison vs target date (supports `{field:otherKey}` as the date source). |
| `regex` | `[pattern]` | `preg_match(pattern, value) === 1` (pattern includes delimiters). |
| `starts_with` | `[prefix, ...]` | String must start with any provided prefix. |
| `ends_with` | `[suffix, ...]` | String must end with any provided suffix. |

Option fields must provide `option_properties.data`.

## Testing

```bash
composer test
```

## License

MIT
