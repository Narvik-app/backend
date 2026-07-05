<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Enum\LoanItemStatus;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanItemMustBeAvailableValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LoanItemMustBeAvailable) {
      throw new UnexpectedTypeException($constraint, LoanItemMustBeAvailable::class);
    }

    if (!$value instanceof Loan) {
      return;
    }

    // Only enforce on creation — updates (PATCH, e.g. returning a loan) are allowed
    if ($value->getId() !== null) {
      return;
    }

    $loanItem = $value->getLoanItem();
    if ($loanItem === null) {
      return;
    }

    if ($loanItem->getStatus() !== LoanItemStatus::available) {
      $this->context
        ->buildViolation($constraint->message)
        ->addViolation();
    }
  }
}
