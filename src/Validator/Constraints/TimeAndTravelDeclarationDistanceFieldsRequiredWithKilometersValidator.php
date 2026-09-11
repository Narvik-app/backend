<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Departure/arrival location and the vehicle only make sense for a distance
 * declaration: required when kilometers is set, irrelevant and optional
 * otherwise. The vehicle in particular drives the travel amount calculation
 * (its category/fiscalPower look up a row in the official mileage scale), so
 * a kilometers-only declaration with no vehicle would have no way to be valued.
 */
final class TimeAndTravelDeclarationDistanceFieldsRequiredWithKilometersValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof TimeAndTravelDeclarationDistanceFieldsRequiredWithKilometers) {
      throw new UnexpectedTypeException($constraint, TimeAndTravelDeclarationDistanceFieldsRequiredWithKilometers::class);
    }

    if (!$value instanceof TimeAndTravelDeclaration) {
      return;
    }

    if (!$value->getKilometers()) {
      return;
    }

    if (!$value->getDepartureLocation()) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('departureLocation')
        ->addViolation();
    }

    if (!$value->getArrivalLocation()) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('arrivalLocation')
        ->addViolation();
    }

    if (!$value->getMemberVehicle()) {
      $this->context
        ->buildViolation($constraint->message)
        ->atPath('memberVehicle')
        ->addViolation();
    }
  }
}
