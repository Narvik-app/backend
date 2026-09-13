<?php

namespace App\Validator\Constraints\Plugin\Loan;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class BackdateAllowed extends Constraint {
  public string $message = 'You are not allowed to set a date other than today.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
