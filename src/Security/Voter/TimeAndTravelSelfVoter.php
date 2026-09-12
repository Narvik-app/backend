<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\MemberVehicle;
use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Allows a member to manage their own time and travel declarations and vehicles.
 * Permission-based access (Permission::TIME_TRAVEL_*) only ever applies to
 * supervisors/admins; a plain member never holds a Permission, so self-service
 * access is granted here instead, mirroring SelfMemberVoter.
 */
class TimeAndTravelSelfVoter extends Voter {
  public const string SELF_READ = 'TIME_TRAVEL_SELF_READ';
  public const string SELF_WRITE = 'TIME_TRAVEL_SELF_WRITE';

  public function __construct(
    private readonly Security $security,
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    if (!in_array($attribute, [self::SELF_READ, self::SELF_WRITE])) {
      return false;
    }

    if ($subject instanceof Request) {
      $memberUuid = $subject->attributes->get("memberUuid");
      return (bool) $memberUuid;
    }

    return $subject instanceof TimeAndTravelDeclaration || $subject instanceof MemberVehicle;
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    // Super admin have full right
    if ($this->security->isGranted(UserRole::super_admin->value)) {
      return true;
    }

    $user = $token->getUser();
    if (!$user instanceof User) {
      return false;
    }

    if ($subject instanceof TimeAndTravelDeclaration || $subject instanceof MemberVehicle) {
      if ($attribute === self::SELF_WRITE && $subject instanceof TimeAndTravelDeclaration) {
        // A locked declaration can never be self-written, regardless of ownership
        if ($subject->getIsLocked()) {
          $vote?->addReason('Declaration is locked.');
          return false;
        }

        // A declaration tied to a presence was prompted by whoever registered that presence — only
        // a supervisor/admin (TIME_TRAVEL_EDIT) or the badger/kiosk session for this club (granted
        // directly in the resource's security expression, same as MemberPresence) can act on it,
        // never the member themselves, even though it's their own declaration.
        if ($subject->getMemberPresence() !== null) {
          $vote?->addReason('Declaration is linked to a presence; only a supervisor/admin can manage it.');
          return false;
        }
      }

      return $this->voteForEntity($subject, $user);
    }

    if ($subject instanceof Request) {
      return $this->voteFromRequest($subject, $user);
    }

    return false;
  }

  private function voteForEntity(TimeAndTravelDeclaration|MemberVehicle $subject, User $user): bool {
    $member = $subject->getMember();
    if (!$member) {
      return false;
    }

    $linkedProfiles = $user->getLinkedProfiles();
    $found = array_find(
      $linkedProfiles->toArray(),
      fn($linkedProfile) => $linkedProfile->getMember()?->getUuid()->toString() === $member->getUuid()->toString()
    );
    return $found !== null;
  }

  private function voteFromRequest(Request $request, User $user): bool {
    $memberUuid = $request->attributes->get("memberUuid");
    $clubUuid = $request->attributes->get("clubUuid");

    $linkedProfiles = $user->getLinkedProfiles();
    $found = array_find(
      $linkedProfiles->toArray(),
      fn($linkedProfile) => $linkedProfile->getMember()?->getUuid()->toString() === $memberUuid
    );

    if ($found === null) {
      return false;
    }

    if ($clubUuid) { // We match also the club
      return $found->getClub()->getUuid()->toString() === $clubUuid;
    }
    return true;
  }
}
