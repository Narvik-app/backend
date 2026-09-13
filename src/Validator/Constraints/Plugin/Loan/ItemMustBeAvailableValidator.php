<?php

namespace App\Validator\Constraints\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Enum\LoanItemStatus;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ItemMustBeAvailableValidator extends AbstractItemCreateValidator {
  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof ItemMustBeAvailable) {
      throw new UnexpectedTypeException($constraint, ItemMustBeAvailable::class);
    }
  }

  protected function isViolation(LoanItem $loanItem): bool {
    return $loanItem->getStatus() !== LoanItemStatus::available;
  }
}
