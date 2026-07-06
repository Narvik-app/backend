<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\Loan\Loan;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\Permission;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class LoanVoter extends Voter {
  public const string DELETE = 'LOAN_DELETE';

  public function __construct(
    private readonly Security $security
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return $subject instanceof Loan && $attribute === self::DELETE;
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    // Not a user or not a loan
    if (
      !$user instanceof User ||
      !$subject instanceof Loan
    ) {
      return false;
    }

    // Admins can delete a loan regardless of when it was started
    if ($this->security->isGranted(ClubRole::admin->value, $subject)) {
      return true;
    }

    if (!$this->security->isGranted(Permission::LOAN_EDIT->value, $subject)) {
      return false;
    }

    // Loans can only be deleted the day they were started
    return $subject->isEditableToday();
  }
}
