<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExport;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Two exports covering the same dates would each only pick up whatever
 * declarations aren't already attached to the other, silently splitting a
 * period across drafts (or leaving an empty one) instead of ever raising an
 * error. Overlap is denied outright, regardless of status or whether the
 * other export already holds data — the fix is to edit or delete it, not to
 * generate around it.
 */
final class PeriodNotOverlappingAnotherExportValidator extends ConstraintValidator {
  public function __construct(
    private readonly TimeAndTravelExportRepository $exportRepository,
  ) {
  }

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof PeriodNotOverlappingAnotherExport) {
      throw new UnexpectedTypeException($constraint, PeriodNotOverlappingAnotherExport::class);
    }

    if (!$value instanceof TimeAndTravelExport) {
      return;
    }

    $club = $value->getClub();
    $start = $value->getStartDate();
    $end = $value->getEndDate();
    if (!$club || !$start || !$end) {
      return;
    }

    if ($this->exportRepository->findOverlapping($club, $start, $end, $value)) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('startDate')
        ->addViolation();
    }
  }
}
