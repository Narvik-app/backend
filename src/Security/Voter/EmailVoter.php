<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\Emailing\Email;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\EmailStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EmailVoter extends Voter {
  public const string UPDATE = 'EMAIL_UPDATE';
  public const string DELETE = 'EMAIL_DELETE';

  public function __construct(
    private readonly Security $security
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return $subject instanceof Email && in_array($attribute, [self::UPDATE, self::DELETE]);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    // Not a member
    if (
      !$user instanceof User ||
      !$subject instanceof Email ||
      !$this->security->isGranted(ClubRole::admin->value, $subject)
    ) {
      return false;
    }

    return $subject->getStatus() === EmailStatus::DRAFT;
  }
}
