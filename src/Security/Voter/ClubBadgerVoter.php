<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\Interface\ClubLinkedEntityInterface;
use App\Enum\ClubRole;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * A STRICT "is this session specifically the badger/kiosk account for this club" check.
 *
 * Deliberately distinct from `is_granted(ClubRole::badger->value, ...)` (via ClubVoter), whose
 * hasRole() hierarchy treats badger as the lowest tier a supervisor/admin also satisfies — fine
 * when paired only with ClubRole::supervisor (as MemberPresence does), but wrong wherever a badger
 * exception needs to sit alongside a Permission::* check that's meant to be a STRICTER gate for
 * supervisors specifically — that hierarchy would silently let any supervisor bypass the
 * permission requirement.
 */
class ClubBadgerVoter extends AbstractClubVoter {
  public const string IS_CLUB_BADGER = 'IS_CLUB_BADGER';

  protected function supports(string $attribute, mixed $subject): bool {
    if ($attribute !== self::IS_CLUB_BADGER) {
      return false;
    }

    if ($subject instanceof Request) {
      return true;
    }

    return $subject instanceof ClubLinkedEntityInterface || $subject instanceof Club;
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool {
    $context = $this->resolveClubContext($subject, $token, $vote);
    if (!$context || $context['isSuperAdmin']) {
      return false;
    }

    return $context['activeProfile']->getRole() === ClubRole::badger;
  }
}
