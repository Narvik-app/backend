<?php

namespace App\Validator\Constraints\Plugin\Loan;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ItemNotAlreadyLoaned extends Constraint {
  public string $message = 'This item is already on loan.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
