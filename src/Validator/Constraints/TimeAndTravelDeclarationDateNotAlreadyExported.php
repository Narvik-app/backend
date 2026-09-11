<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class TimeAndTravelDeclarationDateNotAlreadyExported extends Constraint {
  public string $message = 'This period has already been exported. Contact your club administrator to add a declaration for this period.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
