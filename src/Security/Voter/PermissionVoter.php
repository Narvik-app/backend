<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class PermissionVoter extends AbstractClubVoter {

  protected function supports(string $attribute, mixed $subject): bool {
    $permissionValues = Permission::values();

    if ($subject instanceof Request) {
      $subject = $this->requestService->getClubFromRequest($subject, false);
    }

    return ($subject instanceof ClubLinkedEntityInterface || $subject instanceof Club || $subject === null)
      && in_array($attribute, $permissionValues);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $permission = Permission::tryFrom($attribute);
    if (!$permission) {
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

    $activeProfile = $context['activeProfile'];

    // Admins have all permissions
    if ($activeProfile->getRole()->isAdmin()) {
      return true;
    }

    // For supervisors, check if they have the specific permission
    if ($activeProfile->getRole()->hasSupervisorRole()) {
      $member = $activeProfile->getMember();
      if ($member && $member->hasPermission($permission)) {
        return true;
      }
    }

    $vote?->addReason('User does not have the required permission.');
    return false;
  }
}
