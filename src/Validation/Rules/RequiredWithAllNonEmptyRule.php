<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Required;

class RequiredWithAllNonEmptyRule extends Required
{
    protected $message = 'The :attribute is required.';

    public function fillParameters(array $params): Rule
    {
        $this->params['fields'] = $params;

        return $this;
    }

    public function check($value): bool
    {
        $this->requireParameters(['fields']);

        $validator = $this->validation->getValidator();
        $required = $validator('required');

        foreach ((array) $this->parameter('fields') as $field) {
            $otherValue = $this->getAttribute()?->getValue((string) $field);
            if ( ! $required->check($otherValue, [])) {
                return true;
            }
        }

        $this->setAttributeAsRequired();

        return $required->check($value, []);
    }
}

