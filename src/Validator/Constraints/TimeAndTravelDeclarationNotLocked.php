<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TimeAndTravelDeclarationNotLocked extends Constraint {
  public string $message = 'This declaration is locked, unlock its export first.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
