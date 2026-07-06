<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Enum\LoanItemStatus;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanItemMustBeAvailableValidator extends AbstractLoanItemCreateValidator {
  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof LoanItemMustBeAvailable) {
      throw new UnexpectedTypeException($constraint, LoanItemMustBeAvailable::class);
    }
  }

  protected function isViolation(LoanItem $loanItem): bool {
    return $loanItem->getStatus() !== LoanItemStatus::available;
  }
}
