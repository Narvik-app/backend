<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanItemNotAlreadyLoanedValidator extends AbstractLoanItemCreateValidator {
  public function __construct(private readonly LoanRepository $loanRepository) {}

  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof LoanItemNotAlreadyLoaned) {
      throw new UnexpectedTypeException($constraint, LoanItemNotAlreadyLoaned::class);
    }
  }

  protected function isViolation(LoanItem $loanItem): bool {
    return $this->loanRepository->countOpenByItem($loanItem) > 0;
  }
}
