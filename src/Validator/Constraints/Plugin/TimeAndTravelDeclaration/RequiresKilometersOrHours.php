<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RequiresKilometersOrHours extends Constraint {
  public string $message = 'At least one of kilometers or hours must be declared.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
