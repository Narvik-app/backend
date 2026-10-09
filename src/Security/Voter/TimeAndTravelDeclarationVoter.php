<?php

namespace App\Security\Voter;

use App\Entity\ClubDependent\Plugin\TimeAndTravelDeclaration\TimeAndTravelDeclaration;
use App\Entity\User;
use App\Entity\UserMember;
use App\Enum\ClubRole;
use App\Service\RequestService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class TimeAndTravelDeclarationVoter extends Voter {
  public const CREATE = 'TIME_TRAVEL_DECLARATION_CREATE';
  public const READ = 'TIME_TRAVEL_DECLARATION_READ';
  public const UPDATE = 'TIME_TRAVEL_DECLARATION_UPDATE';
  public const DELETE = 'TIME_TRAVEL_DECLARATION_DELETE';

  public function __construct(
    private readonly RequestService $requestService,
  ) {
  }

  protected function supports(string $attribute, mixed $subject): bool {
    return in_array($attribute, [self::CREATE, self::READ, self::UPDATE, self::DELETE])
      && $subject instanceof TimeAndTravelDeclaration;
  }

  protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
    $user = $token->getUser();

    // If the user is not logged in, deny access
    if (!$user instanceof User || !$subject instanceof TimeAndTravelDeclaration) {
      return false;
    }

    $activeProfile = $this->requestService->getActiveProfile();
    if (!$activeProfile) {
      return false;
    }

    // Member edit his declaration, no issue full access
    if ($subject->getMember() === $activeProfile->getMember()) {
      return true;
    }

    return match ($activeProfile->getRole()) {
      ClubRole::admin => true,
      ClubRole::badger => in_array($attribute, [self::READ, self::CREATE]),
      ClubRole::supervisor => $activeProfile->getClub()?->getSettings()?->getSupervisorCanEditAnyTTDeclaration() ?? false,
      default => false,
    };
  }
}
