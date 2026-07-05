<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class LoanItemMustBeAvailable extends Constraint {
  public string $message = 'Cet article n\'est pas disponible pour le prêt.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
