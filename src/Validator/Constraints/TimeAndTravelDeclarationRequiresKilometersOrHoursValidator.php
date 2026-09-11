<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TimeAndTravelDeclarationRequiresKilometersOrHoursValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof TimeAndTravelDeclarationRequiresKilometersOrHours) {
      throw new UnexpectedTypeException($constraint, TimeAndTravelDeclarationRequiresKilometersOrHours::class);
    }

    if (!$value instanceof TimeAndTravelDeclaration) {
      return;
    }

    $hasKilometers = $value->getKilometers() !== null && $value->getKilometers() > 0;
    $hasHours = $value->getHours() !== null && (float) $value->getHours() > 0;

    if (!$hasKilometers && !$hasHours) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('kilometers')
        ->addViolation();
    }
  }
}
