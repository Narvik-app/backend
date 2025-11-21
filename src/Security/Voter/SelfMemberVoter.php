<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Member;
use App\Entity\ClubDependent\Plugin\Presence\MemberPresence;
use App\Entity\ClubDependent\Plugin\Sale\Sale;
use App\Entity\User;
use App\Enum\ClubRole;
use App\Enum\UserRole;
use App\Repository\ClubDependent\MemberRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SelfMemberVoter extends Voter {
  public const string READ = 'SELF_READ';

  public function __construct(
    private readonly Security $security,
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    if (!in_array($attribute, [self::READ])) {
      return false;
    }

    if ($subject instanceof Request) {
      $memberUuid = $subject->attributes->get("memberUuid");
      return (bool) $memberUuid;
    }

    return $subject instanceof Member;
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

    if ($subject instanceof Member) {
      return $this->voteForMemberEntity($subject, $user);
    }

    if ($subject instanceof Request) {
      return $this->voteFromRequest($subject, $user);
    }

    return false;
  }

  private function voteForMemberEntity(Member $member, User $user): bool {
    $linkedProfiles = $user->getLinkedProfiles();
    foreach ($linkedProfiles as $linkedProfile) {
      if ($linkedProfile->getMember()?->getUuid()->toString() === $member->getUuid()->toString()) {
        return true;
      }
    }

    return false;
  }

  private function voteFromRequest(Request $request, User $user): bool {
    $memberUuid = $request->attributes->get("memberUuid");
    $clubUUid = $request->attributes->get("clubUuid");

    $linkedProfiles = $user->getLinkedProfiles();
    foreach ($linkedProfiles as $linkedProfile) {
      if ($linkedProfile->getMember()?->getUuid()->toString() === $memberUuid) {
        if ($clubUUid) { // We match also the club
          return $linkedProfile->getClub()->getUuid()->toString() === $clubUUid;
        }
        return true;
      }
    }

    return false;
  }
}
