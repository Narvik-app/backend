<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class LoanItemNotAlreadyLoaned extends Constraint {
  public string $message = 'Cet article est déjà en cours de prêt.';

  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
