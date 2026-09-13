<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * A locked declaration (one attached to a locked export) is entirely read-only.
 * Unlike LoanEditableToday, no role bypasses this — not even admins: an
 * "official declaration" an admin can silently edit after the comptable has
 * signed off is worthless as an accounting record. The only path back to
 * editable is unlocking the export.
 */
final class TimeAndTravelDeclarationNotLockedValidator extends AbstractTimeAndTravelDeclarationValidator {
  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof TimeAndTravelDeclarationNotLocked) {
      throw new UnexpectedTypeException($constraint, TimeAndTravelDeclarationNotLocked::class);
    }
  }

  protected function validateDeclaration(TimeAndTravelDeclaration $value, Constraint $constraint): void {
    // Only enforce on update — creation can never target a locked declaration
    if ($value->getId() === null) {
      return;
    }

    if ($value->getIsLocked()) {
      $this->context
        ->buildViolation($constraint->message)
        ->addViolation();
    }
  }
}
