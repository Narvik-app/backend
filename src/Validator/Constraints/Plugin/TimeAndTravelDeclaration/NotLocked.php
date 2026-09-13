<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class NotLocked extends Constraint {
  public string $message = 'This declaration is locked, unlock its export first.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
