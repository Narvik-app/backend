<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanItemNotAlreadyLoanedValidator extends ConstraintValidator {
  public function __construct(private readonly LoanRepository $loanRepository) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LoanItemNotAlreadyLoaned) {
      throw new UnexpectedTypeException($constraint, LoanItemNotAlreadyLoaned::class);
    }

    if (!$value instanceof Loan) {
      return;
    }

    // Only enforce on creation — updates (PATCH) are allowed
    if ($value->getId() !== null) {
      return;
    }

    $loanItem = $value->getLoanItem();
    if ($loanItem === null) {
      return;
    }

    if ($this->loanRepository->countOpenByItem($loanItem) > 0) {
      $this->context
        ->buildViolation($constraint->message)
        ->addViolation();
    }
  }
}
