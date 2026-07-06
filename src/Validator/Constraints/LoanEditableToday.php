<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class LoanEditableToday extends Constraint {
  public string $message = 'This loan can only be edited on the day it was created. Only the return date can be changed afterwards.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
