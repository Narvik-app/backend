<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TimeAndTravelDeclarationDistanceFieldsRequiredWithKilometers extends Constraint {
  public string $message = 'Departure location, arrival location and vehicle are required when kilometers is declared.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
