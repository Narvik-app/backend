<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Shared skeleton for Loan class-constraint validators that only apply on creation
 * and check something about the borrowed LoanItem.
 */
abstract class AbstractLoanItemCreateValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    $this->assertConstraintType($constraint);

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

    if ($this->isViolation($loanItem)) {
      $this->context
        ->buildViolation($constraint->message)
        ->addViolation();
    }
  }

  abstract protected function assertConstraintType(Constraint $constraint): void;

  abstract protected function isViolation(LoanItem $loanItem): bool;
}
