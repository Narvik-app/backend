<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClubVoter extends Voter {

  public function __construct(
    private readonly Security $security,
    private readonly RequestStack $requestStack,
    private readonly RequestService $requestService,
  ) {
  }

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

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
    $request = null;
    if ($subject instanceof Request) {
      $request = $subject;
      $subject = $this->requestService->getClubFromRequest($subject);
    }

    $user = $token->getUser();
    $targetedClubRole = ClubRole::tryFrom($attribute);

    if (!$user instanceof User || !$targetedClubRole) {
      return false;
    }

    // Super admin have full right
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
      return false;
    }

    $activeProfile = $this->requestService->getActiveProfile($request);
    if (!$activeProfile) {
      return false;
    }

    if ($activeProfile->getClub()->getId() === $targetedClub->getId()) {
        $role = $activeProfile->getRole();
        return $role->hasRole($targetedClubRole);
    }

    return false;
  }
}
