<?php

namespace App\Validator\Constraints;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Enum\ClubRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class LoanEditableTodayValidator extends ConstraintValidator {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly Security $security,
  ) {}

  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof LoanEditableToday) {
      throw new UnexpectedTypeException($constraint, LoanEditableToday::class);
    }

    if (!$value instanceof Loan) {
      return;
    }

    // Only enforce on update — creation is always allowed
    if ($value->getId() === null) {
      return;
    }

    // Loans started today can be freely corrected
    if ($value->isEditableToday()) {
      return;
    }

    // Admins can correct a loan on any day
    if ($this->security->isGranted(ClubRole::admin->value, $value)) {
      return;
    }

    // Past loans can still be returned (endDate change) at any time, but no other field may change
    $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($value);
    $editableFields = [
      'member' => $value->getMember(),
      'borrowerName' => $value->getBorrowerName(),
      'author' => $value->getAuthor(),
      'comment' => $value->getComment(),
    ];

    foreach ($editableFields as $field => $currentValue) {
      if (($original[$field] ?? null) !== $currentValue) {
        $this->context
          ->buildViolation($constraint->message)
          ->addViolation();
        return;
      }
    }
  }
}
