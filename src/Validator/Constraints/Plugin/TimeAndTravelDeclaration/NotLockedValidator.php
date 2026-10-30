<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

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
final class NotLockedValidator extends AbstractDeclarationValidator {
  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof NotLocked) {
      throw new UnexpectedTypeException($constraint, NotLocked::class);
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
