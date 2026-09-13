<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Member;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared "is this user's own linked profile" matching logic for voters that grant
 * self-service access (a member managing their own data) rather than permission-based access.
 */
trait SelfProfileMatchTrait {
  private function isLinkedToMember(User $user, ?Member $member): bool {
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

  private function voteFromRequestForSelf(Request $request, User $user): bool {
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
