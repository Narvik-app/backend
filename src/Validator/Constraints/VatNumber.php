<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class VatNumber extends Constraint
{
    public string $message = 'The VAT number "{{ value }}" is not a valid VAT number.';
}
