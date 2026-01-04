<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\ClubRole;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class ClubVoter extends AbstractClubVoter {

  protected function supports(string $attribute, mixed $subject): bool {
    $roles = [];
    foreach (ClubRole::cases() as $role) {
      $roles[] = $role->value;
    }

    if ($subject instanceof Request) {
      $subject = $this->requestService->getClubFromRequest($subject, false);
    }

    return ($subject instanceof ClubLinkedEntityInterface || $subject instanceof Club) && in_array($attribute, $roles);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $targetedClubRole = ClubRole::tryFrom($attribute);
    if (!$targetedClubRole) {
      return false;
    }

    $context = $this->resolveClubContext($subject, $token, $vote);
    if (!$context) {
      return false;
    }

    // Super admin has full rights
    if ($context['isSuperAdmin']) {
      return true;
    }

    $role = $context['activeProfile']->getRole();
    return $role->hasRole($targetedClubRole);
  }
}
