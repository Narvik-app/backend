<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class DateNotAlreadyExported extends Constraint {
  public string $message = 'This period has already been locked by an export. Contact your club administrator to add a declaration for this period.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
