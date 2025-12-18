<?php

declare(strict_types=1);

namespace FormSchema\Validation\Rules;

use Rakit\Validation\Rule;
use Rakit\Validation\Rules\Required;

class RequiredIfAcceptedRule extends Required
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

        $validator = $this->validation->getValidator();
        $accepted = $validator('accepted');

        if ($accepted->check($otherValue)) {
            $this->setAttributeAsRequired();
            $required = $validator('required');

            return $required->check($value, []);
        }

        return true;
    }
}

