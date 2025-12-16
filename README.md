## Form Schema (PHP)

Small, framework-agnostic validator for the form schema used by the builder.

### Install

```bash
composer require form-builder/form-schema
```

### Usage

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

Validate a user-submitted payload against a schema (field validations + required flags):

```php
use FormSchema\SubmissionValidator;

$validator = new SubmissionValidator();

$schema = [/* form schema with pages/sections/fields */];
$payload = request()->all();
$context = ['external_token' => 'abc123']; // optional replacements merged before validation

$result = $validator->validate($schema, $payload, $context);

if (! $result->isValid()) {
    // handle $result->errors()
}
```

Supported field validations include: required (and required_if / required_unless / required_if_accepted / required_if_declined / required_with / required_with_all / required_without / required_without_all), email, phone, boolean, string, numeric, min, max, between, not_between, in, not_in, before, after, regex, starts_with, ends_with. Option fields must provide `option_properties.data`.
