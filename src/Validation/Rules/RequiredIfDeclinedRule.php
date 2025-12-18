<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Required;

class RequiredIfDeclinedRule extends Required
{
    protected $message = 'The :attribute is required.';

    public function fillParameters(array $params): Rule
    {
        $this->params['field'] = array_shift($params);

        return $this;
    }

    public function check($value): bool
    {
        $this->requireParameters(['field']);

        $field = (string) $this->parameter('field');
        $otherValue = $this->getAttribute()?->getValue($field);

        if ($this->isDeclined($otherValue)) {
            $this->setAttributeAsRequired();
            $validator = $this->validation->getValidator();
            $required = $validator('required');

            return $required->check($value, []);
        }

        return true;
    }

    private function isDeclined(mixed $value): bool
    {
        return in_array($value, [false, 0, '0', 'false', 'off', 'no'], true);
    }
}

