<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Activity;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ActivityMustBeEnabledValidator extends ConstraintValidator {
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof ActivityMustBeEnabled) {
      throw new UnexpectedTypeException($constraint, ActivityMustBeEnabled::class);
    }

    $activities = [];

    if ($value instanceof Collection) {
      $activities = $value->getValues();
    }

    if (empty($activities)) {
      return;
    }

    foreach ($value as $activity) {
      if ($activity instanceof Activity) {
        if (!$activity->getIsEnabled()) {
          $this->context
            ->buildViolation($constraint->message)
            ->setParameter('{{ name }}', $activity->getName())
            ->addViolation();
        }
      }
    }

  }
}
