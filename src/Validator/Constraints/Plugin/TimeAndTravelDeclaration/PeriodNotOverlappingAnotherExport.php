<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class PeriodNotOverlappingAnotherExport extends Constraint {
  public string $message = 'This period overlaps an existing export for this club. Edit or delete that export first.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
