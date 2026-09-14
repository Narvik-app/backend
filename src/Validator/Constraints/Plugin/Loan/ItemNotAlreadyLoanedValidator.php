<?php

namespace App\Validator\Constraints\Plugin\Loan;

use App\Entity\ClubDependent\Plugin\Loan\LoanItem;
use App\Repository\ClubDependent\Plugin\Loan\LoanRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ItemNotAlreadyLoanedValidator extends AbstractItemCreateValidator {
  public function __construct(private readonly LoanRepository $loanRepository) {}

  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof ItemNotAlreadyLoaned) {
      throw new UnexpectedTypeException($constraint, ItemNotAlreadyLoaned::class);
    }
  }

  protected function isViolation(LoanItem $loanItem): bool {
    return $this->loanRepository->countOpenByItem($loanItem) > 0;
  }
}
