<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\User;
use App\Enum\Permission;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter {

  public function __construct(
    private readonly Security $security,
    private readonly RequestService $requestService,
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    // Check if attribute is a Permission enum value
    $permissionValues = Permission::values();

    if ($subject instanceof Request) {
      $subject = $this->requestService->getClubFromRequest($subject, false);
    }

    return ($subject instanceof ClubLinkedEntityInterface || $subject instanceof Club || $subject === null)
      && in_array($attribute, $permissionValues);
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $request = null;
    if ($subject instanceof Request) {
      $request = $subject;
      $subject = $this->requestService->getClubFromRequest($subject);
    }

    $user = $token->getUser();
    $permission = Permission::tryFrom($attribute);

    if (!$user instanceof User || !$permission) {
      return false;
    }

    // Super admin have full rights
    if ($this->security->isGranted(UserRole::super_admin->value)) {
      return true;
    }

    /** @var Club|null $targetedClub */
    $targetedClub = null;
    if ($subject instanceof Club) {
      $targetedClub = $subject;
    }
    if ($subject instanceof ClubLinkedEntityInterface) {
      $targetedClub = $subject->getClub();
    }

    // No matching club, we denied by default
    if (!$targetedClub) {
      $vote?->addReason('No matching club for permission check.');
      return false;
    }

    $activeProfile = $this->requestService->getActiveProfile($request);
    if (!$activeProfile) {
      $vote?->addReason('No active profile.');
      return false;
    }

    // Check if the profile is for the targeted club
    if ($activeProfile->getClub()?->getId() !== $targetedClub->getId()) {
      $vote?->addReason('Profile club does not match target club.');
      return false;
    }

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
