<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Entity\User;
use App\Enum\ClubRole;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SaleVoter extends Voter {
  public const string UPDATE = 'SALE_UPDATE';
  public const string DELETE = 'SALE_DELETE';

  public function __construct(
    private readonly Security $security
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return $subject instanceof Sale && in_array($attribute, [self::UPDATE, self::DELETE]);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $user = $token->getUser();

    // Not a member
    if (
      !$user instanceof User ||
      !$subject instanceof Sale ||
      !$this->security->isGranted(ClubRole::supervisor->value, $subject)
    ) {
      return false;
    }

    return $subject->getCreatedAt() >= new \DateTimeImmutable('today midnight');
  }
}
