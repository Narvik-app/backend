<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class LoanBackdateAllowed extends Constraint {
  public string $message = 'You are not allowed to set a date other than today.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
