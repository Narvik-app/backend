<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Shared skeleton for TimeAndTravelDeclaration class-constraint validators.
 */
abstract class AbstractTimeAndTravelDeclarationValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    $this->assertConstraintType($constraint);

    if (!$value instanceof TimeAndTravelDeclaration) {
      return;
    }

    $this->validateDeclaration($value, $constraint);
  }

  abstract protected function assertConstraintType(Constraint $constraint): void;

  abstract protected function validateDeclaration(TimeAndTravelDeclaration $declaration, Constraint $constraint): void;
}
