<?php

namespace App\Validator\Constraints\Plugin\TimeAndTravelDeclaration;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class RequiresKilometersOrHoursValidator extends AbstractDeclarationValidator {
  protected function assertConstraintType(Constraint $constraint): void {
    if (!$constraint instanceof RequiresKilometersOrHours) {
      throw new UnexpectedTypeException($constraint, RequiresKilometersOrHours::class);
    }
  }

  protected function validateDeclaration(TimeAndTravelDeclaration $value, Constraint $constraint): void {
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
