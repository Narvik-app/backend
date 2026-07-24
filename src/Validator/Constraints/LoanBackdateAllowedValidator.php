<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\ClubDependent\Plugin\Loan\LoanRecording;
use App\Enum\ClubRole;
use App\Enum\Permission;
use App\Service\UtilsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanBackdateAllowedValidator extends ConstraintValidator {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly Security $security,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LoanBackdateAllowed) {
      throw new UnexpectedTypeException($constraint, LoanBackdateAllowed::class);
    }

    if (!$value instanceof Loan && !$value instanceof LoanRecording) {
      return;
    }

    $dateField = $value instanceof Loan ? 'startDate' : 'date';
    $date = $value instanceof Loan ? $value->getStartDate() : $value->getDate();

    // Dated today — always allowed
    if (UtilsService::isToday($date)) {
      return;
    }

    // On update, an unchanged (already legitimately-set) date never needs re-checking —
    // only enforce the permission when the date field itself was actually modified.
    if ($value->getId() !== null) {
      $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($value);
      if (($original[$dateField] ?? null) == $date) {
        return;
      }
    }

    // Admins and users with the backdate permission may set a past (or future) date
    if (
      $this->security->isGranted(ClubRole::admin->value, $value) ||
      $this->security->isGranted(Permission::LOAN_BACKDATE->value, $value)
    ) {
      return;
    }

    $this->context
      ->buildViolation($constraint->message)
      ->addViolation();
  }
}
