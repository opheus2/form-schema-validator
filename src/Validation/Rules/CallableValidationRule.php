<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;

class CallableValidationRule extends Rule
{
    protected $message = 'The :attribute is invalid.';

    /**
     * @param  callable(array<string, mixed>): mixed  $callback
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        private readonly mixed $callback,
        private readonly array $context = [],
        private readonly ?string $defaultMessage = null,
    ) {
        if (is_string($defaultMessage) && '' !== $defaultMessage) {
            $this->message = $defaultMessage;
        }
    }

    public function check($value): bool
    {
        $result = ($this->callback)([
            ...$this->context,
            'value' => $value,
            'get' => fn (string $fieldKey) => $this->getAttribute()?->getValue($fieldKey),
        ]);

        if (null === $result || true === $result) {
            return true;
        }

        if (false === $result) {
            return false;
        }

        if (is_string($result) && '' !== $result) {
            $this->message = $result;

            return false;
        }

        if (is_array($result)) {
            $valid = $result['valid'] ?? null;
            if (is_bool($valid)) {
                if ( ! $valid) {
                    $message = $result['message'] ?? null;
                    if (is_string($message) && '' !== $message) {
                        $this->message = $message;
                    }
                }

                return $valid;
            }

            $message = $result['message'] ?? null;
            if (is_string($message) && '' !== $message) {
                $this->message = $message;

                return false;
            }
        }

        return false;
    }
}
