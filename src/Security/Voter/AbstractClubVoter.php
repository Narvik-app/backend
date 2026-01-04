<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Abstract base class for club-based voters.
 * Provides common functionality for user/club/profile resolution.
 */
abstract class AbstractClubVoter extends Voter {

  public function __construct(
    protected readonly Security $security,
    protected readonly RequestService $requestService,
  ) {
  }

  /**
   * Perform common pre-checks for club-based voting.
   * Returns the resolved context or null if access should be denied.
   * 
   * @return array{user: User, targetedClub: Club|null, activeProfile: Profile|null, isSuperAdmin: bool}|null
   */
  protected function resolveClubContext(
    mixed $subject,
    TokenInterface $token,
    ?Vote $vote = null,
    ?Request &$requestOut = null
  ): ?array {
    $request = null;
    if ($subject instanceof Request) {
      $request = $subject;
      $requestOut = $request;
      $subject = $this->requestService->getClubFromRequest($subject);
    }

    $user = $token->getUser();
    if (!$user instanceof User) {
      return null;
    }

    // Super admin have full rights - return early with special marker
    if ($this->security->isGranted(UserRole::super_admin->value)) {
      return ['user' => $user, 'targetedClub' => null, 'activeProfile' => null, 'isSuperAdmin' => true];
    }

    /** @var Club|null $targetedClub */
    $targetedClub = null;
    if ($subject instanceof Club) {
      $targetedClub = $subject;
    }
    if ($subject instanceof ClubLinkedEntityInterface) {
      $targetedClub = $subject->getClub();
    }

    // No matching club, we deny by default
    if (!$targetedClub) {
      $vote?->addReason('No matching club.');
      return null;
    }

    $activeProfile = $this->requestService->getActiveProfile($request);
    if (!$activeProfile) {
      $vote?->addReason('No active profile.');
      return null;
    }

    // Check if the profile is for the targeted club
    if ($activeProfile->getClub()?->getId() !== $targetedClub->getId()) {
      $vote?->addReason('Profile club does not match target club.');
      return null;
    }

    return [
      'user' => $user,
      'targetedClub' => $targetedClub,
      'activeProfile' => $activeProfile,
      'isSuperAdmin' => false,
    ];
  }
}
