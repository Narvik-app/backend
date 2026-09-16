<?php

namespace App\Validator\Constraints\Plugin\Loan;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ItemMustBeAvailable extends Constraint {
  public string $message = 'This item is not available for loan.';

  #[\Override]
  public function getTargets(): string {
    return self::CLASS_CONSTRAINT;
  }
}
