<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Repository\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelExportRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A declaration not yet attached to any export can still be freely created
 * or moved to any date — except a date already covered by an existing export
 * (draft or locked). Otherwise it would silently sit outside every export of
 * that period, requiring a regenerate the comptable has no way to know about.
 * Declarations already attached to an export are unaffected (their date is
 * frozen the moment the export locks, via TimeAndTravelDeclarationNotLocked).
 */
final class TimeAndTravelDeclarationDateNotAlreadyExportedValidator extends ConstraintValidator {
  public function __construct(
    private readonly TimeAndTravelExportRepository $exportRepository,
  ) {
  }

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof TimeAndTravelDeclarationDateNotAlreadyExported) {
      throw new UnexpectedTypeException($constraint, TimeAndTravelDeclarationDateNotAlreadyExported::class);
    }

    if (!$value instanceof TimeAndTravelDeclaration) {
      return;
    }

    // Already attached to an export: its own lock (not this constraint) governs editability.
    if ($value->getExport() !== null) {
      return;
    }

    $club = $value->getClub();
    $date = $value->getDate();
    if (!$club || !$date) {
      return;
    }

    if ($this->exportRepository->existsCoveringDate($club, $date)) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('date')
        ->addViolation();
    }
  }
}
